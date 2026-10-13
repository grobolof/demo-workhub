<?php

declare(strict_types=1);

namespace App\Api;

use CIBlockElement;
use CFile;
use RuntimeException;

class JobBoard
{
    public static function search(string $query, string $cityCode): array
    {
        $helper = new self();
        $companiesId = $helper->iblockId('companies');
        $citiesId = $helper->iblockId('cities');
        $positionsId = $helper->iblockId('positions');

        $cities = $helper->cities($citiesId);
        $companies = $helper->companies($companiesId);
        $cityCode = trim($cityCode);
        $query = trim($query);

        $filter = [
            'IBLOCK_ID' => $positionsId,
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ];

        if ($cityCode !== '') {
            $cityId = 0;
            foreach ($cities as $city) {
                if ($city['code'] === $cityCode) {
                    $cityId = $city['id'];
                    break;
                }
            }
            if ($cityId <= 0) {
                return [
                    'cities' => $cities,
                    'vacancies' => [],
                ];
            }
            $filter['PROPERTY_CITY'] = $cityId;
        }

        if ($query !== '') {
            $companyIds = [];
            foreach ($companies as $company) {
                if (mb_stripos($company['name'], $query) !== false) {
                    $companyIds[] = $company['id'];
                }
            }
            $or = [
                'LOGIC' => 'OR',
                ['%NAME' => $query],
                ['%PREVIEW_TEXT' => $query],
            ];
            if ($companyIds !== []) {
                $or[] = ['PROPERTY_COMPANY' => $companyIds];
            }
            $filter[] = $or;
        }

        $cityMap = [];
        foreach ($cities as $city) {
            $cityMap[$city['id']] = $city;
        }

        $vacancies = [];
        $rows = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'DESC'],
            $filter,
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'PREVIEW_TEXT']
        );
        while ($element = $rows->GetNextElement()) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();
            $companyId = (int)($props['COMPANY']['VALUE'] ?? 0);
            $cityIds = $props['CITY']['VALUE'] ?? [];
            if (!is_array($cityIds)) {
                $cityIds = $cityIds ? [$cityIds] : [];
            }

            $vacancyCities = [];
            foreach ($cityIds as $id) {
                $id = (int)$id;
                if (isset($cityMap[$id])) {
                    $vacancyCities[] = $cityMap[$id];
                }
            }

            $vacancies[] = [
                'id' => (int)$fields['ID'],
                'name' => (string)$fields['NAME'],
                'code' => (string)$fields['CODE'],
                'preview' => trim((string)$fields['PREVIEW_TEXT']),
                'salaryFrom' => self::money($props['SALARY_FROM']['VALUE'] ?? null),
                'salaryTo' => self::money($props['SALARY_TO']['VALUE'] ?? null),
                'company' => $companies[$companyId] ?? null,
                'cities' => $vacancyCities,
            ];
        }

        return [
            'cities' => array_values($cities),
            'vacancies' => $vacancies,
        ];
    }

    private function iblockId(string $code): int
    {
        $row = \CIBlock::GetList([], [
            'TYPE' => 'workhub',
            'CODE' => $code,
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new RuntimeException('Инфоблоки WorkHub ещё не созданы. Выполните миграции.');
        }

        return (int)$row['ID'];
    }

    /** @return array<int, array{id: int, name: string, code: string}> */
    private function cities(int $iblockId): array
    {
        $cities = [];
        $rows = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'NAME', 'CODE']
        );
        while ($row = $rows->Fetch()) {
            $cities[] = [
                'id' => (int)$row['ID'],
                'name' => (string)$row['NAME'],
                'code' => (string)$row['CODE'],
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
