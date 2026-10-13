<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Service\FavouriteService;

final class FavouriteController extends AbstractController
{
    public function __construct(
        Request $request,
        JsonResponse $response,
        private readonly FavouriteService $favourites,
    ) {
        parent::__construct($request, $response);
    }

    public function index(): void
    {
        if ($this->request->isPost()) {
            $body = $this->request->requirePost();
            $this->response->send($this->favourites->toggle((int)($body['vacancyId'] ?? 0)));
        }

        $this->response->send(['vacancies' => $this->favourites->list()]);
    }
}
