<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    required: ['id', 'email', 'role'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'role', type: 'string', enum: ['seeker', 'employer']),
        new OA\Property(property: 'firstName', type: 'string'),
        new OA\Property(property: 'lastName', type: 'string'),
        new OA\Property(property: 'middleName', type: 'string'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
        new OA\Property(property: 'company', ref: '#/components/schemas/CompanyProfile', nullable: true),
    ],
)]

final class UserSchema
{
}
