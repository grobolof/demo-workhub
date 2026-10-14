<?php

declare(strict_types=1);

namespace App\Http;

use Bitrix\Main\Loader;
use OpenApi\Generator;
use Throwable;

final class Front
{
    public static function quiet(): void
    {
        $flags = [
            'NO_KEEP_STATISTIC' => true,
            'NO_AGENT_STATISTIC' => true,
            'NOT_CHECK_PERMISSIONS' => true,
            'BX_NO_ACCELERATOR_RESET' => true,
            'PUBLIC_AJAX_MODE' => true,
            'DisableEventsCheck' => true,
        ];
        foreach ($flags as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    public static function dispatch(string $controller, string $action): never
    {
        self::prepare();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        if (!Loader::includeModule('iblock')) {
            (new JsonResponse())->fail('Модуль инфоблоков недоступен');
        }

        (new Kernel())->run($controller, $action);
    }

    public static function document(): never
    {
        self::prepare();
        try {
            $openapi = (new Generator())->generate([dirname(__DIR__) . '/OpenApi']);
        } catch (Throwable $exception) {
            (new JsonResponse())->fail($exception->getMessage());
        }
        if ($openapi === null) {
            (new JsonResponse())->fail('Не удалось собрать OpenAPI');
        }

        echo $openapi->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private static function prepare(): void
    {
        global $APPLICATION;
        if (is_object($APPLICATION) && method_exists($APPLICATION, 'RestartBuffer')) {
            $APPLICATION->RestartBuffer();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Bitrix-Csrf-Token');
    }
}
