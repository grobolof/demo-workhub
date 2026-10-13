<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApiException;
use JsonException;

final class Request
{
    public function __construct(
        private readonly array $query,
        private readonly string $method,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function query(string $key, string $default = ''): string
    {
        if (!isset($this->query[$key])) {
            return $default;
        }

        return (string)$this->query[$key];
    }

    public function queryInt(string $key, int $default = 0): int
    {
        if (!isset($this->query[$key]) || $this->query[$key] === '') {
            return $default;
        }

        return (int)$this->query[$key];
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function json(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('Некорректный запрос', 400);
        }

        return is_array($data) ? $data : [];
    }

    public function requirePost(): array
    {
        if (!$this->isPost()) {
            throw new ApiException('Метод не поддерживается', 405);
        }
        if (!check_bitrix_sessid()) {
            throw new ApiException('Сессия устарела, обновите страницу', 403);
        }

        return $this->json();
    }

    public function requireForm(): void
    {
        if (!$this->isPost()) {
            throw new ApiException('Метод не поддерживается', 405);
        }
        if (!check_bitrix_sessid()) {
            throw new ApiException('Сессия устарела, обновите страницу', 403);
        }
    }

    public function post(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }

    /** @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}> */
    public function files(string $key): array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return [];
        }

        $bag = $_FILES[$key];
        if (!is_array($bag['name'] ?? null)) {
            if (!isset($bag['tmp_name']) || !is_string($bag['tmp_name']) || $bag['tmp_name'] === '') {
                return [];
            }

            return [[
                'name' => (string)($bag['name'] ?? ''),
                'type' => (string)($bag['type'] ?? ''),
                'tmp_name' => $bag['tmp_name'],
                'error' => (int)($bag['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($bag['size'] ?? 0),
            ]];
        }

        $files = [];
        foreach ($bag['name'] as $index => $name) {
            $files[] = [
                'name' => (string)$name,
                'type' => (string)($bag['type'][$index] ?? ''),
                'tmp_name' => (string)($bag['tmp_name'][$index] ?? ''),
                'error' => (int)($bag['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($bag['size'][$index] ?? 0),
            ];
        }

        return $files;
    }
}
