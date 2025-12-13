<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление закупками — Админ-панель</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<header class="admin-header">
    <div class="admin-header-content">
        <nav class="admin-nav">
            <a href="../index.php">На главную сайта</a>
        </nav>
    </div>
</header>

<div class="admin-container">

    <div class="sidebar">
        <h2>Панель управления</h2>
        <a href="users.php">Управление пользователями</a>
        <a href="categories.php">Управление категориями</a>
        <a href="products.php">Управление товарами</a>
        <a href="orders.php" class="active">Управление закупками</a>
    </div>

    <main class="admin-content">

        <h1 class="admin-title">Управление групповыми закупками</h1>

        <?php
        if (isset($_POST['add'])) {
            $title = trim($_POST['title']);
            $desc = trim($_POST['description']);
            $min_amount = floatval($_POST['min_amount']);
            $status = 'open';
            $product_id = intval($_POST['product_id']);
            $organizer_id = intval($_POST['organizer_id']);

            if ($title === '' || $min_amount <= 0 || $product_id <= 0 || $organizer_id <= 0) {
                echo "<div class='alert alert-error'>Проверьте данные заказа.</div>";
            } else {
                $product_result = $conn->query("SELECT price FROM products WHERE id = $product_id");
                $product = $product_result->fetch_assoc();
                $quantity = ceil($min_amount / $product['price']);

                $stmt = $conn->prepare("
                    INSERT INTO group_orders 
                    (title, description, min_amount, status, current_amount, product_id, quantity, organizer_id, created_at)
                    VALUES (?, ?, ?, ?, 0, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("ssdsiii", $title, $desc, $min_amount, $status, $product_id, $quantity, $organizer_id);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Заказ успешно добавлен.</div>"
                    : "<div class='alert alert-error'>Ошибка при добавлении заказа.</div>";
            }
        }

        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);
            $stmt = $conn->prepare("DELETE FROM group_orders WHERE id = ?");
            $stmt->bind_param("i", $id);
            echo $stmt->execute()
                ? "<div class='alert alert-success'>Заказ удалён.</div>"
                : "<div class='alert alert-error'>Ошибка при удалении.</div>";
        }

        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $stmt = $conn->prepare("SELECT * FROM group_orders WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
        }

        if (isset($_POST['update'])) {
            $id = intval($_POST['id']);
            $title = trim($_POST['title']);
            $desc = trim($_POST['description']);
            $status = trim($_POST['status']);
            $min_amount = floatval($_POST['min_amount']);

            if ($title === '' || $min_amount <= 0 || !in_array($status, ['open','closed','completed'])) {
                echo "<div class='alert alert-error'>Проверьте данные полей.</div>";
            } else {
                $stmt = $conn->prepare("
                    UPDATE group_orders 
                    SET title=?, description=?, status=?, min_amount=? 
                    WHERE id=?
                ");
                $stmt->bind_param("sssdi", $title, $desc, $status, $min_amount, $id);
                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Закупка обновлена.</div>"
                    : "<div class='alert alert-error'>Ошибка при обновлении.</div>";
            }
        }

        $products = $conn->query("SELECT id, name FROM products ORDER BY name ASC");
        $organizers = $conn->query("SELECT id, name FROM users WHERE role IN ('organizer','admin') ORDER BY name ASC");

        $result = $conn->query("
            SELECT g.*, u.name AS organizer, p.name AS product_name
            FROM group_orders g
            LEFT JOIN users u ON g.organizer_id = u.id
            LEFT JOIN products p ON g.product_id = p.id
            ORDER BY g.created_at DESC
        ");
        ?>

        <?php if (isset($order)): ?>
        <div class="card">
            <h3 class="section-title">Редактирование закупки</h3>

            <form method="POST" class="form-inline">
                <input type="hidden" name="id" value="<?= $order['id'] ?>">

                <input type="text" name="title" value="<?= htmlspecialchars($order['title']) ?>" required>
                <input type="text" name="description" value="<?= htmlspecialchars($order['description']) ?>">
                <input type="number" step="0.01" name="min_amount" value="<?= $order['min_amount'] ?>" required>

                <select name="status" required>
                    <option value="open"      <?= $order['status']=='open'?'selected':'' ?>>Открыта</option>
                    <option value="closed"    <?= $order['status']=='closed'?'selected':'' ?>>Закрыта</option>
                    <option value="completed" <?= $order['status']=='completed'?'selected':'' ?>>Завершена</option>
                </select>

                <button name="update" class="btn">Сохранить</button>
                <button type="button" onclick="window.location.href='orders.php'" class="btn">Отмена</button>
            </form>
        </div>

        <?php else: ?>

        <div class="card">
            <h3 class="section-title">Создать новую закупку</h3>

            <form method="POST" class="form-inline">
                <input type="text" name="title" placeholder="Название" required>
                <input type="text" name="description" placeholder="Описание">
                <input type="number" step="0.01" name="min_amount" placeholder="Минимальная сумма" required>

                <select name="product_id" required>
                    <option value="">Выберите товар</option>
                    <?php while ($p = $products->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <select name="organizer_id" required>
                    <option value="">Организатор</option>
                    <?php while ($o = $organizers->fetch_assoc()): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <button name="add" class="btn">Создать</button>
            </form>
        </div>

        <?php endif; ?>

        <div class="card">
            <h3 class="section-title">Список закупок</h3>

            <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Товар</th>
                        <th>Организатор</th>
                        <th>Статус</th>
                        <th>Прогресс</th>
                        <th>Создана</th>
                        <th>Действия</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $progress = min(100, ($row['current_amount'] / $row['min_amount']) * 100);
                        $status_label = [
                            'open' => 'Открыта',
                            'closed' => 'Закрыта',
                            'completed' => 'Завершена'
                        ][$row['status']];
                    ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><?= htmlspecialchars($row['organizer']) ?></td>

                        <td><span class="status status-<?= $row['status'] ?>"><?= $status_label ?></span></td>

                        <td>
                            <div class="progress-bar">
                                <div style="width: <?= $progress ?>%"></div>
                            </div>
                            <div class="progress-text"><?= round($progress) ?>%</div>
                        </td>

                        <td><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>

                        <td>
                            <a class="btn btn-small" href="orders.php?edit=<?= $row['id'] ?>">Редактировать</a>
                            <a class="btn btn-small" href="../order_view.php?id=<?= $row['id'] ?>" target="_blank">Просмотр</a>
                            <a class="btn btn-small confirm-delete" 
                               href="orders.php?delete=<?= $row['id'] ?>"
                               data-confirm="Удалить закупку «<?= htmlspecialchars($row['title']) ?>»?">
                                Удалить
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>

            </table>

            <?php else: ?>
                <p class="empty-state">Закупок пока нет.</p>
            <?php endif; ?>
        </div>

    </main>
</div>

<footer class="admin-footer">
    © <?= date('Y') ?> Групповые закупки — панель администратора
</footer>

<script src="../assets/js/admin.js"></script>
</body>
</html>
