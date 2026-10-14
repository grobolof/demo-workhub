<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'City',
    required: ['id', 'name', 'code'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'Москва'),
        new OA\Property(property: 'code', type: 'string', example: 'moskva'),
        new OA\Property(property: 'sort', type: 'integer', example: 100),
    ],
)]

final class CitySchema
{
}
