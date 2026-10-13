<?php

declare(strict_types=1);

namespace App\Api;

use Bitrix\Main\GroupTable;
use Bitrix\Main\UserTable;
use CUser;

class Auth
{
    public const SEEKER = 'seeker';
    public const EMPLOYER = 'employer';

    public static function payload(): array
    {
        $user = self::current();
        $favouriteIds = [];
        $responseIds = [];
        if ($user !== null && $user['role'] === self::SEEKER) {
            $favouriteIds = Favourites::ids($user['id']);
            $responseIds = Responses::ids($user['id']);
        }

        return [
            'sessid' => bitrix_sessid(),
            'user' => $user,
            'favouriteIds' => $favouriteIds,
            'responseIds' => $responseIds,
        ];
    }

    public static function register(string $email, string $password, string $confirm, string $role): array
    {
        $email = self::email($email);
        self::assertRole($role);
        self::assertPassword($password);
        if ($password !== $confirm) {
            throw new ApiException('Пароли не совпадают');
        }
        if (self::emailTaken($email)) {
            throw new ApiException('Пользователь с такой почтой уже зарегистрирован', 409);
        }

        $user = new CUser();
        $id = $user->Add([
            'LOGIN' => $email,
            'EMAIL' => $email,
            'NAME' => $role === self::SEEKER ? 'Соискатель' : 'Работодатель',
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'ACTIVE' => 'Y',
            'LID' => 's1',
            'GROUP_ID' => [2, self::groupId($role)],
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$user->LAST_ERROR));
        }

        global $USER;
        $USER->Authorize((int)$id, true);

        return self::payload();
    }

    public static function login(string $email, string $password, string $role): array
    {
        $email = self::email($email);
        self::assertRole($role);
        if ($password === '') {
            throw new ApiException('Введите пароль');
        }

        global $USER;
        $result = $USER->Login($email, $password, 'Y');
        if ($result !== true) {
            throw new ApiException('Неверная почта или пароль', 401);
        }

        $actual = self::role((int)$USER->GetID());
        if ($actual !== $role) {
            $USER->Logout();
            if ($actual === null) {
                throw new ApiException('У этого аккаунта нет роли соискателя или работодателя', 403);
            }
            $name = $actual === self::SEEKER ? 'соискатель' : 'работодатель';
            throw new ApiException('Этот аккаунт зарегистрирован как ' . $name, 403);
        }

        return self::payload();
    }

    public static function logout(): array
    {
        global $USER;
        $USER->Logout();

        return self::payload();
    }

    /** @return array{id: int, email: string, role: string}|null */
    public static function current(): ?array
    {
        global $USER;
        if (!$USER instanceof CUser || !$USER->IsAuthorized()) {
            return null;
        }

        $id = (int)$USER->GetID();
        $role = self::role($id);
        if ($role === null) {
            return null;
        }

        return [
            'id' => $id,
            'email' => (string)$USER->GetEmail(),
            'role' => $role,
        ];
    }

    /** @return array{id: int, email: string, role: string} */
    public static function requireSeeker(string $message): array
    {
        global $USER;
        if (!$USER instanceof CUser || !$USER->IsAuthorized()) {
            throw new ApiException('Войдите как соискатель', 401);
        }

        $user = self::current();
        if ($user === null || $user['role'] !== self::SEEKER) {
            throw new ApiException($message, 403);
        }

        return $user;
    }

    private static function email(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (!check_email($email, true)) {
            throw new ApiException('Укажите корректную почту');
        }

        return $email;
    }

    private static function assertRole(string $role): void
    {
        if (!in_array($role, [self::SEEKER, self::EMPLOYER], true)) {
            throw new ApiException('Выберите роль: соискатель или работодатель');
        }
    }

    private static function assertPassword(string $password): void
    {
        if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $password)) {
            throw new ApiException('Пароль должен состоять из латинских букв и цифр и быть длиной от 6 до 20 символов');
        }
    }

    private static function emailTaken(string $email): bool
    {
        $row = UserTable::getRow([
            'filter' => [
                [
                    'LOGIC' => 'OR',
                    ['=EMAIL' => $email],
                    ['=LOGIN' => $email],
                ],
            ],
            'select' => ['ID'],
        ]);

        return (bool)$row;
    }

    private static function role(int $userId): ?string
    {
        $groups = array_map('intval', CUser::GetUserGroup($userId));
        $seeker = in_array(self::groupId(self::SEEKER), $groups, true);
        $employer = in_array(self::groupId(self::EMPLOYER), $groups, true);
        if ($seeker && !$employer) {
            return self::SEEKER;
        }
        if ($employer && !$seeker) {
            return self::EMPLOYER;
        }

        return null;
    }

    private static function groupId(string $code): int
    {
        static $cache = [];
        if (!isset($cache[$code])) {
            $row = GroupTable::getRow([
                'filter' => ['=STRING_ID' => $code],
                'select' => ['ID'],
            ]);
            if (!$row) {
                throw new ApiException('Группы пользователей ещё не созданы. Выполните миграции.', 500);
            }
            $cache[$code] = (int)$row['ID'];
        }

        return $cache[$code];
    }
}
