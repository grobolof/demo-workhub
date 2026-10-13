<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use App\Highload\Link;

class ResumeFavouriteService
{
    public const BLOCK = 'ResumeFavourites';

    public function __construct(
        private readonly AuthService $auth,
        private readonly ResumeService $resumes,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $user = $this->auth->requireEmployer('Избранные резюме доступны только работодателю');

        return $this->resumes->publicByIds((new Link(self::BLOCK, 'UF_RESUME'))->ids($user['id']));
    }

    /** @return array{resumeId: int, favourite: bool} */
    public function toggle(int $resumeId): array
    {
        $user = $this->auth->requireEmployer('Добавлять резюме в избранное могут только работодатели');
        if ($this->resumes->findPublic($resumeId) === null) {
            throw new ApiException('Резюме не найдено', 404);
        }

        $link = new Link(self::BLOCK, 'UF_RESUME');
        $exists = $link->find($user['id'], $resumeId) !== null;
        if ($exists) {
            $link->remove($user['id'], $resumeId);
        } else {
            $link->add($user['id'], $resumeId);
        }

        return [
            'resumeId' => $resumeId,
            'favourite' => !$exists,
        ];
    }
}
