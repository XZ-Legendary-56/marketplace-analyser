<?php
require_once '../config.php';
require_once '../db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$page_title = 'Профиль пользователя';
require_once '../templates/header.php';
?>

<div class="auth-wrapper">
    <div class="profile-box">
        <h2>Профиль: <?php echo htmlspecialchars($user['login']); ?></h2>
        <div class="profile-info">
            <p><strong>Имя:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
            <p><strong>Фамилия:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Телефон:</strong> <?php echo htmlspecialchars($user['phone_number'] ?? 'Не указан'); ?></p>
            <p><strong>Дата регистрации:</strong> <?php echo date('d.m.Y H:i', strtotime($user['registration_date'])); ?></p>
        </div>
        <div class="profile-button-wrapper">
            <a href="<?php echo BASE_URL; ?>pages/logout.php" class="btn">Выход</a>
        </div>
    </div>
</div>

<?php
require_once '../templates/footer.php';
?>