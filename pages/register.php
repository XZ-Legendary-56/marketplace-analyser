<?php
require_once '../config.php';
require_once '../db.php';

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($login) || empty($password)) {
        $errors[] = 'Все поля, кроме телефона, обязательны для заполнения.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Пароли не совпадают.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный формат Email.';
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
    $stmt->execute([$login]);
    if ($stmt->fetch()) {
        $errors[] = 'Этот логин уже занят.';
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = 'Этот Email уже зарегистрирован.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt_user = $pdo->prepare(
                "INSERT INTO users (first_name, last_name, email, phone_number, login, password) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt_user->execute([$first_name, $last_name, $email, $phone_number, $login, $password]);
            
            $user_id = $pdo->lastInsertId();

            $stmt_client = $pdo->prepare("INSERT INTO clients (user_id) VALUES (?)");
            $stmt_client->execute([$user_id]);

            $pdo->commit();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_login'] = $login;
            $_SESSION['user_role'] = 'client';

            header('Location: ' . BASE_URL);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка регистрации. Попробуйте позже.';
        }
    }
}

$page_title = 'Регистрация';
require_once '../templates/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-tabs">
            <a href="<?php echo BASE_URL; ?>pages/login.php">Вход</a>
            <a href="<?php echo BASE_URL; ?>pages/register.php" class="active">Регистрация</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p style="margin:0;"><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group"><label for="first_name">Имя</label><input type="text" id="first_name" name="first_name" required></div>
            <div class="form-group"><label for="last_name">Фамилия</label><input type="text" id="last_name" name="last_name" required></div>
            <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" required></div>
            <div class="form-group">
                <label for="phone_number">Телефон (необязательно)</label>
                <input type="tel" id="phone_number" name="phone_number" >
            </div>
            <div class="form-group"><label for="login">Логин</label><input type="text" id="login" name="login" required></div>
            <div class="form-group"><label for="password">Пароль</label><input type="password" id="password" name="password" required></div>
            <div class="form-group"><label for="password_confirm">Повторите пароль</label><input type="password" id="password_confirm" name="password_confirm" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Зарегистрироваться</button>
        </form>
    </div>
</div>

<?php
require_once '../templates/footer.php';
?>