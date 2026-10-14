<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;
use Bitrix\Main\UserTable;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CIBlockPropertyEnum;
use CFile;

class VacancyManageService
{
    /** @var array<string, string> */
    public const WORKPLACES = [
        'office' => 'Офис',
        'hybrid' => 'Гибрид',
        'remote' => 'Удалённо',
    ];

    /** @var array<string, string> */
    public const SCHEDULES = [
        '5-2' => '5/2',
        'floating' => 'Плавающий',
        'shift' => 'Вахта',
    ];

    private int $iblock = 0;

    /** @var array<string, int> */
    private array $enums = [];

    public function __construct(
        private readonly AuthService $auth,
        private readonly ResumeService $resumes,
    ) {
    }

    /** @return array{vacancies: list<array<string, mixed>>} */
    public function list(): array
    {
        $company = $this->company();

        return ['vacancies' => $this->collect($company['id'])];
    }

    /** @return array{vacancy: array<string, mixed>} */
    public function one(int $id): array
    {
        $company = $this->company();
        $vacancy = $this->owned($company['id'], $id);
        $vacancy['applicants'] = $this->applicants($id);

        return ['vacancy' => $vacancy];
    }

    /** @return array{applicant: array<string, mixed>} */
    public function applicant(int $vacancyId, int $userId): array
    {
        $company = $this->company();
        $this->owned($company['id'], $vacancyId);
        if ($userId <= 0 || !in_array($userId, (new Link(ResponseService::BLOCK))->userIds($vacancyId), true)) {
            throw new ApiException('Отклик не найден', 404);
        }

        $row = UserTable::getRow([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'PERSONAL_PHONE', 'PERSONAL_MOBILE', 'PERSONAL_PHOTO'],
        ]);
        if (!$row) {
            throw new ApiException('Соискатель не найден', 404);
        }

        $phone = trim((string)($row['PERSONAL_PHONE'] ?? ''));
        if ($phone === '') {
            $phone = trim((string)($row['PERSONAL_MOBILE'] ?? ''));
        }
        $photoId = (int)($row['PERSONAL_PHOTO'] ?? 0);
        $avatar = $photoId > 0 ? (string)CFile::GetPath($photoId) : '';

        return [
            'applicant' => [
                'id' => $userId,
                'firstName' => trim((string)$row['NAME']),
                'lastName' => trim((string)$row['LAST_NAME']),
                'middleName' => trim((string)$row['SECOND_NAME']),
                'email' => (string)$row['EMAIL'],
                'phone' => $phone,
                'avatar' => $avatar !== '' ? $avatar : null,
                'resume' => $this->resumes->latestForUser($userId),
            ],
        ];
    }

    /** @return array{vacancy: array<string, mixed>} */
    public function create(string $title, string $description, mixed $salary, string $workplace, string $schedule): array
    {
        $company = $this->company();
        $values = $this->values($title, $description, $salary, $workplace, $schedule);

        $element = new CIBlockElement();
        $id = $element->Add([
            'IBLOCK_ID' => $this->iblockId(),
            'NAME' => $values['title'],
            'ACTIVE' => 'Y',
            'DETAIL_TEXT' => $values['description'],
            'DETAIL_TEXT_TYPE' => 'text',
            'PROPERTY_VALUES' => [
                'COMPANY' => $company['id'],
                'SALARY_FROM' => $values['salary'],
                'SALARY_TO' => $values['salary'],
                'WORKPLACE' => $this->enumId('WORKPLACE', $values['workplace']),
                'SCHEDULE' => $this->enumId('SCHEDULE', $values['schedule']),
            ],
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        return ['vacancy' => $this->owned($company['id'], (int)$id)];
    }

    /** @return array{vacancy: array<string, mixed>} */
    public function update(int $id, string $title, string $description, mixed $salary, string $workplace, string $schedule): array
    {
        $company = $this->company();
        $this->owned($company['id'], $id);
        $values = $this->values($title, $description, $salary, $workplace, $schedule);

        $element = new CIBlockElement();
        $saved = $element->Update($id, [
            'NAME' => $values['title'],
            'DETAIL_TEXT' => $values['description'],
            'DETAIL_TEXT_TYPE' => 'text',
        ]);
        if (!$saved) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        CIBlockElement::SetPropertyValuesEx($id, $this->iblockId(), [
            'SALARY_FROM' => $values['salary'],
            'SALARY_TO' => $values['salary'],
            'WORKPLACE' => $this->enumId('WORKPLACE', $values['workplace']),
            'SCHEDULE' => $this->enumId('SCHEDULE', $values['schedule']),
        ]);

        return ['vacancy' => $this->owned($company['id'], $id)];
    }

    public function delete(int $id): array
    {
        $company = $this->company();
        $this->owned($company['id'], $id);
        if (!CIBlockElement::Delete($id)) {
            throw new ApiException('Не удалось удалить вакансию');
        }

        return ['deleted' => $id];
    }

    /** @return array{id: int, name: string, inn: string} */
    private function company(): array
    {
        $user = $this->auth->requireEmployer('Вакансии компании доступны только работодателю');
        $company = (new CompanyService())->findByUser($user['id']);
        if ($company === null) {
            throw new ApiException('К аккаунту не привязана компания', 409);
        }

        return $company;
    }

    /** @return array{title: string, description: string, salary: int, workplace: string, schedule: string} */
    private function values(string $title, string $description, mixed $salary, string $workplace, string $schedule): array
    {
        $title = trim($title);
        $description = trim($description);
        if ($title === '') {
            throw new ApiException('Укажите название вакансии');
        }
        if (mb_strlen($title) > 255) {
            throw new ApiException('Название вакансии не должно быть длиннее 255 символов');
        }
        if ($description === '') {
            throw new ApiException('Укажите описание вакансии');
        }
        if (mb_strlen($description) > 10000) {
            throw new ApiException('Описание вакансии слишком длинное');
        }
        if (!isset(self::WORKPLACES[$workplace])) {
            throw new ApiException('Выберите место работы');
        }
        if (!isset(self::SCHEDULES[$schedule])) {
            throw new ApiException('Выберите график работы');
        }

        $number = is_int($salary) || is_float($salary) ? (string)$salary : trim((string)$salary);
        $number = str_replace([' ', "\u{00A0}"], '', $number);
        if (!preg_match('/^\d{1,12}$/', $number)) {
            throw new ApiException('Укажите заработную плату числом');
        }

        return [
            'title' => $title,
            'description' => $description,
            'salary' => (int)$number,
            'workplace' => $workplace,
            'schedule' => $schedule,
        ];
    }

    /** @return array<string, mixed> */
    private function owned(int $companyId, int $id): array
    {
        if ($id <= 0) {
            throw new ApiException('Вакансия не найдена', 404);
        }

        $items = $this->collect($companyId, $id);
        if ($items === []) {
            throw new ApiException('Вакансия не найдена', 404);
        }

        return $items[0];
    }

    /** @return list<array<string, mixed>> */
    private function collect(int $companyId, int $id = 0): array
    {
        $filter = [
            'IBLOCK_ID' => $this->iblockId(),
            'PROPERTY_COMPANY' => $companyId,
            'CHECK_PERMISSIONS' => 'N',
        ];
        if ($id > 0) {
            $filter['ID'] = $id;
        }

        $items = [];
        $rows = CIBlockElement::GetList(
            ['ID' => 'DESC'],
            $filter,
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME', 'DETAIL_TEXT']
        );
        while ($element = $rows->GetNextElement(false)) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();
            $workplace = (string)($props['WORKPLACE']['VALUE_XML_ID'] ?? '');
            $schedule = (string)($props['SCHEDULE']['VALUE_XML_ID'] ?? '');
            $salary = $props['SALARY_FROM']['VALUE'] ?? '';
            if ($salary === '' || $salary === null || $salary === false) {
                $salary = $props['SALARY_TO']['VALUE'] ?? 0;
            }
            $items[] = [
                'id' => (int)$fields['ID'],
                'title' => (string)$fields['NAME'],
                'description' => trim((string)($fields['DETAIL_TEXT'] ?? '')),
                'salary' => (int)$salary,
                'workplace' => $workplace,
                'workplaceLabel' => self::WORKPLACES[$workplace] ?? (string)($props['WORKPLACE']['VALUE'] ?? ''),
                'schedule' => $schedule,
                'scheduleLabel' => self::SCHEDULES[$schedule] ?? (string)($props['SCHEDULE']['VALUE'] ?? ''),
                'cityIds' => self::cityIds($props['CITY']['VALUE'] ?? null),
            ];
        }

        $counts = (new Link(ResponseService::BLOCK))->counts(array_column($items, 'id'));
        $cities = $this->cityNames($items);
        foreach ($items as $index => $item) {
            $linked = [];
            foreach ($item['cityIds'] as $cityId) {
                if (isset($cities[$cityId])) {
                    $linked[] = $cities[$cityId];
                }
            }
            $items[$index]['cities'] = $linked;
            $items[$index]['responses'] = $counts[$item['id']] ?? 0;
            unset($items[$index]['cityIds']);
        }

        return $items;
    }

    /** @return list<int> */
    private static function cityIds(mixed $value): array
    {
        if (!is_array($value)) {
            $value = ($value === null || $value === '' || $value === false) ? [] : [$value];
        }

        $ids = [];
        foreach ($value as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function cityNames(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            foreach ($item['cityIds'] as $id) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $cities = [];
        $rows = CIBlockElement::GetList(
            [],
            ['ID' => array_values($ids), 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'NAME', 'CODE']
        );
        while ($row = $rows->Fetch()) {
            $cities[(int)$row['ID']] = [
                'id' => (int)$row['ID'],
                'code' => (string)$row['CODE'],
                'name' => (string)$row['NAME'],
            ];
        }

        return $cities;
    }

    /** @return list<array{id: int, name: string, email: string, avatar: string|null, resumeId: int, resumeTitle: string, respondedAt: string}> */
    private function applicants(int $vacancyId): array
    {
        $responses = (new Link(ResponseService::BLOCK))->responders($vacancyId);
        if ($responses === []) {
            return [];
        }

        $userIds = array_column($responses, 'userId');
        $resumeTitles = $this->resumes->titlesByUsers($userIds);

        $rows = [];
        $result = UserTable::getList([
            'filter' => ['=ID' => $userIds],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'PERSONAL_PHOTO'],
        ]);
        while ($row = $result->fetch()) {
            $rows[(int)$row['ID']] = $row;
        }

        $people = [];
        foreach ($responses as $response) {
            $userId = $response['userId'];
            $row = $rows[$userId] ?? null;
            $resume = $resumeTitles[$userId] ?? null;
            if ($row === null) {
                $people[] = [
                    'id' => $userId,
                    'name' => 'Пользователь',
                    'email' => '',
                    'avatar' => null,
                    'resumeId' => (int)($resume['id'] ?? 0),
                    'resumeTitle' => (string)($resume['title'] ?? ''),
                    'respondedAt' => $response['respondedAt'],
                ];
                continue;
            }

            $name = trim(implode(' ', array_filter([
                trim((string)$row['LAST_NAME']),
                trim((string)$row['NAME']),
                trim((string)$row['SECOND_NAME']),
            ], static fn (string $part): bool => $part !== '')));
            $photoId = (int)($row['PERSONAL_PHOTO'] ?? 0);
            $avatar = $photoId > 0 ? (string)CFile::GetPath($photoId) : '';
            $email = (string)$row['EMAIL'];
            $people[] = [
                'id' => $userId,
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'avatar' => $avatar !== '' ? $avatar : null,
                'resumeId' => (int)($resume['id'] ?? 0),
                'resumeTitle' => (string)($resume['title'] ?? ''),
                'respondedAt' => $response['respondedAt'],
            ];
        }

        return $people;
    }

    private function enumId(string $propertyCode, string $xmlId): int
    {
        $key = $propertyCode . ':' . $xmlId;
        if (isset($this->enums[$key])) {
            return $this->enums[$key];
        }

        $property = CIBlockProperty::GetList([], [
            'IBLOCK_ID' => $this->iblockId(),
            'CODE' => $propertyCode,
        ])->Fetch();
        if (!$property) {
            throw new ApiException('Свойства вакансии ещё не созданы. Выполните миграции.', 500);
        }

        $enum = CIBlockPropertyEnum::GetList([], [
            'PROPERTY_ID' => (int)$property['ID'],
            'XML_ID' => $xmlId,
        ])->Fetch();
        if (!$enum) {
            throw new ApiException('Свойства вакансии ещё не созданы. Выполните миграции.', 500);
        }

        $this->enums[$key] = (int)$enum['ID'];

        return $this->enums[$key];
    }

    private function iblockId(): int
    {
        if ($this->iblock > 0) {
            return $this->iblock;
        }

        $row = CIBlock::GetList([], [
            'TYPE' => 'employers',
            'CODE' => 'vacancies',
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new ApiException('Инфоблок «Вакансии» ещё не создан. Выполните миграции.', 500);
        }

        $this->iblock = (int)$row['ID'];

        return $this->iblock;
    }
}
