<?php

declare(strict_types=1);

namespace App\Tests\Support;

use CURLFile;
use RuntimeException;

final class ApiClient
{
    private string $cookieFile;

    private string $sessid = '';

    public function __construct(private readonly string $baseUrl)
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'workhub');
        if ($cookieFile === false) {
            throw new RuntimeException('Не удалось создать файл cookie');
        }
        $this->cookieFile = $cookieFile;
    }

    public function __destruct()
    {
        if (is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function postJson(string $path, array $payload, bool $csrf = true): array
    {
        return $this->request(
            'POST',
            $path,
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['Content-Type: application/json'],
            $csrf,
        );
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, CURLFile|string> $files
     * @return array{status: int, body: array<string, mixed>}
     */
    public function postForm(string $path, array $fields, array $files = [], bool $csrf = true): array
    {
        return $this->request('POST', $path, $fields + $files, [], $csrf);
    }

    /**
     * @param array<string, string>|string|null $body
     * @param list<string> $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    private function request(string $method, string $path, array|string|null $body = null, array $headers = [], bool $csrf = false): array
    {
        $handle = curl_init(rtrim($this->baseUrl, '/') . $path);
        if ($handle === false) {
            throw new RuntimeException('Не удалось создать запрос');
        }

        $headers[] = 'Accept: application/json';
        if ($csrf) {
            if ($this->sessid === '') {
                throw new RuntimeException('Нет sessid для ' . $path);
            }
            $headers[] = 'X-Bitrix-Csrf-Token: ' . $this->sessid;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        if ($raw === false) {
            throw new RuntimeException(curl_error($handle));
        }

        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        unset($handle);

        $payload = substr($raw, $headerSize);
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Ответ не JSON, статус ' . $status . ': ' . substr($payload, 0, 400));
        }
        if (isset($decoded['sessid']) && is_string($decoded['sessid']) && $decoded['sessid'] !== '') {
            $this->sessid = $decoded['sessid'];
        }

        return ['status' => $status, 'body' => $decoded];
    }
}
