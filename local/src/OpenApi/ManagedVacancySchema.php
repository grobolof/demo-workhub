<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ManagedVacancy',
    required: ['id', 'title', 'salary'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'salary', type: 'integer'),
        new OA\Property(property: 'workplace', type: 'string', example: 'hybrid'),
        new OA\Property(property: 'workplaceLabel', type: 'string', example: 'Гибрид'),
        new OA\Property(property: 'schedule', type: 'string', example: 'floating'),
        new OA\Property(property: 'scheduleLabel', type: 'string', example: 'Плавающий'),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
        new OA\Property(property: 'responses', type: 'integer'),
        new OA\Property(property: 'applicants', type: 'array', items: new OA\Items(ref: '#/components/schemas/Applicant')),
    ],
)]

final class ManagedVacancySchema
{
}
