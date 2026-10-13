<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\AuthService;

final class AuthController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly AuthService $auth,
    ) {
        parent::__construct($request, $response);
    }

    public function session(): void
    {
        $this->response->send($this->auth->payload());
    }

    public function login(): void
    {
        $body = $this->request->requirePost();
        $this->response->send($this->auth->login(
            (string)($body['email'] ?? ''),
            (string)($body['password'] ?? ''),
            (string)($body['role'] ?? ''),
        ));
    }

    public function register(): void
    {
        $body = $this->request->requirePost();
        $this->response->send($this->auth->register(
            (string)($body['email'] ?? ''),
            (string)($body['password'] ?? ''),
            (string)($body['passwordConfirm'] ?? ''),
            (string)($body['role'] ?? ''),
            (string)($body['firstName'] ?? ''),
            (string)($body['lastName'] ?? ''),
            (string)($body['middleName'] ?? ''),
            (string)($body['companyName'] ?? ''),
            (string)($body['inn'] ?? ''),
        ));
    }

    public function logout(): void
    {
        $this->request->requirePost();
        $this->response->send($this->auth->logout());
    }
}
