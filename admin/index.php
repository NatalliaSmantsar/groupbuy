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
    <title>Главная — Админ-панель</title>
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
        <a href="products.php">Управление товарами</a>
        <a href="orders.php">Управление закупками</a>
    </aside>

    <section class="admin-content">

        <h1 class="admin-title">Добро пожаловать в панель администратора!</h1>

    </section>
</div>

<footer class="admin-footer">
    © <?= date('Y') ?> Групповые закупки — панель администратора
</footer>

<script src="../assets/js/admin.js"></script>
</body>
</html>