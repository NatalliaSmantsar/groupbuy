<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Групповые закупки</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="header">
  <nav class="header-nav">
    <a href="index.php">Главная</a>
    <a href="products.php">Каталог товаров</a>
    <?php if(isset($_SESSION['user_id'])): ?>
      <a href="profile.php">Личный кабинет</a>
      <?php if($_SESSION['role'] === 'organizer'): ?>
        <a href="order_create.php">Создать закупку</a>
      <?php endif; ?>
      <?php if($_SESSION['role'] === 'admin'): ?>
        <a href="admin/">Панель администратора</a>
      <?php endif; ?>
      <?php
      $unread_count = 0;
      if (isset($_SESSION['user_id'])) {
          $unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
          $unread_stmt->bind_param("i", $_SESSION['user_id']);
          $unread_stmt->execute();
          $unread_result = $unread_stmt->get_result();
          $unread_count = $unread_result->fetch_assoc()['count'];
      }
      ?>
      <a href="notifications.php" class="notification-link">
          Уведомления
          <?php if ($unread_count > 0): ?>
              <span class="notification-badge"><?= $unread_count ?></span>
          <?php endif; ?>
      </a>
      <a href="logout.php">Выход</a>
    <?php else: ?>
      <a href="login.php">Вход</a>
      <a href="register.php">Регистрация</a>
    <?php endif; ?>
  </nav>
</header>
<main class="main-content">