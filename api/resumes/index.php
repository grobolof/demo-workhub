<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Api\Resumes;

api_run(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $body = api_require_post();
        $action = (string)($body['action'] ?? 'create');
        if ($action === 'update') {
            api_send(Resumes::update(
                (int)($body['id'] ?? 0),
                (string)($body['title'] ?? ''),
                $body['salary'] ?? '',
                (string)($body['description'] ?? ''),
            ));
        }
        if ($action === 'delete') {
            api_send(Resumes::delete((int)($body['id'] ?? 0)));
        }

        api_send(Resumes::create(
            (string)($body['title'] ?? ''),
            $body['salary'] ?? '',
            (string)($body['description'] ?? ''),
        ));
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        api_send(Resumes::one($id));
    }

    api_send(Resumes::list());
});
