<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (!validate_name($name)) {
        $error = "Имя должно содержать от 2 до 50 символов (только буквы, пробелы и дефисы)";
    } elseif (!validate_email($email)) {
        $error = "Введите корректный email адрес";
    } elseif (!validate_password($password)) {
        $error = "Пароль должен содержать минимум 6 символов";
    } elseif ($password !== $confirm_password) {
        $error = "Пароли не совпадают";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $check = $stmt->get_result();
        
        if ($check->num_rows > 0) {
            $error = "Пользователь с таким email уже зарегистрирован";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                header("Location: login.php?success=Регистрация прошла успешно! Теперь вы можете войти.");
                exit;
            } else {
                $error = "Ошибка при регистрации: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>

<div class="container">
    <div class="form">
        <h2 class="section-title">Регистрация</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="register-form">
            <div class="form-group">
                <label for="name" class="form-label">Имя пользователя:</label>
                <input type="text" id="name" name="name" placeholder="Введите ваше имя" 
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                       pattern="[a-zA-Zа-яА-ЯёЁ\s\-]{2,50}" required class="form-control">
                <small style="color: #6c757d; font-size: 0.875em;">Только буквы, пробелы и дефисы (2-50 символов)</small>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email адрес:</label>
                <input type="email" id="email" name="email" placeholder="Введите ваш email" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Пароль:</label>
                <input type="password" id="password" name="password" placeholder="Введите пароль" 
                       minlength="6" required class="form-control">
                <small style="color: #6c757d; font-size: 0.875em;">Минимум 6 символов</small>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Подтвердите пароль:</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Повторите пароль" required class="form-control">
            </div>

            <button type="submit" class="btn" style="width: 100%;">Зарегистрироваться</button>
        </form>

        <p class="center mt-2">Уже есть учетная запись? <a href="login.php">Войти в систему</a></p>
    </div>
</div>

<script>
document.getElementById('register-form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const name = document.getElementById('name').value;
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Пароль должен содержать минимум 6 символов');
        return false;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Пароли не совпадают');
        return false;
    }
    
    if (name.length < 2 || name.length > 50) {
        e.preventDefault();
        alert('Имя должно содержать от 2 до 50 символов');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>