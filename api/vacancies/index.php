<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\JobBoard;

try {
    api_send(JobBoard::search(
        isset($_GET['q']) ? (string)$_GET['q'] : '',
        isset($_GET['city']) ? (string)$_GET['city'] : '',
        isset($_GET['page']) ? (int)$_GET['page'] : 1,
    ));
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
