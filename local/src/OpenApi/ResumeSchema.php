<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Resume',
    required: ['id', 'title', 'description', 'salary'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'salary', type: 'integer'),
        new OA\Property(property: 'author', type: 'string'),
    ],
)]

final class ResumeSchema
{
}
