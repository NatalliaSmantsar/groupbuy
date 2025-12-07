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
    <title>Управление пользователями — Админ-панель</title>
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

    <!-- ФИКСИРОВАННАЯ ЛЕВАЯ ПАНЕЛЬ -->
    <div class="sidebar">
        <h2>Панель управления</h2>
        <a href="users.php" class="active">Управление пользователями</a>
        <a href="categories.php">Управление категориями</a>
        <a href="products.php">Управление товарами</a>
        <a href="orders.php">Управление закупками</a>
    </div>

    <!-- ОСНОВНОЙ КОНТЕНТ -->
    <main class="admin-content">

        <h1 class="admin-title">Управление пользователями</h1>

        <?php
        // Удаление пользователя
        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);

            // Не позволяем удалить себя
            if (isset($_SESSION['user_id']) && $id == $_SESSION['user_id']) {
                echo "<div class='alert alert-error'>Нельзя удалить свою собственную учетную запись.</div>";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                echo $stmt->execute()
                    ? "<div class='alert alert-success'>Пользователь успешно удалён.</div>"
                    : "<div class='alert alert-error'>Ошибка удаления: " . htmlspecialchars($conn->error) . "</div>";
                $stmt->close();
            }
        }

        // Добавление пользователя
        if (isset($_POST['add'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password_raw = $_POST['password'];
            $role = trim($_POST['role']);

            if ($name === '' || $email === '' || $password_raw === '' || $role === '') {
                echo "<div class='alert alert-error'>Все поля обязательны для заполнения.</div>";
            } else {
                // Проверка уникальности email
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                $check->store_result();
                if ($check->num_rows > 0) {
                    echo "<div class='alert alert-error'>Пользователь с таким email уже существует.</div>";
                } else {
                    $password = password_hash($password_raw, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $password, $role);
                    echo $stmt->execute()
                        ? "<div class='alert alert-success'>Пользователь успешно добавлен.</div>"
                        : "<div class='alert alert-error'>Ошибка добавления: " . htmlspecialchars($conn->error) . "</div>";
                    $stmt->close();
                }
                $check->close();
            }
        }
        ?>

        <!-- Форма добавления -->
        <div class="card">
            <h3 class="section-title">Добавить нового пользователя</h3>
            <form method="POST" class="form-inline" data-need-validation>
                <input type="text" name="name" placeholder="Имя пользователя" required class="form-control">
                <input type="email" name="email" placeholder="Email адрес" required class="form-control">
                <input type="password" name="password" placeholder="Пароль" required class="form-control">
                <select name="role" required class="form-control">
                    <option value="user">Обычный пользователь</option>
                    <option value="organizer">Организатор закупок</option>
                    <option value="admin">Администратор</option>
                </select>
                <button type="submit" name="add" class="btn">Добавить пользователя</button>
            </form>
        </div>

        <!-- Список пользователей -->
        <div class="card">
            <h3 class="section-title">Список пользователей</h3>

            <?php
            $result = $conn->query("SELECT id, name, email, role FROM users ORDER BY id ASC");
            if ($result && $result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя пользователя</th>
                            <th>Email адрес</th>
                            <th>Роль в системе</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()):
                            $role_label = $row['role'] === 'admin' ? 'Администратор' :
                                          ($row['role'] === 'organizer' ? 'Организатор' : 'Пользователь');
                            $is_current_user = isset($_SESSION['user_id']) && $row['id'] == $_SESSION['user_id'];
                        ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td>
                                    <span class="status <?= $row['role'] === 'admin' ? 'status-completed' : ($row['role'] === 'organizer' ? 'status-open' : '') ?>">
                                        <?= $role_label ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$is_current_user): ?>
                                        <a class="btn btn-small confirm-delete" 
                                           data-confirm='Вы уверены, что хотите удалить пользователя <?= htmlspecialchars($row['name']) ?>?' 
                                           href='users.php?delete=<?= $row['id'] ?>'>
                                            Удалить
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-small btn-secondary" style="opacity: 0.6;">Текущий пользователь</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>В системе нет зарегистрированных пользователей.</p>
                </div>
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
