<?php
require_once '../config.php';
require_once '../db.php';

session_start();

// -проверка

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit();
}

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$client_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['delete_message'] = ['type' => 'error', 'text' => 'Недопустимый метод запроса.'];
    header('Location: ' . BASE_URL . 'pages/reports.php');
    exit();
}

if (!isset($_POST['report_id']) || !filter_var($_POST['report_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['delete_message'] = ['type' => 'error', 'text' => 'Ошибка: Некорректный ID отчета.'];
    header('Location: ' . BASE_URL . 'pages/reports.php');
    exit();
}

$report_id = (int)$_POST['report_id'];

// удаление папки
function deleteDirectory($dirPath) {
    if (!is_dir($dirPath)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($dirPath);
}

// логика удаления
try {
    $pdo->beginTransaction();

    if ($is_admin) {
        $stmt = $pdo->prepare("SELECT original_file_path, json_result_path FROM reports WHERE id = ?");
        $stmt->execute([$report_id]);
    } else {
        // Клиент: проверяем, что отчет принадлежит ему
        $stmt = $pdo->prepare("SELECT original_file_path, json_result_path FROM reports WHERE id = ? AND client_user_id = ?");
        $stmt->execute([$report_id, $client_id]);
    }
    
    $report = $stmt->fetch();

    if (!$report) {
        $pdo->rollBack();
        $_SESSION['delete_message'] = ['type' => 'error', 'text' => 'Ошибка: Отчет не найден или у вас нет прав на его удаление.'];
    } else {
        // Удаляем запись из базы данных
        $deleteStmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $deleteStmt->execute([$report_id]);

        // Удаляем папки
        deleteDirectory(__DIR__ . '/../' . $report['original_file_path']);
        deleteDirectory(__DIR__ . '/../' . $report['json_result_path']);
        
        $pdo->commit();
        $_SESSION['delete_message'] = ['type' => 'success', 'text' => 'Отчет был успешно удален.'];
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['delete_message'] = ['type' => 'error', 'text' => 'Произошла ошибка при удалении отчета: ' . $e->getMessage()];
}

// редирект
$redirect_url = BASE_URL . 'pages/reports.php';


if ($is_admin && isset($_POST['user_id_for_redirect'])) {
    $redirect_user_id = (int)$_POST['user_id_for_redirect'];
    $redirect_url = BASE_URL . 'pages/user_profile.php?id=' . $redirect_user_id;
}

header('Location: ' . $redirect_url);
exit();