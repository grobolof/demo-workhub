<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\ResumeService;

final class ResumeController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly ResumeService $resumes,
    ) {
        parent::__construct($request, $response);
    }

    public function index(): void
    {
        if ($this->request->isPost()) {
            $body = $this->request->requirePost();
            $action = (string)($body['action'] ?? 'create');
            if ($action === 'update') {
                $this->response->send($this->resumes->update(
                    (int)($body['id'] ?? 0),
                    (string)($body['title'] ?? ''),
                    $body['salary'] ?? '',
                    (string)($body['description'] ?? ''),
                ));
            }
            if ($action === 'delete') {
                $this->response->send($this->resumes->delete((int)($body['id'] ?? 0)));
            }

            $this->response->send($this->resumes->create(
                (string)($body['title'] ?? ''),
                $body['salary'] ?? '',
                (string)($body['description'] ?? ''),
            ));
        }

        $id = $this->request->queryInt('id');
        if ($id > 0) {
            $this->response->send($this->resumes->one($id));
        }

        $this->response->send($this->resumes->list());
    }

    public function search(): void
    {
        $this->response->send($this->resumes->browse($this->request->query('q')));
    }
}
