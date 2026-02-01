<?php

$page_title = $page_title ?? 'MP Analytics';

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_login = $_SESSION['user_login'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container">
       <div class="logo">
            <a href="<?php echo BASE_URL; ?>">
                <svg class="site-logo-icon" viewBox="0 0 29 17" xmlns="http://www.w3.org/2000/svg">
                    <path d="M 19 2 L 10 17 L 2 3 L 2 17 L 0 17 L 0 0 L 3 0 L 10 12 L 17 0 L 22 0 C 29 0 29 13 22 13 L 19 13 L 19 17 L 17 17 L 17 11 L 22 11 C 24 11 27 4 22 2 L 19 2 M 19 2"/>
                </svg>
                <span>MPanalytics</span>
            </a>
        </div>
        
        <div class="header-right">
            <nav class="main-nav">
                <?php if (!$is_logged_in): // Если пользователь НЕ зашел (гость) ?>
                    <a href="<?php echo BASE_URL; ?>pages/contacts.php" class="btn">Контакты</a>
                
                <?php elseif ($user_role === 'client'): // Если зашел КЛИЕНТ ?>
                    <a href="<?php echo BASE_URL; ?>pages/reports.php" class="btn">Отчеты</a>
                    <a href="<?php echo BASE_URL; ?>pages/contacts.php" class="btn">Контакты</a>

                <?php elseif ($user_role === 'admin'): // Если зашел АДМИН ?>
                    <a href="<?php echo BASE_URL; ?>pages/users.php" class="btn">Пользователи</a>
                    <a href="<?php echo BASE_URL; ?>pages/contacts.php" class="btn">Контакты</a>
                <?php endif; ?>
            </nav>

            <div class="profile-button-container">
                <?php if ($is_logged_in): // Если пользователь зашел (любой) ?>
                    <a href="<?php echo BASE_URL; ?>pages/profile.php" class="btn btn-primary">
                        <?php echo htmlspecialchars($user_login); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>pages/login.php" class="btn btn-primary">Вход</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="container">