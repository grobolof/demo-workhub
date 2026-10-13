<?php

declare(strict_types=1);

namespace App\Api;

use App\Search\VacancyIndex;
use CIBlockElement;
use CFile;
use RuntimeException;

class JobBoard
{
    /** @return list<array{id: int, name: string, code: string}> */
    public static function cities(): array
    {
        $helper = new self();

        return array_values($helper->loadCities($helper->iblockId('cities')));
    }

    /** @return list<array{id: int, name: string, image: string}> */
    public static function banners(): array
    {
        $helper = new self();
        $banners = [];
        $rows = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => $helper->iblockId('banners'),
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

    public static function search(string $query, string $cityCode): array
    {
        $helper = new self();
        $companies = $helper->companies($helper->iblockId('companies'));
        $cities = $helper->loadCities($helper->iblockId('cities'));
        $cityCode = trim($cityCode);
        $query = trim($query);
        $cityId = $helper->cityId($cities, $cityCode);

        if ($cityCode !== '' && $cityId <= 0) {
            return [
                'cities' => array_values($cities),
                'vacancies' => [],
            ];
        }

        $filter = [
            'IBLOCK_ID' => $helper->iblockId('vacancies'),
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ];
        $orderedIds = null;

        if ($query !== '') {
            $orderedIds = VacancyIndex::searchIds($query, $cityId > 0 ? $cityId : null);
            if ($orderedIds === []) {
                return [
                    'cities' => array_values($cities),
                    'vacancies' => [],
                ];
            }
            $filter['ID'] = $orderedIds;
        }

        if ($cityId > 0) {
            $filter['PROPERTY_CITY'] = $cityId;
        }

        $vacancies = $helper->collect($filter, $companies, $cities, false);
        if ($orderedIds !== null) {
            $order = array_flip($orderedIds);
            usort($vacancies, static function (array $left, array $right) use ($order): int {
                return ($order[$left['id']] ?? 0) <=> ($order[$right['id']] ?? 0);
            });
        }

        return [
            'cities' => array_values($cities),
            'vacancies' => $vacancies,
        ];
    }

    public static function vacancy(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $helper = new self();
        $companies = $helper->companies($helper->iblockId('companies'));
        $cities = $helper->loadCities($helper->iblockId('cities'));
        $vacancies = $helper->collect([
            'IBLOCK_ID' => $helper->iblockId('vacancies'),
            'ID' => $id,
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ], $companies, $cities, true);

        return $vacancies[0] ?? null;
    }

    /** @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int)$id, $ids),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $helper = new self();
        $companies = $helper->companies($helper->iblockId('companies'));
        $cities = $helper->loadCities($helper->iblockId('cities'));
        $vacancies = $helper->collect([
            'IBLOCK_ID' => $helper->iblockId('vacancies'),
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

    public static function company(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $helper = new self();
        $companiesId = $helper->iblockId('companies');
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
            ['ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
        )->Fetch();
        if (!$row) {
            return null;
        }

        $logoId = (int)$row['PREVIEW_PICTURE'] > 0 ? (int)$row['PREVIEW_PICTURE'] : (int)$row['DETAIL_PICTURE'];
        $logo = $logoId > 0 ? (string)CFile::GetPath($logoId) : '';
        $company = [
            'id' => (int)$row['ID'],
            'name' => (string)$row['NAME'],
            'code' => (string)$row['CODE'],
            'logo' => $logo !== '' ? $logo : null,
            'preview' => trim((string)$row['PREVIEW_TEXT']),
            'detail' => trim((string)$row['DETAIL_TEXT']),
        ];

        $companies = $helper->companies($companiesId);
        $cities = $helper->loadCities($helper->iblockId('cities'));
        $vacancies = $helper->collect([
            'IBLOCK_ID' => $helper->iblockId('vacancies'),
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
            'PROPERTY_COMPANY' => $id,
        ], $companies, $cities, false);

        $groups = [];
        foreach ($vacancies as $vacancy) {
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
                'TYPE' => 'workhub',
                'CODE' => $item,
                'CHECK_PERMISSIONS' => 'N',
            ])->Fetch();
            if ($row) {
                return (int)$row['ID'];
            }
        }

        throw new RuntimeException('Инфоблоки WorkHub ещё не созданы. Выполните миграции.');
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
     * @return list<array<string, mixed>>
     */
    private function collect(array $filter, array $companies, array $cities, bool $withDetail): array
    {
        $select = ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'SORT'];
        if ($withDetail) {
            $select[] = 'DETAIL_TEXT';
        }

        $vacancies = [];
        $rows = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'DESC'],
            $filter,
            false,
            false,
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
                'preview' => trim((string)$fields['PREVIEW_TEXT']),
                'salaryFrom' => self::money($props['SALARY_FROM']['VALUE'] ?? null),
                'salaryTo' => self::money($props['SALARY_TO']['VALUE'] ?? null),
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

    /** @return array<int, array{id: int, name: string, code: string, logo: string|null}> */
    private function companies(int $iblockId): array
    {
        $companies = [];
        $rows = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE']
        );
        while ($row = $rows->Fetch()) {
            $logo = '';
            if ((int)$row['PREVIEW_PICTURE'] > 0) {
                $logo = (string)CFile::GetPath((int)$row['PREVIEW_PICTURE']);
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
