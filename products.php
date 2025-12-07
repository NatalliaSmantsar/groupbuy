<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';

$search = sanitize_input($_GET['q'] ?? '');
$cat_filter = intval($_GET['category'] ?? 0);
?>

<div class="container">
    <h1 class="page-title">Каталог товаров</h1>

    <form method="GET" class="search-form">
        <input type="text" name="q" placeholder="Поиск по названию товара..." 
               value="<?= $search ?>" maxlength="100" class="form-control">
        <select name="category" class="form-control">
            <option value="0">Все категории</option>
            <?php
            $cats = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
            while ($c = $cats->fetch_assoc()) {
                $sel = $c['id'] == $cat_filter ? 'selected' : '';
                echo "<option value='{$c['id']}' $sel>" . htmlspecialchars($c['name']) . "</option>";
            }
            ?>
        </select>
        <button type="submit" class="btn">Найти товары</button>
    </form>

    <?php
    $params = [];
    $sql = "SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id";
    $conds = [];

    if ($search !== '') {
        $like = '%' . $conn->real_escape_string($search) . '%';
        $conds[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    if ($cat_filter > 0) {
        $conds[] = "p.category_id = ?";
        $params[] = $cat_filter;
    }

    if (count($conds) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conds);
    }
    $sql .= " ORDER BY p.id DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (count($params) > 0) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    if ($res && $res->num_rows > 0): ?>
        <div class="catalog-grid">
            <?php while ($p = $res->fetch_assoc()): ?>
                <div class="catalog-card">
                    <div class="catalog-info">
                        <h3 class="catalog-title"><?= htmlspecialchars($p['name']) ?></h3>
                        <p class="product-price">
                            <?= number_format($p['price'], 2, ',', ' ') ?> BYN
                        </p>
                        <div class="product-actions">
                            <button type="button" onclick="window.location.href='product_view.php?id=<?= $p['id'] ?>'" class="btn">
                                Подробнее
                            </button>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'organizer'): ?>
                                <button type="button" onclick="window.location.href='order_create.php?product_id=<?= $p['id'] ?>'" class="btn">
                                    Закупка
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Товары не найдены</h3>
            <p>Попробуйте изменить параметры поиска</p>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin/products.php" class="btn">Добавить товары</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>