<?php

declare(strict_types=1);

set_time_limit(10);
ini_set('default_socket_timeout', '5');

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'u82295';
const DB_USER = 'u82295';
const DB_PASSWORD = '7819341';

const DEFAULT_ADMIN_LOGIN = 'admin';
const DEFAULT_ADMIN_PASSWORD = '123';

$availableLanguages = [
    'Pascal',
    'C',
    'C++',
    'JavaScript',
    'PHP',
    'Python',
    'Java',
    'Haskell',
    'Clojure',
    'Prolog',
    'Scala',
    'Go',
];

$genderOptions = [
    'male' => 'Мужской',
    'female' => 'Женский',
];

$emptyValues = [
    'id' => 0,
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'birth_date' => '',
    'gender' => '',
    'languages' => [],
    'biography' => '',
    'contract_accepted' => false,
];

function getPdo(): PDO
{
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    ensureAdminSchema($pdo);

    return $pdo;
}

function ensureAdminSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $statement = $pdo->prepare('SELECT COUNT(*) FROM admin_accounts WHERE login = :login');
    $statement->execute([':login' => DEFAULT_ADMIN_LOGIN]);

    if ((int) $statement->fetchColumn() === 0) {
        $insert = $pdo->prepare(
            'INSERT INTO admin_accounts (login, password_hash) VALUES (:login, :password_hash)'
        );
        $insert->execute([
            ':login' => DEFAULT_ADMIN_LOGIN,
            ':password_hash' => password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
        ]);
    }

    $checked = true;
}

function requireAdmin(PDO $pdo): void
{
    $login = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $password = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

    $statement = $pdo->prepare(
        'SELECT password_hash
         FROM admin_accounts
         WHERE login = :login'
    );
    $statement->execute([':login' => $login]);
    $hash = $statement->fetchColumn();

    if ($login === '' || $password === '' || $hash === false || !password_verify($password, (string) $hash)) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Task 6 admin"');
        echo '<h1>401 Требуется авторизация</h1>';
        exit();
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function normalizeFormValues(array $input): array
{
    return [
        'id' => (int) ($input['id'] ?? 0),
        'full_name' => trim((string) ($input['full_name'] ?? '')),
        'phone' => trim((string) ($input['phone'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'birth_date' => trim((string) ($input['birth_date'] ?? '')),
        'gender' => trim((string) ($input['gender'] ?? '')),
        'languages' => array_values(array_unique(array_map('strval', $input['languages'] ?? []))),
        'biography' => trim((string) ($input['biography'] ?? '')),
        'contract_accepted' => isset($input['contract_accepted']) && (string) $input['contract_accepted'] === '1',
    ];
}

function validateFormValues(array $values, array $availableLanguages, array $genderOptions): array
{
    $errors = [];

    if ($values['id'] <= 0) {
        $errors['id'] = 'Не выбрана анкета для редактирования.';
    }

    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Укажите ФИО.';
    } elseif (stringLength($values['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[\p{L}\s-]+$/u', $values['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефис.';
    }

    if ($values['phone'] === '') {
        $errors['phone'] = 'Укажите телефон.';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $values['phone'])) {
        $errors['phone'] = 'Телефон должен содержать только цифры, пробелы, скобки, дефис и плюс в начале.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Укажите e-mail.';
    } elseif (!preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $values['email'])) {
        $errors['email'] = 'Введите корректный e-mail.';
    } elseif (stringLength($values['email']) > 255) {
        $errors['email'] = 'E-mail не должен превышать 255 символов.';
    }

    if ($values['birth_date'] === '') {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } else {
        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $values['birth_date']);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $isValidDate = $birthDate instanceof DateTimeImmutable
            && $birthDate->format('Y-m-d') === $values['birth_date']
            && ($dateErrors === false || ((int) $dateErrors['warning_count'] === 0 && (int) $dateErrors['error_count'] === 0));

        if (!$isValidDate) {
            $errors['birth_date'] = 'Введите корректную дату рождения.';
        } elseif ($birthDate > new DateTimeImmutable('today')) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
        }
    }

    if (!array_key_exists($values['gender'], $genderOptions)) {
        $errors['gender'] = 'Выберите допустимый пол.';
    }

    if ($values['languages'] === []) {
        $errors['languages'] = 'Выберите хотя бы один любимый язык программирования.';
    } else {
        foreach ($values['languages'] as $language) {
            if (!in_array($language, $availableLanguages, true)) {
                $errors['languages'] = 'Можно выбирать только языки из предложенного списка.';
                break;
            }
        }
    }

    if ($values['biography'] === '') {
        $errors['biography'] = 'Напишите биографию.';
    } elseif (stringLength($values['biography']) > 2000) {
        $errors['biography'] = 'Биография не должна превышать 2000 символов.';
    }

    if (!$values['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    return $errors;
}

function syncSubmissionLanguages(PDO $pdo, int $submissionId, array $languages): void
{
    $delete = $pdo->prepare('DELETE FROM submission_languages WHERE submission_id = :submission_id');
    $delete->execute([':submission_id' => $submissionId]);

    $languageSelect = $pdo->prepare('SELECT id FROM programming_languages WHERE name = :name');
    $insert = $pdo->prepare(
        'INSERT INTO submission_languages (submission_id, language_id) VALUES (:submission_id, :language_id)'
    );

    foreach ($languages as $language) {
        $languageSelect->execute([':name' => $language]);
        $languageId = $languageSelect->fetchColumn();

        if ($languageId === false) {
            throw new RuntimeException('Не найден язык программирования: ' . $language);
        }

        $insert->execute([
            ':submission_id' => $submissionId,
            ':language_id' => (int) $languageId,
        ]);
    }
}

function loadSubmission(PDO $pdo, int $id, array $emptyValues): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, full_name, phone, email, birth_date, gender, biography, contract_accepted
         FROM submissions
         WHERE id = :id'
    );
    $statement->execute([':id' => $id]);
    $submission = $statement->fetch();

    if ($submission === false) {
        return null;
    }

    $languages = $pdo->prepare(
        'SELECT pl.name
         FROM submission_languages sl
         INNER JOIN programming_languages pl ON pl.id = sl.language_id
         WHERE sl.submission_id = :submission_id
         ORDER BY pl.name'
    );
    $languages->execute([':submission_id' => $id]);

    return array_merge($emptyValues, [
        'id' => (int) $submission['id'],
        'full_name' => (string) $submission['full_name'],
        'phone' => (string) $submission['phone'],
        'email' => (string) $submission['email'],
        'birth_date' => (string) $submission['birth_date'],
        'gender' => (string) $submission['gender'],
        'languages' => array_map('strval', $languages->fetchAll(PDO::FETCH_COLUMN)),
        'biography' => (string) $submission['biography'],
        'contract_accepted' => (bool) $submission['contract_accepted'],
    ]);
}

function loadSubmissions(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT s.id,
                s.full_name,
                s.phone,
                s.email,
                s.birth_date,
                s.gender,
                s.biography,
                s.contract_accepted,
                s.created_at,
                sa.login,
                GROUP_CONCAT(pl.name ORDER BY pl.name SEPARATOR \', \') AS languages
         FROM submissions s
         LEFT JOIN submission_accounts sa ON sa.submission_id = s.id
         LEFT JOIN submission_languages sl ON sl.submission_id = s.id
         LEFT JOIN programming_languages pl ON pl.id = sl.language_id
         GROUP BY s.id, s.full_name, s.phone, s.email, s.birth_date, s.gender,
                  s.biography, s.contract_accepted, s.created_at, sa.login
         ORDER BY s.id DESC'
    );

    return $statement->fetchAll();
}

function loadLanguageStats(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT pl.name, COUNT(sl.submission_id) AS users_count
         FROM programming_languages pl
         LEFT JOIN submission_languages sl ON sl.language_id = pl.id
         GROUP BY pl.id, pl.name
         ORDER BY pl.name'
    );

    return $statement->fetchAll();
}

function updateSubmission(PDO $pdo, array $values): void
{
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'UPDATE submissions
             SET full_name = :full_name,
                 phone = :phone,
                 email = :email,
                 birth_date = :birth_date,
                 gender = :gender,
                 biography = :biography,
                 contract_accepted = :contract_accepted
             WHERE id = :id'
        );
        $statement->execute([
            ':id' => $values['id'],
            ':full_name' => $values['full_name'],
            ':phone' => $values['phone'],
            ':email' => $values['email'],
            ':birth_date' => $values['birth_date'],
            ':gender' => $values['gender'],
            ':biography' => $values['biography'],
            ':contract_accepted' => $values['contract_accepted'] ? 1 : 0,
        ]);

        syncSubmissionLanguages($pdo, (int) $values['id'], $values['languages']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function deleteSubmission(PDO $pdo, int $id): void
{
    $statement = $pdo->prepare('DELETE FROM submissions WHERE id = :id');
    $statement->execute([':id' => $id]);
}

$message = '';
$error = '';
$editValues = $emptyValues;
$editErrors = [];

try {
    $pdo = getPdo();
    requireAdmin($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Не выбрана анкета для удаления.');
            }

            deleteSubmission($pdo, $id);
            $message = 'Анкета удалена.';
        } elseif ($action === 'update') {
            $editValues = normalizeFormValues($_POST);
            $editErrors = validateFormValues($editValues, $availableLanguages, $genderOptions);

            if ($editErrors === []) {
                updateSubmission($pdo, $editValues);
                $message = 'Анкета обновлена.';
                $editValues = $emptyValues;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
        $editId = (int) $_GET['edit'];
        $loaded = $editId > 0 ? loadSubmission($pdo, $editId, $emptyValues) : null;

        if ($loaded === null) {
            $error = 'Анкета для редактирования не найдена.';
        } else {
            $editValues = $loaded;
        }
    }

    $submissions = loadSubmissions($pdo);
    $stats = loadLanguageStats($pdo);
} catch (Throwable $exception) {
    $submissions = [];
    $stats = [];
    $error = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 6 - Администратор</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-page {
            width: min(1180px, 100%);
        }

        .admin-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .admin-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 18px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
            background: #fff;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        .admin-table th {
            background: #f8fafc;
            font-weight: 700;
        }

        .admin-table tr:last-child td {
            border-bottom: 0;
        }

        .admin-section {
            margin-top: 28px;
        }

        .admin-section h2 {
            margin: 0 0 14px;
            font-size: 24px;
        }

        .admin-section__header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 14px;
        }

        .button--danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 14px 30px rgba(220, 38, 38, 0.22);
        }

        .button--link {
            display: inline-block;
            margin-top: 0;
            text-decoration: none;
        }

        .button--compact {
            margin-top: 0;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 13px;
        }

        .bio-cell {
            max-width: 260px;
            white-space: pre-wrap;
        }

        @media (max-width: 768px) {
            .admin-section__header {
                display: grid;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card admin-page">
        <div class="card__header">
            <p class="eyebrow">Web / Backend</p>
            <div class="hero">
                <div>
                    <h1>Панель администратора</h1>
                    <p class="subtitle">Просмотр, редактирование, удаление анкет и статистика по любимым языкам программирования.</p>
                </div>
                <div class="status-box">
                    <span class="status-box__label">HTTP Auth</span>
                    <strong><?php echo escape((string) ($_SERVER['PHP_AUTH_USER'] ?? '')); ?></strong>
                </div>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert--success"><?php echo escape($message); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <?php if ($editValues['id'] > 0): ?>
            <section class="admin-section">
                <div class="admin-section__header">
                    <h2>Редактирование анкеты #<?php echo (int) $editValues['id']; ?></h2>
                    <a class="button button--secondary button--compact button--link" href="admin.php">Отмена</a>
                </div>

                <?php if ($editErrors !== []): ?>
                    <div class="alert alert--error">
                        <?php foreach ($editErrors as $editError): ?>
                            <div><?php echo escape((string) $editError); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="admin.php" method="post" novalidate>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo (int) $editValues['id']; ?>">

                    <div class="form-grid">
                        <div class="field field--full">
                            <label for="full_name">ФИО</label>
                            <input id="full_name" name="full_name" type="text" maxlength="150" value="<?php echo escape($editValues['full_name']); ?>">
                        </div>

                        <div class="field">
                            <label for="phone">Телефон</label>
                            <input id="phone" name="phone" type="tel" value="<?php echo escape($editValues['phone']); ?>">
                        </div>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input id="email" name="email" type="email" value="<?php echo escape($editValues['email']); ?>">
                        </div>

                        <div class="field">
                            <label for="birth_date">Дата рождения</label>
                            <input id="birth_date" name="birth_date" type="date" value="<?php echo escape($editValues['birth_date']); ?>">
                        </div>

                        <fieldset class="field field--full fieldset">
                            <legend>Пол</legend>
                            <div class="radio-group">
                                <?php foreach ($genderOptions as $genderValue => $genderLabel): ?>
                                    <label class="radio-option">
                                        <input
                                            type="radio"
                                            name="gender"
                                            value="<?php echo escape($genderValue); ?>"
                                            <?php echo $editValues['gender'] === $genderValue ? 'checked' : ''; ?>
                                        >
                                        <span><?php echo escape($genderLabel); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div class="field field--full">
                            <label for="languages">Любимые языки программирования</label>
                            <select id="languages" name="languages[]" multiple size="8">
                                <?php foreach ($availableLanguages as $language): ?>
                                    <option value="<?php echo escape($language); ?>" <?php echo in_array($language, $editValues['languages'], true) ? 'selected' : ''; ?>>
                                        <?php echo escape($language); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field field--full">
                            <label for="biography">Биография</label>
                            <textarea id="biography" name="biography" rows="6"><?php echo escape($editValues['biography']); ?></textarea>
                        </div>

                        <div class="field field--full">
                            <label class="checkbox-option">
                                <input type="checkbox" name="contract_accepted" value="1" <?php echo $editValues['contract_accepted'] ? 'checked' : ''; ?>>
                                <span>С контрактом ознакомлен(а)</span>
                            </label>
                        </div>
                    </div>

                    <button class="button" type="submit">Сохранить изменения</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="admin-section">
            <h2>Статистика по языкам</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Язык</th>
                        <th>Количество пользователей</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats as $row): ?>
                        <tr>
                            <td><?php echo escape((string) $row['name']); ?></td>
                            <td><?php echo (int) $row['users_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-section">
            <h2>Анкеты пользователей</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>E-mail</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Биография</th>
                        <th>Контракт</th>
                        <th>Создано</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($submissions === []): ?>
                        <tr>
                            <td colspan="12">Анкет пока нет.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?php echo (int) $submission['id']; ?></td>
                            <td><?php echo escape((string) ($submission['login'] ?? '')); ?></td>
                            <td><?php echo escape((string) $submission['full_name']); ?></td>
                            <td><?php echo escape((string) $submission['phone']); ?></td>
                            <td><?php echo escape((string) $submission['email']); ?></td>
                            <td><?php echo escape((string) $submission['birth_date']); ?></td>
                            <td><?php echo escape($genderOptions[(string) $submission['gender']] ?? (string) $submission['gender']); ?></td>
                            <td><?php echo escape((string) ($submission['languages'] ?? '')); ?></td>
                            <td class="bio-cell"><?php echo escape((string) $submission['biography']); ?></td>
                            <td><?php echo (int) $submission['contract_accepted'] === 1 ? 'Да' : 'Нет'; ?></td>
                            <td><?php echo escape((string) $submission['created_at']); ?></td>
                            <td>
                                <div class="admin-actions">
                                    <a class="button button--secondary button--compact button--link" href="admin.php?edit=<?php echo (int) $submission['id']; ?>">Редактировать</a>
                                    <form action="admin.php" method="post" onsubmit="return confirm('Удалить анкету #<?php echo (int) $submission['id']; ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $submission['id']; ?>">
                                        <button class="button button--danger button--compact" type="submit">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
</body>
</html>
