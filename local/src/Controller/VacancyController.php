<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\JobBoardService;
use App\Service\VacancyManageService;

final class VacancyController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly JobBoardService $jobBoard,
        private readonly VacancyManageService $managed,
    ) {
        parent::__construct($request, $response);
    }

    public function search(): void
    {
        $this->response->send($this->jobBoard->search(
            $this->request->query('q'),
            $this->request->query('city'),
            $this->request->queryInt('page', 1),
            $this->request->query('sort'),
        ));
    }

    public function suggest(): void
    {
        $this->response->send($this->jobBoard->suggest(
            $this->request->query('q'),
            $this->request->query('city'),
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

    public function account(): void
    {
        if ($this->request->isPost()) {
            $body = $this->request->requirePost();
            $action = (string)($body['action'] ?? 'create');
            if ($action === 'update') {
                $this->response->send($this->managed->update(
                    (int)($body['id'] ?? 0),
                    (string)($body['title'] ?? ''),
                    (string)($body['description'] ?? ''),
                    $body['salary'] ?? '',
                    (string)($body['workplace'] ?? ''),
                    (string)($body['schedule'] ?? ''),
                ));
            }
            if ($action === 'delete') {
                $this->response->send($this->managed->delete((int)($body['id'] ?? 0)));
            }

            $this->response->send($this->managed->create(
                (string)($body['title'] ?? ''),
                (string)($body['description'] ?? ''),
                $body['salary'] ?? '',
                (string)($body['workplace'] ?? ''),
                (string)($body['schedule'] ?? ''),
            ));
        }

        $applicant = $this->request->queryInt('applicant');
        if ($applicant > 0) {
            $this->response->send($this->managed->applicant(
                $this->request->queryInt('vacancy'),
                $applicant,
            ));
        }

        $id = $this->request->queryInt('id');
        if ($id > 0) {
            $this->response->send($this->managed->one($id));
        }

        $this->response->send($this->managed->list());
    }
}
