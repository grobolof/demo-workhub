<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\JobBoard;

try {
    $stats = ['resumes' => 0, 'vacancies' => 0, 'companies' => 0];
    try {
        $stats = JobBoard::stats();
    } catch (Throwable) {
    }

    api_send([
        'banners' => JobBoard::banners(),
        'stats' => $stats,
    ]);
} catch (Throwable $exception) {
    api_fail($exception->getMessage());
}
