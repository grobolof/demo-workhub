<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\Resumes;

try {
    api_send(Resumes::browse(isset($_GET['q']) ? (string)$_GET['q'] : ''));
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
