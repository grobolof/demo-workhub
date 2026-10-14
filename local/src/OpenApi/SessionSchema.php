<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Session',
    required: ['sessid', 'favouriteIds', 'responseIds', 'resumeFavouriteIds'],
    properties: [
        new OA\Property(property: 'sessid', type: 'string'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'favouriteIds', type: 'array', items: new OA\Items(type: 'integer')),
        new OA\Property(property: 'responseIds', type: 'array', items: new OA\Items(type: 'integer')),
        new OA\Property(property: 'resumeFavouriteIds', type: 'array', items: new OA\Items(type: 'integer')),
        new OA\Property(property: 'adminUrl', type: 'string', description: 'Абсолютная ссылка входа в админ-панель'),
    ],
)]

final class SessionSchema
{
}
