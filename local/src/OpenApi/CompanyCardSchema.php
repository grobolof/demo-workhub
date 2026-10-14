<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CompanyCard',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
    ],
)]

final class CompanyCardSchema
{
}
