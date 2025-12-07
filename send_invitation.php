<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';

header('Content-Type: application/json; charset=utf-8');

function json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Неавторизованный пользователь');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Неверный метод запроса');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $invited_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $invited_user_email = isset($_POST['user_email']) ? sanitize_input($_POST['user_email']) : '';
    $invited_user_name = isset($_POST['user_name']) ? sanitize_input($_POST['user_name']) : '';

    if ($order_id <= 0 || $invited_user_id <= 0) {
        throw new Exception('Неверные данные запроса');
    }

    if (!validate_email($invited_user_email) || empty($invited_user_name)) {
        throw new Exception('Неверные данные пользователя');
    }

    $order_stmt = $conn->prepare("SELECT organizer_id FROM group_orders WHERE id = ?");
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order = $order_result->fetch_assoc();
    
    if (!$order) {
        throw new Exception("Закупка не найдена");
    }
    
    if ($order['organizer_id'] != $_SESSION['user_id']) {
        throw new Exception("Нет прав для приглашения");
    }

    $order_info_stmt = $conn->prepare("
        SELECT g.*, p.name as product_name, p.price as product_price, u.name as organizer_name
        FROM group_orders g 
        JOIN products p ON g.product_id = p.id 
        JOIN users u ON g.organizer_id = u.id 
        WHERE g.id = ?
    ");
    $order_info_stmt->bind_param("i", $order_id);
    $order_info_stmt->execute();
    $order_info_result = $order_info_stmt->get_result();
    $order_info = $order_info_result->fetch_assoc();
    
    if (!$order_info) {
        throw new Exception("Информация о закупке не найдена");
    }

    $organizer_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $organizer_stmt->bind_param("i", $_SESSION['user_id']);
    $organizer_stmt->execute();
    $organizer_result = $organizer_stmt->get_result();
    $organizer = $organizer_result->fetch_assoc();
    
    if (!$organizer) {
        throw new Exception("Организатор не найден");
    }

    require_once 'includes/smart_algorithms.php';

    $email_sent = send_participation_invitation(
        $invited_user_email,
        $invited_user_name,
        $order_info,
        $organizer['name']
    );

    if ($email_sent) {
        json_response(['success' => true, 'message' => 'Приглашение успешно отправлено']);
    } else {
        throw new Exception("Ошибка отправки уведомления");
    }

} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()]);
}
?>