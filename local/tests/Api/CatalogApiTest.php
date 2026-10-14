<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class CatalogApiTest extends ApiTestCase
{
    public function testListCities(): void
    {
        $response = $this->client()->get('/api/cities/');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['body']['cities'] ?? null);
        $this->assertNotEmpty($response['body']['cities']);
        foreach ($response['body']['cities'] as $city) {
            $this->assertIsArray($city);
            $this->assertCity($city);
            $this->assertIsInt($city['sort'] ?? null);
        }
    }

    public function testListBanners(): void
    {
        $response = $this->client()->get('/api/banners/');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['body']['banners'] ?? null);
        $this->assertNotEmpty($response['body']['banners']);
        foreach ($response['body']['banners'] as $banner) {
            $this->assertIsArray($banner);
            $this->assertIsInt($banner['id'] ?? null);
            $this->assertIsString($banner['name'] ?? null);
            $this->assertNotSame('', $banner['name']);
            $this->assertIsString($banner['image'] ?? null);
            $this->assertNotSame('', $banner['image']);
        }
        $stats = $response['body']['stats'] ?? null;
        $this->assertIsArray($stats);
        foreach (['resumes', 'vacancies', 'companies'] as $key) {
            $this->assertIsInt($stats[$key] ?? null);
            $this->assertGreaterThanOrEqual(0, $stats[$key]);
        }
    }

    public function testSearchVacancies(): void
    {
        $client = $this->client();
        $page = $this->assertVacancyPage($client->get('/api/vacancies/'));
        $this->assertSame(1, $page['page']);
        $this->assertGreaterThan(0, $page['total']);
        $this->assertNotEmpty($page['vacancies']);
        $this->assertIsArray($page['cities'] ?? null);
        $this->assertNotEmpty($page['cities']);
        foreach ($page['cities'] as $city) {
            $this->assertIsArray($city);
            $this->assertCity($city);
        }

        $city = $page['cities'][0]['code'];
        $filtered = $this->assertVacancyPage($client->get('/api/vacancies/?city=' . rawurlencode((string)$city) . '&page=1'));
        $this->assertLessThanOrEqual($page['total'], $filtered['total']);

        $word = $this->searchWord($page['vacancies'][0]['name']);
        $found = $this->assertVacancyPage($client->get('/api/vacancies/?q=' . rawurlencode($word)));
        $this->assertGreaterThan(0, $found['total']);

        if ($page['total'] > $page['limit']) {
            $second = $this->assertVacancyPage($client->get('/api/vacancies/?page=2'));
            $this->assertSame(2, $second['page']);
            $this->assertNotEmpty($second['vacancies']);
        }
    }

    public function testSearchVacanciesSort(): void
    {
        $client = $this->client();
        $desc = $this->assertVacancyPage($client->get('/api/vacancies/?sort=salary_desc'));
        $asc = $this->assertVacancyPage($client->get('/api/vacancies/?sort=salary_asc'));
        $this->assertGreaterThanOrEqual(2, count($desc['vacancies']));
        $this->assertGreaterThanOrEqual(2, count($asc['vacancies']));
        $this->assertGreaterThanOrEqual($this->salary($desc['vacancies'][1]), $this->salary($desc['vacancies'][0]));
        $this->assertLessThanOrEqual($this->salary($asc['vacancies'][1]), $this->salary($asc['vacancies'][0]));
    }

    public function testSuggestVacancies(): void
    {
        $client = $this->client();
        $page = $this->assertVacancyPage($client->get('/api/vacancies/'));
        $word = $this->searchWord($page['vacancies'][0]['name']);
        $response = $client->get('/api/suggest/?q=' . rawurlencode($word));

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['body']['suggestions'] ?? null);
        $this->assertNotEmpty($response['body']['suggestions']);
        foreach ($response['body']['suggestions'] as $suggestion) {
            $this->assertIsString($suggestion);
            $this->assertNotSame('', $suggestion);
        }

        $city = $page['cities'][0]['code'];
        $inCity = $client->get('/api/suggest/?q=' . rawurlencode($word) . '&city=' . rawurlencode((string)$city));
        $this->assertSame(200, $inCity['status']);
        $this->assertIsArray($inCity['body']['suggestions'] ?? null);

        $short = $client->get('/api/suggest/?q=' . rawurlencode(mb_substr($word, 0, 1)));
        $this->assertSame(200, $short['status']);
        $this->assertSame([], $short['body']['suggestions'] ?? null);
    }

    public function testShowVacancy(): void
    {
        $client = $this->client();
        $page = $this->assertVacancyPage($client->get('/api/vacancies/'));
        $id = $page['vacancies'][0]['id'];
        $response = $client->get('/api/vacancy/?id=' . $id);

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['body']['vacancy'] ?? null);
        $this->assertVacancyCard($response['body']['vacancy'], true);
        $this->assertSame($id, $response['body']['vacancy']['id']);

        $this->assertError($client->get('/api/vacancy/?id=999999999'), 404);
    }

    public function testListAndShowCompanies(): void
    {
        $client = $this->client();
        $list = $client->get('/api/companies/');

        $this->assertSame(200, $list['status']);
        $this->assertIsArray($list['body']['companies'] ?? null);
        $this->assertNotEmpty($list['body']['companies']);
        foreach ($list['body']['companies'] as $company) {
            $this->assertIsArray($company);
            $this->assertCompanyCard($company);
        }

        $id = $list['body']['companies'][0]['id'];
        $show = $client->get('/api/company/?id=' . $id);
        $this->assertSame(200, $show['status']);
        $company = $show['body']['company'] ?? null;
        $this->assertIsArray($company);
        $this->assertSame($id, $company['id']);
        $this->assertIsString($company['detail'] ?? null);
        $this->assertIsArray($company['cities'] ?? null);
        foreach ($company['cities'] as $group) {
            $this->assertIsArray($group);
            $this->assertCity($group);
            $this->assertIsArray($group['vacancies'] ?? null);
            foreach ($group['vacancies'] as $vacancy) {
                $this->assertIsArray($vacancy);
                $this->assertVacancyCard($vacancy);
            }
        }

        $this->assertError($client->get('/api/company/?id=999999999'), 404);
    }

    /** @param array<string, mixed> $vacancy */
    private function salary(array $vacancy): int
    {
        $from = $vacancy['salaryFrom'] ?? $vacancy['salaryTo'] ?? 0;

        return is_int($from) ? $from : 0;
    }

    private function searchWord(mixed $name): string
    {
        $this->assertIsString($name);
        $parts = preg_split('/\s+/u', $name) ?: [];
        foreach ($parts as $part) {
            if (mb_strlen($part) >= 4) {
                return $part;
            }
        }

        return $name;
    }
}
