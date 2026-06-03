<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=localhost;dbname=u82318;charset=utf8', 'u82318', '5918027', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
    return $pdo;
}

// ---- Режим редактирования (администратор) ----
$edit_mode = false;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_mode = true;
    $edit_id = (int)$_GET['edit_id'];
    $_SESSION['edit_id'] = $edit_id;
}

// -------------------------------------------------------------------
// GET-ЗАПРОС (показать форму)
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = [];

    if (isset($_COOKIE['save'])) {
        $messages[] = '✅ Данные успешно сохранены!';
        if (isset($_COOKIE['login']) && isset($_COOKIE['pass'])) {
            $messages[] = 'Вы можете войти с логином: ' . htmlspecialchars($_COOKIE['login']) . ' и паролем: ' . htmlspecialchars($_COOKIE['pass']) . ' для изменения данных.';
        }
        setcookie('save', '', time() - 3600);
        setcookie('login', '', time() - 3600);
        setcookie('pass', '', time() - 3600);
    }

    $errors = [
        'full_name' => isset($_COOKIE['full_name_error']),
        'phone'     => isset($_COOKIE['phone_error']),
        'email'     => isset($_COOKIE['email_error']),
        'birth_date'=> isset($_COOKIE['birth_date_error']),
        'gender'    => isset($_COOKIE['gender_error']),
        'languages' => isset($_COOKIE['languages_error']),
        'agreed'    => isset($_COOKIE['agreed_error'])
    ];

    $values = [
        'full_name' => $_COOKIE['full_name_value'] ?? '',
        'phone'     => $_COOKIE['phone_value'] ?? '',
        'email'     => $_COOKIE['email_value'] ?? '',
        'birth_date'=> $_COOKIE['birth_date_value'] ?? '',
        'gender'    => $_COOKIE['gender_value'] ?? '',
        'languages' => isset($_COOKIE['languages_value']) ? explode(',', $_COOKIE['languages_value']) : [],
        'biography' => $_COOKIE['biography_value'] ?? '',
        'agreed'    => isset($_COOKIE['agreed_value'])
    ];

    // Если пользователь авторизован – загружаем его данные из БД
    $is_authorized = false;
    if (isset($_SESSION['user_id'])) {
        $is_authorized = true;
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $values['full_name'] = htmlspecialchars($user['full_name']);
            $values['phone']     = htmlspecialchars($user['phone']);
            $values['email']     = htmlspecialchars($user['email']);
            $values['birth_date']= htmlspecialchars($user['birth_date']);
            $values['gender']    = htmlspecialchars($user['gender']);
            $values['biography'] = htmlspecialchars($user['biography']);
            $values['agreed']    = (bool)$user['agreed'];
            // Загружаем языки
            $stmt2 = $pdo->prepare("SELECT l.name FROM user_languages ul JOIN languages l ON ul.language_id = l.id WHERE ul.user_id = ?");
            $stmt2->execute([$_SESSION['user_id']]);
            $langs = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $values['languages'] = $langs;
            // Очищаем куки
            foreach (array_keys($errors) as $field) {
                setcookie($field . '_error', '', time() - 3600);
                setcookie($field . '_value', '', time() - 3600);
            }
            $errors = array_fill_keys(array_keys($errors), false);
        }
    }

    // ---- Режим редактирования (администратор) ----
    if ($edit_mode && isset($_SESSION['edit_id'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['edit_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $stmt2 = $pdo->prepare("SELECT l.name FROM user_languages ul JOIN languages l ON ul.language_id = l.id WHERE ul.user_id = ?");
            $stmt2->execute([$_SESSION['edit_id']]);
            $langs = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $values = [
                'full_name' => htmlspecialchars($user['full_name']),
                'phone'     => htmlspecialchars($user['phone']),
                'email'     => htmlspecialchars($user['email']),
                'birth_date'=> $user['birth_date'],
                'gender'    => $user['gender'],
                'languages' => $langs,
                'biography' => htmlspecialchars($user['biography']),
                'agreed'    => (bool)$user['agreed']
            ];
            $errors = array_fill_keys(array_keys($errors), false);
        }
    }

    // Удаляем одноразовые куки для неавторизованных
    if (!$is_authorized && !$edit_mode) {
        foreach (array_keys($errors) as $field) {
            setcookie($field . '_error', '', time() - 3600);
            setcookie($field . '_value', '', time() - 3600);
        }
        setcookie('languages_value', '', time() - 3600);
        setcookie('biography_value', '', time() - 3600);
        setcookie('agreed_value', '', time() - 3600);
    }

    include 'form.php';
    exit();
}

// -------------------------------------------------------------------
// POST-ЗАПРОС (валидация и сохранение)
// -------------------------------------------------------------------
else {
    $errors_flag = false;

    $full_name = trim($_POST['full_name'] ?? '');
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]{1,150}$/u', $full_name)) {
        setcookie('full_name_error', '1', time() + 86400);
        setcookie('full_name_value', $full_name, time() + 86400);
        $errors_flag = true;
    }

    $phone = trim($_POST['phone'] ?? '');
    if (!preg_match('/^\+7\d{10}$/', $phone)) {
        setcookie('phone_error', '1', time() + 86400);
        setcookie('phone_value', $phone, time() + 86400);
        $errors_flag = true;
    }

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setcookie('email_error', '1', time() + 86400);
        setcookie('email_value', $email, time() + 86400);
        $errors_flag = true;
    }

    $birth_date = $_POST['birth_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        setcookie('birth_date_error', '1', time() + 86400);
        setcookie('birth_date_value', $birth_date, time() + 86400);
        $errors_flag = true;
    }

    $gender = $_POST['gender'] ?? '';
    if (!in_array($gender, ['male', 'female', 'other'])) {
        setcookie('gender_error', '1', time() + 86400);
        setcookie('gender_value', $gender, time() + 86400);
        $errors_flag = true;
    }

    $allowed_languages = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
    $languages = $_POST['languages'] ?? [];
    if (empty($languages)) {
        setcookie('languages_error', '1', time() + 86400);
        setcookie('languages_value', implode(',', $languages), time() + 86400);
        $errors_flag = true;
    } else {
        $valid = true;
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowed_languages)) {
                $valid = false;
                break;
            }
        }
        if (!$valid) {
            setcookie('languages_error', '1', time() + 86400);
            setcookie('languages_value', implode(',', $languages), time() + 86400);
            $errors_flag = true;
        } else {
            setcookie('languages_value', implode(',', $languages), time() + 86400);
        }
    }

    $biography = trim($_POST['biography'] ?? '');
    setcookie('biography_value', $biography, time() + 86400);

    $agreed = isset($_POST['agreed']);
    if (!$agreed) {
        setcookie('agreed_error', '1', time() + 86400);
        setcookie('agreed_value', $agreed ? '1' : '0', time() + 86400);
        $errors_flag = true;
    }

    if ($errors_flag) {
        header('Location: index.php');
        exit();
    }

    $pdo = getDB();
    $is_authorized = isset($_SESSION['user_id']);
    $is_edit_mode = isset($_SESSION['edit_id']);

    try {
        if ($is_edit_mode) {
            // Редактирование администратором
            $user_id = $_SESSION['edit_id'];
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, agreed=? WHERE id=?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, (int)$agreed, $user_id]);

            $stmt = $pdo->prepare("DELETE FROM user_languages WHERE user_id=?");
            $stmt->execute([$user_id]);

            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            $stmtId = $pdo->prepare("SELECT id FROM languages WHERE name = ?");
            foreach ($languages as $lang) {
                $stmtId->execute([$lang]);
                $row = $stmtId->fetch(PDO::FETCH_ASSOC);
                if ($row) $stmtLang->execute([$user_id, $row['id']]);
            }

            unset($_SESSION['edit_id']);
            setcookie('save', '1', time() + 86400);
            header('Location: admin.php');
            exit();
        } elseif ($is_authorized) {
            // Авторизованный пользователь обновляет свои данные
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, agreed=? WHERE id=?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, (int)$agreed, $user_id]);

            $stmt = $pdo->prepare("DELETE FROM user_languages WHERE user_id=?");
            $stmt->execute([$user_id]);

            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            $stmtId = $pdo->prepare("SELECT id FROM languages WHERE name = ?");
            foreach ($languages as $lang) {
                $stmtId->execute([$lang]);
                $row = $stmtId->fetch(PDO::FETCH_ASSOC);
                if ($row) $stmtLang->execute([$user_id, $row['id']]);
            }

            setcookie('save', '1', time() + 86400);
            header('Location: index.php');
            exit();
        } else {
            // Новая запись – генерация логина и пароля
            $login = generateUniqueLogin($pdo);
            $plain_password = generatePassword();
            $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (full_name, phone, email, birth_date, gender, biography, agreed, login, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, (int)$agreed, $login, $password_hash]);
            $user_id = $pdo->lastInsertId();

            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language_id) VALUES (?, ?)");
            $stmtId = $pdo->prepare("SELECT id FROM languages WHERE name = ?");
            foreach ($languages as $lang) {
                $stmtId->execute([$lang]);
                $row = $stmtId->fetch(PDO::FETCH_ASSOC);
                if ($row) $stmtLang->execute([$user_id, $row['id']]);
            }

            setcookie('save', '1', time() + 86400);
            setcookie('login', $login, time() + 86400);
            setcookie('pass', $plain_password, time() + 86400);
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        echo 'Ошибка БД: ' . htmlspecialchars($e->getMessage());
    }
}

function generateUniqueLogin($pdo) {
    $base = 'user_';
    do {
        $login = $base . bin2hex(random_bytes(4));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}
?>
