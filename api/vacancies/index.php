<?php

declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
define('PUBLIC_AJAX_MODE', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use App\Api\JobBoard;
use Bitrix\Main\Loader;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

try {
    if (!Loader::includeModule('iblock')) {
        throw new RuntimeException('Модуль инфоблоков недоступен');
    }

    global $APPLICATION;
    $APPLICATION->RestartBuffer();

    $payload = JobBoard::search(
        isset($_GET['q']) ? (string)$_GET['q'] : '',
        isset($_GET['city']) ? (string)$_GET['city'] : '',
    );

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

exit;
