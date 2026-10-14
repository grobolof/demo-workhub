<?php

declare(strict_types=1);

namespace App\Tests\Support;

use CURLFile;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    private string $png = '';

    protected function client(): ApiClient
    {
        $base = getenv('WORKHUB_BASE_URL');
        if (!is_string($base) || $base === '') {
            $base = 'http://workhub.docker.local';
        }

        $client = new ApiClient($base);
        $session = $client->get('/api/session/');
        $this->assertSession($session);

        return $client;
    }

    protected function png(): CURLFile
    {
        if ($this->png === '') {
            $path = tempnam(sys_get_temp_dir(), 'workhub');
            if ($path === false) {
                $this->fail('Не удалось создать временный файл');
            }
            $png = $path . '.png';
            rename($path, $png);
            file_put_contents($png, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                true,
            ));
            $this->png = $png;
        }

        return new CURLFile($this->png, 'image/png', 'pixel.png');
    }

    protected function tearDown(): void
    {
        if ($this->png !== '' && is_file($this->png)) {
            unlink($this->png);
            $this->png = '';
        }
        parent::tearDown();
    }

    /**
     * @param array{status: int, body: array<string, mixed>} $response
     * @return array<string, mixed>
     */
    protected function assertSession(array $response, ?string $role = null): array
    {
        $this->assertSame(200, $response['status']);
        $body = $response['body'];
        $this->assertIsString($body['sessid'] ?? null);
        $this->assertNotSame('', $body['sessid']);
        $this->assertIsArray($body['favouriteIds'] ?? null);
        $this->assertIsArray($body['responseIds'] ?? null);
        $this->assertIsArray($body['resumeFavouriteIds'] ?? null);
        if ($role === null) {
            $this->assertNull($body['user']);

            return $body;
        }

        $this->assertUser($body['user'] ?? null, $role);

        return $body;
    }

    /** @param mixed $user */
    protected function assertUser(mixed $user, string $role): void
    {
        $this->assertIsArray($user);
        $this->assertIsInt($user['id'] ?? null);
        $this->assertGreaterThan(0, $user['id']);
        $this->assertIsString($user['email'] ?? null);
        $this->assertSame($role, $user['role'] ?? null);
        $this->assertArrayHasKey('firstName', $user);
        $this->assertArrayHasKey('lastName', $user);
        $this->assertArrayHasKey('middleName', $user);
        $this->assertTrue($user['avatar'] === null || is_string($user['avatar']));
        if ($role === 'employer') {
            $this->assertIsArray($user['company'] ?? null);
            $this->assertIsInt($user['company']['id'] ?? null);
            $this->assertIsString($user['company']['name'] ?? null);
            $this->assertIsString($user['company']['inn'] ?? null);
            $this->assertArrayHasKey('description', $user['company']);
            $this->assertTrue($user['company']['logo'] === null || is_string($user['company']['logo']));
        }
    }

    /**
     * @param array{status: int, body: array<string, mixed>} $response
     */
    protected function assertError(array $response, int $status): void
    {
        $this->assertSame($status, $response['status']);
        $this->assertIsString($response['body']['error'] ?? null);
        $this->assertNotSame('', $response['body']['error']);
    }

    /** @param array<string, mixed> $city */
    protected function assertCity(array $city): void
    {
        $this->assertIsInt($city['id'] ?? null);
        $this->assertIsString($city['name'] ?? null);
        $this->assertNotSame('', $city['name']);
        $this->assertIsString($city['code'] ?? null);
        $this->assertNotSame('', $city['code']);
    }

    /** @param array<string, mixed> $card */
    protected function assertVacancyCard(array $card, bool $detail = false): void
    {
        $this->assertIsInt($card['id'] ?? null);
        $this->assertGreaterThan(0, $card['id']);
        $this->assertIsString($card['name'] ?? null);
        $this->assertNotSame('', $card['name']);
        $this->assertTrue($card['salaryFrom'] === null || is_int($card['salaryFrom']));
        $this->assertTrue($card['salaryTo'] === null || is_int($card['salaryTo']));
        $this->assertIsArray($card['cities'] ?? null);
        foreach ($card['cities'] as $city) {
            $this->assertIsArray($city);
            $this->assertCity($city);
        }
        if ($card['company'] !== null) {
            $this->assertIsArray($card['company']);
            $this->assertCompanyCard($card['company']);
        }
        if ($detail) {
            $this->assertIsString($card['detail'] ?? null);
        }
    }

    /**
     * @param array{status: int, body: array<string, mixed>} $response
     * @return array<string, mixed>
     */
    protected function assertVacancyPage(array $response): array
    {
        $this->assertSame(200, $response['status']);
        $body = $response['body'];
        $this->assertIsArray($body['vacancies'] ?? null);
        $this->assertIsInt($body['total'] ?? null);
        $this->assertIsInt($body['page'] ?? null);
        $this->assertIsInt($body['limit'] ?? null);
        $this->assertGreaterThan(0, $body['limit']);
        foreach ($body['vacancies'] as $vacancy) {
            $this->assertIsArray($vacancy);
            $this->assertVacancyCard($vacancy);
        }

        return $body;
    }

    /** @param array<string, mixed> $company */
    protected function assertCompanyCard(array $company): void
    {
        $this->assertIsInt($company['id'] ?? null);
        $this->assertGreaterThan(0, $company['id']);
        $this->assertIsString($company['name'] ?? null);
        $this->assertNotSame('', $company['name']);
        $this->assertTrue(($company['logo'] ?? null) === null || is_string($company['logo']));
    }

    /** @param array<string, mixed> $resume */
    protected function assertResume(array $resume): void
    {
        $this->assertIsInt($resume['id'] ?? null);
        $this->assertGreaterThan(0, $resume['id']);
        $this->assertIsString($resume['title'] ?? null);
        $this->assertIsString($resume['description'] ?? null);
        $this->assertIsInt($resume['salary'] ?? null);
    }

    /** @param array<string, mixed> $vacancy */
    protected function assertManagedVacancy(array $vacancy): void
    {
        $this->assertIsInt($vacancy['id'] ?? null);
        $this->assertGreaterThan(0, $vacancy['id']);
        $this->assertIsString($vacancy['title'] ?? null);
        $this->assertNotSame('', $vacancy['title']);
        $this->assertIsInt($vacancy['salary'] ?? null);
        $this->assertIsString($vacancy['description'] ?? null);
        $this->assertContains($vacancy['workplace'] ?? null, ['office', 'hybrid', 'remote']);
        $this->assertContains($vacancy['schedule'] ?? null, ['5-2', 'floating', 'shift']);
        $this->assertIsArray($vacancy['cities'] ?? null);
        $this->assertIsInt($vacancy['responses'] ?? null);
    }

    /**
     * @return array{email: string, password: string, inn: string}
     */
    protected function register(ApiClient $client, string $role, string $inn = ''): array
    {
        $suffix = bin2hex(random_bytes(4));
        $email = 'phpunit-' . $role . '-' . $suffix . '@example.com';
        $password = 'Testpass1';
        $payload = [
            'email' => $email,
            'password' => $password,
            'passwordConfirm' => $password,
            'role' => $role,
            'firstName' => 'Иван',
            'lastName' => 'Тестов',
            'middleName' => 'Петрович',
        ];
        if ($role === 'employer') {
            $payload['companyName'] = 'Компания ' . $suffix;
            $payload['inn'] = $inn !== '' ? $inn : (string)random_int(1000000000, 9999999999);
        }

        $response = $client->postJson('/api/register/', $payload);
        if ($role === 'employer' && $response['status'] === 409 && $inn === '') {
            return $this->register($client, $role);
        }
        $this->assertSession($response, $role);
        $this->assertSame($email, $response['body']['user']['email']);

        return [
            'email' => $email,
            'password' => $password,
            'inn' => (string)($payload['inn'] ?? ''),
        ];
    }
}
