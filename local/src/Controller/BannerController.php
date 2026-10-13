<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\JobBoardService;
use Throwable;

final class BannerController extends AbstractController
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
        $stats = ['resumes' => 0, 'vacancies' => 0, 'companies' => 0];
        try {
            $stats = $this->jobBoard->stats();
        } catch (Throwable) {
        }

        $this->response->send([
            'banners' => $this->jobBoard->banners(),
            'stats' => $stats,
        ]);
    }
}
