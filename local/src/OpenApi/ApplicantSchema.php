<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Applicant',
    required: ['id', 'name', 'respondedAt'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
        new OA\Property(property: 'resumeId', type: 'integer'),
        new OA\Property(property: 'resumeTitle', type: 'string'),
        new OA\Property(property: 'respondedAt', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'ApplicantCard',
    required: ['id', 'firstName', 'lastName', 'email'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'firstName', type: 'string'),
        new OA\Property(property: 'lastName', type: 'string'),
        new OA\Property(property: 'middleName', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'phone', type: 'string'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
        new OA\Property(property: 'resume', ref: '#/components/schemas/Resume', nullable: true),
    ],
)]

final class ApplicantSchema
{
}
