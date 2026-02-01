<?php
require_once '../config.php';
require_once '../db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit();
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("Ошибка: Некорректный ID отчета.");
}
$report_id = (int)$_GET['id'];

// проверка доступа
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$client_id = $_SESSION['user_id'];

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

// список товаров
$products = [];
$costs_file_path = __DIR__ . '/../' . $report['original_file_path'] . '/costs.xlsx';
if (file_exists($costs_file_path)) {
    try {
        $spreadsheet = IOFactory::load($costs_file_path);
        $sheet = $spreadsheet->getActiveSheet();
        // Начинаем с 3-й строки, т.к. первые две - заголовки
        for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
            $productName = $sheet->getCell('A' . $row)->getValue();
            if (!empty($productName)) {
                $products[] = $productName;
            }
        }
    } catch (Exception $e) {
        die("Ошибка чтения файла себестоимости: " . $e->getMessage());
    }
}

// анализ товара
$analysis_data = null;
$selected_product = $_POST['product_name'] ?? ($products[0] ?? '');
$success_message = '';
$error_message = '';
$json_file_for_display = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze'])) {
    if (empty($selected_product)) {
        $error_message = "Ошибка: Пожалуйста, выберите товар для анализа.";
    } else {
        // файлы для питона
        $user_folder_name = $_SESSION['user_login'] . '_' . $_SESSION['user_id'];
        $report_date_folder = basename($report['original_file_path']);
        
        $input_path = __DIR__ . '/../' . $report['original_file_path'] . '/';
        $output_path = __DIR__ . '/../' . $report['json_result_path'] . '/';
        $relative_json_path = 'json_source/' . $user_folder_name . '/' . $report_date_folder;

        // вызов питона
        $python_executable = escapeshellarg(__DIR__ . '/../download_process/.venv/Scripts/python.exe');
        $script_path = escapeshellarg(__DIR__ . '/../download_process/parsers/analysis_goods_script.py');
        
        $cmd = join(" ", [
            $python_executable,
            $script_path,
            escapeshellarg($input_path),
            escapeshellarg($output_path),
            escapeshellarg($selected_product),
            escapeshellarg($relative_json_path)
        ]);

        $output = shell_exec($cmd . " 2>&1"); // 2>&1 для перехвата ошибок
        $result = json_decode(trim($output), true);
        
        if ($result && isset($result['status']) && $result['status'] === 'success') {
            $success_message = "Анализ для товара '{$selected_product}' успешно завершен!";
            $json_file_for_display = $result['json_file'];
        } else {
            $error_message = "Ошибка при выполнении анализа: " . htmlspecialchars($result['message'] ?? $output);
        }
    }
}

// отображение дсон
if (!empty($json_file_for_display) && file_exists($json_file_for_display)) {
     $analysis_data = json_decode(file_get_contents($json_file_for_display), true);
} elseif (empty($json_file_for_display) && !isset($_POST['analyze'])) {
    $safe_product_name = preg_replace('/[\\\\\/`*`?:"<>|]/', '', $selected_product);
    $last_json_path = __DIR__ . '/../' . $report['json_result_path'] . "/product_analysis_{$safe_product_name}.json";
    if (file_exists($last_json_path)) {
        $analysis_data = json_decode(file_get_contents($last_json_path), true);
    }
}


// страница
$page_title = 'Аналитика по товарам';
require_once '../templates/header.php';

// Вспомогательные функции для рендеринга
function format_currency($number) { return number_format($number ?? 0, 2, ',', ' ') . ' ₽'; }
function format_profit($number) {
    $num = $number ?? 0;
    $class = $num >= 0 ? 'profit-positive' : 'profit-negative';
    return "<span class=\"{$class}\">" . format_currency($num) . "</span>";
}
?>

<style>

    .report-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
    .analysis-form-container { background-color: var(--surface-color); border: 1px solid var(--border-color); padding: 20px; border-radius: 8px; margin-bottom: 30px; }
    .analysis-form { display: flex; align-items: flex-end; gap: 15px; }
    .analysis-form .form-group { flex-grow: 1; margin: 0; }
    .analysis-form select.form-control { width: 100%; padding: 9px; background-color: var(--bg-color); border: 1px solid var(--border-color); border-radius: 5px; color: var(--text-color); font-size: 15px; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .summary-card { background-color: var(--surface-color); border: 1px solid var(--border-color); padding: 20px; border-radius: 8px; text-align: center; }
    .summary-card .label { font-size: 14px; color: var(--text-secondary-color); margin-bottom: 8px; }
    .summary-card .value { font-size: 24px; font-weight: bold; }
    .summary-card img { max-width: 100%; border-radius: 5px; margin-top: 15px; border: 1px solid var(--border-color); }
    .profit-positive { color: #2ecc71; } .profit-negative { color: #e74c3c; }
    .platform-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
    .platform-card { background-color: var(--surface-color); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
    .platform-header { background-color: #2a2f36; padding: 15px 20px; font-size: 20px; font-weight: bold; }
    .platform-body { padding: 20px; display: flex; flex-direction: column; gap: 20px; }
    .platform-body .metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .platform-body .metric .label { font-size: 14px; color: var(--text-secondary-color); }
    .platform-body .metric .value { font-size: 18px; font-weight: 500; }
    .platform-body .chart-container img { width: 100%; border-radius: 5px; border: 1px solid var(--border-color); }
    .summary-card a, 
    .chart-container a {
        display: block;
        transition: transform 0.2s;
    }

    .summary-card a:hover, 
    .chart-container a:hover {
        transform: scale(1.02);
    }

    .summary-card img, 
    .chart-container img {
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .summary-card img:hover, 
    .chart-container img:hover {
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
</style>

<div class="report-page-container">
    <div class="report-page-header">
        <h1>Аналитика по товарам</h1>
        <a href="<?php echo BASE_URL; ?>pages/reports.php" class="btn">← Назад к списку отчетов</a>
    </div>

    <div class="analysis-form-container">
        <form action="report-products.php?id=<?php echo $report_id; ?>" method="POST" class="analysis-form">
            <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
            <div class="form-group">
                <label for="product_name">Выберите товар для анализа:</label>
                <select name="product_name" id="product_name" class="form-control">
                    <?php if (empty($products)): ?>
                        <option value="">Список товаров не найден</option>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product); ?>" <?php echo ($product === $selected_product) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" name="analyze" class="btn btn-primary">Анализировать</button>
        </form>
    </div>

    <?php if ($success_message): ?><div class="message message-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="message message-error"><?php echo $error_message; ?></div><?php endif; ?>

    <?php if ($analysis_data): ?>
        <h2>Анализ товара: <?php echo htmlspecialchars($analysis_data['product_name']); ?></h2>

        <h3>Общая сводка и прогнозы</h3>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Лучший МП по выручке (прогноз)</div>
                <div class="value"><?php echo htmlspecialchars($analysis_data['general_stats']['best_marketplace_by_revenue']['name'] ?? 'Н/Д'); ?></div>
                <div class="value profit-positive"><?php echo format_currency($analysis_data['general_stats']['best_marketplace_by_revenue']['forecast_revenue']); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Лучший МП по продажам (прогноз)</div>
                <div class="value"><?php echo htmlspecialchars($analysis_data['general_stats']['best_marketplace_by_sales_count']['name'] ?? 'Н/Д'); ?></div>
                <div class="value profit-positive"><?php echo htmlspecialchars($analysis_data['general_stats']['best_marketplace_by_sales_count']['forecast_sales'] ?? 0); ?> шт.</div>
            </div>
        </div>

        <div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <?php foreach($analysis_data['general_stats']['charts'] as $key => $chart_path): if($chart_path): ?>
                <div class="summary-card">
                    <img src="<?php echo BASE_URL . str_replace('\\', '/', $chart_path); ?>" alt="Диаграмма <?php echo $key; ?>">
                </div>
            <?php endif; endforeach; ?>
        </div>

        <h3>Детализация по маркетплейсам</h3>
        <div class="platform-grid">
            <?php foreach($analysis_data['marketplaces'] as $name => $mp): ?>
                <div class="platform-card">
                    <div class="platform-header"><?php echo htmlspecialchars($name); ?></div>
                    <div class="platform-body">
                        <div class="metrics-grid">
                            <div class="metric"><div class="label">Выручка</div><div class="value"><?php echo format_currency($mp['revenue']); ?></div></div>
                            <div class="metric"><div class="label">Прибыль</div><div class="value"><?php echo format_profit($mp['profit']); ?></div></div>
                            <div class="metric"><div class="label">Продажи</div><div class="value"><?php echo $mp['sales_count']; ?> шт.</div></div>
                            <div class="metric"><div class="label">Прогноз продаж</div><div class="value"><?php echo ($mp['forecast']['sales_count'] ?? 0); ?> шт.</div></div>
                        </div>
                        <div class="chart-container">
                            <?php if(isset($mp['charts']['revenue_trend']) && $mp['charts']['revenue_trend']): ?>
                                <a href="<?php echo BASE_URL . str_replace('\\', '/', $mp['charts']['revenue_trend']); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo BASE_URL . str_replace('\\', '/', $mp['charts']['revenue_trend']); ?>" alt="Тренд выручки">
                                </a>
                            <?php endif; ?>
                            <?php if(isset($mp['charts']['forecast']) && $mp['charts']['forecast']): ?>
                                <a href="<?php echo BASE_URL . str_replace('\\', '/', $mp['charts']['forecast']); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo BASE_URL . str_replace('\\', '/', $mp['charts']['forecast']); ?>" alt="Прогноз">
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (isset($_POST['analyze']) && !$error_message): ?>
        <div class="message message-error">Не удалось загрузить данные для отображения.</div>
    <?php endif; ?>
</div>

<?php require_once '../templates/footer.php'; ?>