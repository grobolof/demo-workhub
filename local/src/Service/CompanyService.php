<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use CIBlock;
use CIBlockElement;

class CompanyService
{
    private int $iblock = 0;

    public function assertInnAvailable(string $inn): void
    {
        if ($this->findByInn($inn) !== null) {
            throw new ApiException('Компания с таким ИНН уже зарегистрирована', 409);
        }
    }

    /** @return array{id: int, name: string, inn: string}|null */
    public function findByUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $row = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => $this->iblockId(),
                'PROPERTY_USER' => $userId,
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            ['nTopCount' => 1],
            ['ID', 'IBLOCK_ID', 'NAME']
        )->GetNextElement(false);
        if (!$row) {
            return null;
        }

        $fields = $row->GetFields();
        $props = $row->GetProperties();

        return [
            'id' => (int)$fields['ID'],
            'name' => (string)$fields['NAME'],
            'inn' => trim((string)($props['INN']['VALUE'] ?? '')),
        ];
    }

    public function create(int $userId, string $name, string $inn): int
    {
        $this->assertInnAvailable($inn);

        $element = new CIBlockElement();
        $id = $element->Add([
            'IBLOCK_ID' => $this->iblockId(),
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => [
                'INN' => $inn,
                'USER' => $userId,
            ],
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        return (int)$id;
    }

    private function findByInn(string $inn): ?int
    {
        $row = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockId(),
                'PROPERTY_INN' => $inn,
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        return $row ? (int)$row['ID'] : null;
    }

    private function iblockId(): int
    {
        if ($this->iblock > 0) {
            return $this->iblock;
        }

        $row = CIBlock::GetList([], [
            'TYPE' => 'employers',
            'CODE' => 'companies',
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new ApiException('Инфоблок «Компании» ещё не создан. Выполните миграции.', 500);
        }

        $this->iblock = (int)$row['ID'];

        return $this->iblock;
    }
}
