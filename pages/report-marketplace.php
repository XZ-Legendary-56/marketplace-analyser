<?php
require_once '../config.php';
require_once '../db.php';

session_start();

// доступ

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit();
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("Ошибка: Некорректный ID отчета.");
}
$report_id = (int)$_GET['id'];

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$client_id = $_SESSION['user_id'];

// Формируем SQL-запрос и параметры динамически
$sql = "SELECT * FROM reports WHERE id = ?";
$params = [$report_id];

// Если пользователь не админ, добавляем условие проверки владения
if (!$is_admin) {
    $sql .= " AND client_user_id = ?";
    $params[] = $client_id;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$report = $stmt->fetch();

if (!$report) {

    die("Ошибка: Отчет не найден или у вас нет прав для его просмотра.");
}


$json_file_path = __DIR__ . '/../' . $report['json_result_path'] . '/complete_marketplace_stats.json';

if (!file_exists($json_file_path)) {
    die("Критическая ошибка: Файл с данными отчета не найден. Путь: " . htmlspecialchars($json_file_path));
}

$json_content = file_get_contents($json_file_path);
$data = json_decode($json_content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Ошибка: не удалось прочитать данные из файла аналитики. Файл поврежден.");
}

function format_currency($number) {
    return number_format($number, 2, ',', ' ') . ' ₽';
}

function format_profit($number) {
    $class = $number >= 0 ? 'profit-positive' : 'profit-negative';
    $formatted_number = format_currency($number);
    return "<span class=\"{$class}\">{$formatted_number}</span>";
}

$months_ru = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
];
$dateObj = new DateTime($report['report_date']);
$monthIndex = (int)$dateObj->format('n') - 1;
$year = $dateObj->format('Y');
$formatted_period = $months_ru[$monthIndex] . ' ' . $year;

$page_title = 'Аналитика по МП за ' . $formatted_period;
require_once '../templates/header.php';
?>

<style>
    .report-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .report-title h1 { margin: 0; font-size: 26px; }
    .report-title .period { color: var(--text-secondary-color); font-size: 16px; margin-top: 5px; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background-color: var(--surface-color);
        border: 1px solid var(--border-color);
        padding: 20px;
        border-radius: 8px;
    }
    .summary-card .label { font-size: 14px; color: var(--text-secondary-color); margin-bottom: 8px; }
    .summary-card .value { font-size: 24px; font-weight: bold; }
    .profit-positive { color: #2ecc71; }
    .profit-negative { color: #e74c3c; }

    .platform-card {
        background-color: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 25px;
        overflow: hidden;
    }
    .platform-header {
        background-color: #2a2f36;
        padding: 15px 20px;
        font-size: 20px;
        font-weight: bold;
    }
    .platform-body {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 25px;
    }
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
    }
    .metric { text-align: center; }
    .lists-and-graphs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
    .product-lists, .graph-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .graph-container img {
        width: 100%;
        height: auto;
        border-radius: 5px;
        border: 1px solid var(--border-color);
    }
    @media (max-width: 992px) {
        .lists-and-graphs { grid-template-columns: 1fr; }
    }
</style>

<div class="report-page-container">

    <div class="report-page-header">
        <div class="report-title">
            <h1>Аналитика по маркетплейсам</h1>
            <div class="period"><?php echo htmlspecialchars($formatted_period); ?></div>
        </div>
        <a href="<?php echo BASE_URL; ?>pages/reports.php" class="btn">← Назад к списку отчетов</a>
    </div>

    <!-- Общая сводка -->
    <h3>Общая сводка</h3>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Общая выручка</div>
            <div class="value"><?php echo format_currency($data['general_stats']['total_revenue']); ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Общая прибыль</div>
            <div class="value"><?php echo format_profit($data['general_stats']['total_profit']); ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Всего уникальных товаров</div>
            <div class="value"><?php echo htmlspecialchars($data['general_stats']['total_products']); ?></div>
        </div>
    </div>
    
    <!-- Детализация по маркетплейсам -->
    <?php foreach ($data['platforms'] as $name => $platform): ?>
        <div class="platform-card">
            <div class="platform-header"><?php echo htmlspecialchars($name); ?></div>
            <div class="platform-body">
                
                <div class="metrics-grid">
                    <div class="metric">
                        <div class="label">Выручка</div>
                        <div class="value"><?php echo format_currency($platform['revenue']); ?></div>
                    </div>
                    <div class="metric">
                        <div class="label">Прибыль</div>
                        <div class="value"><?php echo format_profit($platform['profit']); ?></div>
                    </div>
                    <div class="metric">
                        <div class="label">Затраты на услуги</div>
                        <div class="value"><?php echo format_currency($platform['services_cost']); ?></div>
                    </div>
                    <div class="metric">
                        <div class="label">ROI</div>
                        <div class="value <?php echo $platform['roi'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo number_format($platform['roi'], 2, ',', ' '); ?>%
                        </div>
                    </div>
                </div>

                <div class="lists-and-graphs">
                    <div class="product-lists">
                        <div class="table-wrapper">
                            <table class="users-table">
                                <thead><tr><th colspan="2">Топ-3 по продажам (шт)</th></tr></thead>
                                <tbody>
                                    <?php foreach($platform['top_sales'] as $item): ?>
                                    <tr><td><?php echo htmlspecialchars($item[0]); ?></td><td style="text-align:right;"><?php echo htmlspecialchars($item[1]); ?> шт.</td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-wrapper">
                             <table class="users-table">
                                <thead><tr><th colspan="2">Топ-3 по прибыли</th></tr></thead>
                                <tbody>
                                    <?php foreach($platform['top_profit'] as $item): ?>
                                    <tr><td><?php echo htmlspecialchars($item[0]); ?></td><td style="text-align:right;"><?php echo format_profit($item[1]); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="graph-container">
                        <?php if (isset($platform['graphs']['sales_dynamics'])): ?>
                            <?php 
                                $graphPath = str_replace('\\', '/', $platform['graphs']['sales_dynamics']);
                            ?>
                             <a href="<?php echo BASE_URL . $graphPath; ?>" target="_blank">
                                <img src="<?php echo BASE_URL . $graphPath; ?>" alt="График динамики продаж">
                            </a>
                        <?php endif; ?>
                        <?php if (isset($platform['graphs']['5day_periods'])): ?>
                            <?php 
                                $graphPath5day = str_replace('\\', '/', $platform['graphs']['5day_periods']);
                            ?>
                             <a href="<?php echo BASE_URL . $graphPath5day; ?>" target="_blank">
                                <img src="<?php echo BASE_URL . $graphPath5day; ?>" alt="График по 5-дневным периодам">
                            </a>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>
    <?php endforeach; ?>

</div>

<?php
require_once '../templates/footer.php';
?>