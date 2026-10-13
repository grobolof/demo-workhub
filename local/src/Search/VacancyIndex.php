<?php

declare(strict_types=1);

namespace App\Search;

use CIBlock;
use CIBlockElement;
use RuntimeException;
use Throwable;

class VacancyIndex
{
    private const INDEX = 'workhub-vacancies';

    public static function onAfterSave(array $fields): void
    {
        if (array_key_exists('RESULT', $fields) && !$fields['RESULT']) {
            return;
        }

        $id = (int)($fields['ID'] ?? 0);
        if ($id <= 0 || !self::targets((int)($fields['IBLOCK_ID'] ?? 0))) {
            return;
        }

        self::safely(static fn () => self::sync($id));
    }

    public static function onAfterDelete(array $fields): void
    {
        $id = (int)($fields['ID'] ?? 0);
        if ($id <= 0 || !self::targets((int)($fields['IBLOCK_ID'] ?? 0))) {
            return;
        }

        self::safely(static fn () => self::remove($id));
    }

    public static function onAfterProperties(int|string $elementId, int|string $iblockId, mixed $values = null, mixed $flags = null): void
    {
        $id = (int)$elementId;
        if ($id <= 0 || !self::targets((int)$iblockId)) {
            return;
        }

        self::safely(static fn () => self::sync($id));
    }

    public static function rebuild(): void
    {
        $client = self::client();
        if ($client->exists('/' . self::INDEX)) {
            $client->request('DELETE', '/' . self::INDEX);
        }
        self::create($client);
        self::bulk(self::documents());
    }

    /** @return list<int> */
    public static function searchIds(string $query, ?int $cityId): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        self::ensureReady();

        $must = [
            'bool' => [
                'should' => [
                    [
                        'match' => [
                            'name' => [
                                'query' => $query,
                                'operator' => 'and',
                            ],
                        ],
                    ],
                    [
                        'match' => [
                            'name.prefix' => [
                                'query' => $query,
                                'operator' => 'and',
                            ],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];

        $bool = ['must' => [$must]];
        if ($cityId !== null && $cityId > 0) {
            $bool['filter'] = [
                ['term' => ['city_ids' => $cityId]],
            ];
        }

        $response = self::client()->request('POST', '/' . self::INDEX . '/_search', [
            'size' => 200,
            '_source' => false,
            'query' => ['bool' => $bool],
        ]);

        $ids = [];
        foreach ($response['body']['hits']['hits'] ?? [] as $hit) {
            $id = (int)($hit['_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function sync(int $elementId): void
    {
        if ($elementId <= 0) {
            return;
        }

        self::ensureIndex();
        $document = self::document($elementId);
        if ($document === null) {
            self::remove($elementId);
            return;
        }

        self::client()->request(
            'PUT',
            '/' . self::INDEX . '/_doc/' . $elementId . '?refresh=wait_for',
            $document
        );
    }

    public static function remove(int $elementId): void
    {
        if ($elementId <= 0) {
            return;
        }

        $client = self::client();
        if (!$client->exists('/' . self::INDEX)) {
            return;
        }

        $client->request('DELETE', '/' . self::INDEX . '/_doc/' . $elementId . '?refresh=wait_for', null, [404]);
    }

    private static function ensureReady(): void
    {
        self::ensureIndex();
        $count = self::client()->request('GET', '/' . self::INDEX . '/_count');
        if ((int)($count['body']['count'] ?? 0) > 0) {
            return;
        }

        $documents = self::documents();
        if ($documents !== []) {
            self::bulk($documents);
        }
    }

    private static function ensureIndex(): void
    {
        $client = self::client();
        if ($client->exists('/' . self::INDEX)) {
            return;
        }

        self::create($client);
    }

    private static function create(ElasticClient $client): void
    {
        $response = $client->request('PUT', '/' . self::INDEX, self::definition(), [400]);
        if ($response['status'] !== 400) {
            return;
        }

        $type = (string)($response['body']['error']['type'] ?? '');
        if ($type !== 'resource_already_exists_exception') {
            $reason = (string)($response['body']['error']['reason'] ?? 'не удалось создать индекс');
            throw new RuntimeException('Ошибка Elasticsearch: ' . $reason);
        }
    }

    /** @param list<array{id: int, name: string, city_ids: list<int>}> $documents */
    private static function bulk(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $lines = '';
        foreach ($documents as $document) {
            $lines .= json_encode(['index' => ['_id' => $document['id']]], JSON_THROW_ON_ERROR) . "\n";
            $lines .= json_encode([
                'name' => $document['name'],
                'city_ids' => $document['city_ids'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        }

        $response = self::client()->raw(
            'POST',
            '/' . self::INDEX . '/_bulk?refresh=wait_for',
            $lines,
            'application/x-ndjson'
        );
        if (!empty($response['body']['errors'])) {
            throw new RuntimeException('Ошибка Elasticsearch: не удалось проиндексировать вакансии');
        }
    }

    /** @return list<array{id: int, name: string, city_ids: list<int>}> */
    private static function documents(): array
    {
        $iblockId = self::iblockId();
        if ($iblockId <= 0) {
            return [];
        }

        $documents = [];
        $rows = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME']
        );
        while ($row = $rows->Fetch()) {
            $documents[] = [
                'id' => (int)$row['ID'],
                'name' => trim((string)$row['NAME']),
                'city_ids' => self::cityIds((int)$row['IBLOCK_ID'], (int)$row['ID']),
            ];
        }

        return $documents;
    }

    /** @return array{name: string, city_ids: list<int>}|null */
    private static function document(int $elementId): ?array
    {
        $iblockId = self::iblockId();
        if ($iblockId <= 0) {
            return null;
        }

        $row = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'ID' => $elementId,
                'ACTIVE' => 'Y',
                'CHECK_PERMISSIONS' => 'N',
            ],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME']
        )->Fetch();
        if (!$row) {
            return null;
        }

        return [
            'name' => trim((string)$row['NAME']),
            'city_ids' => self::cityIds((int)$row['IBLOCK_ID'], (int)$row['ID']),
        ];
    }

    /** @return list<int> */
    private static function cityIds(int $iblockId, int $elementId): array
    {
        $ids = [];
        $rows = CIBlockElement::GetProperty($iblockId, $elementId, ['sort' => 'asc'], ['CODE' => 'CITY']);
        while ($row = $rows->Fetch()) {
            $id = (int)($row['VALUE'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function targets(int $iblockId): bool
    {
        if ($iblockId <= 0) {
            return false;
        }

        $iblock = CIBlock::GetByID($iblockId)->Fetch();
        $code = (string)($iblock['CODE'] ?? '');

        return in_array($code, ['vacancies', 'positions'], true);
    }

    private static function iblockId(): int
    {
        foreach (['vacancies', 'positions'] as $code) {
            $row = CIBlock::GetList([], [
                'TYPE' => 'workhub',
                'CODE' => $code,
                'CHECK_PERMISSIONS' => 'N',
            ])->Fetch();
            if ($row) {
                return (int)$row['ID'];
            }
        }

        return 0;
    }

    private static function client(): ElasticClient
    {
        return ElasticClient::fromEnv();
    }

    private static function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            AddMessage2Log($exception->getMessage(), 'workhub');
        }
    }

    private static function definition(): array
    {
        return [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'analysis' => [
                    'filter' => [
                        'russian_edge' => [
                            'type' => 'edge_ngram',
                            'min_gram' => 2,
                            'max_gram' => 20,
                        ],
                    ],
                    'analyzer' => [
                        'russian_prefix' => [
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'russian_edge'],
                        ],
                        'russian_prefix_search' => [
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'properties' => [
                    'name' => [
                        'type' => 'text',
                        'analyzer' => 'russian',
                        'fields' => [
                            'prefix' => [
                                'type' => 'text',
                                'analyzer' => 'russian_prefix',
                                'search_analyzer' => 'russian_prefix_search',
                            ],
                        ],
                    ],
                    'city_ids' => ['type' => 'integer'],
                ],
            ],
        ];
    }
}
