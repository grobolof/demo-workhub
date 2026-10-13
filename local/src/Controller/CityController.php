<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\JobBoardService;

final class CityController extends AbstractController
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
        $this->response->send(['cities' => $this->jobBoard->cities()]);
    }
}
