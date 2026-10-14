<?php

declare(strict_types=1);

use App\Demo\Restorer;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

global $APPLICATION, $USER;

if (!Restorer::allowed()) {
    $APPLICATION->AuthForm('Недостаточно прав');
}

$APPLICATION->SetTitle('Восстановить демо');
set_time_limit(0);

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && (string)($_POST['restore'] ?? '') === 'Y') {
    try {
        $message = (new Restorer())->run();
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($message !== null) {
    CAdminMessage::ShowNote($message);
}
if ($error !== null) {
    CAdminMessage::ShowMessage($error);
}
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>" onsubmit="return confirm('Удалить все данные, кроме пользователей admin и support, и загрузить демо заново?');">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="restore" value="Y">
    <p>Команда удаляет компании, вакансии, резюме, баннеры, заявки, города, избранное и отклики, а также всех пользователей кроме admin и support. Затем заново создаёт демо-данные.</p>
    <p>Работодатели и соискатели входят с паролем <b><?= htmlspecialcharsbx(Restorer::PASSWORD) ?></b>. Поддержка: логин support, пароль support.</p>
    <input type="submit" class="adm-btn-save" value="Восстановить демо">
</form>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
