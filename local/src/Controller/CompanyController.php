<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\JobBoardService;

final class CompanyController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly JobBoardService $jobBoard,
    ) {
        parent::__construct($request, $response);
    }

    public function index(): void
    {
        $this->response->send(['companies' => $this->jobBoard->companyCatalog()]);
    }

    public function show(): void
    {
        $company = $this->jobBoard->company($this->request->queryInt('id'));
        if ($company === null) {
            $this->response->fail('Компания не найдена', 404);
        }

        $this->response->send(['company' => $company]);
    }
}
