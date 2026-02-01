<?php
require_once '../config.php';
require_once '../db.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit();
}

$client_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, report_date, original_file_path FROM reports WHERE client_user_id = ? ORDER BY report_date DESC");
$stmt->execute([$client_id]);
$reports = $stmt->fetchAll();

$months_ru = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];



$page_title = 'Мои Отчеты';
require_once '../templates/header.php';
?>

<?php
if (isset($_SESSION['delete_message'])) {
    $message = $_SESSION['delete_message'];
    $message_class = $message['type'] === 'success' ? 'message-success' : 'message-error';
    // Добавляем немного отступа сверху для красоты
    echo "<div class=\"message {$message_class}\" style=\"margin-top: 20px;\">" . htmlspecialchars($message['text']) . "</div>";
    unset($_SESSION['delete_message']);
}
?>

<div class="reports-container">
    <div class="reports-header">
        <h1>Мои Отчеты</h1>
        <a href="<?php echo BASE_URL; ?>pages/add-analytics.php" class="btn btn-primary">
            Добавить аналитику
        </a>
    </div>
    <div class="reports-body">
        
        <?php if (empty($reports)): ?>
            <div class="empty-list-message">
                <p>У вас пока нет загруженных отчетов.</p>
                <p>Начните с <a href="<?php echo BASE_URL; ?>pages/add-analytics.php">добавления первой аналитики</a>, чтобы увидеть результаты.</p>

            </div>
        <?php else: ?>
            <div class="table-wrapper">
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
                                // Форматируем дату в "Месяц Год"
                                $dateObj = new DateTime($report['report_date']);
                                $monthIndex = (int)$dateObj->format('n') - 1;
                                $year = $dateObj->format('Y');
                                $formatted_date = $months_ru[$monthIndex] . ' ' . $year;

                                // --- НОВЫЙ БЛОК: Проверка наличия всех файлов для анализа по товарам ---
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
                                <td>
                                    <strong><?php echo htmlspecialchars($formatted_date); ?></strong>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?php echo BASE_URL; ?>pages/report-marketplace.php?id=<?php echo $report['id']; ?>" class="btn btn-small">Аналитика по МП</a>
                                    
                                    <?php if ($allFilesExist): ?>
                          
                                        <a href="<?php echo BASE_URL; ?>pages/report-products.php?id=<?php echo $report['id']; ?>" class="btn btn-small">Аналитика по товарам</a>
                                    <?php else: ?>
            
                                        <span class="btn btn-small" style="opacity: 0.5; cursor: not-allowed;" title="Для анализа по товарам необходимо загрузить отчеты всех трех маркетплейсов (WB, Ozon, YM).">Аналитика по товарам</span>
                                    <?php endif; ?>

                                    <form action="<?php echo BASE_URL; ?>pages/delete-report.php" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить отчет за <?php echo htmlspecialchars($formatted_date); ?>? Это действие необратимо.');" style="display: inline-block; margin-left: 5px;">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
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

<?php
require_once '../templates/footer.php';
?>