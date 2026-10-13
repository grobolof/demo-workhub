<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;
use Bitrix\Main\GroupTable;
use Bitrix\Main\UserTable;
use CUser;

class AuthService
{
    public const SEEKER = 'seeker';
    public const EMPLOYER = 'employer';

    /** @var array<string, int> */
    private array $groups = [];

    public function payload(): array
    {
        $user = $this->current();
        $favouriteIds = [];
        $responseIds = [];
        if ($user !== null && $user['role'] === self::SEEKER) {
            $favouriteIds = (new Link(FavouriteService::BLOCK))->vacancyIds($user['id']);
            $responseIds = (new Link(ResponseService::BLOCK))->vacancyIds($user['id']);
        }

        return [
            'sessid' => bitrix_sessid(),
            'user' => $user,
            'favouriteIds' => $favouriteIds,
            'responseIds' => $responseIds,
        ];
    }

    public function register(string $email, string $password, string $confirm, string $role): array
    {
        $email = $this->email($email);
        $this->assertRole($role);
        $this->assertPassword($password);
        if ($password !== $confirm) {
            throw new ApiException('Пароли не совпадают');
        }
        if ($this->emailTaken($email)) {
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
            'GROUP_ID' => [2, $this->groupId($role)],
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$user->LAST_ERROR));
        }

        global $USER;
        $USER->Authorize((int)$id, true);

        return $this->payload();
    }

    public function login(string $email, string $password, string $role): array
    {
        $email = $this->email($email);
        $this->assertRole($role);
        if ($password === '') {
            throw new ApiException('Введите пароль');
        }

        global $USER;
        $result = $USER->Login($this->loginName($email), $password, 'Y');
        if ($result !== true) {
            throw new ApiException('Неверная почта или пароль', 401);
        }

        $actual = $this->role((int)$USER->GetID());
        if ($actual !== $role) {
            $USER->Logout();
            if ($actual === null) {
                throw new ApiException('У этого аккаунта нет роли соискателя или работодателя', 403);
            }
            $name = $actual === self::SEEKER ? 'соискатель' : 'работодатель';
            throw new ApiException('Этот аккаунт зарегистрирован как ' . $name, 403);
        }

        return $this->payload();
    }

    public function logout(): array
    {
        global $USER;
        $USER->Logout();

        return $this->payload();
    }

    /** @return array{id: int, email: string, role: string}|null */
    public function current(): ?array
    {
        global $USER;
        if (!$USER instanceof CUser || !$USER->IsAuthorized()) {
            return null;
        }

        $id = (int)$USER->GetID();
        $role = $this->role($id);
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
    public function requireSeeker(string $message): array
    {
        global $USER;
        if (!$USER instanceof CUser || !$USER->IsAuthorized()) {
            throw new ApiException('Войдите как соискатель', 401);
        }

        $user = $this->current();
        if ($user === null || $user['role'] !== self::SEEKER) {
            throw new ApiException($message, 403);
        }

        return $user;
    }

    private function email(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (!check_email($email, true)) {
            throw new ApiException('Укажите корректную почту');
        }

        return $email;
    }

    private function assertRole(string $role): void
    {
        if (!in_array($role, [self::SEEKER, self::EMPLOYER], true)) {
            throw new ApiException('Выберите роль: соискатель или работодатель');
        }
    }

    private function assertPassword(string $password): void
    {
        if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $password)) {
            throw new ApiException('Пароль должен состоять из латинских букв и цифр и быть длиной от 6 до 20 символов');
        }
    }

    private function loginName(string $email): string
    {
        $row = UserTable::getRow([
            'filter' => [
                [
                    'LOGIC' => 'OR',
                    ['=EMAIL' => $email],
                    ['=LOGIN' => $email],
                ],
            ],
            'select' => ['LOGIN'],
        ]);

        return $row ? (string)$row['LOGIN'] : $email;
    }

    private function emailTaken(string $email): bool
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

    private function role(int $userId): ?string
    {
        $groups = array_map('intval', CUser::GetUserGroup($userId));
        $seeker = in_array($this->groupId(self::SEEKER), $groups, true);
        $employer = in_array($this->groupId(self::EMPLOYER), $groups, true);
        if ($seeker && !$employer) {
            return self::SEEKER;
        }
        if ($employer && !$seeker) {
            return self::EMPLOYER;
        }

        return null;
    }

    private function groupId(string $code): int
    {
        if (!isset($this->groups[$code])) {
            $row = GroupTable::getRow([
                'filter' => ['=STRING_ID' => $code],
                'select' => ['ID'],
            ]);
            if (!$row) {
                throw new ApiException('Группы пользователей ещё не созданы. Выполните миграции.', 500);
            }
            $this->groups[$code] = (int)$row['ID'];
        }

        return $this->groups[$code];
    }
}
