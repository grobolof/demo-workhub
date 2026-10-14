<?php

declare(strict_types=1);

namespace App\Http;

use Bitrix\Main\Context;
use Throwable;

final class PublicUrl
{
    public static function admin(): string
    {
        $configured = trim((string)(getenv('APP_HOST') ?: ''));
        if (preg_match('#^https?://#i', $configured) === 1) {
            return rtrim($configured, '/') . '/bitrix';
        }

        [$scheme, $current] = self::requestOrigin();
        $name = strtolower((string)preg_replace('/:\d+$/', '', $current));
        $internal = $name === '' || in_array($name, [
            'application',
            'localhost',
            '127.0.0.1',
            'host.docker.internal',
        ], true);
        $host = $internal ? $configured : $current;
        if ($host === '') {
            return '/bitrix';
        }

        return $scheme . '://' . $host . '/bitrix';
    }

    /** @return array{0: string, 1: string} */
    private static function requestOrigin(): array
    {
        $scheme = 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        try {
            if (class_exists(Context::class)) {
                $request = Context::getCurrent()->getRequest();
                $scheme = $request->isHttps() ? 'https' : 'http';
                $httpHost = (string)$request->getHttpHost();
                if ($httpHost !== '') {
                    $host = $httpHost;
                }
            }
        } catch (Throwable) {
        }

        $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($forwarded === 'https' || $forwarded === 'http') {
            $scheme = $forwarded;
        }

        return [$scheme, $host];
    }
}
