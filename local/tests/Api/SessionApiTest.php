<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class SessionApiTest extends ApiTestCase
{
    public function testGuestSession(): void
    {
        $body = $this->assertSession($this->client()->get('/api/session/'));
        $this->assertSame([], $body['favouriteIds']);
        $this->assertSame([], $body['responseIds']);
        $this->assertSame([], $body['resumeFavouriteIds']);
    }

    public function testRegisterValidationAndDuplicate(): void
    {
        $client = $this->client();
        $this->assertError($client->postJson('/api/register/', [
            'email' => 'not-an-email',
            'password' => 'short',
            'passwordConfirm' => 'short',
            'role' => 'seeker',
        ]), 400);

        $this->assertError($client->postJson('/api/register/', [
            'email' => 'phpunit-mismatch-' . bin2hex(random_bytes(3)) . '@example.com',
            'password' => 'Testpass1',
            'passwordConfirm' => 'Testpass2',
            'role' => 'seeker',
            'firstName' => 'Иван',
            'lastName' => 'Тестов',
        ]), 400);

        $account = $this->register($client, 'seeker');
        $again = $this->client();
        $this->assertError($again->postJson('/api/register/', [
            'email' => $account['email'],
            'password' => 'Testpass1',
            'passwordConfirm' => 'Testpass1',
            'role' => 'seeker',
            'firstName' => 'Иван',
            'lastName' => 'Тестов',
        ]), 409);
    }

    public function testRegisterEmployerDuplicateInn(): void
    {
        $first = $this->client();
        $account = $this->register($first, 'employer');
        $second = $this->client();

        $this->assertError($second->postJson('/api/register/', [
            'email' => 'phpunit-inn-' . bin2hex(random_bytes(3)) . '@example.com',
            'password' => 'Testpass1',
            'passwordConfirm' => 'Testpass1',
            'role' => 'employer',
            'firstName' => 'Иван',
            'lastName' => 'Тестов',
            'companyName' => 'Другая компания',
            'inn' => $account['inn'],
        ]), 409);
    }

    public function testLoginValidationWrongPasswordAndRole(): void
    {
        $seeker = $this->client();
        $account = $this->register($seeker, 'seeker');
        $this->assertSame(200, $seeker->postJson('/api/logout/', [])['status']);

        $guest = $this->client();
        $this->assertError($guest->postJson('/api/login/', [
            'email' => '',
            'password' => '',
            'role' => 'seeker',
        ]), 400);
        $this->assertError($guest->postJson('/api/login/', [
            'email' => $account['email'],
            'password' => 'Wrongpass1',
            'role' => 'seeker',
        ]), 401);
        $this->assertError($guest->postJson('/api/login/', [
            'email' => $account['email'],
            'password' => $account['password'],
            'role' => 'employer',
        ]), 403);
        $guest->get('/api/session/');

        $logged = $this->assertSession($guest->postJson('/api/login/', [
            'email' => $account['email'],
            'password' => $account['password'],
            'role' => 'seeker',
        ]), 'seeker');
        $this->assertSame($account['email'], $logged['user']['email']);
    }

    public function testLogoutAndProfileNames(): void
    {
        $client = $this->client();
        $this->register($client, 'seeker');
        $updated = $this->assertSession($client->postJson('/api/profile/', [
            'lastName' => 'Сидоров',
            'firstName' => 'Пётр',
            'middleName' => 'Ильич',
        ]), 'seeker');
        $this->assertSame('Сидоров', $updated['user']['lastName']);
        $this->assertSame('Пётр', $updated['user']['firstName']);
        $this->assertSame('Ильич', $updated['user']['middleName']);

        $this->assertError($client->postJson('/api/profile/', [
            'lastName' => '',
            'firstName' => 'Пётр',
        ]), 400);

        $loggedOut = $this->assertSession($client->postJson('/api/logout/', []));
        $this->assertNull($loggedOut['user']);
        $this->assertError($client->postJson('/api/profile/', [
            'lastName' => 'Сидоров',
            'firstName' => 'Пётр',
        ]), 401);
    }

    public function testCsrfAndMethod(): void
    {
        $client = $this->client();
        $this->assertError($client->postJson('/api/logout/', [], false), 403);
        $this->assertError($client->get('/api/login/'), 405);
    }

    public function testEmployerProfileCompanyAndLogo(): void
    {
        $client = $this->client();
        $account = $this->register($client, 'employer');

        $updated = $this->assertSession($client->postJson('/api/profile/', [
            'scope' => 'company',
            'name' => 'Обновлённая компания',
            'inn' => $account['inn'],
            'description' => 'Описание тестовой компании',
        ]), 'employer');
        $this->assertSame('Обновлённая компания', $updated['user']['company']['name']);
        $this->assertSame($account['inn'], $updated['user']['company']['inn']);
        $this->assertSame('Описание тестовой компании', $updated['user']['company']['description']);

        $this->assertError($client->postJson('/api/profile/', [
            'scope' => 'company',
            'name' => 'Обновлённая компания',
            'inn' => $account['inn'],
            'description' => '',
        ]), 400);

        $withLogo = $this->assertSession($client->postForm('/api/profile/', [], [
            'logo' => $this->png(),
        ]), 'employer');
        $this->assertIsString($withLogo['user']['company']['logo']);
        $this->assertNotSame('', $withLogo['user']['company']['logo']);

        $withoutLogo = $this->assertSession($client->postForm('/api/profile/', [
            'remove' => '1',
            'target' => 'logo',
        ]), 'employer');
        $this->assertNull($withoutLogo['user']['company']['logo']);

        $this->assertError($client->postForm('/api/profile/', [], [
            'avatar' => $this->png(),
        ]), 403);
    }

    public function testSeekerAvatar(): void
    {
        $client = $this->client();
        $this->register($client, 'seeker');

        $withAvatar = $this->assertSession($client->postForm('/api/profile/', [], [
            'avatar' => $this->png(),
        ]), 'seeker');
        $this->assertIsString($withAvatar['user']['avatar']);
        $this->assertNotSame('', $withAvatar['user']['avatar']);

        $withoutAvatar = $this->assertSession($client->postForm('/api/profile/', [
            'remove' => '1',
        ]), 'seeker');
        $this->assertNull($withoutAvatar['user']['avatar']);

        $this->assertError($client->postForm('/api/profile/', []), 400);
    }
}
