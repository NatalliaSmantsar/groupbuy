<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

$group_order_id = intval($_POST['group_order_id'] ?? 0);
$text = sanitize_input($_POST['text'] ?? '');

if ($group_order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный идентификатор закупки']);
    exit;
}

if (empty($text)) {
    echo json_encode(['success' => false, 'message' => 'Введите текст сообщения']);
    exit;
}

if (strlen($text) > 500) {
    echo json_encode(['success' => false, 'message' => 'Сообщение не должно превышать 500 символов']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM group_orders WHERE id = ?");
$stmt->bind_param("i", $group_order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Закупка не найдена']);
    exit;
}

$user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO messages (group_order_id, sender_id, text, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $group_order_id, $_SESSION['user_id'], $text);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => [
            'username' => $user['name'],
            'text' => $text,
            'time' => date('d.m.Y H:i')
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при отправке сообщения']);
}
?>