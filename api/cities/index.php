<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\JobBoard;

try {
    api_send(['cities' => JobBoard::cities()]);
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
