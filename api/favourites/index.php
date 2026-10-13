<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

(new App\Http\Kernel())->run(App\Controller\FavouriteController::class, 'index');
