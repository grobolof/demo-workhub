<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'WorkHub API',
    version: '1.0.0',
    description: 'Публичное API сервиса поиска работы WorkHub. Изменяющие запросы передают Bitrix sessid в заголовке X-Bitrix-Csrf-Token. Значение sessid возвращает GET /api/session/.',
)]
#[OA\Server(url: '/', description: 'Текущий хост')]
#[OA\Tag(name: 'Каталог', description: 'Вакансии, города и баннеры')]
#[OA\Tag(name: 'Компании', description: 'Каталог работодателей')]
#[OA\Tag(name: 'Сессия', description: 'Регистрация, вход и профиль')]
#[OA\Tag(name: 'Соискатель', description: 'Резюме, избранное и отклики')]
#[OA\Tag(name: 'Работодатель', description: 'Вакансии работодателя и отклики соискателей')]
#[OA\Tag(name: 'Заявки', description: 'Обращения в поддержку')]
#[OA\Parameter(
    parameter: 'Csrf',
    name: 'X-Bitrix-Csrf-Token',
    in: 'header',
    required: true,
    description: 'Bitrix sessid из GET /api/session/',
    schema: new OA\Schema(type: 'string'),
)]
#[OA\Schema(
    schema: 'ApiError',
    required: ['error'],
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Вакансия не найдена'),
    ],
)]
#[OA\Schema(
    schema: 'City',
    required: ['id', 'name', 'code'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'Москва'),
        new OA\Property(property: 'code', type: 'string', example: 'moscow'),
    ],
)]
#[OA\Schema(
    schema: 'CompanyCard',
    required: ['id', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'VacancyCard',
    required: ['id', 'name', 'salaryFrom', 'salaryTo', 'cities'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'preview', type: 'string'),
        new OA\Property(property: 'detail', type: 'string'),
        new OA\Property(property: 'salaryFrom', type: 'integer', nullable: true),
        new OA\Property(property: 'salaryTo', type: 'integer', nullable: true),
        new OA\Property(property: 'workplace', type: 'string', example: 'Гибрид'),
        new OA\Property(property: 'schedule', type: 'string', example: 'Плавающий'),
        new OA\Property(property: 'company', ref: '#/components/schemas/CompanyCard', nullable: true),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
    ],
)]
#[OA\Schema(
    schema: 'VacancyPage',
    required: ['vacancies', 'total', 'page', 'limit'],
    properties: [
        new OA\Property(property: 'vacancies', type: 'array', items: new OA\Items(ref: '#/components/schemas/VacancyCard')),
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'page', type: 'integer'),
        new OA\Property(property: 'limit', type: 'integer'),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
    ],
)]
#[OA\Schema(
    schema: 'User',
    required: ['id', 'email', 'role'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'role', type: 'string', enum: ['seeker', 'employer']),
        new OA\Property(property: 'firstName', type: 'string'),
        new OA\Property(property: 'lastName', type: 'string'),
        new OA\Property(property: 'middleName', type: 'string'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
        new OA\Property(property: 'company', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'Session',
    required: ['sessid', 'favouriteIds', 'responseIds', 'resumeFavouriteIds'],
    properties: [
        new OA\Property(property: 'sessid', type: 'string'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'favouriteIds', type: 'array', items: new OA\Items(type: 'integer')),
        new OA\Property(property: 'responseIds', type: 'array', items: new OA\Items(type: 'integer')),
        new OA\Property(property: 'resumeFavouriteIds', type: 'array', items: new OA\Items(type: 'integer')),
    ],
)]
#[OA\Schema(
    schema: 'Resume',
    required: ['id', 'title', 'description', 'salary'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'salary', type: 'integer'),
        new OA\Property(property: 'author', type: 'string'),
    ],
)]
#[OA\Schema(
    schema: 'ManagedVacancy',
    required: ['id', 'title', 'salary'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'salary', type: 'integer'),
        new OA\Property(property: 'workplace', type: 'string', example: 'hybrid'),
        new OA\Property(property: 'workplaceLabel', type: 'string', example: 'Гибрид'),
        new OA\Property(property: 'schedule', type: 'string', example: 'floating'),
        new OA\Property(property: 'scheduleLabel', type: 'string', example: 'Плавающий'),
        new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
        new OA\Property(property: 'responses', type: 'integer'),
        new OA\Property(property: 'applicants', type: 'array', items: new OA\Items(ref: '#/components/schemas/Applicant')),
    ],
)]
#[OA\Schema(
    schema: 'Applicant',
    required: ['id', 'name', 'respondedAt'],
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
        new OA\Property(property: 'resumeId', type: 'integer'),
        new OA\Property(property: 'resumeTitle', type: 'string'),
        new OA\Property(property: 'respondedAt', type: 'string', format: 'date-time'),
    ],
)]
final class WorkhubApi
{
    #[OA\Get(
        path: '/api/cities/',
        operationId: 'listCities',
        summary: 'Список городов',
        tags: ['Каталог'],
        responses: [
            new OA\Response(response: 200, description: 'Города', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'cities', type: 'array', items: new OA\Items(ref: '#/components/schemas/City')),
            ], type: 'object')),
            new OA\Response(response: 500, description: 'Ошибка', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function cities(): void
    {
    }

    #[OA\Get(
        path: '/api/banners/',
        operationId: 'listBanners',
        summary: 'Баннеры главной и счётчики',
        tags: ['Каталог'],
        responses: [
            new OA\Response(response: 200, description: 'Баннеры', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'banners', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'image', type: 'string'),
                ], type: 'object')),
                new OA\Property(property: 'stats', properties: [
                    new OA\Property(property: 'resumes', type: 'integer'),
                    new OA\Property(property: 'vacancies', type: 'integer'),
                    new OA\Property(property: 'companies', type: 'integer'),
                ], type: 'object'),
            ], type: 'object')),
        ],
    )]
    public function banners(): void
    {
    }

    #[OA\Get(
        path: '/api/vacancies/',
        operationId: 'searchVacancies',
        summary: 'Поиск вакансий',
        tags: ['Каталог'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', description: 'Текст поиска', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city', in: 'query', description: 'Код города', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'sort', in: 'query', description: 'salary_asc или salary_desc', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Страница вакансий', content: new OA\JsonContent(ref: '#/components/schemas/VacancyPage')),
        ],
    )]
    public function vacancies(): void
    {
    }

    #[OA\Get(
        path: '/api/suggest/',
        operationId: 'suggestVacancies',
        summary: 'Подсказки поиска вакансий',
        tags: ['Каталог'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Подсказки', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'suggestions', type: 'array', items: new OA\Items(type: 'string')),
            ], type: 'object')),
        ],
    )]
    public function suggest(): void
    {
    }

    #[OA\Get(
        path: '/api/vacancy/',
        operationId: 'showVacancy',
        summary: 'Карточка вакансии',
        tags: ['Каталог'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Вакансия', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'vacancy', ref: '#/components/schemas/VacancyCard'),
            ], type: 'object')),
            new OA\Response(response: 404, description: 'Вакансия не найдена', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function vacancy(): void
    {
    }

    #[OA\Get(
        path: '/api/companies/',
        operationId: 'listCompanies',
        summary: 'Каталог компаний',
        tags: ['Компании'],
        responses: [
            new OA\Response(response: 200, description: 'Компании', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'companies', type: 'array', items: new OA\Items(type: 'object')),
            ], type: 'object')),
        ],
    )]
    public function companies(): void
    {
    }

    #[OA\Get(
        path: '/api/company/',
        operationId: 'showCompany',
        summary: 'Страница компании',
        tags: ['Компании'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Компания и её вакансии', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'company', type: 'object'),
            ], type: 'object')),
            new OA\Response(response: 404, description: 'Компания не найдена', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function company(): void
    {
    }

    #[OA\Get(
        path: '/api/session/',
        operationId: 'session',
        summary: 'Текущая сессия',
        tags: ['Сессия'],
        responses: [
            new OA\Response(response: 200, description: 'Сессия', content: new OA\JsonContent(ref: '#/components/schemas/Session')),
        ],
    )]
    public function session(): void
    {
    }

    #[OA\Post(
        path: '/api/register/',
        operationId: 'register',
        summary: 'Регистрация',
        tags: ['Сессия'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password', 'passwordConfirm', 'role'], properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'passwordConfirm', type: 'string'),
            new OA\Property(property: 'role', type: 'string', enum: ['seeker', 'employer']),
            new OA\Property(property: 'firstName', type: 'string'),
            new OA\Property(property: 'lastName', type: 'string'),
            new OA\Property(property: 'middleName', type: 'string'),
            new OA\Property(property: 'companyName', type: 'string'),
            new OA\Property(property: 'inn', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Сессия нового пользователя', content: new OA\JsonContent(ref: '#/components/schemas/Session')),
            new OA\Response(response: 400, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function register(): void
    {
    }

    #[OA\Post(
        path: '/api/login/',
        operationId: 'login',
        summary: 'Вход',
        tags: ['Сессия'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password', 'role'], properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'role', type: 'string', enum: ['seeker', 'employer']),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Сессия', content: new OA\JsonContent(ref: '#/components/schemas/Session')),
            new OA\Response(response: 400, description: 'Неверные данные', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function login(): void
    {
    }

    #[OA\Post(
        path: '/api/logout/',
        operationId: 'logout',
        summary: 'Выход',
        tags: ['Сессия'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Пустая сессия', content: new OA\JsonContent(ref: '#/components/schemas/Session')),
        ],
    )]
    public function logout(): void
    {
    }

    #[OA\Post(
        path: '/api/profile/',
        operationId: 'profile',
        summary: 'Профиль, аватар и логотип компании',
        description: 'JSON обновляет ФИО (lastName, firstName, middleName) или компанию при scope=company (name, inn, description). multipart/form-data сохраняет файл avatar или logo либо удаляет его полем remove=1 (для логотипа ещё target=logo).',
        tags: ['Сессия'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'scope', type: 'string', enum: ['company']),
            new OA\Property(property: 'lastName', type: 'string'),
            new OA\Property(property: 'firstName', type: 'string'),
            new OA\Property(property: 'middleName', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'inn', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Обновлённая сессия', content: new OA\JsonContent(ref: '#/components/schemas/Session')),
            new OA\Response(response: 400, description: 'Ошибка', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function profile(): void
    {
    }

    #[OA\Get(
        path: '/api/favourites/',
        operationId: 'listFavourites',
        summary: 'Избранные вакансии соискателя',
        tags: ['Соискатель'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Страница избранного', content: new OA\JsonContent(ref: '#/components/schemas/VacancyPage')),
            new OA\Response(response: 401, description: 'Нужна авторизация соискателя', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    #[OA\Post(
        path: '/api/favourites/',
        operationId: 'toggleFavourite',
        summary: 'Добавить или убрать вакансию из избранного',
        tags: ['Соискатель'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['vacancyId'], properties: [
            new OA\Property(property: 'vacancyId', type: 'integer'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Состояние избранного', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'vacancyId', type: 'integer'),
                new OA\Property(property: 'favourite', type: 'boolean'),
            ], type: 'object')),
        ],
    )]
    public function favourites(): void
    {
    }

    #[OA\Get(
        path: '/api/responses/',
        operationId: 'listResponses',
        summary: 'Отклики соискателя, от новых к старым',
        tags: ['Соискатель'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Страница откликов', content: new OA\JsonContent(ref: '#/components/schemas/VacancyPage')),
        ],
    )]
    #[OA\Post(
        path: '/api/responses/',
        operationId: 'addResponse',
        summary: 'Откликнуться на вакансию',
        tags: ['Соискатель'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['vacancyId'], properties: [
            new OA\Property(property: 'vacancyId', type: 'integer'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Отклик сохранён', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'vacancyId', type: 'integer'),
                new OA\Property(property: 'responded', type: 'boolean'),
            ], type: 'object')),
            new OA\Response(response: 404, description: 'Вакансия не найдена', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function responses(): void
    {
    }

    #[OA\Get(
        path: '/api/resumes/',
        operationId: 'listResumes',
        summary: 'Резюме текущего соискателя',
        tags: ['Соискатель'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'query', description: 'Если указан, возвращается одно резюме', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список или одно резюме', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'resumes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Resume')),
                new OA\Property(property: 'resume', ref: '#/components/schemas/Resume'),
                new OA\Property(property: 'limit', type: 'integer'),
            ], type: 'object')),
        ],
    )]
    #[OA\Post(
        path: '/api/resumes/',
        operationId: 'saveResume',
        summary: 'Создать, изменить или удалить резюме',
        tags: ['Соискатель'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'action', type: 'string', enum: ['create', 'update', 'delete']),
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'salary', type: 'integer'),
            new OA\Property(property: 'description', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Резюме сохранено'),
            new OA\Response(response: 400, description: 'Ошибка', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function resumes(): void
    {
    }

    #[OA\Get(
        path: '/api/resume-search/',
        operationId: 'searchResumes',
        summary: 'Поиск резюме для работодателя',
        tags: ['Работодатель'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Резюме', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'resumes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Resume')),
            ], type: 'object')),
        ],
    )]
    public function resumeSearch(): void
    {
    }

    #[OA\Get(
        path: '/api/resume-favourites/',
        operationId: 'listResumeFavourites',
        summary: 'Избранные резюме работодателя',
        tags: ['Работодатель'],
        responses: [
            new OA\Response(response: 200, description: 'Избранные резюме', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'resumes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Resume')),
            ], type: 'object')),
        ],
    )]
    #[OA\Post(
        path: '/api/resume-favourites/',
        operationId: 'toggleResumeFavourite',
        summary: 'Добавить или убрать резюме из избранного',
        tags: ['Работодатель'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['resumeId'], properties: [
            new OA\Property(property: 'resumeId', type: 'integer'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Состояние избранного'),
        ],
    )]
    public function resumeFavourites(): void
    {
    }

    #[OA\Get(
        path: '/api/my-vacancies/',
        operationId: 'employerVacancies',
        summary: 'Вакансии работодателя, карточка с откликами или резюме соискателя',
        description: 'Без параметров возвращает список. id — одна вакансия, отклики в applicants отсортированы от новых к старым. vacancy и applicant — резюме конкретного соискателя.',
        tags: ['Работодатель'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'vacancy', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'applicant', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Вакансии или соискатель', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'vacancies', type: 'array', items: new OA\Items(ref: '#/components/schemas/ManagedVacancy')),
                new OA\Property(property: 'vacancy', ref: '#/components/schemas/ManagedVacancy'),
                new OA\Property(property: 'applicant', type: 'object'),
            ], type: 'object')),
        ],
    )]
    #[OA\Post(
        path: '/api/my-vacancies/',
        operationId: 'saveEmployerVacancy',
        summary: 'Создать, изменить или удалить вакансию',
        tags: ['Работодатель'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'action', type: 'string', enum: ['create', 'update', 'delete']),
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
            new OA\Property(property: 'salary', type: 'integer'),
            new OA\Property(property: 'workplace', type: 'string', enum: ['office', 'hybrid', 'remote']),
            new OA\Property(property: 'schedule', type: 'string', enum: ['5-2', 'floating', 'shift']),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Вакансия сохранена', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'vacancy', ref: '#/components/schemas/ManagedVacancy'),
            ], type: 'object')),
        ],
    )]
    public function myVacancies(): void
    {
    }

    #[OA\Post(
        path: '/api/requests/',
        operationId: 'createRequest',
        summary: 'Заявка в поддержку',
        tags: ['Заявки'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/Csrf')],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['email', 'subject', 'message'], properties: [
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'subject', type: 'string'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
            ])),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'Заявка принята'),
            new OA\Response(response: 400, description: 'Ошибка', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function requests(): void
    {
    }
}
