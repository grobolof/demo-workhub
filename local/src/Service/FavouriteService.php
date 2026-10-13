<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;

class FavouriteService
{
    public const BLOCK = 'Favourites';

    public function __construct(
        private readonly AuthService $auth,
        private readonly JobBoardService $jobBoard,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $user = $this->auth->requireSeeker('Избранное доступно только соискателю');

        return $this->jobBoard->byIds((new Link(self::BLOCK))->vacancyIds($user['id']));
    }

    /** @return array{vacancyId: int, favourite: bool} */
    public function toggle(int $vacancyId): array
    {
        $user = $this->auth->requireSeeker('Добавлять в избранное могут только соискатели');
        if ($this->jobBoard->vacancy($vacancyId) === null) {
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
