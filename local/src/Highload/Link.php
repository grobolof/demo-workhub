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

    public function __construct(
        private string $name,
        private string $field = 'UF_VACANCY',
    ) {
    }

    /** @return list<int> */
    public function vacancyIds(int $userId): array
    {
        return $this->ids($userId);
    }

    /** @return list<int> */
    public function ids(int $userId): array
    {
        $ids = [];
        foreach ($this->rows($userId) as $row) {
            $ids[] = $row['targetId'];
        }

        return $ids;
    }

    public function find(int $userId, int $targetId): ?int
    {
        foreach ($this->rows($userId) as $row) {
            if ($row['targetId'] === $targetId) {
                return $row['id'];
            }
        }

        return null;
    }

    public function add(int $userId, int $targetId): void
    {
        if ($this->find($userId, $targetId) !== null) {
            return;
        }

        $class = $this->dataClass();
        $result = $class::add([
            'UF_USER' => $userId,
            $this->field => $targetId,
        ]);
        if ($result->isSuccess() || $this->find($userId, $targetId) !== null) {
            return;
        }

        throw new RuntimeException(implode(' ', $result->getErrorMessages()));
    }

    public function remove(int $userId, int $targetId): void
    {
        $id = $this->find($userId, $targetId);
        if ($id === null) {
            return;
        }

        $class = $this->dataClass();
        $result = $class::delete($id);
        if (!$result->isSuccess()) {
            throw new RuntimeException(implode(' ', $result->getErrorMessages()));
        }
    }

    /** @return list<array{id: int, targetId: int}> */
    private function rows(int $userId): array
    {
        $class = $this->dataClass();
        $rows = [];
        $result = $class::getList([
            'filter' => ['=UF_USER' => $userId],
            'select' => ['ID', $this->field],
            'order' => ['ID' => 'DESC'],
        ]);
        while ($row = $result->fetch()) {
            $rows[] = [
                'id' => (int)$row['ID'],
                'targetId' => (int)$row[$this->field],
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
