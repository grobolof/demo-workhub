<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\RequestService;

final class RequestController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly RequestService $requests,
    ) {
        parent::__construct($request, $response);
    }

    public function create(): void
    {
        $this->request->requireForm();
        $this->response->send($this->requests->create(
            $this->request->post('email'),
            $this->request->post('subject'),
            $this->request->post('message'),
            $this->request->files('files'),
        ));
    }
}
