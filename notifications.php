<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='container'><div class='alert alert-error'>Пожалуйста, войдите в систему.</div></div>";
    include 'includes/footer.php';
    exit;
}

$user_id = intval($_SESSION['user_id']);

if (isset($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    if ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        header("Location: notifications.php");
        exit;
    }
}

if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: notifications.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT n.*, g.title as order_title, u.name as organizer_name 
    FROM user_notifications n
    JOIN group_orders g ON n.order_id = g.id
    JOIN users u ON n.organizer_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['count'];
?>

<link rel="stylesheet" href="assets/css/notifications.css">

<div class="container">
    <h1 class="page-title">Мои уведомления</h1>

    <?php if ($unread_count > 0): ?>
        <div class="alert alert-info" style="display: flex; justify-content: space-between; align-items: center;">
            <span>У вас <strong><?= $unread_count ?></strong> непрочитанных уведомлений</span>
            <button type="button" onclick="window.location.href='notifications.php?mark_all_read=1'" class="btn btn-small">
                Пометить все как прочитанные
            </button>
        </div>
    <?php endif; ?>

    <div class="notifications-section">
        <?php if ($notifications->num_rows > 0): ?>
            <div class="notification-list">
                <?php while ($notification = $notifications->fetch_assoc()): 
                    $status_class = $notification['is_read'] ? '' : 'unread';
                ?>
                    <div class="notification-item <?= $status_class ?>">
                        <div class="notification-header">
                            <h4 class="notification-title"><?= htmlspecialchars($notification['subject']) ?></h4>
                            <span class="notification-status">
                                <?= $notification['is_read'] ? 'Прочитано' : 'Новое' ?>
                            </span>
                        </div>
                        
                        <div class="notification-meta">
                            От организатора: <strong><?= htmlspecialchars($notification['organizer_name']) ?></strong> | 
                            Закупка: <a href="order_view.php?id=<?= $notification['order_id'] ?>"><?= htmlspecialchars($notification['order_title']) ?></a> | 
                            Дата получения: <?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?>
                        </div>
                        
                        <div class="notification-body">
                            <?= nl2br(htmlspecialchars(trim($notification['message']))) ?>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                                <button type="button" onclick="window.location.href='notifications.php?mark_read=<?= $notification['id'] ?>'" class="btn btn-small">
                                    Пометить как прочитанное
                                </button>
                            <?php endif; ?>
                            <button type="button" onclick="window.location.href='order_view.php?id=<?= $notification['order_id'] ?>'" class="btn btn-small <?= $notification['is_read'] ? '' : 'btn-success' ?>">
                                Перейти к закупке
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>У вас пока нет уведомлений</h3>
                <p>Когда организаторы будут приглашать вас к закупкам или будут важные обновления, уведомления появятся здесь.</p>
                <button type="button" onclick="window.location.href='index.php'" class="btn">
                    Найти закупки для участия
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>