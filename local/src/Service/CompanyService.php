<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use CIBlock;
use CIBlockElement;
use CFile;

class CompanyService
{
    private int $iblock = 0;

    public function assertInnAvailable(string $inn): void
    {
        if ($this->findByInn($inn) !== null) {
            throw new ApiException('Компания с таким ИНН уже зарегистрирована', 409);
        }
    }

    /** @return array{id: int, name: string, inn: string, description: string, logo: string|null}|null */
    public function findByUser(int $userId): ?array
    {
        $company = $this->load($userId);
        if ($company === null) {
            return null;
        }

        unset($company['pictureId']);

        return $company;
    }

    public function update(int $userId, string $name, string $inn, string $description): void
    {
        $company = $this->load($userId);
        if ($company === null) {
            throw new ApiException('К аккаунту не привязана компания', 409);
        }

        $other = $this->findByInn($inn);
        if ($other !== null && $other !== $company['id']) {
            throw new ApiException('Компания с таким ИНН уже зарегистрирована', 409);
        }

        $element = new CIBlockElement();
        $saved = $element->Update($company['id'], [
            'NAME' => $name,
            'DETAIL_TEXT' => $description,
            'DETAIL_TEXT_TYPE' => 'text',
        ]);
        if (!$saved) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        CIBlockElement::SetPropertyValuesEx($company['id'], $this->iblockId(), [
            'INN' => $inn,
        ]);
    }

    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file */
    public function savePicture(int $userId, array $file): void
    {
        $company = $this->load($userId);
        if ($company === null) {
            throw new ApiException('К аккаунту не привязана компания', 409);
        }

        $file['MODULE_ID'] = 'iblock';
        $element = new CIBlockElement();
        if (!$element->Update($company['id'], ['DETAIL_PICTURE' => $file])) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }
    }

    public function removePicture(int $userId): void
    {
        $company = $this->load($userId);
        if ($company === null) {
            throw new ApiException('К аккаунту не привязана компания', 409);
        }
        if ($company['pictureId'] <= 0) {
            return;
        }

        $element = new CIBlockElement();
        if (!$element->Update($company['id'], [
            'DETAIL_PICTURE' => ['del' => 'Y'],
        ])) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }
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

    /** @return array{id: int, name: string, inn: string, description: string, logo: string|null, pictureId: int}|null */
    private function load(int $userId): ?array
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
            ['ID', 'IBLOCK_ID', 'NAME', 'DETAIL_TEXT', 'DETAIL_PICTURE']
        )->GetNextElement(false);
        if (!$row) {
            return null;
        }

        $fields = $row->GetFields();
        $props = $row->GetProperties();
        $pictureId = (int)($fields['DETAIL_PICTURE'] ?? 0);
        $logo = $pictureId > 0 ? (string)CFile::GetPath($pictureId) : '';

        return [
            'id' => (int)$fields['ID'],
            'name' => (string)$fields['NAME'],
            'inn' => trim((string)($props['INN']['VALUE'] ?? '')),
            'description' => trim((string)($fields['DETAIL_TEXT'] ?? '')),
            'logo' => $logo !== '' ? $logo : null,
            'pictureId' => $pictureId,
        ];
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
