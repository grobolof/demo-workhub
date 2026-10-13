<?php

declare(strict_types=1);

namespace App\Highload;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use RuntimeException;

class Link
{
    /** @var array<string, class-string> */
    private static array $classes = [];

    public function __construct(private string $name)
    {
    }

    /** @return list<int> */
    public function vacancyIds(int $userId): array
    {
        $ids = [];
        foreach ($this->rows($userId) as $row) {
            $ids[] = $row['vacancyId'];
        }

        return $ids;
    }

    public function find(int $userId, int $vacancyId): ?int
    {
        foreach ($this->rows($userId) as $row) {
            if ($row['vacancyId'] === $vacancyId) {
                return $row['id'];
            }
        }

        return null;
    }

    public function add(int $userId, int $vacancyId): void
    {
        if ($this->find($userId, $vacancyId) !== null) {
            return;
        }

        $class = $this->dataClass();
        $result = $class::add([
            'UF_USER' => $userId,
            'UF_VACANCY' => $vacancyId,
        ]);
        if ($result->isSuccess() || $this->find($userId, $vacancyId) !== null) {
            return;
        }

        throw new RuntimeException(implode(' ', $result->getErrorMessages()));
    }

    public function remove(int $userId, int $vacancyId): void
    {
        $id = $this->find($userId, $vacancyId);
        if ($id === null) {
            return;
        }

        $class = $this->dataClass();
        $result = $class::delete($id);
        if (!$result->isSuccess()) {
            throw new RuntimeException(implode(' ', $result->getErrorMessages()));
        }
    }

    /** @return list<array{id: int, vacancyId: int}> */
    private function rows(int $userId): array
    {
        $class = $this->dataClass();
        $rows = [];
        $result = $class::getList([
            'filter' => ['=UF_USER' => $userId],
            'select' => ['ID', 'UF_VACANCY'],
            'order' => ['ID' => 'DESC'],
        ]);
        while ($row = $result->fetch()) {
            $rows[] = [
                'id' => (int)$row['ID'],
                'vacancyId' => (int)$row['UF_VACANCY'],
            ];
        }

        return $rows;
    }

    /** @return class-string */
    private function dataClass(): string
    {
        if (isset(self::$classes[$this->name])) {
            return self::$classes[$this->name];
        }

        if (!Loader::includeModule('highloadblock')) {
            throw new RuntimeException('Модуль highload-блоков недоступен');
        }

        $block = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $this->name],
        ])->fetch();
        if (!$block) {
            throw new RuntimeException('Highload-блок ' . $this->name . ' не найден. Выполните миграции.');
        }

        self::$classes[$this->name] = HighloadBlockTable::compileEntity($block)->getDataClass();

        return self::$classes[$this->name];
    }
}
