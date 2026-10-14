<?php

declare(strict_types=1);

namespace App\Demo;

use App\Search\VacancyIndex;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\GroupTable;
use Bitrix\Main\Loader;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CIBlockPropertyEnum;
use CFile;
use CUser;
use CUtil;
use RuntimeException;
use Throwable;

final class Restorer
{
    public const PASSWORD = 'demo1234';

    private const KEEP = ['admin', 'support'];

    /** @var array<string, int> */
    private array $iblocks = [];

    /** @var array<string, int> */
    private array $enums = [];

    public static function userListViewOnly(object $user): bool
    {
        if (!method_exists($user, 'IsAdmin') || $user->IsAdmin()) {
            return false;
        }
        if (!method_exists($user, 'CanDoOperation')) {
            return false;
        }

        $canEdit = $user->CanDoOperation('edit_all_users')
            || $user->CanDoOperation('edit_subordinate_users')
            || $user->CanDoOperation('edit_own_profile')
            || $user->CanDoOperation('edit_php');
        $canView = $user->CanDoOperation('view_all_users') || $user->CanDoOperation('view_subordinate_users');

        return $canView && !$canEdit;
    }

    public static function allowed(): bool
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAuthorized()) {
            return false;
        }
        if ($USER->IsAdmin()) {
            return true;
        }

        $groupId = self::groupId('support');

        return $groupId > 0 && in_array($groupId, array_map('intval', CUser::GetUserGroup((int)$USER->GetID())), true);
    }

    public function run(): string
    {
        if (!Loader::includeModule('iblock') || !Loader::includeModule('highloadblock')) {
            throw new RuntimeException('Модули инфоблоков или highloadblock недоступны');
        }

        return $this->asAdmin(function (): string {
            $this->clearHighload();
            foreach (['vacancies', 'resumes', 'companies', 'banners', 'requests', 'cities'] as $code) {
                $this->clearElements($this->iblockId($code));
            }
            $this->clearUsers();
            $this->ensureSupport();

            $cities = $this->seedCities();
            $banners = $this->seedBanners();
            $companies = $this->seedCompanies();
            $vacancies = $this->seedVacancies($companies, $cities);
            $seekers = $this->seedSeekers();
            $index = $this->rebuildIndex();

            return sprintf(
                'Городов: %d, баннеров: %d, компаний: %d, вакансий: %d, соискателей: %d. %s Пароль работодателей и соискателей: %s.',
                count($cities),
                $banners,
                count($companies),
                $vacancies,
                $seekers,
                $index,
                self::PASSWORD,
            );
        });
    }

    private function asAdmin(callable $work): string
    {
        global $USER;
        $back = is_object($USER) ? (int)$USER->GetID() : 0;
        $admin = CUser::GetByLogin('admin')->Fetch();
        if (!$admin) {
            throw new RuntimeException('Пользователь admin не найден');
        }
        if (!is_object($USER)) {
            $USER = new CUser();
        }
        if ((int)$USER->GetID() !== (int)$admin['ID']) {
            $USER->Authorize((int)$admin['ID']);
        }

        try {
            return $work();
        } finally {
            if ($back > 0 && (int)$USER->GetID() !== $back && CUser::GetByID($back)->Fetch()) {
                $USER->Authorize($back);
            }
        }
    }

    private function clearHighload(): void
    {
        foreach (['Favourites', 'Responses', 'ResumeFavourites'] as $name) {
            $row = HighloadBlockTable::getList([
                'filter' => ['=NAME' => $name],
                'select' => ['ID', 'NAME', 'TABLE_NAME'],
            ])->fetch();
            if (!$row) {
                continue;
            }
            $class = HighloadBlockTable::compileEntity($row)->getDataClass();
            $rows = $class::getList(['select' => ['ID']]);
            while ($item = $rows->fetch()) {
                $class::delete((int)$item['ID']);
            }
        }
    }

    private function clearElements(int $iblockId): void
    {
        $ids = [];
        $rows = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID'],
        );
        while ($row = $rows->Fetch()) {
            $ids[] = (int)$row['ID'];
        }
        foreach ($ids as $id) {
            if (!CIBlockElement::Delete($id)) {
                throw new RuntimeException('Не удалось удалить элемент ' . $id);
            }
        }
    }

    private function clearUsers(): void
    {
        $ids = [];
        $by = 'id';
        $order = 'asc';
        $rows = CUser::GetList($by, $order, [], ['FIELDS' => ['ID', 'LOGIN']]);
        while ($row = $rows->Fetch()) {
            $login = mb_strtolower((string)$row['LOGIN']);
            if (in_array($login, self::KEEP, true)) {
                continue;
            }
            $ids[] = (int)$row['ID'];
        }
        foreach ($ids as $id) {
            if (!CUser::Delete($id)) {
                global $APPLICATION;
                $error = is_object($APPLICATION) ? (string)$APPLICATION->GetException() : '';
                throw new RuntimeException('Не удалось удалить пользователя ' . $id . ($error !== '' ? ': ' . $error : ''));
            }
        }
    }

    private function ensureSupport(): void
    {
        $groupId = self::groupId('support');
        if ($groupId <= 0) {
            throw new RuntimeException('Группа «Поддержка» не найдена. Выполните миграции.');
        }

        $fields = [
            'LOGIN' => 'support',
            'EMAIL' => 'support@mail.ru',
            'NAME' => 'Константин',
            'LAST_NAME' => 'Раскольников',
            'SECOND_NAME' => 'Георгиевич',
            'ACTIVE' => 'Y',
            'GROUP_ID' => [$groupId],
            'PASSWORD' => 'support',
            'CONFIRM_PASSWORD' => 'support',
        ];
        $user = new CUser();
        $existing = CUser::GetByLogin('support')->Fetch();
        if (!empty($existing['ID'])) {
            if (!$user->Update((int)$existing['ID'], $fields)) {
                throw new RuntimeException(strip_tags((string)$user->LAST_ERROR));
            }
        }
        if (empty($existing['ID']) && !$user->Add($fields)) {
            throw new RuntimeException(strip_tags((string)$user->LAST_ERROR));
        }
        self::grantSupportRights($groupId);
    }

    /** @return array<string, int> */
    private function seedCities(): array
    {
        $ids = [];
        $iblockId = $this->iblockId('cities');
        foreach (Catalog::cities() as $index => $city) {
            $ids[$city['code']] = $this->addElement($iblockId, [
                'NAME' => $city['name'],
                'CODE' => $city['code'],
                'SORT' => ($index + 1) * 100,
            ]);
        }

        return $ids;
    }

    private function seedBanners(): int
    {
        $iblockId = $this->iblockId('banners');
        $count = 0;
        foreach (Catalog::banners() as $index => $banner) {
            $this->addElement($iblockId, [
                'NAME' => $banner['name'],
                'CODE' => $banner['code'],
                'SORT' => ($index + 1) * 100,
                'PREVIEW_PICTURE' => $this->file('banners/' . $banner['file']),
            ]);
            $count++;
        }

        return $count;
    }

    /** @return array<string, int> */
    private function seedCompanies(): array
    {
        $iblockId = $this->iblockId('companies');
        $employerGroup = self::groupId('employer');
        if ($employerGroup <= 0) {
            throw new RuntimeException('Группа работодателей не найдена');
        }

        $ids = [];
        foreach (Catalog::companies() as $index => $company) {
            $person = $company['person'];
            $userId = $this->addUser($person, [$employerGroup]);
            $picture = $this->file('logos/' . $company['logo']);
            $detail = $this->file('logos/' . $company['logo']);
            $ids[$company['code']] = $this->addElement($iblockId, [
                'NAME' => $company['name'],
                'CODE' => $company['code'],
                'SORT' => ($index + 1) * 100,
                'DETAIL_TEXT' => implode("\n\n", $company['blocks']),
                'DETAIL_TEXT_TYPE' => 'text',
                'PREVIEW_PICTURE' => $picture,
                'DETAIL_PICTURE' => $detail,
                'PROPERTY_VALUES' => [
                    'INN' => $company['inn'],
                    'USER' => $userId,
                ],
            ]);
        }

        return $ids;
    }

    /**
     * @param array<string, int> $companies
     * @param array<string, int> $cities
     */
    private function seedVacancies(array $companies, array $cities): int
    {
        $iblockId = $this->iblockId('vacancies');
        $names = [];
        foreach (Catalog::cities() as $city) {
            $names[$city['code']] = $city['name'];
        }

        $count = 0;
        $sort = 100;
        foreach (Catalog::assignCities() as $vacancy) {
            $cityCode = $vacancy['city'];
            $text = Catalog::vacancyText($vacancy, $names[$cityCode]);
            $this->addElement($iblockId, [
                'NAME' => $vacancy['title'],
                'CODE' => $this->code($vacancy['title'] . '-' . $vacancy['company'] . '-' . $cityCode),
                'SORT' => $sort,
                'DETAIL_TEXT' => $text,
                'DETAIL_TEXT_TYPE' => 'text',
                'PROPERTY_VALUES' => [
                    'COMPANY' => $companies[$vacancy['company']],
                    'CITY' => $cities[$cityCode],
                    'SALARY_FROM' => $vacancy['salaryFrom'],
                    'SALARY_TO' => $vacancy['salaryTo'],
                    'WORKPLACE' => $this->enumId($iblockId, 'WORKPLACE', $vacancy['workplace']),
                    'SCHEDULE' => $this->enumId($iblockId, 'SCHEDULE', $vacancy['schedule']),
                ],
            ]);
            $sort += 10;
            $count++;
        }

        return $count;
    }

    private function seedSeekers(): int
    {
        $iblockId = $this->iblockId('resumes');
        $seekerGroup = self::groupId('seeker');
        if ($seekerGroup <= 0) {
            throw new RuntimeException('Группа соискателей не найдена');
        }

        $count = 0;
        foreach (Catalog::seekers() as $person) {
            $userId = $this->addUser($person, [$seekerGroup], $this->file('avatars/' . $person['avatar']));
            $this->addElement($iblockId, [
                'NAME' => $person['title'],
                'SORT' => 500,
                'DETAIL_TEXT' => implode("\n\n", $person['blocks']),
                'DETAIL_TEXT_TYPE' => 'text',
                'PROPERTY_VALUES' => [
                    'SALARY' => $person['salary'],
                    'USER' => $userId,
                ],
            ]);
            $count++;
        }

        return $count;
    }

    /** @param list<int> $groups */
    private function addUser(array $person, array $groups, ?array $photo = null): int
    {
        $fields = [
            'LOGIN' => $person['email'],
            'EMAIL' => $person['email'],
            'NAME' => $person['firstName'],
            'LAST_NAME' => $person['lastName'],
            'SECOND_NAME' => $person['middleName'],
            'PASSWORD' => self::PASSWORD,
            'CONFIRM_PASSWORD' => self::PASSWORD,
            'ACTIVE' => 'Y',
            'LID' => 's1',
            'GROUP_ID' => array_merge([2], $groups),
        ];
        if ($photo !== null) {
            $photo['MODULE_ID'] = 'main';
            $fields['PERSONAL_PHOTO'] = $photo;
        }

        $user = new CUser();
        $id = $user->Add($fields);
        if (!$id) {
            throw new RuntimeException(strip_tags((string)$user->LAST_ERROR));
        }

        return (int)$id;
    }

    /** @param array<string, mixed> $fields */
    private function addElement(int $iblockId, array $fields): int
    {
        $fields['IBLOCK_ID'] = $iblockId;
        $fields['ACTIVE'] = 'Y';
        $element = new CIBlockElement();
        $id = $element->Add($fields);
        if (!$id) {
            throw new RuntimeException(strip_tags((string)$element->LAST_ERROR));
        }

        return (int)$id;
    }

    /** @return array<string, mixed> */
    private function file(string $relative): array
    {
        $path = dirname(__DIR__, 2) . '/fixtures/' . $relative;
        $file = CFile::MakeFileArray($path);
        if (!is_array($file) || (int)($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('Не удалось прочитать файл ' . $path);
        }
        $file['MODULE_ID'] = 'iblock';

        return $file;
    }

    private function enumId(int $iblockId, string $code, string $xmlId): int
    {
        $key = $code . ':' . $xmlId;
        if (isset($this->enums[$key])) {
            return $this->enums[$key];
        }

        $property = CIBlockProperty::GetList([], [
            'IBLOCK_ID' => $iblockId,
            'CODE' => $code,
        ])->Fetch();
        $enum = $property ? CIBlockPropertyEnum::GetList([], [
            'PROPERTY_ID' => (int)$property['ID'],
            'XML_ID' => $xmlId,
        ])->Fetch() : false;
        if (!$enum) {
            throw new RuntimeException('Не найдено значение ' . $xmlId . ' свойства ' . $code);
        }

        $this->enums[$key] = (int)$enum['ID'];

        return $this->enums[$key];
    }

    private function iblockId(string $code): int
    {
        if (isset($this->iblocks[$code])) {
            return $this->iblocks[$code];
        }

        $types = [
            'banners' => 'system',
            'requests' => 'system',
            'resumes' => 'seekers',
            'vacancies' => 'employers',
            'companies' => 'employers',
            'cities' => 'employers',
        ];
        $row = CIBlock::GetList([], [
            'TYPE' => $types[$code],
            'CODE' => $code,
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new RuntimeException('Инфоблок «' . $code . '» не найден');
        }

        $this->iblocks[$code] = (int)$row['ID'];

        return $this->iblocks[$code];
    }

    public static function grantSupportRights(int $groupId): void
    {
        if ($groupId <= 0) {
            return;
        }

        \CMain::SetGroupRight('iblock', $groupId, 'W');
        \CMain::SetGroupRight('highloadblock', $groupId, 'W');
        self::grantSupportUserListView($groupId);

        global $APPLICATION;
        $permissions = ['*' => 'D'];
        $accessFile = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/bitrix/.access.php';
        if (is_file($accessFile)) {
            $PERM = [];
            include $accessFile;
            if (isset($PERM['admin']) && is_array($PERM['admin'])) {
                $permissions = $PERM['admin'];
            }
        }
        $permissions[(string)$groupId] = 'R';
        if (is_object($APPLICATION)) {
            $APPLICATION->SetFileAccessPermission('/bitrix/admin/', $permissions);
        }
    }

    private static function grantSupportUserListView(int $groupId): void
    {
        $taskId = self::supportUserListTaskId();
        $operations = \CTask::GetOperations($taskId, true);
        if (!in_array('view_all_users', $operations, true) || count($operations) !== 1) {
            throw new RuntimeException('Уровень доступа поддержки должен содержать только просмотр пользователей');
        }

        \CGroup::SetModulePermission($groupId, 'main', $taskId);
    }

    private static function supportUserListTaskId(): int
    {
        $name = 'workhub_support_view_users';
        $row = \Bitrix\Main\TaskTable::getList([
            'filter' => ['=NAME' => $name, '=MODULE_ID' => 'main'],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        $taskId = !empty($row['ID']) ? (int)$row['ID'] : 0;
        if ($taskId <= 0) {
            $taskId = (int)\CTask::Add([
                'NAME' => $name,
                'MODULE_ID' => 'main',
                'SYS' => 'N',
                'BINDING' => 'module',
                'DESCRIPTION' => 'Просмотр списка пользователей без изменения',
            ]);
        }
        if ($taskId <= 0) {
            throw new RuntimeException('Не удалось создать уровень доступа для просмотра пользователей');
        }

        \CTask::SetOperations($taskId, ['view_all_users'], true);

        return $taskId;
    }

    private static function groupId(string $code): int
    {
        $row = GroupTable::getRow([
            'filter' => ['=STRING_ID' => $code],
            'select' => ['ID'],
        ]);

        return $row ? (int)$row['ID'] : 0;
    }

    private function code(string $value): string
    {
        $code = CUtil::translit($value, 'ru', [
            'max_len' => 100,
            'change_case' => 'L',
            'replace_space' => '-',
            'replace_other' => '-',
            'delete_repeat_replace' => true,
        ]);

        return trim($code, '-') ?: 'item';
    }

    private function rebuildIndex(): string
    {
        try {
            VacancyIndex::rebuild();
        } catch (Throwable $exception) {
            return 'Индекс вакансий не обновлён: ' . $exception->getMessage() . '.';
        }

        return 'Индекс вакансий обновлён.';
    }
}
