<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;

abstract class AbstractController
{
    public function __construct(
        protected readonly Request $request,
        protected readonly JsonResponse $response,
    ) {
    }
}
