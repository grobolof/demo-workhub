<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;
use Bitrix\Main\Type\DateTime;

class ResponseService
{
    public const BLOCK = 'Responses';

    public function __construct(
        private readonly AuthService $auth,
        private readonly JobBoardService $jobBoard,
    ) {
    }

    /** @return array{vacancies: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function list(int $page = 1): array
    {
        $user = $this->auth->requireSeeker('Отклики доступны только соискателю');
        $page = max(1, $page);
        $limit = JobBoardService::PAGE_SIZE;
        $ids = (new Link(self::BLOCK))->vacancyIds($user['id']);
        $slice = array_slice($ids, ($page - 1) * $limit, $limit);

        return [
            'vacancies' => $this->jobBoard->byIds($slice),
            'total' => count($ids),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /** @return array{vacancyId: int, responded: bool} */
    public function add(int $vacancyId): array
    {
        $user = $this->auth->requireSeeker('Откликнуться могут только соискатели');
        if ($this->jobBoard->vacancy($vacancyId) === null) {
            throw new ApiException('Вакансия не найдена', 404);
        }

        (new Link(self::BLOCK))->add($user['id'], $vacancyId, [
            'UF_CREATED' => new DateTime(),
        ]);

        return [
            'vacancyId' => $vacancyId,
            'responded' => true,
        ];
    }
}
