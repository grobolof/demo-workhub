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

    public function profile(): void
    {
        $type = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($type, 'multipart/form-data') !== false) {
            $this->request->requireForm();
            $files = $this->request->files('avatar');
            if ($files !== []) {
                $this->response->send($this->auth->saveAvatar($files[0]));
            }
            $logo = $this->request->files('logo');
            if ($logo !== []) {
                $this->response->send($this->auth->saveCompanyLogo($logo[0]));
            }
            if ($this->request->post('remove') === '1') {
                if ($this->request->post('target') === 'logo') {
                    $this->response->send($this->auth->removeCompanyLogo());
                }
                $this->response->send($this->auth->removeAvatar());
            }

            throw new \App\Exception\ApiException('Выберите изображение');
        }

        $body = $this->request->requirePost();
        if (($body['scope'] ?? '') === 'company') {
            $this->response->send($this->auth->updateCompany(
                (string)($body['name'] ?? ''),
                (string)($body['inn'] ?? ''),
                (string)($body['description'] ?? ''),
            ));
        }
        $this->response->send($this->auth->updateNames(
            (string)($body['lastName'] ?? ''),
            (string)($body['firstName'] ?? ''),
            (string)($body['middleName'] ?? ''),
        ));
    }
}
