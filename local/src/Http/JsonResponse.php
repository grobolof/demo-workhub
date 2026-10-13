<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public function send(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public function fail(string $message, int $status = 500): never
    {
        $this->send(['error' => $message], $status);
    }
}
