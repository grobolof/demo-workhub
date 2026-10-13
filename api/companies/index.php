<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\JobBoard;

try {
    api_send(['companies' => JobBoard::companyCatalog()]);
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
