<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета (Задание 5)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5e0e8 0%, #d9c8d4 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff5f8;
            padding: 35px 40px;
            border-radius: 28px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0dbe3;
        }
        h1 { text-align: center; color: #a55a7a; margin-bottom: 25px; }
        label { display: block; margin: 18px 0 6px; font-weight: 500; color: #6b4a5a; }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2cdd7;
            border-radius: 18px;
            font-size: 14px;
            background: #ffffff;
        }
        input.error, select.error, textarea.error { border: 2px solid red; background: #ffe6e6; }
        .error-text { color: red; font-size: 12px; margin-top: 5px; }
        input[type="radio"] { width: auto; margin-right: 8px; }
        .radio-group { display: flex; gap: 20px; align-items: center; margin-top: 5px; }
        .radio-group label { margin: 0; font-weight: normal; display: inline-flex; align-items: center; gap: 5px; }
        select[multiple] { padding: 10px; border-radius: 20px; min-height: 120px; }
        button {
            background: #c2819b; color: white; border: none; padding: 12px 20px;
            border-radius: 40px; cursor: pointer; width: 100%; margin-top: 28px;
            font-size: 16px; font-weight: 600;
        }
        button:hover { background: #a8627e; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; margin-top: 20px; font-weight: normal; }
        .checkbox-label input { width: 18px; height: 18px; margin: 0; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .top-links { text-align: right; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="top-links">
        <?php if (isset($_SESSION['user_id'])): ?>
            Вы вошли как <strong><?= htmlspecialchars($_SESSION['login'] ?? '') ?></strong>
            <a href="logout.php">(выйти)</a>
        <?php else: ?>
            <a href="login.php">Войти</a> для изменения ранее сохранённых данных.
        <?php endif; ?>
    </div>

    <h1>Анкета (Задание 5)</h1>
    <p style="text-align:center; color:#6b4a5a;">Проверка корректного заполнения, авторизация, редактирование</p>

    <?php if (!empty($messages)): ?>
        <div class="success"><?= implode('<br>', array_map('htmlspecialchars', $messages)) ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <label>ФИО:</label>
        <input type="text" name="full_name" class="<?= $errors['full_name'] ? 'error' : '' ?>" value="<?= htmlspecialchars($values['full_name']) ?>" placeholder="Иванова Анна Сергеевна">
        <?php if ($errors['full_name']): ?><div class="error-text">Только буквы, пробелы, дефис, 1-150 симв.</div><?php endif; ?>

        <label>Телефон (+7XXXXXXXXXX):</label>
        <input type="text" name="phone" class="<?= $errors['phone'] ? 'error' : '' ?>" value="<?= htmlspecialchars($values['phone']) ?>" placeholder="+71234567890">
        <?php if ($errors['phone']): ?><div class="error-text">Формат: +7XXXXXXXXXX (10 цифр после +7)</div><?php endif; ?>

        <label>Email:</label>
        <input type="text" name="email" class="<?= $errors['email'] ? 'error' : '' ?>" value="<?= htmlspecialchars($values['email']) ?>" placeholder="anna@example.com">
        <?php if ($errors['email']): ?><div class="error-text">Некорректный email</div><?php endif; ?>

        <label>Дата рождения:</label>
        <input type="date" name="birth_date" class="<?= $errors['birth_date'] ? 'error' : '' ?>" value="<?= htmlspecialchars($values['birth_date']) ?>">
        <?php if ($errors['birth_date']): ?><div class="error-text">Выберите дату</div><?php endif; ?>

        <label>Пол:</label>
        <div class="radio-group">
            <label><input type="radio" name="gender" value="male" <?= $values['gender'] == 'male' ? 'checked' : '' ?>> Мужской</label>
            <label><input type="radio" name="gender" value="female" <?= $values['gender'] == 'female' ? 'checked' : '' ?>> Женский</label>
            <label><input type="radio" name="gender" value="other" <?= $values['gender'] == 'other' ? 'checked' : '' ?>> Другой</label>
        </div>
        <?php if ($errors['gender']): ?><div class="error-text">Выберите пол</div><?php endif; ?>

        <label>Любимые языки программирования:</label>
        <select name="languages[]" multiple class="<?= $errors['languages'] ? 'error' : '' ?>">
            <option value="Pascal" <?= in_array('Pascal', $values['languages']) ? 'selected' : '' ?>>Pascal</option>
            <option value="C" <?= in_array('C', $values['languages']) ? 'selected' : '' ?>>C</option>
            <option value="C++" <?= in_array('C++', $values['languages']) ? 'selected' : '' ?>>C++</option>
            <option value="JavaScript" <?= in_array('JavaScript', $values['languages']) ? 'selected' : '' ?>>JavaScript</option>
            <option value="PHP" <?= in_array('PHP', $values['languages']) ? 'selected' : '' ?>>PHP</option>
            <option value="Python" <?= in_array('Python', $values['languages']) ? 'selected' : '' ?>>Python</option>
            <option value="Java" <?= in_array('Java', $values['languages']) ? 'selected' : '' ?>>Java</option>
            <option value="Haskell" <?= in_array('Haskell', $values['languages']) ? 'selected' : '' ?>>Haskell</option>
            <option value="Clojure" <?= in_array('Clojure', $values['languages']) ? 'selected' : '' ?>>Clojure</option>
            <option value="Prolog" <?= in_array('Prolog', $values['languages']) ? 'selected' : '' ?>>Prolog</option>
            <option value="Scala" <?= in_array('Scala', $values['languages']) ? 'selected' : '' ?>>Scala</option>
            <option value="Go" <?= in_array('Go', $values['languages']) ? 'selected' : '' ?>>Go</option>
        </select>
        <?php if ($errors['languages']): ?><div class="error-text">Выберите хотя бы один язык</div><?php endif; ?>

        <label>Биография:</label>
        <textarea name="biography" rows="4"><?= htmlspecialchars($values['biography']) ?></textarea>

        <label class="checkbox-label">
            <input type="checkbox" name="agreed" <?= $values['agreed'] ? 'checked' : '' ?>> С контрактом ознакомлен(а)
        </label>
        <?php if ($errors['agreed']): ?><div class="error-text">Необходимо согласие с контрактом</div><?php endif; ?>

        <button type="submit">💾 Сохранить</button>
    </form>
</div>
</body>
</html>
