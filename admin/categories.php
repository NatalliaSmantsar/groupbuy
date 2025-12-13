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
    <title>Управление категориями — Админ-панель</title>
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
        <a href="categories.php" class="active">Управление категориями</a>
        <a href="products.php">Управление товарами</a>
        <a href="orders.php">Управление закупками</a>
    </aside>

    <section class="admin-content">

        <h1 class="admin-title">Управление категориями товаров</h1>

        <?php
        if (isset($_POST['add'])) {
            $name = trim($_POST['name']);

            if ($name === '') {
                echo "<div class='alert alert-error'>Название категории не может быть пустым.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->bind_param("s", $name);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Категория успешно добавлена.</div>"
                    : "<div class='alert alert-error'>Ошибка при добавлении категории.</div>";

                $stmt->close();
            }
        }

        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);

            $check = $conn->prepare("SELECT id FROM products WHERE category_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $used = $check->get_result()->num_rows > 0;
            $check->close();

            if ($used) {
                echo "<div class='alert alert-error'>Нельзя удалить категорию, которая используется товарами.</div>";
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $id);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Категория удалена.</div>"
                    : "<div class='alert alert-error'>Ошибка удаления категории.</div>";

                $stmt->close();
            }
        }

        if (isset($_GET['edit'])) {
            $edit_id = intval($_GET['edit']);

            $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $category = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($category):
        ?>
                <div class="card">
                    <h3 class="section-title">Редактирование категории</h3>

                    <form method="POST" class="form-inline">
                        <input type="hidden" name="id" value="<?= $category['id']; ?>">

                        <input type="text" name="name" required
                               value="<?= htmlspecialchars($category['name']); ?>"
                               placeholder="Название категории">

                        <button type="submit" name="update" class="btn">Сохранить</button>
                        <a href="categories.php" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
        <?php
            else:
                echo "<div class='alert alert-error'>Категория не найдена.</div>";
            endif;
        }

        if (isset($_POST['update'])) {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);

            if ($name === '') {
                echo "<div class='alert alert-error'>Название категории не может быть пустым.</div>";
            } else {
                $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
                $stmt->bind_param("si", $name, $id);

                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Категория обновлена.</div>"
                    : "<div class='alert alert-error'>Ошибка обновления категории.</div>";

                $stmt->close();
            }
        }
        ?>

        <?php if (!isset($_GET['edit'])): ?>
        <div class="card">
            <h3 class="section-title">Добавить новую категорию</h3>
            <form method="POST" class="form-inline">
                <input type="text" name="name" placeholder="Название новой категории" required>
                <button type="submit" name="add" class="btn">Добавить</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3 class="section-title">Список категорий</h3>

            <?php
            $result = $conn->query("
                SELECT c.*, COUNT(p.id) AS product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id
                GROUP BY c.id
                ORDER BY c.name ASC
            ");

            if ($result && $result->num_rows > 0):
            ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Товаров</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= $row['product_count'] ?></td>
                            <td>
                                <a class="btn btn-small" href="?edit=<?= $row['id'] ?>">Ред.</a>

                                <?php if ($row['product_count'] == 0): ?>
                                    <a class="btn btn-small btn-danger confirm-delete"
                                       href="?delete=<?= $row['id'] ?>"
                                       data-confirm="Удалить категорию «<?= htmlspecialchars($row['name']) ?>»?">
                                        Удалить
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-state">Категории отсутствуют.</p>
            <?php endif; ?>
        </div>

    </section>
</div>

<footer class="admin-footer">
    © <?= date('Y') ?> Групповые закупки — панель администратора
</footer>

<script src="../assets/js/admin.js"></script>
</body>
</html>
