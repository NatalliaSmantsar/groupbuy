<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    if (!validate_email($email)) {
        $error = "Введите корректный email адрес";
    } elseif (empty($password)) {
        $error = "Введите пароль";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                
                header("Location: index.php?success=Добро пожаловать, " . urlencode($_SESSION['name']) . "!");
                exit;
            } else {
                $error = "Неверный пароль!";
            }
        } else {
            $error = "Пользователь не найден!";
        }
        $stmt->close();
    }
}
?>

<div class="container">
    <div class="form">
        <h2 class="section-title">Вход в систему</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" id="login-form">
            <div class="form-group">
                <label for="email" class="form-label">Email адрес:</label>
                <input type="email" id="email" name="email" placeholder="Введите ваш email" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Пароль:</label>
                <input type="password" id="password" name="password" placeholder="Введите пароль" required class="form-control">
            </div>

            <button type="submit" class="btn" style="width: 100%;">Войти в систему</button>
        </form>

        <p class="center mt-2">Нет учетной записи? <a href="register.php">Зарегистрироваться</a></p>
    </div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    if (!email || !password) {
        e.preventDefault();
        alert('Пожалуйста, заполните все поля');
        return false;
    }
    
    if (!email.includes('@')) {
        e.preventDefault();
        alert('Введите корректный email адрес');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>