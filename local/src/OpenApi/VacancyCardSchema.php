<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VacancyCard',
    required: ['id', 'name', 'salaryFrom', 'salaryTo', 'cities'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'preview', type: 'string'),
        new OA\Property(property: 'detail', type: 'string'),
        new OA\Property(property: 'salaryFrom', type: 'integer', nullable: true),
        new OA\Property(property: 'salaryTo', type: 'integer', nullable: true),
        new OA\Property(property: 'workplace', type: 'string', example: 'Гибрид'),
        new OA\Property(property: 'schedule', type: 'string', example: 'Плавающий'),
        new OA\Property(property: 'company', ref: '#/components/schemas/CompanyCard', nullable: true),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
    ],
)]

final class VacancyCardSchema
{
}
