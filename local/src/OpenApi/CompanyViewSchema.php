<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CompanyCityGroup',
    required: ['id', 'name', 'code', 'vacancies'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'Москва'),
        new OA\Property(property: 'code', type: 'string', example: 'moskva'),
        new OA\Property(property: 'vacancies', type: 'array', items: new OA\Items(ref: '#/components/schemas/VacancyCard')),
    ],
)]
#[OA\Schema(
    schema: 'CompanyView',
    required: ['id', 'name', 'detail', 'cities'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
        new OA\Property(property: 'detail', type: 'string'),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/CompanyCityGroup')),
    ],
)]

final class CompanyViewSchema
{
}
