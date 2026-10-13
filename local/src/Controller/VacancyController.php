<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\JobBoardService;

final class VacancyController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly JobBoardService $jobBoard,
    ) {
        parent::__construct($request, $response);
    }

    public function search(): void
    {
        $this->response->send($this->jobBoard->search(
            $this->request->query('q'),
            $this->request->query('city'),
            $this->request->queryInt('page', 1),
        ));
    }

    public function show(): void
    {
        $vacancy = $this->jobBoard->vacancy($this->request->queryInt('id'));
        if ($vacancy === null) {
            $this->response->fail('Вакансия не найдена', 404);
        }

        $this->response->send(['vacancy' => $vacancy]);
    }
}
