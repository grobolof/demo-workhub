<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiError',
    required: ['error'],
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Вакансия не найдена'),
    ],
)]

final class ApiErrorSchema
{
}
