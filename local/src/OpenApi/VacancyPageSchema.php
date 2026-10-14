<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VacancyPage',
    required: ['vacancies', 'total', 'page', 'limit'],
    properties: [
        new OA\Property(property: 'vacancies', type: 'array', items: new OA\Items(ref: '#/components/schemas/VacancyCard')),
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'page', type: 'integer'),
        new OA\Property(property: 'limit', type: 'integer'),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
    ],
)]

final class VacancyPageSchema
{
}
