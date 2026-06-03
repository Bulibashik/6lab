<?php
// admin.php — панель администратора (HTTP Basic Auth)
session_start();
header('Content-Type: text/html; charset=UTF-8');

// ---- HTTP Basic Auth ----
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    exit('<h1>401 Требуется авторизация</h1>');
}

// ---- Подключение к БД ----
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=localhost;dbname=u82318;charset=utf8', 'u82318', '5918027', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
    return $pdo;
}
$pdo = getDB();

// ---- Обработка удаления записи ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Удаляем связи языков
    $stmt = $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?");
    $stmt->execute([$id]);
    // Удаляем пользователя
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: admin.php');
    exit();
}

// ---- Получение списка всех пользователей ----
$users = [];
$stmt = $pdo->query("SELECT id, full_name, phone, email, birth_date, gender, biography, agreed, created_at FROM users ORDER BY created_at DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Получаем языки для пользователя
    $stmt2 = $pdo->prepare("SELECT l.name FROM user_languages ul JOIN languages l ON ul.language_id = l.id WHERE ul.user_id = ?");
    $stmt2->execute([$row['id']]);
    $langs = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    $row['languages'] = implode(', ', $langs);
    $users[] = $row;
}

// ---- Статистика по языкам ----
$stmt = $pdo->query("
    SELECT l.name, COUNT(ul.language_id) as cnt
    FROM languages l
    LEFT JOIN user_languages ul ON l.id = ul.language_id
    GROUP BY l.id
    ORDER BY cnt DESC
");
$lang_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 30px; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 30px; border-radius: 20px; }
        h1, h2 { text-align: center; color: #1e466e; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #c2819b; color: white; }
        .actions a { margin-right: 10px; }
        .edit { color: green; }
        .delete { color: red; }
        .lang-stats { display: inline-block; margin: 10px; padding: 10px; background: #f9f9f9; border-radius: 10px; }
        hr { margin: 30px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>👑 Панель администратора</h1>
    <p style="text-align:center">Вы вошли как <strong>admin</strong>. Здесь вы можете просматривать, редактировать и удалять все анкеты.</p>

    <h2>📊 Статистика по языкам программирования</h2>
    <div>
        <?php foreach ($lang_stats as $stat): ?>
            <div class="lang-stats">
                <strong><?= htmlspecialchars($stat['name']) ?></strong>: <?= $stat['cnt'] ?> чел.
            </div>
        <?php endforeach; ?>
    </div>

    <hr>

    <h2>📋 Все пользователи</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Языки</th><th>Биография</th><th>Согласие</th><th>Дата регистрации</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['full_name']) ?></td>
                <td><?= htmlspecialchars($user['phone']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= $user['birth_date'] ?></td>
                <td><?= $user['gender'] ?></td>
                <td><?= htmlspecialchars($user['languages']) ?></td>
                <td><?= nl2br(htmlspecialchars($user['biography'])) ?></td>
                <td><?= $user['agreed'] ? 'Да' : 'Нет' ?></td>
                <td><?= $user['created_at'] ?></td>
                <td class="actions">
                    <a class="edit" href="index.php?edit_id=<?= $user['id'] ?>">✏️ Редактировать</a>
                    <a class="delete" href="admin.php?delete=<?= $user['id'] ?>" onclick="return confirm('Удалить запись?')">🗑️ Удалить</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:20px"><a href="index.php">← Вернуться на главную страницу</a></p>
</div>
</body>
</html>
