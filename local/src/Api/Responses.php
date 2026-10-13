<?php

declare(strict_types=1);

namespace App\Api;

use App\Highload\Link;

class Responses
{
    private const BLOCK = 'Responses';

    /** @return list<int> */
    public static function ids(int $userId): array
    {
        return (new Link(self::BLOCK))->vacancyIds($userId);
    }

    /** @return list<array<string, mixed>> */
    public static function list(): array
    {
        $user = Auth::requireSeeker('Отклики доступны только соискателю');

        return JobBoard::byIds(self::ids($user['id']));
    }

    /** @return array{vacancyId: int, responded: bool} */
    public static function add(int $vacancyId): array
    {
        $user = Auth::requireSeeker('Откликнуться могут только соискатели');
        if (JobBoard::vacancy($vacancyId) === null) {
            throw new ApiException('Вакансия не найдена', 404);
        }

        (new Link(self::BLOCK))->add($user['id'], $vacancyId);

        return [
            'vacancyId' => $vacancyId,
            'responded' => true,
        ];
    }
}
