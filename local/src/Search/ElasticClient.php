<?php

declare(strict_types=1);

namespace App\Search;

use RuntimeException;

class ElasticClient
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    public static function fromEnv(): self
    {
        $url = getenv('ELASTICSEARCH_URL');
        if (!is_string($url) || trim($url) === '') {
            $url = 'http://elasticsearch:9200';
        }

        return new self(rtrim($url, '/'));
    }

    public function exists(string $path): bool
    {
        return $this->request('HEAD', $path, null, [404])['status'] === 200;
    }

    /** @param array<int, int> $okStatuses */
    public function request(string $method, string $path, ?array $body = null, array $okStatuses = []): array
    {
        $payload = null;
        $contentType = 'application/json';
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return $this->send($method, $path, $payload, $contentType, $okStatuses);
    }

    /** @param array<int, int> $okStatuses */
    public function raw(string $method, string $path, string $payload, string $contentType, array $okStatuses = []): array
    {
        return $this->send($method, $path, $payload, $contentType, $okStatuses);
    }

    /** @param array<int, int> $okStatuses */
    private function send(string $method, string $path, ?string $payload, string $contentType, array $okStatuses): array
    {
        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->once($method, $path, $payload, $contentType, $okStatuses);
            } catch (RuntimeException $exception) {
                $last = $exception;
                if (!str_contains($exception->getMessage(), 'недоступен') || $attempt === 3) {
                    throw $exception;
                }
                usleep(400000);
            }
        }

        throw $last ?? new RuntimeException('Elasticsearch недоступен');
    }

    /** @param array<int, int> $okStatuses */
    private function once(string $method, string $path, ?string $payload, string $contentType, array $okStatuses): array
    {
        if (function_exists('curl_init')) {
            return $this->viaCurl($method, $path, $payload, $contentType, $okStatuses);
        }

        return $this->viaStream($method, $path, $payload, $contentType, $okStatuses);
    }

    /** @param array<int, int> $okStatuses */
    private function viaCurl(string $method, string $path, ?string $payload, string $contentType, array $okStatuses): array
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Elasticsearch недоступен');
        }

        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_NOBODY => $method === 'HEAD',
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($handle);
        $error = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($raw === false || $error !== 0 || $status === 0) {
            throw new RuntimeException('Elasticsearch недоступен');
        }

        return $this->decode($status, (string)$raw, $okStatuses);
    }

    /** @param array<int, int> $okStatuses */
    private function viaStream(string $method, string $path, ?string $payload, string $contentType, array $okStatuses): array
    {
        $headers = "Accept: application/json\r\n";
        if ($payload !== null) {
            $headers .= 'Content-Type: ' . $contentType . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $payload ?? '',
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($this->baseUrl . $path, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match) === 1) {
                $status = (int)$match[1];
            }
        }
        if ($raw === false || $status === 0) {
            throw new RuntimeException('Elasticsearch недоступен');
        }

        return $this->decode($status, $raw, $okStatuses);
    }

    /** @param array<int, int> $okStatuses */
    private function decode(int $status, string $raw, array $okStatuses): array
    {
        $body = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }

        if ($status >= 400 && !in_array($status, $okStatuses, true)) {
            $reason = $body['error']['reason'] ?? $raw;
            if (!is_string($reason) || $reason === '') {
                $reason = 'HTTP ' . $status;
            }
            throw new RuntimeException('Ошибка Elasticsearch: ' . $reason);
        }

        return ['status' => $status, 'body' => $body];
    }
}
