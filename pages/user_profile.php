<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

session_start();

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if (!$is_admin) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: ' . BASE_URL . 'pages/users.php');
    exit();
}
$user_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ' . BASE_URL . 'pages/users.php');
    exit();
}

$stmt_reports = $pdo->prepare("SELECT id, report_date, original_file_path, json_result_path FROM reports WHERE client_user_id = ? ORDER BY report_date DESC");
$stmt_reports->execute([$user_id]);
$reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);

$months_ru = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];


$page_title = 'Профиль пользователя ' . htmlspecialchars($user['login']);
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container">
    <div class="user-profile-page-container">
        <div class="profile-box-wide">
             <h2>Профиль: <?php echo htmlspecialchars($user['login']); ?></h2>
             <div class="profile-info">
                 <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id']); ?></p>
                 <p><strong>Имя:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
                 <p><strong>Фамилия:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
                 <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                 <p><strong>Телефон:</strong> <?php echo htmlspecialchars($user['phone_number'] ?: 'Не указан'); ?></p>
                 <p><strong>Дата регистрации:</strong> <?php echo date('d.m.Y в H:i', strtotime($user['registration_date'])); ?></p>
             </div>
             <div class="profile-button-wrapper">
                <a href="<?php echo BASE_URL; ?>pages/users.php" class="btn">Назад к списку</a>
             </div>
        </div>

        <div class="reports-placeholder-box" style="text-align: left;">
            <h2 style="text-align: center;">Отчеты пользователя</h2>
            <?php if (empty($reports)): ?>
                <p class="empty-list-message" style="text-align: center;">У этого пользователя еще нет отчетов.</p>
            <?php else: ?>
                <div class="table-wrapper" style="margin-top: 20px;">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Период</th>
                                <th style="width: 1%; text-align: right;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <?php
                                    $dateObj = new DateTime($report['report_date']);
                                    $formatted_date = $months_ru[(int)$dateObj->format('n') - 1] . ' ' . $dateObj->format('Y');
                                    
                                    // Проверка наличия файлов для кнопки "Аналитика по товарам"
                                    $allFilesExist = true;
                                    $required_files = ['wb_report.xlsx', 'ozon_report.xlsx', 'ym_report.xlsx'];
                                    $report_dir = __DIR__ . '/../' . $report['original_file_path'] . '/';
                                    foreach ($required_files as $filename) {
                                        if (!file_exists($report_dir . $filename)) {
                                            $allFilesExist = false;
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($formatted_date); ?></strong></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="<?php echo BASE_URL; ?>pages/report-marketplace.php?id=<?php echo $report['id']; ?>" class="btn btn-small">Аналитика по МП</a>
                                        
                                        <?php if ($allFilesExist): ?>
                                            <a href="<?php echo BASE_URL; ?>pages/report-products.php?id=<?php echo $report['id']; ?>" class="btn btn-small">Аналитика по товарам</a>
                                        <?php else: ?>
                                            <span class="btn btn-small" style="opacity: 0.5; cursor: not-allowed;" title="Для анализа по товарам необходимо загрузить отчеты всех трех маркетплейсов.">Аналитика по товарам</span>
                                        <?php endif; ?>

                                        <form action="<?php echo BASE_URL; ?>pages/delete-report.php" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить отчет за <?php echo htmlspecialchars($formatted_date); ?> для пользователя <?php echo htmlspecialchars($user['login']); ?>?');" style="display: inline-block; margin-left: 5px;">
                                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                            <input type="hidden" name="user_id_for_redirect" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-small btn-danger">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>