<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

session_start();

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if (!$is_admin) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$current_admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';


function deleteDirectory($dirPath) {
    if (!is_dir($dirPath)) {
        return; // Если папки нет, ничего не делаем
    }
    // Используем итератор для надежного обхода всех вложенных файлов и папок
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($dirPath); // Удаляем саму корневую папку
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $user_to_delete_id = (int)$_POST['delete_user_id'];

    if ($user_to_delete_id === $current_admin_id) {
        $message = "Вы не можете удалить свою собственную учетную запись.";
        $message_type = 'error';
    } else {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();

           
            $stmt_get_user = $pdo->prepare("SELECT login FROM users WHERE id = ?");
            $stmt_get_user->execute([$user_to_delete_id]);
            $user_to_delete = $stmt_get_user->fetch();

            if ($user_to_delete) {
                // 
                // (ON DELETE CASCADE 
                $stmt_delete_db = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt_delete_db->execute([$user_to_delete_id]);

                // Шаг 3: Формируем пути к папкам пользователя
                $user_folder_name = $user_to_delete['login'] . '_' . $user_to_delete_id;
                $main_source_path = __DIR__ . '/../main_source/' . $user_folder_name;
                $json_source_path = __DIR__ . '/../json_source/' . $user_folder_name;

                deleteDirectory($main_source_path);
                deleteDirectory($json_source_path);


                $pdo->commit();
                $message = "Пользователь и все его данные были успешно удалены.";
                $message_type = 'success';
            } else {
 
                $pdo->rollBack();
                $message = "Ошибка: Пользователь для удаления не найден.";
                $message_type = 'error';
            }
        } catch (Exception $e) {
   
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Произошла критическая ошибка при удалении пользователя: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$stmt = $pdo->prepare("SELECT id, login, last_name, registration_date FROM users WHERE id != ? ORDER BY id ASC");
$stmt->execute([$current_admin_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Управление пользователями | MP Analytics';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container">
    <div class="users-page-container">
        <h1>Управление пользователями</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type === 'success' ? 'message-success' : 'message-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>Фамилия</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">Других пользователей в системе нет.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>pages/user_profile.php?id=<?php echo $user['id']; ?>" class="user-link">
                                        <?php echo htmlspecialchars($user['login']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['registration_date'])); ?></td>
                                <td class="actions-cell">
                                    <form method="POST" action="users.php" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя и ВСЕ его данные? Это действие необратимо.');">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>