<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\Favourites;

api_run(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $body = api_require_post();
        api_send(Favourites::toggle((int)($body['vacancyId'] ?? 0)));
    }

    api_send(['vacancies' => Favourites::list()]);
});
