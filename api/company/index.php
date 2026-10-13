<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\JobBoard;

try {
    $company = JobBoard::company(isset($_GET['id']) ? (int)$_GET['id'] : 0);
    if ($company === null) {
        api_fail('Компания не найдена', 404);
    }

    api_send(['company' => $company]);
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
