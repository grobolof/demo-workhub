<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CompanyProfile',
    required: ['id', 'name', 'inn', 'description'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'inn', type: 'string', example: '7707083893'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
    ],
)]

final class CompanyProfileSchema
{
}
