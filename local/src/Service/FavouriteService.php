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

    /** @return array{vacancies: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function list(int $page = 1): array
    {
        $user = $this->auth->requireSeeker('Избранное доступно только соискателю');

        return $this->page((new Link(self::BLOCK))->vacancyIds($user['id']), $page);
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

    /**
     * @param list<int> $ids
     * @return array{vacancies: list<array<string, mixed>>, total: int, page: int, limit: int}
     */
    private function page(array $ids, int $page): array
    {
        $page = max(1, $page);
        $limit = JobBoardService::PAGE_SIZE;
        $slice = array_slice($ids, ($page - 1) * $limit, $limit);

        return [
            'vacancies' => $this->jobBoard->byIds($slice),
            'total' => count($ids),
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
