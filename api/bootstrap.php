<?php

declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('PUBLIC_AJAX_MODE', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Bitrix-Csrf-Token');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

function api_send(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function api_fail(string $message, int $status = 500): never
{
    api_send(['error' => $message], $status);
}

if (!Loader::includeModule('iblock')) {
    api_fail('Модуль инфоблоков недоступен');
}

function api_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        api_fail('Некорректный запрос', 400);
    }

    return is_array($data) ? $data : [];
}

function api_require_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_fail('Метод не поддерживается', 405);
    }
    if (!check_bitrix_sessid()) {
        api_fail('Сессия устарела, обновите страницу', 403);
    }

    return api_input();
}

function api_run(callable $action): never
{
    try {
        $action();
    } catch (App\Api\ApiException $exception) {
        api_fail($exception->getMessage(), $exception->status());
    } catch (Throwable $exception) {
        api_fail($exception->getMessage());
    }

    api_fail('Пустой ответ');
}

global $APPLICATION;
$APPLICATION->RestartBuffer();
