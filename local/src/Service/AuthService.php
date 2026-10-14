<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;
use App\Http\PublicUrl;
use Bitrix\Main\GroupTable;
use Bitrix\Main\UserTable;
use CFile;
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
        $resumeFavouriteIds = [];
        if ($user !== null && $user['role'] === self::SEEKER) {
            $favouriteIds = (new Link(FavouriteService::BLOCK))->vacancyIds($user['id']);
            $responseIds = (new Link(ResponseService::BLOCK))->vacancyIds($user['id']);
        }
        if ($user !== null && $user['role'] === self::EMPLOYER) {
            $resumeFavouriteIds = (new Link(ResumeFavouriteService::BLOCK, 'UF_RESUME'))->ids($user['id']);
        }

        return [
            'sessid' => bitrix_sessid(),
            'user' => $user,
            'favouriteIds' => $favouriteIds,
            'responseIds' => $responseIds,
            'resumeFavouriteIds' => $resumeFavouriteIds,
            'adminUrl' => PublicUrl::admin(),
        ];
    }

    public function register(
        string $email,
        string $password,
        string $confirm,
        string $role,
        string $firstName,
        string $lastName,
        string $middleName,
        string $companyName,
        string $inn,
    ): array {
        $email = $this->email($email);
        $this->assertRole($role);
        $this->assertPassword($password);
        if ($password !== $confirm) {
            throw new ApiException('Пароли не совпадают');
        }
        $firstName = $this->personName($firstName, 'Имя', true);
        $lastName = $this->personName($lastName, 'Фамилия', true);
        $middleName = $this->personName($middleName, 'Отчество', false);
        if ($role === self::EMPLOYER) {
            $companyName = $this->companyName($companyName);
            $inn = $this->inn($inn);
            (new CompanyService())->assertInnAvailable($inn);
        }
        if ($this->emailTaken($email)) {
            throw new ApiException('Пользователь с такой почтой уже зарегистрирован', 409);
        }

        $user = new CUser();
        $id = $user->Add([
            'LOGIN' => $email,
            'EMAIL' => $email,
            'NAME' => $firstName,
            'LAST_NAME' => $lastName,
            'SECOND_NAME' => $middleName,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'ACTIVE' => 'Y',
            'LID' => 's1',
            'GROUP_ID' => [2, $this->groupId($role)],
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$user->LAST_ERROR));
        }

        if ($role === self::EMPLOYER) {
            try {
                (new CompanyService())->create((int)$id, $companyName, $inn);
            } catch (ApiException $exception) {
                CUser::Delete((int)$id);
                throw $exception;
            }
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

    public function updateNames(string $lastName, string $firstName, string $middleName): array
    {
        $user = $this->current();
        if ($user === null) {
            throw new ApiException('Войдите в кабинет', 401);
        }

        $lastName = $this->personName($lastName, 'Фамилия', true);
        $firstName = $this->personName($firstName, 'Имя', true);
        $middleName = $this->personName($middleName, 'Отчество', false);

        $editor = new CUser();
        if (!$editor->Update($user['id'], [
            'NAME' => $firstName,
            'LAST_NAME' => $lastName,
            'SECOND_NAME' => $middleName,
        ])) {
            throw new ApiException(ApiException::plain((string)$editor->LAST_ERROR));
        }

        return $this->payload();
    }

    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file */
    public function saveAvatar(array $file): array
    {
        $user = $this->requireSeeker('Аватар доступен только соискателю');
        $prepared = $this->avatarFile($file);
        $prepared['MODULE_ID'] = 'main';
        $prepared['old_file'] = $this->photoId($user['id']);

        $editor = new CUser();
        if (!$editor->Update($user['id'], ['PERSONAL_PHOTO' => $prepared])) {
            throw new ApiException(ApiException::plain((string)$editor->LAST_ERROR));
        }

        return $this->payload();
    }

    public function removeAvatar(): array
    {
        $user = $this->requireSeeker('Аватар доступен только соискателю');
        $photoId = $this->photoId($user['id']);
        if ($photoId <= 0) {
            return $this->payload();
        }

        $editor = new CUser();
        if (!$editor->Update($user['id'], [
            'PERSONAL_PHOTO' => [
                'del' => 'Y',
                'old_file' => $photoId,
            ],
        ])) {
            throw new ApiException(ApiException::plain((string)$editor->LAST_ERROR));
        }

        return $this->payload();
    }

    public function updateCompany(string $name, string $inn, string $description): array
    {
        $user = $this->requireEmployer('Компанию может изменить только работодатель');
        $name = $this->companyName($name);
        $inn = $this->inn($inn);
        $description = trim($description);
        if ($description === '') {
            throw new ApiException('Укажите описание компании');
        }
        if (mb_strlen($description) > 10000) {
            throw new ApiException('Описание компании слишком длинное');
        }

        (new CompanyService())->update($user['id'], $name, $inn, $description);

        return $this->payload();
    }

    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file */
    public function saveCompanyLogo(array $file): array
    {
        $user = $this->requireEmployer('Картинку компании может загрузить только работодатель');
        (new CompanyService())->savePicture($user['id'], $this->avatarFile($file));

        return $this->payload();
    }

    public function removeCompanyLogo(): array
    {
        $user = $this->requireEmployer('Картинку компании может удалить только работодатель');
        (new CompanyService())->removePicture($user['id']);

        return $this->payload();
    }

    /** @return array{id: int, email: string, role: string, firstName: string, lastName: string, middleName: string, avatar: string|null, company: array{id: int, name: string, inn: string, description: string, logo: string|null}|null}|null */
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

        $row = UserTable::getRow([
            'filter' => ['=ID' => $id],
            'select' => ['EMAIL', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'PERSONAL_PHOTO'],
        ]);
        $photoId = (int)($row['PERSONAL_PHOTO'] ?? 0);
        $avatar = $photoId > 0 ? (string)CFile::GetPath($photoId) : '';

        return [
            'id' => $id,
            'email' => (string)($row['EMAIL'] ?? $USER->GetEmail()),
            'role' => $role,
            'firstName' => trim((string)($row['NAME'] ?? '')),
            'lastName' => trim((string)($row['LAST_NAME'] ?? '')),
            'middleName' => trim((string)($row['SECOND_NAME'] ?? '')),
            'avatar' => $avatar !== '' ? $avatar : null,
            'company' => $role === self::EMPLOYER ? (new CompanyService())->findByUser($id) : null,
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

    /** @return array{id: int, email: string, role: string} */
    public function requireEmployer(string $message): array
    {
        global $USER;
        if (!$USER instanceof CUser || !$USER->IsAuthorized()) {
            throw new ApiException($message, 401);
        }

        $user = $this->current();
        if ($user === null || $user['role'] !== self::EMPLOYER) {
            throw new ApiException($message, 403);
        }

        return $user;
    }

    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     *  @return array{name: string, type: string, tmp_name: string, error: int, size: int}
     */
    private function avatarFile(array $file): array
    {
        if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['tmp_name'] === '') {
            throw new ApiException('Выберите изображение');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ApiException('Не удалось загрузить изображение');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new ApiException('Изображение не должно быть больше 5 МБ');
        }

        $name = basename(str_replace('\\', '/', $file['name']));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            throw new ApiException('Можно загрузить только PNG, JPG или JPEG');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new ApiException('Можно загрузить только изображения PNG, JPG или JPEG');
        }

        $file['name'] = $name;

        return $file;
    }

    private function photoId(int $userId): int
    {
        $row = UserTable::getRow([
            'filter' => ['=ID' => $userId],
            'select' => ['PERSONAL_PHOTO'],
        ]);

        return (int)($row['PERSONAL_PHOTO'] ?? 0);
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

    private function personName(string $value, string $title, bool $required): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            if ($required) {
                throw new ApiException('Укажите поле «' . $title . '»');
            }

            return '';
        }
        if (mb_strlen($value) > 50 || !preg_match('/^[\p{L}][\p{L}\s\-]*$/u', $value)) {
            throw new ApiException('Поле «' . $title . '» может содержать только буквы, пробел и дефис');
        }

        return $value;
    }

    private function companyName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            throw new ApiException('Укажите название компании');
        }
        if (mb_strlen($value) > 255) {
            throw new ApiException('Название компании не должно быть длиннее 255 символов');
        }

        return $value;
    }

    private function inn(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^\d{10}(\d{2})?$/', $value)) {
            throw new ApiException('Укажите ИНН из 10 или 12 цифр');
        }

        return $value;
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
