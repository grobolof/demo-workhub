<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\ResponseService;

final class ResponseController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly ResponseService $responses,
    ) {
        parent::__construct($request, $response);
    }

    public function index(): void
    {
        if ($this->request->isPost()) {
            $body = $this->request->requirePost();
            $this->response->send($this->responses->add((int)($body['vacancyId'] ?? 0)));
        }

        $this->response->send($this->responses->list($this->request->queryInt('page', 1)));
    }
}
