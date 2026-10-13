<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\Auth;

api_run(static function (): void {
    $body = api_require_post();
    api_send(Auth::register(
        (string)($body['email'] ?? ''),
        (string)($body['password'] ?? ''),
        (string)($body['passwordConfirm'] ?? ''),
        (string)($body['role'] ?? ''),
    ));
});
