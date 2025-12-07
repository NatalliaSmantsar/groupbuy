<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';
?>

<div class="container">
    <h1 class="page-title">Групповые закупки</h1>

    <?php
    $q = sanitize_input($_GET['q'] ?? '');
    $status = sanitize_input($_GET['status'] ?? '');
    ?>

    <form method="GET" class="search-form">
        <input type="text" name="q" placeholder="Поиск по названию или организатору..." 
               value="<?= $q ?>" maxlength="100" class="form-control">
        <select name="status" class="form-control">
            <option value="">Все статусы</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Открытые</option>
            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Отменённые</option>
            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Завершенные</option>
        </select>
        <button type="submit" class="btn">Поиск</button>
    </form>

    <?php
    $params = [];
    $sql = "SELECT g.*, u.name AS organizer 
            FROM group_orders g 
            LEFT JOIN users u ON g.organizer_id = u.id";

    $conds = [];
    if ($q !== '') {
        $like = '%' . $conn->real_escape_string($q) . '%';
        $conds[] = "(g.title LIKE ? OR u.name LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }
    
    if ($status !== '') {
        $conds[] = "g.status = ?";
        $params[] = $status;
    }

    if (count($conds) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conds);
    } else {
        $sql .= " WHERE g.status IN ('open', 'completed')";
    }

    $sql .= " ORDER BY g.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (count($params) > 0) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    if ($result && $result->num_rows > 0): ?>
        <div class="orders-grid">
            <?php while ($row = $result->fetch_assoc()): 
                $status_label = $row['status'] === 'open' ? 'Открыта' : 
                               ($row['status'] === 'completed' ? 'Завершена' : 'Закрыта');
                $status_class = 'status-' . $row['status'];
            ?>
                <div class="order-card">
                    <div class="order-info">
                        <h3 class="order-title"><?= htmlspecialchars($row['title']) ?></h3>
                        
                        <div class="order-meta">
                            <div class="meta-item">
                                <span class="meta-label">Организатор</span>
                                <span class="meta-value"><?= htmlspecialchars($row['organizer']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Статус</span>
                                <span class="status <?= $status_class ?>"><?= $status_label ?></span>
                            </div>
                        </div>

                        <button type="button" onclick="window.location.href='order_view.php?id=<?= $row['id'] ?>'" class="btn" style="width: 100%; text-align: center; margin-top: 15px;">
                            Подробнее о закупке
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Закупки не найдены</h3>
            <p>Попробуйте изменить параметры поиска или создать новую закупку</p>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'organizer'): ?>
                <button type="button" onclick="window.location.href='order_create.php'" class="btn">
                    Создать закупку
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>