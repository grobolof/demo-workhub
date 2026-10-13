<?php

declare(strict_types=1);

namespace App\Api;

use App\Highload\Link;

class Favourites
{
    private const BLOCK = 'Favourites';

    /** @return list<int> */
    public static function ids(int $userId): array
    {
        return (new Link(self::BLOCK))->vacancyIds($userId);
    }

    /** @return list<array<string, mixed>> */
    public static function list(): array
    {
        $user = Auth::requireSeeker('Избранное доступно только соискателю');

        return JobBoard::byIds(self::ids($user['id']));
    }

    /** @return array{vacancyId: int, favourite: bool} */
    public static function toggle(int $vacancyId): array
    {
        $user = Auth::requireSeeker('Добавлять в избранное могут только соискатели');
        if (JobBoard::vacancy($vacancyId) === null) {
            throw new ApiException('Вакансия не найдена', 404);
        }

        $link = new Link(self::BLOCK);
        $exists = $link->find($user['id'], $vacancyId) !== null;
        if ($exists) {
            $link->remove($user['id'], $vacancyId);
        } else {
            $link->add($user['id'], $vacancyId);
        }

        return [
            'vacancyId' => $vacancyId,
            'favourite' => !$exists,
        ];
    }
}
