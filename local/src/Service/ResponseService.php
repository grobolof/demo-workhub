<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;

class ResponseService
{
    public const BLOCK = 'Responses';

    public function __construct(
        private readonly AuthService $auth,
        private readonly JobBoardService $jobBoard,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $user = $this->auth->requireSeeker('Отклики доступны только соискателю');

        return $this->jobBoard->byIds((new Link(self::BLOCK))->vacancyIds($user['id']));
    }

    /** @return array{vacancyId: int, responded: bool} */
    public function add(int $vacancyId): array
    {
        $user = $this->auth->requireSeeker('Откликнуться могут только соискатели');
        if ($this->jobBoard->vacancy($vacancyId) === null) {
            throw new ApiException('Вакансия не найдена', 404);
        }

        (new Link(self::BLOCK))->add($user['id'], $vacancyId);

        return [
            'vacancyId' => $vacancyId,
            'responded' => true,
        ];
    }
}
