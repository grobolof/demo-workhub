<?php

declare(strict_types=1);

namespace App\Demo;

use RuntimeException;

final class Catalog
{
    /** @return list<array{code: string, name: string}> */
    public static function cities(): array
    {
        return [
            ['code' => 'moskva', 'name' => 'Москва'],
            ['code' => 'sankt-peterburg', 'name' => 'Санкт-Петербург'],
            ['code' => 'kazan', 'name' => 'Казань'],
            ['code' => 'novosibirsk', 'name' => 'Новосибирск'],
            ['code' => 'ekaterinburg', 'name' => 'Екатеринбург'],
            ['code' => 'krasnodar', 'name' => 'Краснодар'],
            ['code' => 'nizhniy-novgorod', 'name' => 'Нижний Новгород'],
            ['code' => 'samara', 'name' => 'Самара'],
            ['code' => 'rostov-na-donu', 'name' => 'Ростов-на-Дону'],
            ['code' => 'voronezh', 'name' => 'Воронеж'],
            ['code' => 'ufa', 'name' => 'Уфа'],
            ['code' => 'perm', 'name' => 'Пермь'],
            ['code' => 'krasnoyarsk', 'name' => 'Красноярск'],
            ['code' => 'chelyabinsk', 'name' => 'Челябинск'],
            ['code' => 'omsk', 'name' => 'Омск'],
            ['code' => 'tyumen', 'name' => 'Тюмень'],
            ['code' => 'saratov', 'name' => 'Саратов'],
            ['code' => 'irkutsk', 'name' => 'Иркутск'],
            ['code' => 'vladivostok', 'name' => 'Владивосток'],
            ['code' => 'kaliningrad', 'name' => 'Калининград'],
        ];
    }

    /** @return list<array{code: string, name: string, file: string}> */
    public static function banners(): array
    {
        return [
            ['code' => 'office', 'name' => 'Офис: пространство для команд', 'file' => 'office.jpg'],
            ['code' => 'warehouse', 'name' => 'Склад: сборка и отгрузка', 'file' => 'warehouse.jpg'],
            ['code' => 'shop', 'name' => 'Магазин: работа в торговом зале', 'file' => 'shop.jpg'],
            ['code' => 'delivery', 'name' => 'Доставка: курьеры в городе', 'file' => 'delivery.jpg'],
            ['code' => 'team', 'name' => 'Команда: собеседование и первые недели', 'file' => 'team.jpg'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function companies(): array
    {
        return array_merge(
            require __DIR__ . '/data/companies.php',
            require __DIR__ . '/data/companies_b.php',
            require __DIR__ . '/data/companies_c.php',
        );
    }

    /**
     * Один город приходится на 3–4 вакансии, и эти вакансии относятся к разным компаниям.
     *
     * @return list<array<string, mixed>>
     */
    public static function assignCities(): array
    {
        $cities = array_column(self::cities(), 'code');
        $usage = array_fill_keys($cities, 0);
        $assigned = [];

        foreach (self::companies() as $company) {
            $usedHere = [];
            foreach ($company['vacancies'] as $vacancy) {
                $pick = null;
                $least = PHP_INT_MAX;
                foreach ($cities as $code) {
                    if (isset($usedHere[$code]) || $usage[$code] >= 4) {
                        continue;
                    }
                    if ($usage[$code] < $least) {
                        $least = $usage[$code];
                        $pick = $code;
                    }
                }
                if ($pick === null) {
                    throw new RuntimeException('Не удалось разложить города по вакансиям');
                }
                $usage[$pick]++;
                $usedHere[$pick] = true;
                $vacancy['city'] = $pick;
                $vacancy['company'] = $company['code'];
                $vacancy['companyName'] = $company['name'];
                $assigned[] = $vacancy;
            }
        }

        foreach ($usage as $code => $times) {
            if ($times < 3 || $times > 4) {
                throw new RuntimeException('Город ' . $code . ' встретился ' . $times . ' раз');
            }
        }

        return $assigned;
    }

    /** @param array<string, mixed> $vacancy */
    public static function vacancyText(array $vacancy, string $city): string
    {
        $workplace = [
            'office' => 'офис',
            'hybrid' => 'гибрид',
            'remote' => 'удалённая работа',
        ][$vacancy['workplace']];
        $schedule = [
            '5-2' => 'пятидневка',
            'floating' => 'плавающий график',
            'shift' => 'сменный график',
        ][$vacancy['schedule']];
        $money = number_format((int)$vacancy['salaryFrom'], 0, '', ' ')
            . '–'
            . number_format((int)$vacancy['salaryTo'], 0, '', ' ')
            . ' ₽';

        $blocks = [
            $vacancy['companyName'] . ' открывает вакансию «' . $vacancy['title'] . '» в городе ' . $city . '. ' . $vacancy['lead'],
            $vacancy['day'],
            'Что ожидаем. ' . $vacancy['expect'],
            'Условия. Формат — ' . $workplace . ', график — ' . $schedule . '. Вилка ' . $money . ' до вычета налога. ' . $vacancy['terms'],
            $vacancy['start'],
        ];
        if (($vacancy['extra'] ?? '') !== '') {
            $blocks[] = $vacancy['extra'];
        }

        return implode("\n\n", $blocks);
    }

    /** @return list<array<string, mixed>> */
    public static function seekers(): array
    {
        return require __DIR__ . '/data/seekers.php';
    }
}
