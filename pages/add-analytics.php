<?php
require_once '../config.php';
require_once '../db.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit();
}

$success_messages = [];
$error_messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_date = $_POST['report_date']; 
    $client_id = $_SESSION['user_id'];
    $client_login = $_SESSION['user_login'];

    if (empty($_FILES['costs']['name']) || $_FILES['costs']['error'] !== UPLOAD_ERR_OK) {
        $error_messages[] = 'Ошибка: Файл с себестоимостью (Costs) является обязательным.';
    }
    $marketplaceFileUploaded = false;
    foreach (['wb', 'ozon', 'ym'] as $input_name) {
        if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $marketplaceFileUploaded = true;
            break;
        }
    }
    if (!$marketplaceFileUploaded) {
        $error_messages[] = 'Ошибка: Необходимо загрузить отчет хотя бы одного маркетплейса.';
    }

    if (empty($error_messages)) {
        // папки
        $user_folder_name = $client_login . '_' . $client_id;
        
        // Папка для исходных загруженных файлов
        $main_source_dir = __DIR__ . '/../main_source/' . $user_folder_name . '/' . $report_date . '/';
        if (!is_dir($main_source_dir)) {
            mkdir($main_source_dir, 0755, true);
        }

        // Папка для итоговых JSON и графиков
        $json_source_dir = __DIR__ . '/../json_source/' . $user_folder_name . '/' . $report_date . '/';
        if (!is_dir($json_source_dir)) {
            mkdir($json_source_dir, 0755, true);
        }
        
        // сохранение файлов
        $standard_filenames = ['costs' => 'costs', 'wb' => 'wb_report', 'ozon' => 'ozon_report', 'ym' => 'ym_report'];
        $sources = ['costs' => 'costs', 'wb' => 'wb', 'ozon' => 'ozon', 'ym' => 'ym'];
        
        foreach ($sources as $name => $file_input_name) {
            if (!empty($_FILES[$file_input_name]['name']) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$file_input_name];
                $original_filename = basename($file['name']);
                $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                $new_filename = $standard_filenames[$name] . '.' . $extension;
                $file_path = $main_source_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $success_messages[] = "Файл '{$original_filename}' успешно сохранен как '{$new_filename}'.";
                } else {
                    $error_messages[] = "Ошибка при сохранении файла '{$original_filename}'.";
                }
            }
        }

        // скрпты
        if (empty($error_messages)) {
            $python_executable = escapeshellarg(__DIR__ . '/../download_process/.venv/Scripts/python.exe');
            $path_to_python_project = __DIR__ . '/../download_process/';

            // мейн пай
            $main_py_path = escapeshellarg($path_to_python_project . 'main.py');
            $data_path_arg = escapeshellarg($main_source_dir);
            $report_date_arg = escapeshellarg($report_date);
            
            $command1 = "cd " . escapeshellarg($path_to_python_project) . " && set PYTHONIOENCODING=UTF-8 && " . $python_executable . " " . $main_py_path . " " . $data_path_arg . " " . $report_date_arg;
            $output1 = shell_exec($command1);
            $result1 = json_decode(trim($output1), true);

            if (!$result1 || !isset($result1['status']) || $result1['status'] !== 'success') {
                $error_messages[] = "Ошибка на этапе предварительной обработки файлов!";
                $error_messages[] = "Ответ скрипта main.py: " . htmlspecialchars($result1['message'] ?? $output1);
            } else {
                $success_messages[] = "Предварительная обработка файлов завершена успешно.";

                // анализ
                $analysis_py_path = escapeshellarg($path_to_python_project . 'parsers/analysis_script.py');
                $json_path_arg = escapeshellarg($json_source_dir);
                $relative_json_path = 'json_source/' . $user_folder_name . '/' . $report_date;
                $relative_json_path_arg = escapeshellarg($relative_json_path);

                $command2 = "cd " . escapeshellarg($path_to_python_project) . " && set PYTHONIOENCODING=UTF-8 && " . $python_executable . " " . $analysis_py_path . " " . $data_path_arg . " " . $json_path_arg . " " . $relative_json_path_arg;
                $output2 = shell_exec($command2);
                $result2 = json_decode(trim($output2), true);

                if (!$result2 || !isset($result2['status']) || $result2['status'] !== 'success') {
                    $error_messages[] = "Ошибка на этапе финального анализа данных!";
                    $error_messages[] = "Ответ скрипта analysis_script.py: " . htmlspecialchars($result2['message'] ?? $output2);
                } else {
                    $success_messages[] = "Финальный анализ данных и генерация отчета прошли успешно!";

                    // сохранение в бд
                    try {
                        $report_full_date = $report_date . '-01'; // Добавляем день для типа DATE в MySQL
                        $original_path_db = 'main_source/' . $user_folder_name . '/' . $report_date;
                        $json_path_db = 'json_source/' . $user_folder_name . '/' . $report_date;

                        // Проверяем, есть ли уже отчет за этот период
                        $stmt = $pdo->prepare("SELECT id FROM reports WHERE client_user_id = ? AND report_date = ?");
                        $stmt->execute([$client_id, $report_full_date]);
                        $existing_report = $stmt->fetch();

                        if ($existing_report) {
                            // Обновляем существующий отчет
                            $stmt = $pdo->prepare("UPDATE reports SET original_file_path = ?, json_result_path = ?, uploaded_at = NOW() WHERE id = ?");
                            $stmt->execute([$original_path_db, $json_path_db, $existing_report['id']]);
                            $success_messages[] = "Данные в базе обновлены для периода " . $report_date;
                        } else {
                            // Вставляем новый отчет
                            $stmt = $pdo->prepare("INSERT INTO reports (client_user_id, report_date, original_file_path, json_result_path) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$client_id, $report_full_date, $original_path_db, $json_path_db]);
                            $success_messages[] = "Новый отчет за " . $report_date . " успешно добавлен в базу данных.";
                        }
                    } catch (PDOException $e) {
                        $error_messages[] = "Ошибка базы данных: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$page_title = 'Добавить/Обновить Аналитику';
require_once '../templates/header.php';
?>


<div class="upload-form-wrapper">
    <div class="upload-form-box">
        <h2>Загрузка файлов аналитики</h2>

        <?php if (!empty($success_messages)): ?>
            <div class="success-message">
                <?php foreach ($success_messages as $message): echo "<p>" . htmlspecialchars($message) . "</p>"; endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_messages)): ?>
            <div class="error-message">
                <?php foreach ($error_messages as $message): echo "<p>" . htmlspecialchars($message) . "</p>"; endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div id="js-error-container"></div>

        <form action="add-analytics.php" method="POST" enctype="multipart/form-data" id="analytics-form">
            <div class="form-group">
                <label for="report_date">Период отчета (Месяц и Год)</label>
                <input type="month" id="report_date" name="report_date" class="form-control" required value="<?php echo date('Y-m'); ?>">
            </div>
            
            <fieldset class="file-upload-fieldset">
                <legend>Файлы отчетов</legend>
                <div class="form-group">
                    <label for="costs_file" class="required">Себестоимость (ОБЯЗАТЕЛЬНО)</label>
                    <input type="file" id="costs_file" name="costs" class="form-control" required accept=".xlsx, .xls">
                </div>

                <div id="dynamic-inputs-container">
                    <div id="wb_group" class="form-group hidden">
                        <label for="wb_file">Wildberries</label>
                        <input type="file" id="wb_file" name="wb" class="form-control " accept=".xlsx, .xls">
                    </div>
                    <div id="ozon_group" class="form-group hidden">
                        <label for="ozon_file">Ozon</label>
                        <input type="file" id="ozon_file" name="ozon" class="form-control" accept=".xlsx, .xls">
                    </div>
                    <div id="ym_group" class="form-group hidden">
                        <label for="ym_file">Яндекс.Маркет</label>
                        <input type="file" id="ym_file" name="ym" class="form-control" accept=".xlsx, .xls">
                    </div>
                </div>

                <div class="add-marketplace-controls">
                    <select id="marketplace-selector">
                        <option value="" disabled selected>Добавить маркетплейс...</option>
                        <option value="wb">Wildberries</option>
                        <option value="ozon">Ozon</option>
                        <option value="ym">Яндекс.Маркет</option>
                    </select>
                    <button type="button" id="add-mp-btn" class="btn">+</button>
                </div>
            </fieldset>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 15px;">Загрузить</button>
        </form>
    </div>
</div>

<script>

    document.addEventListener('DOMContentLoaded', function() {
        const addButton = document.getElementById('add-mp-btn');
        const selector = document.getElementById('marketplace-selector');
        const form = document.getElementById('analytics-form');
        const jsErrorContainer = document.getElementById('js-error-container');

        addButton.addEventListener('click', function() {
            const selectedValue = selector.value;
            if (!selectedValue) return;

            const groupToShow = document.getElementById(selectedValue + '_group');
            if (groupToShow) {
                groupToShow.classList.remove('hidden');
            }

            selector.options[selector.selectedIndex].remove();
            selector.value = "";

            if (selector.options.length <= 1) {
                document.querySelector('.add-marketplace-controls').classList.add('hidden');
            }
        });
        
        form.addEventListener('submit', function(event) {
            jsErrorContainer.innerHTML = '';

            const wbFile = document.getElementById('wb_file').value;
            const ozonFile = document.getElementById('ozon_file').value;
            const ymFile = document.getElementById('ym_file').value;

            if (document.getElementById('wb_group').classList.contains('hidden') &&
                document.getElementById('ozon_group').classList.contains('hidden') &&
                document.getElementById('ym_group').classList.contains('hidden'))
            {
                 return;
            }

            if (wbFile === '' && ozonFile === '' && ymFile === '') {
                event.preventDefault(); 
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = '<p>Ошибка: Необходимо загрузить отчет хотя бы одного маркетплейса.</p>';
                jsErrorContainer.appendChild(errorDiv);
            }
        });
    });
</script>

<?php
require_once '../templates/footer.php';
?>