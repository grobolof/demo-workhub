<?php

declare(strict_types=1);

namespace App\Api;

use CIBlock;
use CIBlockElement;

class Resumes
{
    public const LIMIT = 10;

    /** @return array{resumes: list<array<string, mixed>>, limit: int} */
    public static function list(): array
    {
        $user = Auth::requireSeeker('Резюме доступны только соискателю');

        return [
            'resumes' => self::collect($user['id']),
            'limit' => self::LIMIT,
        ];
    }

    /** @return array{resume: array<string, mixed>} */
    public static function one(int $id): array
    {
        $user = Auth::requireSeeker('Резюме доступны только соискателю');

        return ['resume' => self::owned($user['id'], $id)];
    }

    /** @return array{resume: array<string, mixed>} */
    public static function create(string $title, mixed $salary, string $description): array
    {
        $user = Auth::requireSeeker('Добавлять резюме могут только соискатели');
        $values = self::values($title, $salary, $description);
        if (count(self::collect($user['id'])) >= self::LIMIT) {
            throw new ApiException('Можно добавить не больше 10 резюме', 409);
        }

        $element = new CIBlockElement();
        $id = $element->Add([
            'IBLOCK_ID' => self::iblockId(),
            'NAME' => $values['title'],
            'ACTIVE' => 'Y',
            'DETAIL_TEXT' => $values['description'],
            'DETAIL_TEXT_TYPE' => 'text',
            'PROPERTY_VALUES' => [
                'SALARY' => $values['salary'],
                'USER' => $user['id'],
            ],
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        return ['resume' => self::owned($user['id'], (int)$id)];
    }

    /** @return array{resume: array<string, mixed>} */
    public static function update(int $id, string $title, mixed $salary, string $description): array
    {
        $user = Auth::requireSeeker('Изменять резюме могут только соискатели');
        self::owned($user['id'], $id);
        $values = self::values($title, $salary, $description);

        $element = new CIBlockElement();
        $saved = $element->Update($id, [
            'NAME' => $values['title'],
            'DETAIL_TEXT' => $values['description'],
            'DETAIL_TEXT_TYPE' => 'text',
        ]);
        if (!$saved) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        CIBlockElement::SetPropertyValuesEx($id, self::iblockId(), [
            'SALARY' => $values['salary'],
        ]);

        return ['resume' => self::owned($user['id'], $id)];
    }

    public static function delete(int $id): array
    {
        $user = Auth::requireSeeker('Удалять резюме могут только соискатели');
        self::owned($user['id'], $id);
        if (!CIBlockElement::Delete($id)) {
            throw new ApiException('Не удалось удалить резюме');
        }

        return ['deleted' => $id];
    }

    /** @return array{title: string, description: string, salary: int} */
    private static function values(string $title, mixed $salary, string $description): array
    {
        $title = trim($title);
        $description = trim($description);
        if ($title === '') {
            throw new ApiException('Укажите название резюме');
        }
        if (mb_strlen($title) > 255) {
            throw new ApiException('Название резюме не должно быть длиннее 255 символов');
        }
        if ($description === '') {
            throw new ApiException('Укажите описание резюме');
        }
        if (mb_strlen($description) > 10000) {
            throw new ApiException('Описание резюме слишком длинное');
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
        ];
    }

    /** @return array<string, mixed> */
    private static function owned(int $userId, int $id): array
    {
        if ($id <= 0) {
            throw new ApiException('Резюме не найдено', 404);
        }

        $items = self::collect($userId, $id);
        if ($items === []) {
            throw new ApiException('Резюме не найдено', 404);
        }

        return $items[0];
    }

    /** @return list<array<string, mixed>> */
    private static function collect(int $userId, int $id = 0): array
    {
        $filter = [
            'IBLOCK_ID' => self::iblockId(),
            'PROPERTY_USER' => $userId,
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
            $items[] = [
                'id' => (int)$fields['ID'],
                'title' => (string)$fields['NAME'],
                'description' => trim((string)($fields['DETAIL_TEXT'] ?? '')),
                'salary' => (int)($props['SALARY']['VALUE'] ?? 0),
            ];
        }

        return $items;
    }

    private static function iblockId(): int
    {
        static $id = 0;
        if ($id > 0) {
            return $id;
        }

        $row = CIBlock::GetList([], [
            'TYPE' => 'workhub',
            'CODE' => 'resumes',
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new ApiException('Инфоблок «Резюме» ещё не создан. Выполните миграции.', 500);
        }

        $id = (int)$row['ID'];

        return $id;
    }
}
