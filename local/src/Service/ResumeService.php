<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use CIBlock;
use CIBlockElement;

class ResumeService
{
    public const LIMIT = 10;

    private int $iblock = 0;

    public function __construct(private readonly AuthService $auth)
    {
    }

    /** @return array{resumes: list<array<string, mixed>>, limit: int} */
    public function list(): array
    {
        $user = $this->auth->requireSeeker('Резюме доступны только соискателю');

        return [
            'resumes' => $this->collect($user['id']),
            'limit' => self::LIMIT,
        ];
    }

    /** @return array{resume: array<string, mixed>} */
    public function one(int $id): array
    {
        $user = $this->auth->requireSeeker('Резюме доступны только соискателю');

        return ['resume' => $this->owned($user['id'], $id)];
    }

    /** @return array{resume: array<string, mixed>} */
    public function create(string $title, mixed $salary, string $description): array
    {
        $user = $this->auth->requireSeeker('Добавлять резюме могут только соискатели');
        $values = $this->values($title, $salary, $description);
        if (count($this->collect($user['id'])) >= self::LIMIT) {
            throw new ApiException('Можно добавить не больше 10 резюме', 409);
        }

        $element = new CIBlockElement();
        $id = $element->Add([
            'IBLOCK_ID' => $this->iblockId(),
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

        return ['resume' => $this->owned($user['id'], (int)$id)];
    }

    /** @return array{resume: array<string, mixed>} */
    public function update(int $id, string $title, mixed $salary, string $description): array
    {
        $user = $this->auth->requireSeeker('Изменять резюме могут только соискатели');
        $this->owned($user['id'], $id);
        $values = $this->values($title, $salary, $description);

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
            'SALARY' => $values['salary'],
        ]);

        return ['resume' => $this->owned($user['id'], $id)];
    }

    public function delete(int $id): array
    {
        $user = $this->auth->requireSeeker('Удалять резюме могут только соискатели');
        $this->owned($user['id'], $id);
        if (!CIBlockElement::Delete($id)) {
            throw new ApiException('Не удалось удалить резюме');
        }

        return ['deleted' => $id];
    }

    /** @return array{resumes: list<array<string, mixed>>} */
    public function browse(string $query): array
    {
        $filter = [
            'IBLOCK_ID' => $this->iblockId(),
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'N',
        ];
        $query = trim($query);
        if ($query !== '') {
            $filter['%NAME'] = $query;
        }

        $items = [];
        $rows = CIBlockElement::GetList(
            ['ID' => 'DESC'],
            $filter,
            false,
            ['nTopCount' => 100],
            ['ID', 'NAME', 'DETAIL_TEXT']
        );
        while ($element = $rows->GetNextElement(false)) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();
            $description = trim((string)($fields['DETAIL_TEXT'] ?? ''));
            if (mb_strlen($description) > 220) {
                $description = rtrim(mb_substr($description, 0, 220)) . '…';
            }
            $items[] = [
                'id' => (int)$fields['ID'],
                'title' => (string)$fields['NAME'],
                'description' => $description,
                'salary' => (int)($props['SALARY']['VALUE'] ?? 0),
            ];
        }

        return ['resumes' => $items];
    }

    /** @return array{title: string, description: string, salary: int} */
    private function values(string $title, mixed $salary, string $description): array
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
    private function owned(int $userId, int $id): array
    {
        if ($id <= 0) {
            throw new ApiException('Резюме не найдено', 404);
        }

        $items = $this->collect($userId, $id);
        if ($items === []) {
            throw new ApiException('Резюме не найдено', 404);
        }

        return $items[0];
    }

    /** @return list<array<string, mixed>> */
    private function collect(int $userId, int $id = 0): array
    {
        $filter = [
            'IBLOCK_ID' => $this->iblockId(),
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

    private function iblockId(): int
    {
        if ($this->iblock > 0) {
            return $this->iblock;
        }

        $row = CIBlock::GetList([], [
            'TYPE' => 'workhub',
            'CODE' => 'resumes',
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new ApiException('Инфоблок «Резюме» ещё не создан. Выполните миграции.', 500);
        }

        $this->iblock = (int)$row['ID'];

        return $this->iblock;
    }
}
