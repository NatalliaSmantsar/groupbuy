<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "<div class='container'><div class='alert alert-error'>Неверный идентификатор товара</div></div>";
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "<div class='container'><div class='alert alert-error'>Товар не найден</div></div>";
    include 'includes/footer.php';
    exit;
}
?>

<div class="container">
    <div class="card">
        <div class="order-header">
            <h1 class="order-title"><?= htmlspecialchars($product['name']) ?></h1>
            
            <div class="order-meta">
                <div class="meta-item">
                    <span class="meta-label">Цена за единицу</span>
                    <span class="meta-value product-price"><?= number_format($product['price'], 2, ',', ' ') ?> BYN</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Категория товара</span>
                    <span class="meta-value"><?= htmlspecialchars($product['category_name']) ?></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Описание товара</label>
                <div class="order-description">
                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Закупки с этим товаром</h3>
        
        <?php
        $orders_stmt = $conn->prepare("
            SELECT g.*, u.name AS organizer_name
            FROM group_orders g
            LEFT JOIN users u ON g.organizer_id = u.id
            WHERE g.product_id = ? AND g.status IN ('open', 'completed')
            ORDER BY g.created_at DESC
        ");
        $orders_stmt->bind_param("i", $id);
        $orders_stmt->execute();
        $orders = $orders_stmt->get_result();
        
        if ($orders->num_rows > 0): ?>
            <div class="orders-grid">
                <?php while ($order = $orders->fetch_assoc()): 
                    $progress_percent = min(100, ($order['current_amount'] / $order['min_amount']) * 100);
                    $status_label = $order['status'] === 'open' ? 'Открыта' : 'Завершена';
                ?>
                    <div class="order-card">
                        <h4 class="order-title"><?= htmlspecialchars($order['title']) ?></h4>
                        
                        <div class="order-meta">
                            <div class="meta-item">
                                <span class="meta-label">Организатор</span>
                                <span class="meta-value"><?= htmlspecialchars($order['organizer_name']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Статус</span>
                                <span class="status status-<?= $order['status'] ?>"><?= $status_label ?></span>
                            </div>
                        </div>

                        <button type="button" onclick="window.location.href='order_view.php?id=<?= $order['id'] ?>'" class="btn" style="width: 100%; text-align: center; margin-top: 15px;">
                            Подробнее о закупке
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Нет активных закупок с этим товаром.</p>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'organizer'): ?>
                    <button type="button" onclick="window.location.href='order_create.php?product_id=<?= $product['id'] ?>'" class="btn">
                        Создать первую закупку
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>