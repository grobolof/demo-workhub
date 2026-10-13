<?php

declare(strict_types=1);

namespace App\Http;

use App\Controller\AbstractController;
use App\Controller\AuthController;
use App\Controller\BannerController;
use App\Controller\CityController;
use App\Controller\CompanyController;
use App\Controller\FavouriteController;
use App\Controller\RequestController;
use App\Controller\ResumeController;
use App\Controller\ResumeFavouriteController;
use App\Controller\ResponseController;
use App\Controller\VacancyController;
use App\Exception\ApiException;
use App\Service\AuthService;
use App\Service\FavouriteService;
use App\Service\JobBoardService;
use App\Service\RequestService;
use App\Service\ResponseService;
use App\Service\ResumeFavouriteService;
use App\Service\ResumeService;
use App\Service\VacancyManageService;
use RuntimeException;
use Throwable;

final class Kernel
{
    private Request $request;

    private JsonResponse $response;

    public function __construct()
    {
        $this->request = Request::fromGlobals();
        $this->response = new JsonResponse();
    }

    public function run(string $controllerClass, string $action): never
    {
        try {
            $controller = $this->controller($controllerClass);
            $controller->{$action}();
        } catch (ApiException $exception) {
            $this->response->fail($exception->getMessage(), $exception->status());
        } catch (Throwable $exception) {
            $this->response->fail($exception->getMessage());
        }

        $this->response->fail('Пустой ответ');
    }

    private function controller(string $class): AbstractController
    {
        $request = $this->request;
        $response = $this->response;
        $jobBoard = new JobBoardService();
        $auth = new AuthService();
        $resumes = new ResumeService($auth);

        return match ($class) {
            AuthController::class => new AuthController($request, $response, $auth),
            BannerController::class => new BannerController($request, $response, $jobBoard),
            CityController::class => new CityController($request, $response, $jobBoard),
            CompanyController::class => new CompanyController($request, $response, $jobBoard),
            FavouriteController::class => new FavouriteController($request, $response, new FavouriteService($auth, $jobBoard)),
            RequestController::class => new RequestController($request, $response, new RequestService()),
            ResumeController::class => new ResumeController($request, $response, $resumes),
            ResumeFavouriteController::class => new ResumeFavouriteController(
                $request,
                $response,
                new ResumeFavouriteService($auth, $resumes),
            ),
            ResponseController::class => new ResponseController($request, $response, new ResponseService($auth, $jobBoard)),
            VacancyController::class => new VacancyController($request, $response, $jobBoard, new VacancyManageService($auth)),
            default => throw new RuntimeException('Неизвестный контроллер'),
        };
    }
}
