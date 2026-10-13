<?php

declare(strict_types=1);

namespace App\Iblock;

use Bitrix\Main\Loader;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CUtil;

class ElementGuard
{
    private const TYPES = ['system', 'seekers', 'employers'];

    /** @var array<int, array{code: string, type: string}> */
    private static array $iblocks = [];

    public static function onStart(array &$fields): bool
    {
        if (!Loader::includeModule('iblock')) {
            return true;
        }

        $iblockId = self::resolveIblockId($fields);
        $meta = self::iblockMeta($iblockId);
        if (!in_array($meta['type'], self::TYPES, true)) {
            return true;
        }

        if ($meta['code'] === 'cities' && array_key_exists('NAME', $fields)) {
            $fields['NAME'] = trim((string)$fields['NAME']);
        }

        if (in_array($meta['code'], ['companies', 'cities'], true)) {
            self::fillSymbolicCode($fields, $iblockId);
        }

        return true;
    }

    public static function onBeforeSave(array &$fields): bool
    {
        if (!Loader::includeModule('iblock')) {
            return true;
        }

        $iblockId = self::resolveIblockId($fields);
        $meta = self::iblockMeta($iblockId);
        if (!in_array($meta['type'], self::TYPES, true)) {
            return true;
        }

        if ($meta['code'] === 'cities' && !self::assertUniqueCityName($fields, $iblockId)) {
            return false;
        }

        if (in_array($meta['code'], ['vacancies', 'positions'], true) && !self::assertSalary($fields, $iblockId)) {
            return false;
        }

        return true;
    }

    private static function fillSymbolicCode(array &$fields, int $iblockId): void
    {
        $isNew = empty($fields['ID']);
        if (!$isNew && !array_key_exists('CODE', $fields)) {
            return;
        }

        $code = trim((string)($fields['CODE'] ?? ''));
        if ($code !== '') {
            return;
        }

        $name = trim((string)($fields['NAME'] ?? ''));
        if ($name === '' && !$isNew) {
            $current = CIBlockElement::GetByID((int)$fields['ID'])->Fetch();
            $name = trim((string)($current['NAME'] ?? ''));
        }
        if ($name === '') {
            return;
        }

        $code = CUtil::translit($name, 'ru', [
            'max_len' => 100,
            'change_case' => 'L',
            'replace_space' => '-',
            'replace_other' => '-',
            'delete_repeat_replace' => true,
        ]);
        $code = trim($code, '-');
        if ($code === '') {
            return;
        }

        $fields['CODE'] = self::uniqueCode($iblockId, $code, (int)($fields['ID'] ?? 0));
    }

    private static function uniqueCode(int $iblockId, string $code, int $elementId): string
    {
        $base = $code;
        $suffix = 2;
        while (self::codeTaken($iblockId, $code, $elementId)) {
            $code = $base . '-' . $suffix;
            $suffix++;
        }

        return $code;
    }

    private static function codeTaken(int $iblockId, string $code, int $elementId): bool
    {
        $filter = [
            'IBLOCK_ID' => $iblockId,
            '=CODE' => $code,
            'CHECK_PERMISSIONS' => 'N',
        ];
        if ($elementId > 0) {
            $filter['!ID'] = $elementId;
        }

        return (bool)CIBlockElement::GetList([], $filter, false, ['nTopCount' => 1], ['ID'])->Fetch();
    }

    private static function assertUniqueCityName(array $fields, int $iblockId): bool
    {
        if (!array_key_exists('NAME', $fields)) {
            return true;
        }

        $name = trim((string)$fields['NAME']);
        if ($name === '') {
            return true;
        }

        $elementId = (int)($fields['ID'] ?? 0);
        $needle = mb_strtolower($name);
        $rows = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'NAME']
        );
        while ($row = $rows->Fetch()) {
            if ((int)$row['ID'] === $elementId) {
                continue;
            }
            if (mb_strtolower(trim((string)$row['NAME'])) === $needle) {
                return self::fail('Город с таким названием уже существует');
            }
        }

        return true;
    }

    private static function assertSalary(array $fields, int $iblockId): bool
    {
        $elementId = (int)($fields['ID'] ?? 0);
        $from = self::submittedProperty($fields, $iblockId, 'SALARY_FROM');
        $to = self::submittedProperty($fields, $iblockId, 'SALARY_TO');

        if ($from === null) {
            $from = self::storedProperty($iblockId, $elementId, 'SALARY_FROM');
        }
        if ($to === null) {
            $to = self::storedProperty($iblockId, $elementId, 'SALARY_TO');
        }

        if ($from === '' && $to === '') {
            return self::fail('Зарплата (от) и Зарплата (до)');
        }

        return true;
    }

    private static function submittedProperty(array $fields, int $iblockId, string $code): ?string
    {
        if (!isset($fields['PROPERTY_VALUES']) || !is_array($fields['PROPERTY_VALUES'])) {
            return null;
        }

        $values = $fields['PROPERTY_VALUES'];
        if (array_key_exists($code, $values)) {
            return self::flatten($values[$code]);
        }

        $property = CIBlockProperty::GetList([], [
            'IBLOCK_ID' => $iblockId,
            'CODE' => $code,
        ])->Fetch();
        if (!$property) {
            return null;
        }

        $id = (string)$property['ID'];
        if (array_key_exists($id, $values)) {
            return self::flatten($values[$id]);
        }
        if (array_key_exists((int)$id, $values)) {
            return self::flatten($values[(int)$id]);
        }

        return null;
    }

    private static function storedProperty(int $iblockId, int $elementId, string $code): string
    {
        if ($elementId <= 0) {
            return '';
        }

        $rows = CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => $code]);
        while ($row = $rows->Fetch()) {
            $value = self::flatten($row['VALUE'] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function flatten(mixed $value): string
    {
        if (is_array($value)) {
            if (array_key_exists('VALUE', $value)) {
                return self::flatten($value['VALUE']);
            }
            foreach ($value as $item) {
                $flat = self::flatten($item);
                if ($flat !== '') {
                    return $flat;
                }
            }

            return '';
        }

        if ($value === null || $value === false) {
            return '';
        }

        return trim((string)$value);
    }

    private static function fail(string $message): bool
    {
        global $APPLICATION;
        $APPLICATION->ThrowException($message);

        return false;
    }

    private static function resolveIblockId(array $fields): int
    {
        $iblockId = (int)($fields['IBLOCK_ID'] ?? 0);
        if ($iblockId > 0 || empty($fields['ID'])) {
            return $iblockId;
        }

        $row = CIBlockElement::GetByID((int)$fields['ID'])->Fetch();

        return (int)($row['IBLOCK_ID'] ?? 0);
    }

    /** @return array{code: string, type: string} */
    private static function iblockMeta(int $iblockId): array
    {
        if ($iblockId <= 0) {
            return ['code' => '', 'type' => ''];
        }
        if (!isset(self::$iblocks[$iblockId])) {
            $iblock = CIBlock::GetByID($iblockId)->Fetch() ?: [];
            self::$iblocks[$iblockId] = [
                'code' => (string)($iblock['CODE'] ?? ''),
                'type' => (string)($iblock['IBLOCK_TYPE_ID'] ?? ''),
            ];
        }

        return self::$iblocks[$iblockId];
    }
}
