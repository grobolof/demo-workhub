<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class RequestApiTest extends ApiTestCase
{
    public function testCreateRequest(): void
    {
        $client = $this->client();

        $this->assertError($client->postForm('/api/requests/', [
            'email' => 'not-an-email',
            'subject' => 'Тема',
            'message' => 'Сообщение',
        ]), 400);
        $this->assertError($client->postForm('/api/requests/', [
            'email' => 'phpunit-request@example.com',
            'subject' => '',
            'message' => 'Сообщение',
        ]), 400);
        $this->assertError($client->postForm('/api/requests/', [
            'email' => 'phpunit-request@example.com',
            'subject' => 'Тема',
            'message' => 'Сообщение',
        ], [], false), 403);

        $created = $client->postForm('/api/requests/', [
            'email' => 'phpunit-request-' . bin2hex(random_bytes(3)) . '@example.com',
            'subject' => 'Тема PHPUnit',
            'message' => 'Текст заявки в поддержку',
        ]);
        $this->assertSame(200, $created['status']);
        $this->assertIsInt($created['body']['id'] ?? null);
        $this->assertGreaterThan(0, $created['body']['id']);

        $withFile = $client->postForm('/api/requests/', [
            'email' => 'phpunit-request-' . bin2hex(random_bytes(3)) . '@example.com',
            'subject' => 'Тема с файлом',
            'message' => 'Заявка с вложением',
        ], [
            'files[0]' => $this->png(),
        ]);
        $this->assertSame(200, $withFile['status']);
        $this->assertIsInt($withFile['body']['id'] ?? null);
        $this->assertGreaterThan(0, $withFile['body']['id']);
    }
}
