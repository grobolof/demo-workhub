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

    /** @param array<string, mixed> $extra */
    public function add(int $userId, int $targetId, array $extra = []): void
    {
        if ($this->find($userId, $targetId) !== null) {
            return;
        }

        $class = $this->dataClass();
        $result = $class::add([
            'UF_USER' => $userId,
            $this->field => $targetId,
            ...$extra,
        ]);
        if ($result->isSuccess() || $this->find($userId, $targetId) !== null) {
            return;
        }

        throw new RuntimeException(implode(' ', $result->getErrorMessages()));
    }

    /** @param list<int> $targetIds
     *  @return array<int, int>
     */
    public function counts(array $targetIds): array
    {
        $targetIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int)$id, $targetIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($targetIds === []) {
            return [];
        }

        $counts = array_fill_keys($targetIds, 0);
        $class = $this->dataClass();
        $result = $class::getList([
            'filter' => ['=' . $this->field => $targetIds],
            'select' => ['ID', $this->field],
        ]);
        while ($row = $result->fetch()) {
            $id = (int)$row[$this->field];
            if (isset($counts[$id])) {
                $counts[$id]++;
            }
        }

        return $counts;
    }

    /** @return list<int> */
    public function userIds(int $targetId): array
    {
        if ($targetId <= 0) {
            return [];
        }

        $class = $this->dataClass();
        $ids = [];
        $result = $class::getList([
            'filter' => ['=' . $this->field => $targetId],
            'select' => ['ID', 'UF_USER'],
            'order' => ['ID' => 'DESC'],
        ]);
        while ($row = $result->fetch()) {
            $ids[] = (int)$row['UF_USER'];
        }

        return $ids;
    }

    /** @return list<array{userId: int, respondedAt: string}> */
    public function responders(int $targetId): array
    {
        if ($targetId <= 0) {
            return [];
        }

        $class = $this->dataClass();
        $people = [];
        $result = $class::getList([
            'filter' => ['=' . $this->field => $targetId],
            'select' => ['ID', 'UF_USER', 'UF_CREATED'],
            'order' => ['UF_CREATED' => 'DESC', 'ID' => 'DESC'],
        ]);
        while ($row = $result->fetch()) {
            $people[] = [
                'id' => (int)$row['ID'],
                'userId' => (int)$row['UF_USER'],
                'respondedAt' => self::moment($row['UF_CREATED'] ?? null),
                'stamp' => self::stamp($row['UF_CREATED'] ?? null),
            ];
        }

        usort($people, static function (array $left, array $right): int {
            $byTime = $right['stamp'] <=> $left['stamp'];
            if ($byTime !== 0) {
                return $byTime;
            }

            return $right['id'] <=> $left['id'];
        });

        foreach ($people as $index => $person) {
            unset($people[$index]['id'], $people[$index]['stamp']);
        }

        return $people;
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

    private static function stamp(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface || (is_object($value) && method_exists($value, 'getTimestamp'))) {
            return (int)$value->getTimestamp();
        }
        if (!is_string($value) || trim($value) === '') {
            return 0;
        }

        $value = trim($value);
        foreach (['d.m.Y H:i:s', 'd.m.Y H:i', \DateTimeInterface::ATOM, 'Y-m-d H:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed->getTimestamp();
            }
        }

        $unix = strtotime($value);

        return $unix === false ? 0 : $unix;
    }

    private static function moment(mixed $value): string
    {
        if ($value instanceof \Bitrix\Main\Type\DateTime) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return '';
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
