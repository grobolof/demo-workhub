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
ini_set('max_execution_time', '0');

$wantsStream = (string)($_SERVER['HTTP_X_WORKHUB_RESTORE'] ?? '') === 'stream';
$isRestore = $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['restore'] ?? '') === 'Y';

if ($wantsStream && $isRestore) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/x-ndjson; charset=UTF-8');
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    ini_set('zlib.output_compression', '0');
    ini_set('output_buffering', '0');
    ini_set('implicit_flush', '1');
    set_time_limit(0);
    ini_set('max_execution_time', '0');

    $emit = static function (array $payload): void {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        flush();
    };

    if (!check_bitrix_sessid()) {
        $emit(['type' => 'error', 'message' => 'Сессия истекла. Обновите страницу и повторите команду.']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        exit;
    }

    try {
        $detail = (new Restorer())->run(static function (int $remaining) use ($emit): void {
            $emit(['type' => 'progress', 'remaining' => $remaining]);
        });
        $emit([
            'type' => 'done',
            'message' => 'Команда успешно завершена.',
            'detail' => $detail,
        ]);
    } catch (Throwable $exception) {
        $emit(['type' => 'error', 'message' => $exception->getMessage()]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    exit;
}

$message = null;
$error = null;
if ($isRestore && check_bitrix_sessid()) {
    set_time_limit(0);
    ini_set('max_execution_time', '0');
    try {
        $message = 'Команда успешно завершена. ' . (new Restorer())->run();
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
<form id="workhub-restore-form" method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="restore" value="Y">
    <p>Команда удаляет компании, вакансии, резюме, баннеры, заявки, города, избранное и отклики, а также всех пользователей кроме admin и support. Затем заново создаёт демо-данные.</p>
    <p>Работодатели и соискатели входят с паролем <b><?= htmlspecialcharsbx(Restorer::PASSWORD) ?></b>. Поддержка: логин support, пароль support.</p>
    <div id="workhub-restore-result"></div>
    <p id="workhub-restore-status" hidden aria-live="polite"></p>
    <input type="submit" class="adm-btn-save" value="Восстановить демо">
</form>
<script>
(function () {
    var form = document.getElementById('workhub-restore-form');
    if (!form) {
        return;
    }

    var button = form.querySelector('input.adm-btn-save');
    var status = document.getElementById('workhub-restore-status');
    var result = document.getElementById('workhub-restore-result');
    var running = false;
    var left = null;
    var started = 0;
    var timer = null;

    function formatLeft(total) {
        var value = total < 0 ? 0 : Math.floor(total);
        var minutes = Math.floor(value / 60);
        var seconds = value % 60;
        if (minutes > 0) {
            return minutes + ' мин ' + seconds + ' с';
        }
        return seconds + ' с';
    }

    function renderStatus() {
        if (!status || status.dataset.done === 'Y') {
            return;
        }
        var elapsed = Math.floor((Date.now() - started) / 1000);
        if (left === null) {
            status.textContent = 'Осталось … (прошло ' + elapsed + ' с)';
            return;
        }
        var text = 'Осталось ' + formatLeft(left);
        if (left === 0) {
            text += ' (прошло ' + elapsed + ' с)';
        }
        status.textContent = text;
    }

    function note(text, ok) {
        if (status) {
            status.dataset.done = 'Y';
            status.hidden = true;
        }
        if (!result) {
            return;
        }
        result.textContent = '';
        var wrap = document.createElement('div');
        wrap.className = 'adm-info-message-wrap ' + (ok ? 'adm-info-message-green' : 'adm-info-message-red');
        var box = document.createElement('div');
        box.className = 'adm-info-message';
        var title = document.createElement('div');
        title.className = 'adm-info-message-title';
        title.textContent = text;
        box.appendChild(title);
        var icon = document.createElement('div');
        icon.className = 'adm-info-message-icon';
        box.appendChild(icon);
        wrap.appendChild(box);
        result.appendChild(wrap);
    }

    function showSuccess(message, detail) {
        note(message || 'Команда успешно завершена.', true);
        if (detail && result) {
            var text = document.createElement('div');
            text.textContent = detail;
            var box = result.querySelector('.adm-info-message');
            if (box) {
                box.insertBefore(text, box.querySelector('.adm-info-message-icon'));
            }
        }
    }

    function showError(message) {
        note(message || 'Не удалось выполнить команду', false);
    }

    function handleLine(line) {
        var event;
        var trimmed = (line || '').trim();
        if (!trimmed) {
            return;
        }
        try {
            event = JSON.parse(trimmed);
        } catch (error) {
            return;
        }
        if (event.type === 'progress' && typeof event.remaining === 'number' && isFinite(event.remaining)) {
            left = Math.max(0, Math.ceil(event.remaining));
            renderStatus();
        }
        if (event.type === 'done') {
            showSuccess(event.message, event.detail);
        }
        if (event.type === 'error') {
            showError(event.message);
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (running) {
            return;
        }
        running = true;
        if (button) {
            button.disabled = true;
        }
        if (!window.confirm('Удалить все данные, кроме пользователей admin и support, и загрузить демо заново?')) {
            running = false;
            if (button) {
                button.disabled = false;
            }
            return;
        }

        if (result) {
            result.textContent = '';
        }
        left = null;
        started = Date.now();
        if (status) {
            status.hidden = false;
            status.dataset.done = '';
        }
        renderStatus();
        if (timer) {
            clearInterval(timer);
        }
        timer = setInterval(function () {
            if (!status || status.dataset.done === 'Y') {
                return;
            }
            if (left !== null && left > 0) {
                left -= 1;
            }
            renderStatus();
        }, 1000);

        var finished = false;
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'X-Workhub-Restore': 'stream',
                'Accept': 'application/x-ndjson'
            }
        }).then(function (response) {
            if (!response.ok || !response.body) {
                throw new Error('Запрос завершился с ошибкой ' + response.status);
            }
            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            var pump = function () {
                return reader.read().then(function (chunk) {
                    if (chunk.done) {
                        handleLine(buffer);
                        return;
                    }
                    buffer += decoder.decode(chunk.value, { stream: true });
                    var lines = buffer.split('\n');
                    buffer = lines.pop();
                    lines.forEach(handleLine);
                    return pump();
                });
            };
            return pump();
        }).then(function () {
            if (status && status.dataset.done !== 'Y') {
                showError('Команда прервалась до завершения');
            }
            finished = true;
        }).catch(function (error) {
            if (!finished && (!status || status.dataset.done !== 'Y')) {
                showError(error && error.message ? error.message : 'Не удалось выполнить команду');
            }
        }).then(function () {
            running = false;
            if (button) {
                button.disabled = false;
            }
            if (timer) {
                clearInterval(timer);
            }
        });
    });
})();
</script>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
