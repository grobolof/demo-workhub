<?php

use App\Controller\AuthController;
use App\Controller\BannerController;
use App\Controller\CityController;
use App\Controller\CompanyController;
use App\Controller\FavouriteController;
use App\Controller\RequestController;
use App\Controller\ResponseController;
use App\Controller\ResumeController;
use App\Controller\ResumeFavouriteController;
use App\Controller\VacancyController;
use App\Http\Front;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes
        ->prefix('api')
        ->middleware(static function (): void {
            Front::quiet();
        })
        ->group(static function (RoutingConfigurator $routes): void {
            $map = [
                'banners/' => [BannerController::class, 'index'],
                'cities/' => [CityController::class, 'index'],
                'companies/' => [CompanyController::class, 'index'],
                'company/' => [CompanyController::class, 'show'],
                'favourites/' => [FavouriteController::class, 'index'],
                'login/' => [AuthController::class, 'login'],
                'logout/' => [AuthController::class, 'logout'],
                'my-vacancies/' => [VacancyController::class, 'account'],
                'profile/' => [AuthController::class, 'profile'],
                'register/' => [AuthController::class, 'register'],
                'requests/' => [RequestController::class, 'create'],
                'responses/' => [ResponseController::class, 'index'],
                'resume-favourites/' => [ResumeFavouriteController::class, 'index'],
                'resume-search/' => [ResumeController::class, 'search'],
                'resumes/' => [ResumeController::class, 'index'],
                'session/' => [AuthController::class, 'session'],
                'suggest/' => [VacancyController::class, 'suggest'],
                'vacancies/' => [VacancyController::class, 'search'],
                'vacancy/' => [VacancyController::class, 'show'],
            ];

            foreach ($map as $path => [$controller, $action]) {
                $routes->any($path, static function () use ($controller, $action): void {
                    Front::dispatch($controller, $action);
                });
            }

            $routes->get('openapi.json', static function (): void {
                Front::document();
            });
        });
};
