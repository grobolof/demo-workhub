<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\JobBoard;

try {
    $vacancy = JobBoard::vacancy(isset($_GET['id']) ? (int)$_GET['id'] : 0);
    if ($vacancy === null) {
        api_fail('Вакансия не найдена', 404);
    }

    api_send(['vacancy' => $vacancy]);
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
