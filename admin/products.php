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
    <title>Управление товарами — Админ-панель</title>
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

    <aside class="sidebar">
        <h2>Панель управления</h2>
        <a href="users.php">Управление пользователями</a>
        <a href="categories.php">Управление категориями</a>
        <a href="products.php" class="active">Управление товарами</a>
        <a href="orders.php">Управление закупками</a>
    </aside>

    <main class="admin-content">

        <h1 class="admin-title">Управление товарами</h1>

        <?php
        if (isset($_POST['add'])) {
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $cat = intval($_POST['category_id']);

            if ($name === '' || $price <= 0 || $cat <= 0) {
                echo "<div class='alert alert-error'>Все поля должны быть корректно заполнены.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id)
                                        VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssdi", $name, $desc, $price, $cat);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Товар успешно добавлен.</div>"
                    : "<div class='alert alert-error'>Ошибка при добавлении товара.</div>";

                $stmt->close();
            }
        }

        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);

            $check = $conn->prepare("SELECT id FROM group_orders WHERE product_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $used = $check->get_result()->num_rows > 0;

            if ($used) {
                echo "<div class='alert alert-error'>Нельзя удалить товар, который используется в закупках.</div>";
            } else {
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                $stmt->bind_param("i", $id);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Товар удалён.</div>"
                    : "<div class='alert alert-error'>Ошибка при удалении товара.</div>";

                $stmt->close();
            }
        }

        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $prod = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$prod) {
                echo "<div class='alert alert-error'>Товар не найден.</div>";
            }
        }

        if (isset($_POST['update'])) {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $desc = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $cat = intval($_POST['category_id']);

            if ($name === '' || $price <= 0 || $cat <= 0) {
                echo "<div class='alert alert-error'>Проверьте корректность данных.</div>";
            } else {
                $stmt = $conn->prepare("UPDATE products
                                        SET name=?, description=?, price=?, category_id=?
                                        WHERE id=?");
                $stmt->bind_param("ssdii", $name, $desc, $price, $cat, $id);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Товар обновлён.</div>"
                    : "<div class='alert alert-error'>Ошибка при обновлении товара.</div>";

                $stmt->close();
            }
        }

        $cats = $conn->query("SELECT * FROM categories ORDER BY name ASC");
        ?>

        <div class="card">
            <h3 class="section-title">
                <?= isset($prod) ? "Редактирование товара" : "Добавить новый товар" ?>
            </h3>

            <form method="POST" class="form-inline">

                <?php if (isset($prod)): ?>
                    <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                <?php endif; ?>

                <input type="text" name="name" placeholder="Название"
                       value="<?= isset($prod) ? htmlspecialchars($prod['name']) : '' ?>" required>

                <input type="text" name="description" placeholder="Описание"
                       value="<?= isset($prod) ? htmlspecialchars($prod['description']) : '' ?>">

                <input type="number" step="0.01" name="price" placeholder="Цена"
                       value="<?= isset($prod) ? $prod['price'] : '' ?>" required>

                <select name="category_id" required>
                    <option value="">Категория</option>
                    <?php
                    $cats->data_seek(0);
                    while ($c = $cats->fetch_assoc()):
                        $sel = (isset($prod) && $prod['category_id'] == $c['id']) ? "selected" : "";
                        echo "<option value='{$c['id']}' $sel>" . htmlspecialchars($c['name']) . "</option>";
                    endwhile;
                    ?>
                </select>

                <?php if (isset($prod)): ?>
                    <button type="submit" name="update" class="btn">Сохранить</button>
                    <a href="products.php" class="btn btn-secondary">Отмена</a>
                <?php else: ?>
                    <button type="submit" name="add" class="btn">Добавить</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3 class="section-title">Список товаров</h3>

            <?php
            $result = $conn->query("
                SELECT p.*, c.name AS category
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY p.id DESC
            ");

            if ($result && $result->num_rows > 0):
            ?>

                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Цена</th>
                        <th>Категория</th>
                        <th>Действия</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= number_format($row['price'], 2, ',', ' ') ?> BYN</td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td>
                                <a class="btn btn-small" href="?edit=<?= $row['id'] ?>">Редактировать</a>
                                <a class="btn btn-small" target="_blank"
                                   href="../product_view.php?id=<?= $row['id'] ?>">Просмотр</a>
                                <a class="btn btn-small btn-danger confirm-delete"
                                   href="?delete=<?= $row['id'] ?>"
                                   data-confirm="Удалить «<?= htmlspecialchars($row['name']) ?>»?">Удалить</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <div class="empty-state">Нет товаров.</div>
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
