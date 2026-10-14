<?php

declare(strict_types=1);

namespace App\Service;

use App\Search\VacancyIndex;
use CIBlockElement;
use CFile;
use RuntimeException;

class JobBoardService
{
    public const PAGE_SIZE = 10;

    private const IBLOCK_TYPES = [
        'banners' => 'system',
        'resumes' => 'seekers',
        'vacancies' => 'employers',
        'positions' => 'employers',
        'companies' => 'employers',
        'cities' => 'employers',
    ];

    /** @return list<array{id: int, name: string, code: string}> */
    public function cities(): array
    {
        return array_values($this->loadCities($this->iblockId('cities')));
    }

    /** @return list<array{id: int, name: string, image: string}> */
    public function banners(): array
    {
        $banners = [];
        $rows = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => $this->iblockId('banners'),
                'ACTIVE' => 'Y',
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID', 'NAME', 'PREVIEW_PICTURE']
        );
        while ($row = $rows->Fetch()) {
            $image = '';
            if ((int)$row['PREVIEW_PICTURE'] > 0) {
                $image = (string)CFile::GetPath((int)$row['PREVIEW_PICTURE']);
            }
            if ($image === '') {
                continue;
            }
            $banners[] = [
                'id' => (int)$row['ID'],
                'name' => (string)$row['NAME'],
                'image' => $image,
            ];
        }

        return $banners;
    }

    /** @return array{resumes: int, vacancies: int, companies: int} */
    public function stats(): array
    {
        return [
            'resumes' => $this->safeCount('resumes'),
            'vacancies' => $this->safeCount('vacancies'),
            'companies' => $this->safeCount('companies'),
        ];
    }

    /** @return array{suggestions: list<string>} */
    public function suggest(string $query, string $cityCode): array
    {
        $query = trim($query);
        $cityCode = trim($cityCode);
        if (mb_strlen($query) < 2) {
            return ['suggestions' => []];
        }

        $cities = $this->loadCities($this->iblockId('cities'));
        $cityId = $this->cityId($cities, $cityCode);
        if ($cityCode !== '' && $cityId <= 0) {
            return ['suggestions' => []];
        }

        return [
            'suggestions' => VacancyIndex::suggest($query, $cityId > 0 ? $cityId : null),
        ];
    }

    /** @return list<array{id: int, name: string, code: string, logo: string|null}> */
    public function companyCatalog(): array
    {
        return array_values($this->companies($this->iblockId('companies')));
    }

    public function search(string $query, string $cityCode, int $page = 1, string $sort = ''): array
    {
        $page = max(1, $page);
        $limit = self::PAGE_SIZE;
        $sort = in_array($sort, ['salary_asc', 'salary_desc'], true) ? $sort : '';
        $companies = $this->companies($this->iblockId('companies'));
        $cities = $this->loadCities($this->iblockId('cities'));
        $cityCode = trim($cityCode);
        $query = trim($query);
        $cityId = $this->cityId($cities, $cityCode);
        $empty = [
            'cities' => array_values($cities),
            'vacancies' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
        ];

        if ($cityCode !== '' && $cityId <= 0) {
            return $empty;
        }

        $filter = [
            'IBLOCK_ID' => $this->iblockId('vacancies'),
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ];
        $orderedIds = null;

        if ($query !== '') {
            $orderedIds = VacancyIndex::searchIds($query, $cityId > 0 ? $cityId : null);
            if ($orderedIds === []) {
                return $empty;
            }
        }

        if ($cityId > 0) {
            $filter['PROPERTY_CITY'] = $cityId;
        }

        if ($orderedIds === null && $sort !== '') {
            $orderedIds = [];
            $idRows = CIBlockElement::GetList(['ID' => 'DESC'], $filter, false, false, ['ID']);
            while ($idRow = $idRows->Fetch()) {
                $orderedIds[] = (int)$idRow['ID'];
            }
        }

        if ($orderedIds !== null && $sort !== '') {
            $orderedIds = $this->sortBySalary($orderedIds, $sort);
        }

        $nav = false;
        if ($orderedIds !== null) {
            $total = count($orderedIds);
            $slice = array_slice($orderedIds, ($page - 1) * $limit, $limit);
            $empty['total'] = $total;
            if ($slice === []) {
                return $empty;
            }
            $filter['ID'] = $slice;
        } else {
            $total = $this->countFiltered($filter);
            $offset = ($page - 1) * $limit;
            if ($offset >= $total) {
                $empty['total'] = $total;
                return $empty;
            }
            $nav = ['nTopCount' => $limit, 'nOffset' => $offset];
        }

        $vacancies = $this->collect($filter, $companies, $cities, false, $nav);
        if ($orderedIds !== null) {
            $order = array_flip($orderedIds);
            usort($vacancies, static function (array $left, array $right) use ($order): int {
                return ($order[$left['id']] ?? 0) <=> ($order[$right['id']] ?? 0);
            });
        }

        return [
            'cities' => array_values($cities),
            'vacancies' => $vacancies,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function vacancy(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $companies = $this->companies($this->iblockId('companies'));
        $cities = $this->loadCities($this->iblockId('cities'));
        $vacancies = $this->collect([
            'IBLOCK_ID' => $this->iblockId('vacancies'),
            'ID' => $id,
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ], $companies, $cities, true);

        return $vacancies[0] ?? null;
    }

    /** @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int)$id, $ids),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $companies = $this->companies($this->iblockId('companies'));
        $cities = $this->loadCities($this->iblockId('cities'));
        $vacancies = $this->collect([
            'IBLOCK_ID' => $this->iblockId('vacancies'),
            'ID' => $ids,
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ], $companies, $cities, false);

        $order = array_flip($ids);
        usort($vacancies, static function (array $left, array $right) use ($order): int {
            return ($order[$left['id']] ?? 0) <=> ($order[$right['id']] ?? 0);
        });

        return $vacancies;
    }

    public function company(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $companiesId = $this->iblockId('companies');
        $row = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $companiesId,
                'ID' => $id,
                'ACTIVE' => 'Y',
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'DETAIL_PICTURE']
        )->Fetch();
        if (!$row) {
            return null;
        }

        $logoId = (int)$row['DETAIL_PICTURE'];
        $logo = $logoId > 0 ? (string)CFile::GetPath($logoId) : '';
        $company = [
            'id' => (int)$row['ID'],
            'name' => (string)$row['NAME'],
            'code' => (string)$row['CODE'],
            'logo' => $logo !== '' ? $logo : null,
            'detail' => trim((string)$row['DETAIL_TEXT']),
        ];

        $companies = $this->companies($companiesId);
        $cities = $this->loadCities($this->iblockId('cities'));
        $vacancies = $this->collect([
            'IBLOCK_ID' => $this->iblockId('vacancies'),
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
            'PROPERTY_COMPANY' => $id,
        ], $companies, $cities, false);

        $groups = [];
        $unassigned = [];
        foreach ($vacancies as $vacancy) {
            if ($vacancy['cities'] === []) {
                $unassigned[] = $vacancy;
                continue;
            }
            foreach ($vacancy['cities'] as $city) {
                $cityId = (int)$city['id'];
                if (!isset($groups[$cityId])) {
                    $groups[$cityId] = [
                        'id' => $cityId,
                        'name' => $city['name'],
                        'code' => $city['code'],
                        'sort' => (int)($cities[$cityId]['sort'] ?? 0),
                        'vacancies' => [],
                    ];
                }
                $groups[$cityId]['vacancies'][] = $vacancy;
            }
        }

        if ($unassigned !== []) {
            $groups[0] = [
                'id' => 0,
                'name' => 'Город не указан',
                'code' => 'unspecified',
                'sort' => 100000,
                'vacancies' => $unassigned,
            ];
        }

        $groups = array_values($groups);
        usort($groups, static function (array $left, array $right): int {
            return [$left['sort'], $left['name']] <=> [$right['sort'], $right['name']];
        });
        foreach ($groups as &$group) {
            unset($group['sort']);
        }
        unset($group);

        $company['cities'] = $groups;

        return $company;
    }

    private function iblockId(string $code): int
    {
        $codes = $code === 'vacancies' ? ['vacancies', 'positions'] : [$code];
        foreach ($codes as $item) {
            $row = \CIBlock::GetList([], [
                'TYPE' => self::IBLOCK_TYPES[$item] ?? 'employers',
                'CODE' => $item,
                'CHECK_PERMISSIONS' => 'N',
            ])->Fetch();
            if ($row) {
                return (int)$row['ID'];
            }
        }

        throw new RuntimeException('Инфоблоки WorkHub ещё не созданы. Выполните миграции.');
    }

    private function safeCount(string $code): int
    {
        try {
            return $this->countActive($code);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @param list<int> $ids
     *  @return list<int>
     */
    private function sortBySalary(array $ids, string $sort): array
    {
        $ids = array_values(array_map(static fn (mixed $id): int => (int)$id, $ids));
        if ($ids === []) {
            return [];
        }

        $salaries = [];
        $rows = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockId('vacancies'),
                'ID' => $ids,
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID', 'PROPERTY_SALARY_FROM', 'PROPERTY_SALARY_TO']
        );
        while ($row = $rows->Fetch()) {
            $id = (int)$row['ID'];
            if (isset($salaries[$id])) {
                continue;
            }
            $value = $row['PROPERTY_SALARY_FROM_VALUE'] ?? null;
            if ($value === null || $value === '' || $value === false) {
                $value = $row['PROPERTY_SALARY_TO_VALUE'] ?? 0;
            }
            $salaries[$id] = is_numeric($value) ? (int)$value : 0;
        }

        usort($ids, static function (int $left, int $right) use ($salaries, $sort): int {
            $cmp = ($salaries[$left] ?? 0) <=> ($salaries[$right] ?? 0);
            if ($sort === 'salary_desc') {
                $cmp = -$cmp;
            }

            return $cmp !== 0 ? $cmp : ($right <=> $left);
        });

        return $ids;
    }

    /** @param list<array{id: int, name: string, code: string, sort: int}> $cities */
    private function cityId(array $cities, string $code): int
    {
        if ($code === '') {
            return 0;
        }
        foreach ($cities as $city) {
            if ($city['code'] === $code) {
                return $city['id'];
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<int, array{id: int, name: string, code: string, logo: string|null}> $companies
     * @param array<int, array{id: int, name: string, code: string, sort: int}> $cities
     * @param array<string, int>|false $nav
     * @return list<array<string, mixed>>
     */
    private function collect(array $filter, array $companies, array $cities, bool $withDetail, array|false $nav = false): array
    {
        $select = ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'SORT'];

        $vacancies = [];
        $rows = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'DESC'],
            $filter,
            false,
            $nav === false ? false : $nav,
            $select
        );
        while ($element = $rows->GetNextElement(false)) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();
            $companyId = (int)($props['COMPANY']['VALUE'] ?? 0);
            $cityIds = $props['CITY']['VALUE'] ?? [];
            if (!is_array($cityIds)) {
                $cityIds = $cityIds ? [$cityIds] : [];
            }

            $vacancyCities = [];
            foreach ($cityIds as $cityId) {
                $cityId = (int)$cityId;
                if (isset($cities[$cityId])) {
                    $vacancyCities[] = [
                        'id' => $cities[$cityId]['id'],
                        'name' => $cities[$cityId]['name'],
                        'code' => $cities[$cityId]['code'],
                    ];
                }
            }

            $vacancy = [
                'id' => (int)$fields['ID'],
                'name' => (string)$fields['NAME'],
                'code' => (string)$fields['CODE'],
                'preview' => trim((string)($fields['DETAIL_TEXT'] ?? '')),
                'salaryFrom' => self::money($props['SALARY_FROM']['VALUE'] ?? null),
                'salaryTo' => self::money($props['SALARY_TO']['VALUE'] ?? null),
                'workplace' => self::label($props['WORKPLACE']['VALUE'] ?? null),
                'schedule' => self::label($props['SCHEDULE']['VALUE'] ?? null),
                'company' => $companies[$companyId] ?? null,
                'cities' => $vacancyCities,
            ];
            if ($withDetail) {
                $vacancy['detail'] = trim((string)($fields['DETAIL_TEXT'] ?? ''));
            }
            $vacancies[] = $vacancy;
        }

        return $vacancies;
    }

    /** @return array<int, array{id: int, name: string, code: string, sort: int}> */
    private function loadCities(int $iblockId): array
    {
        $cities = [];
        $rows = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'SORT']
        );
        while ($row = $rows->Fetch()) {
            $cities[(int)$row['ID']] = [
                'id' => (int)$row['ID'],
                'name' => (string)$row['NAME'],
                'code' => (string)$row['CODE'],
                'sort' => (int)$row['SORT'],
            ];
        }

        return $cities;
    }

    /** @param array<string, mixed> $filter */
    private function countFiltered(array $filter): int
    {
        $count = CIBlockElement::GetList([], $filter, []);

        return (int)$count;
    }

    private function countActive(string $code): int
    {
        $count = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockId($code),
                'ACTIVE' => 'Y',
                'CHECK_PERMISSIONS' => 'N',
            ],
            []
        );

        return (int)$count;
    }

    /** @return array<int, array{id: int, name: string, code: string, logo: string|null}> */
    private function companies(int $iblockId): array
    {
        $companies = [];
        $rows = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'DETAIL_PICTURE']
        );
        while ($row = $rows->Fetch()) {
            $logo = '';
            if ((int)$row['DETAIL_PICTURE'] > 0) {
                $logo = (string)CFile::GetPath((int)$row['DETAIL_PICTURE']);
            }
            $companies[(int)$row['ID']] = [
                'id' => (int)$row['ID'],
                'name' => (string)$row['NAME'],
                'code' => (string)$row['CODE'],
                'logo' => $logo !== '' ? $logo : null,
            ];
        }

        return $companies;
    }

    private static function label(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['VALUE'] ?? '';
        }
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        return trim((string)$value);
    }

    private static function money(mixed $value): ?int
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $value = $value['VALUE'] ?? '';
        }
        if (!is_numeric($value)) {
            return null;
        }

        return (int)round((float)$value);
    }
}
