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
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "<div class='container'><div class='alert alert-error'>Пользователь не найден.</div></div>";
    include 'includes/footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_name'])) {
    $new_name = sanitize_input($_POST['name']);
    
    if (!validate_name($new_name)) {
        $error = "Имя должно содержать от 2 до 50 символов (только буквы, пробелы и дефисы)";
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $user_id);
        if ($stmt->execute()) {
            $_SESSION['name'] = $new_name;
            $success = "Имя успешно сохранено.";
            $user['name'] = $new_name;
        } else {
            $error = "Ошибка при сохранении имени.";
        }
        $stmt->close();
    }
}

$active_tab = $_GET['tab'] ?? 'info';
?>

<link rel="stylesheet" href="assets/css/tabs.css">

<div class="container">
    <h1 class="page-title">Личный кабинет</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="tabs-navigation">
        <a href="?tab=info" class="tab-link <?= $active_tab === 'info' ? 'active' : '' ?>">
            Основная информация
        </a>
        <?php if ($_SESSION['role'] === 'organizer'): ?>
            <a href="?tab=my_orders" class="tab-link <?= $active_tab === 'my_orders' ? 'active' : '' ?>">
                Мои закупки
            </a>
        <?php endif; ?>
        <a href="?tab=participations" class="tab-link <?= $active_tab === 'participations' ? 'active' : '' ?>">
            Мои участия в закупках
        </a>
    </div>

    <div class="tab-content">
        <?php if ($active_tab === 'info'): ?>
            <form method="POST" class="name-form">
                <label class="form-label">Имя пользователя</label>
                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                        pattern="[a-zA-Zа-яА-ЯёЁ\s\-]{2,50}" required>
                <button type="submit" name="save_name" class="btn">Сохранить изменения</button>
            </form>
        <?php endif; ?>

        <?php if ($active_tab === 'my_orders' && $_SESSION['role'] === 'organizer'): ?>
            <div class="card">
                <?php
                $orders_stmt = $conn->prepare("
                    SELECT g.*, p.name AS product_name,
                           (SELECT COUNT(*) FROM order_items WHERE group_order_id = g.id) AS participants_count
                    FROM group_orders g 
                    LEFT JOIN products p ON g.product_id = p.id
                    WHERE g.organizer_id = ? 
                    ORDER BY g.created_at DESC
                ");
                $orders_stmt->bind_param("i", $user_id);
                $orders_stmt->execute();
                $orders = $orders_stmt->get_result();
                
                if ($orders && $orders->num_rows > 0): ?>
                    <div class="orders-grid">
                        <?php while ($o = $orders->fetch_assoc()): 
                            $status_label = $o['status'] === 'open' ? 'Открыта' : 
                                           ($o['status'] === 'completed' ? 'Завершена' : 'Закрыта');
                            $status_class = 'status-' . $o['status'];
                        ?>
                            <div class="order-card">
                                <h4 class="order-title"><?= htmlspecialchars($o['title']) ?></h4>
                                
                                <div class="order-meta">
                                    <div class="meta-item">
                                        <span class="meta-label">Статус</span>
                                        <span class="status <?= $status_class ?>"><?= $status_label ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Участников</span>
                                        <span class="meta-value"><?= $o['participants_count'] ?> чел.</span>
                                    </div>
                                </div>
                                
                                <button type="button" onclick="window.location.href='order_view.php?id=<?= $o['id'] ?>'" class="btn" style="width: 100%; text-align: center; margin-top: 15px;">
                                    Управлять закупкой
                                </button>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Вы не создавали закупок.</p>
                        <button type="button" onclick="window.location.href='order_create.php'" class="btn">
                            Создать первую закупку
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'participations'): ?>
            <div class="card">
                <?php
                $participations_stmt = $conn->prepare("
                    SELECT oi.*, g.title AS order_title, g.status AS order_status, 
                           oi.quantity, oi.total_price
                    FROM order_items oi
                    LEFT JOIN group_orders g ON oi.group_order_id = g.id
                    WHERE oi.participant_id = ?
                    ORDER BY oi.id DESC
                ");
                $participations_stmt->bind_param("i", $user_id);
                $participations_stmt->execute();
                $participations = $participations_stmt->get_result();
                
                if ($participations && $participations->num_rows > 0): ?>
                    <div class="orders-grid">
                        <?php while ($part = $participations->fetch_assoc()): 
                            $status_label = $part['order_status'] === 'open' ? 'Открыта' : 
                                           ($part['order_status'] === 'completed' ? 'Завершена' : 'Закрыта');
                            $status_class = 'status-' . $part['order_status'];
                        ?>
                            <div class="order-card">
                                <h4 class="order-title"><?= htmlspecialchars($part['order_title']) ?></h4>
                                
                                <div class="order-meta">
                                    <div class="meta-item">
                                        <span class="meta-label">Статус</span>
                                        <span class="status <?= $status_class ?>"><?= $status_label ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Ваше вложение</span>
                                        <span class="meta-value" style="color: #4b3ff0; font-weight: 600;">
                                            <?= number_format($part['total_price'], 2, ',', ' ') ?> BYN
                                        </span>
                                    </div>
                                </div>
                                
                                <button type="button" onclick="window.location.href='order_view.php?id=<?= $part['group_order_id'] ?>'" class="btn" style="width: 100%; text-align: center; margin-top: 15px;">
                                    Перейти к закупке
                                </button>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Вы еще не участвовали в закупках.</p>
                        <button type="button" onclick="window.location.href='index.php'" class="btn">
                            Найти закупки для участия
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>