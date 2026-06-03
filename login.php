<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
header('Content-Type: text/html; charset=UTF-8');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($login) || empty($password)) {
        $error = 'Заполните оба поля.';
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=u82318;charset=utf8', 'u82318', '5918027', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['login'] = $login;
                header('Location: index.php');
                exit();
            } else {
                $error = 'Неверный логин или пароль.';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <style>
        body { font-family: Arial; background: #f0f2f5; padding: 30px; }
        .container { max-width: 400px; margin: auto; background: white; padding: 30px; border-radius: 20px; }
        input { width: 100%; padding: 8px; margin-bottom: 10px; }
        button { background: #c2819b; color: white; padding: 10px; width: 100%; border: none; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h1>Вход</h1>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <label>Логин:</label>
        <input type="text" name="login" required>
        <label>Пароль:</label>
        <input type="password" name="password" required>
        <button type="submit">Войти</button>
    </form>
    <p><a href="index.php">← На главную</a></p>
</div>
</body>
</html>
