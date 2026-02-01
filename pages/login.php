<?php
require_once '../config.php';
require_once '../db.php';

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit();
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        // Определяем роль
        $stmt_admin = $pdo->prepare("SELECT user_id FROM admins WHERE user_id = ?");
        $stmt_admin->execute([$user['id']]);
        $is_admin = $stmt_admin->fetch();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        $_SESSION['user_role'] = $is_admin ? 'admin' : 'client';
        
        header('Location: ' . BASE_URL);
        exit();
    } else {
        $error_message = 'Неверный логин или пароль.';
    }
}

$page_title = 'Вход в систему';
require_once '../templates/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-tabs">
            <a href="<?php echo BASE_URL; ?>pages/login.php" class="active">Вход</a>
            <a href="<?php echo BASE_URL; ?>pages/register.php">Регистрация</a>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Войти</button>
        </form>
    </div>
</div>

<?php
require_once '../templates/footer.php';
?>