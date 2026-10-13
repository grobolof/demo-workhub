<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\ResumeFavouriteService;

final class ResumeFavouriteController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly ResumeFavouriteService $favourites,
    ) {
        parent::__construct($request, $response);
    }

    public function index(): void
    {
        if ($this->request->isPost()) {
            $body = $this->request->requirePost();
            $this->response->send($this->favourites->toggle((int)($body['resumeId'] ?? 0)));
        }

        $this->response->send(['resumes' => $this->favourites->list()]);
    }
}
