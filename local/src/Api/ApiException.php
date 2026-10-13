<?php

declare(strict_types=1);

namespace App\Api;

use RuntimeException;

class ApiException extends RuntimeException
{
    private int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function plain(string $message): string
    {
        $message = trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $message))));
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

        return $message !== '' ? $message : 'Не удалось выполнить запрос';
    }
}
