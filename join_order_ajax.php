<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Неавторизованный пользователь']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный идентификатор закупки']);
    exit;
}

if (!validate_quantity($quantity)) {
    echo json_encode(['success' => false, 'message' => 'Количество должно быть от 1 до 1000']);
    exit;
}

$stmt = $conn->prepare("
    SELECT g.*, p.price AS product_price 
    FROM group_orders g 
    LEFT JOIN products p ON g.product_id = p.id 
    WHERE g.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Закупка не найдена']);
    exit;
}

if ($order['status'] !== 'open') {
    echo json_encode(['success' => false, 'message' => 'Закупка закрыта для участия']);
    exit;
}

$product_price = $order['product_price'] ?? 0;
if ($product_price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ошибка цены товара']);
    exit;
}

$current_quantity = $order['current_amount'] / $product_price;
$remaining_quantity = $order['quantity'] - $current_quantity;

if ($quantity > $remaining_quantity) {
    echo json_encode([
        'success' => false, 
        'message' => "Можно заказать не более " . floor($remaining_quantity) . " шт. (осталось до завершения закупки)"
    ]);
    exit;
}

$total_price = $product_price * $quantity;

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO order_items (group_order_id, participant_id, product_id, quantity, total_price)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiid", $order_id, $user_id, $order['product_id'], $quantity, $total_price);
    $stmt->execute();
    
    $stmt = $conn->prepare("
        UPDATE group_orders 
        SET current_amount = current_amount + ? 
        WHERE id = ?
    ");
    $stmt->bind_param("di", $total_price, $order_id);
    $stmt->execute();
    
    $new_amount = $order['current_amount'] + $total_price;
    $new_quantity = $current_quantity + $quantity;
    $order_completed = false;
    
    if ($new_quantity >= $order['quantity']) {
        $stmt = $conn->prepare("UPDATE group_orders SET status = 'completed' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order_completed = true;
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Вы успешно присоединились к закупке!',
        'current_amount' => number_format($new_amount, 2, ',', ' '),
        'current_quantity' => number_format($new_quantity, 1, ',', ' '),
        'completed' => $order_completed,
        'remaining_quantity' => max(0, $order['quantity'] - $new_quantity)
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Join order error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при присоединении к закупке'
    ]);
}
?>