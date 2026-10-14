<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiClient;
use App\Tests\Support\ApiTestCase;

final class AccountApiTest extends ApiTestCase
{
    private ?ApiClient $employer = null;

    private ?ApiClient $seeker = null;

    private int $vacancyId = 0;

    private int $resumeId = 0;

    public function testGuestCannotUsePrivateEndpoints(): void
    {
        $guest = $this->client();

        $this->assertError($guest->get('/api/favourites/'), 401);
        $this->assertError($guest->get('/api/responses/'), 401);
        $this->assertError($guest->get('/api/resumes/'), 401);
        $this->assertError($guest->get('/api/resume-search/'), 401);
        $this->assertError($guest->get('/api/resume-favourites/'), 401);
        $this->assertError($guest->get('/api/my-vacancies/'), 401);
        $this->assertError($guest->postJson('/api/favourites/', ['vacancyId' => 1]), 401);
        $this->assertError($guest->postJson('/api/responses/', ['vacancyId' => 1]), 401);
        $this->assertError($guest->postJson('/api/resumes/', [
            'action' => 'create',
            'title' => 'Резюме',
            'salary' => 100000,
            'description' => 'Описание',
        ]), 401);
        $this->assertError($guest->postJson('/api/my-vacancies/', [
            'action' => 'create',
            'title' => 'Вакансия',
            'description' => 'Описание',
            'salary' => 100000,
            'workplace' => 'office',
            'schedule' => '5-2',
        ]), 401);
    }

    public function testSeekerAndEmployerFlow(): void
    {
        $this->employer = $this->client();
        $this->seeker = $this->client();
        $employerAccount = $this->register($this->employer, 'employer');
        $seekerAccount = $this->register($this->seeker, 'seeker');

        try {
            $this->assertEmployerVacancy();
            $this->assertSeekerResume();
            $this->assertFavouriteAndResponse();
            $this->assertEmployerSeesApplicant();
            $this->assertResumeSearchAndFavourite();
            $this->assertWrongRole($seekerAccount, $employerAccount);
            $this->cleanupCreated();
        } finally {
            $this->cleanupCreated();
        }
    }

    private function assertEmployerVacancy(): void
    {
        $employer = $this->employer;
        $this->assertNotNull($employer);

        $this->assertError($employer->postJson('/api/my-vacancies/', [
            'action' => 'create',
            'title' => '',
            'description' => 'Описание',
            'salary' => 120000,
            'workplace' => 'hybrid',
            'schedule' => 'floating',
        ]), 400);

        $created = $employer->postJson('/api/my-vacancies/', [
            'action' => 'create',
            'title' => 'PHPUnit вакансия',
            'description' => 'Описание тестовой вакансии',
            'salary' => 150000,
            'workplace' => 'hybrid',
            'schedule' => 'floating',
        ]);
        $this->assertSame(200, $created['status']);
        $this->assertIsArray($created['body']['vacancy'] ?? null);
        $this->assertManagedVacancy($created['body']['vacancy']);
        $this->vacancyId = $created['body']['vacancy']['id'];
        $this->assertSame('PHPUnit вакансия', $created['body']['vacancy']['title']);
        $this->assertSame(150000, $created['body']['vacancy']['salary']);

        $list = $employer->get('/api/my-vacancies/');
        $this->assertSame(200, $list['status']);
        $this->assertContains($this->vacancyId, array_column($list['body']['vacancies'] ?? [], 'id'));

        $one = $employer->get('/api/my-vacancies/?id=' . $this->vacancyId);
        $this->assertSame(200, $one['status']);
        $this->assertIsArray($one['body']['vacancy'] ?? null);
        $this->assertManagedVacancy($one['body']['vacancy']);
        $this->assertSame($this->vacancyId, $one['body']['vacancy']['id']);
        $this->assertIsArray($one['body']['vacancy']['applicants'] ?? null);

        $updated = $employer->postJson('/api/my-vacancies/', [
            'action' => 'update',
            'id' => $this->vacancyId,
            'title' => 'PHPUnit вакансия обновлена',
            'description' => 'Новое описание',
            'salary' => 160000,
            'workplace' => 'office',
            'schedule' => '5-2',
        ]);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('PHPUnit вакансия обновлена', $updated['body']['vacancy']['title']);
        $this->assertSame(160000, $updated['body']['vacancy']['salary']);
        $this->assertSame('office', $updated['body']['vacancy']['workplace']);
        $this->assertSame('5-2', $updated['body']['vacancy']['schedule']);

        $public = $employer->get('/api/vacancy/?id=' . $this->vacancyId);
        $this->assertSame(200, $public['status']);
        $this->assertSame($this->vacancyId, $public['body']['vacancy']['id']);
    }

    private function assertSeekerResume(): void
    {
        $seeker = $this->seeker;
        $employer = $this->employer;
        $this->assertNotNull($seeker);
        $this->assertNotNull($employer);

        $this->assertError($employer->postJson('/api/resumes/', [
            'action' => 'create',
            'title' => 'Чужое резюме',
            'salary' => 100000,
            'description' => 'Описание',
        ]), 403);

        $empty = $seeker->get('/api/resumes/');
        $this->assertSame(200, $empty['status']);
        $this->assertSame([], $empty['body']['resumes'] ?? null);
        $this->assertIsInt($empty['body']['limit'] ?? null);

        $this->assertError($seeker->postJson('/api/resumes/', [
            'action' => 'create',
            'title' => '',
            'salary' => 100000,
            'description' => 'Описание',
        ]), 400);

        $created = $seeker->postJson('/api/resumes/', [
            'action' => 'create',
            'title' => 'PHPUnit резюме ' . bin2hex(random_bytes(3)),
            'salary' => 140000,
            'description' => 'Описание тестового резюме',
        ]);
        $this->assertSame(200, $created['status']);
        $this->assertIsArray($created['body']['resume'] ?? null);
        $this->assertResume($created['body']['resume']);
        $this->resumeId = $created['body']['resume']['id'];

        $one = $seeker->get('/api/resumes/?id=' . $this->resumeId);
        $this->assertSame(200, $one['status']);
        $this->assertSame($this->resumeId, $one['body']['resume']['id'] ?? null);

        $updated = $seeker->postJson('/api/resumes/', [
            'action' => 'update',
            'id' => $this->resumeId,
            'title' => 'PHPUnit резюме обновлено',
            'salary' => 145000,
            'description' => 'Обновлённое описание',
        ]);
        $this->assertSame(200, $updated['status']);
        $this->assertSame('PHPUnit резюме обновлено', $updated['body']['resume']['title']);
        $this->assertSame(145000, $updated['body']['resume']['salary']);

        $this->assertError($seeker->postJson('/api/resumes/', [
            'action' => 'create',
            'title' => 'Второе резюме',
            'salary' => 100000,
            'description' => 'Лишнее',
        ]), 409);
    }

    private function assertFavouriteAndResponse(): void
    {
        $seeker = $this->seeker;
        $employer = $this->employer;
        $this->assertNotNull($seeker);
        $this->assertNotNull($employer);
        $this->assertGreaterThan(0, $this->vacancyId);

        $this->assertError($employer->postJson('/api/favourites/', [
            'vacancyId' => $this->vacancyId,
        ]), 403);
        $this->assertError($seeker->postJson('/api/favourites/', [
            'vacancyId' => 999999999,
        ]), 404);

        $added = $seeker->postJson('/api/favourites/', ['vacancyId' => $this->vacancyId]);
        $this->assertSame(200, $added['status']);
        $this->assertSame($this->vacancyId, $added['body']['vacancyId'] ?? null);
        $this->assertTrue($added['body']['favourite'] ?? null);

        $list = $this->assertVacancyPage($seeker->get('/api/favourites/?page=1'));
        $this->assertSame(1, $list['total']);
        $this->assertSame($this->vacancyId, $list['vacancies'][0]['id']);

        $session = $this->assertSession($seeker->get('/api/session/'), 'seeker');
        $this->assertContains($this->vacancyId, $session['favouriteIds']);

        $removed = $seeker->postJson('/api/favourites/', ['vacancyId' => $this->vacancyId]);
        $this->assertSame(200, $removed['status']);
        $this->assertFalse($removed['body']['favourite'] ?? null);

        $this->assertError($seeker->postJson('/api/responses/', ['vacancyId' => 999999999]), 404);
        $responded = $seeker->postJson('/api/responses/', ['vacancyId' => $this->vacancyId]);
        $this->assertSame(200, $responded['status']);
        $this->assertSame($this->vacancyId, $responded['body']['vacancyId'] ?? null);
        $this->assertTrue($responded['body']['responded'] ?? null);

        $responses = $this->assertVacancyPage($seeker->get('/api/responses/?page=1'));
        $this->assertSame(1, $responses['total']);
        $this->assertSame($this->vacancyId, $responses['vacancies'][0]['id']);

        $after = $this->assertSession($seeker->get('/api/session/'), 'seeker');
        $this->assertContains($this->vacancyId, $after['responseIds']);
    }

    private function assertEmployerSeesApplicant(): void
    {
        $employer = $this->employer;
        $seeker = $this->seeker;
        $this->assertNotNull($employer);
        $this->assertNotNull($seeker);

        $session = $this->assertSession($seeker->get('/api/session/'), 'seeker');
        $seekerId = $session['user']['id'];

        $one = $employer->get('/api/my-vacancies/?id=' . $this->vacancyId);
        $this->assertSame(200, $one['status']);
        $applicants = $one['body']['vacancy']['applicants'] ?? null;
        $this->assertIsArray($applicants);
        $this->assertNotEmpty($applicants);
        $applicant = $applicants[0];
        $this->assertIsArray($applicant);
        $this->assertSame($seekerId, $applicant['id']);
        $this->assertIsString($applicant['name'] ?? null);
        $this->assertIsString($applicant['email'] ?? null);
        $this->assertIsString($applicant['respondedAt'] ?? null);
        $this->assertSame($this->resumeId, $applicant['resumeId'] ?? null);
        $this->assertIsString($applicant['resumeTitle'] ?? null);

        $card = $employer->get('/api/my-vacancies/?vacancy=' . $this->vacancyId . '&applicant=' . $seekerId);
        $this->assertSame(200, $card['status']);
        $person = $card['body']['applicant'] ?? null;
        $this->assertIsArray($person);
        $this->assertSame($seekerId, $person['id']);
        $this->assertSame('Иван', $person['firstName']);
        $this->assertSame('Тестов', $person['lastName']);
        $this->assertIsString($person['email'] ?? null);
        $this->assertArrayHasKey('phone', $person);
        $this->assertIsArray($person['resume'] ?? null);
        $this->assertResume($person['resume']);
        $this->assertSame($this->resumeId, $person['resume']['id']);
    }

    private function assertResumeSearchAndFavourite(): void
    {
        $employer = $this->employer;
        $seeker = $this->seeker;
        $this->assertNotNull($employer);
        $this->assertNotNull($seeker);

        $this->assertError($seeker->get('/api/resume-search/?q=PHPUnit'), 403);
        $this->assertError($seeker->get('/api/resume-favourites/'), 403);

        $search = $employer->get('/api/resume-search/?q=' . rawurlencode('PHPUnit резюме обновлено'));
        $this->assertSame(200, $search['status']);
        $this->assertIsArray($search['body']['resumes'] ?? null);
        $found = false;
        foreach ($search['body']['resumes'] as $resume) {
            $this->assertIsArray($resume);
            $this->assertResume($resume);
            $this->assertIsString($resume['author'] ?? null);
            if (($resume['id'] ?? null) === $this->resumeId) {
                $found = true;
            }
        }
        $this->assertTrue($found);

        $this->assertError($employer->postJson('/api/resume-favourites/', ['resumeId' => 999999999]), 404);

        $added = $employer->postJson('/api/resume-favourites/', ['resumeId' => $this->resumeId]);
        $this->assertSame(200, $added['status']);
        $this->assertSame($this->resumeId, $added['body']['resumeId'] ?? null);
        $this->assertTrue($added['body']['favourite'] ?? null);

        $list = $employer->get('/api/resume-favourites/');
        $this->assertSame(200, $list['status']);
        $this->assertContains($this->resumeId, array_column($list['body']['resumes'] ?? [], 'id'));

        $session = $this->assertSession($employer->get('/api/session/'), 'employer');
        $this->assertContains($this->resumeId, $session['resumeFavouriteIds']);

        $removed = $employer->postJson('/api/resume-favourites/', ['resumeId' => $this->resumeId]);
        $this->assertSame(200, $removed['status']);
        $this->assertFalse($removed['body']['favourite'] ?? null);
    }

    /**
     * @param array{email: string, password: string, inn: string} $seekerAccount
     * @param array{email: string, password: string, inn: string} $employerAccount
     */
    private function assertWrongRole(array $seekerAccount, array $employerAccount): void
    {
        $seeker = $this->seeker;
        $this->assertNotNull($seeker);

        $this->assertSession($seeker->postJson('/api/logout/', []));
        $this->assertError($seeker->postJson('/api/login/', [
            'email' => $employerAccount['email'],
            'password' => $employerAccount['password'],
            'role' => 'seeker',
        ]), 403);
        $seeker->get('/api/session/');
        $this->assertSession($seeker->postJson('/api/login/', [
            'email' => $seekerAccount['email'],
            'password' => $seekerAccount['password'],
            'role' => 'seeker',
        ]), 'seeker');
    }

    private function cleanupCreated(): void
    {
        if ($this->seeker !== null && $this->resumeId > 0) {
            $deleted = $this->seeker->postJson('/api/resumes/', [
                'action' => 'delete',
                'id' => $this->resumeId,
            ]);
            if (($deleted['status'] ?? 0) === 200) {
                $this->assertSame($this->resumeId, $deleted['body']['deleted'] ?? null);
                $this->resumeId = 0;
            }
        }
        if ($this->employer !== null && $this->vacancyId > 0) {
            $deleted = $this->employer->postJson('/api/my-vacancies/', [
                'action' => 'delete',
                'id' => $this->vacancyId,
            ]);
            if (($deleted['status'] ?? 0) === 200) {
                $this->assertSame($this->vacancyId, $deleted['body']['deleted'] ?? null);
                $gone = $this->employer->get('/api/vacancy/?id=' . $this->vacancyId);
                $this->assertSame(404, $gone['status']);
                $this->vacancyId = 0;
            }
        }
    }
}
