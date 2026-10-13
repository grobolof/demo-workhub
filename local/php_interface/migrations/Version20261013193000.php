<?php

namespace Sprint\Migration;

use CFile;

class Version20261013193000 extends Version
{
    protected $description = 'WorkHub: инфоблоки компаний, городов и вакансий';

    protected $author = 'workhub';

    public function up()
    {
        $helper = $this->getHelperManager();

        $helper->Iblock()->saveIblockType([
            'ID' => 'workhub',
            'SECTIONS' => 'N',
            'IN_RSS' => 'N',
            'SORT' => 100,
            'LANG' => [
                'ru' => [
                    'NAME' => 'WorkHub',
                    'SECTION_NAME' => 'Разделы',
                    'ELEMENT_NAME' => 'Элементы',
                ],
                'en' => [
                    'NAME' => 'WorkHub',
                    'SECTION_NAME' => 'Sections',
                    'ELEMENT_NAME' => 'Elements',
                ],
            ],
        ]);

        $companiesId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'workhub',
            'LID' => ['s1'],
            'CODE' => 'companies',
            'NAME' => 'Компании',
            'SORT' => 100,
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'N',
        ]);
        $helper->Iblock()->saveIblockFields($companiesId, [
            'CODE' => [
                'IS_REQUIRED' => 'Y',
                'DEFAULT_VALUE' => [
                    'UNIQUE' => 'Y',
                    'TRANSLITERATION' => 'Y',
                    'TRANS_LEN' => '100',
                    'TRANS_CASE' => 'L',
                    'TRANS_SPACE' => '-',
                    'TRANS_OTHER' => '-',
                    'TRANS_EAT' => 'Y',
                    'USE_GOOGLE' => 'N',
                ],
            ],
            'PREVIEW_TEXT' => ['IS_REQUIRED' => 'Y'],
            'DETAIL_TEXT' => ['IS_REQUIRED' => 'Y'],
            'PREVIEW_PICTURE' => ['IS_REQUIRED' => 'Y'],
            'DETAIL_PICTURE' => ['IS_REQUIRED' => 'Y'],
        ]);

        $citiesId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'workhub',
            'LID' => ['s1'],
            'CODE' => 'cities',
            'NAME' => 'Города',
            'SORT' => 200,
            'INDEX_ELEMENT' => 'N',
            'INDEX_SECTION' => 'N',
        ]);
        $helper->Iblock()->saveIblockFields($citiesId, [
            'CODE' => [
                'IS_REQUIRED' => 'N',
                'DEFAULT_VALUE' => [
                    'UNIQUE' => 'Y',
                    'TRANSLITERATION' => 'Y',
                    'TRANS_LEN' => '100',
                    'TRANS_CASE' => 'L',
                    'TRANS_SPACE' => '-',
                    'TRANS_OTHER' => '-',
                    'TRANS_EAT' => 'Y',
                    'USE_GOOGLE' => 'N',
                ],
            ],
        ]);

        $vacanciesId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'workhub',
            'LID' => ['s1'],
            'CODE' => 'vacancies',
            'NAME' => 'Вакансии',
            'SORT' => 300,
            'INDEX_ELEMENT' => 'Y',
            'INDEX_SECTION' => 'N',
        ]);
        $helper->Iblock()->saveIblockFields($vacanciesId, [
            'PREVIEW_TEXT' => ['IS_REQUIRED' => 'Y'],
            'DETAIL_TEXT' => ['IS_REQUIRED' => 'Y'],
        ]);
        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'Компания',
            'CODE' => 'COMPANY',
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => 'workhub:companies',
            'IS_REQUIRED' => 'Y',
            'MULTIPLE' => 'N',
            'SORT' => 100,
        ]);
        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'Город',
            'CODE' => 'CITY',
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => 'workhub:cities',
            'IS_REQUIRED' => 'Y',
            'MULTIPLE' => 'Y',
            'SORT' => 200,
        ]);
        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'Зарплата (от)',
            'CODE' => 'SALARY_FROM',
            'PROPERTY_TYPE' => 'N',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'N',
            'SORT' => 300,
        ]);
        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'Зарплата (до)',
            'CODE' => 'SALARY_TO',
            'PROPERTY_TYPE' => 'N',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'N',
            'SORT' => 400,
        ]);

        $companyIds = [];
        foreach ($this->companies() as $company) {
            $picture = $this->picture($company['code']);
            $detail = $this->picture($company['code']);
            $companyIds[$company['code']] = $helper->Iblock()->saveElement($companiesId, [
                'NAME' => $company['name'],
                'CODE' => $company['code'],
                'ACTIVE' => 'Y',
                'SORT' => $company['sort'],
                'PREVIEW_TEXT' => $company['preview'],
                'PREVIEW_TEXT_TYPE' => 'text',
                'DETAIL_TEXT' => $company['detail'],
                'DETAIL_TEXT_TYPE' => 'text',
                'PREVIEW_PICTURE' => $picture,
                'DETAIL_PICTURE' => $detail,
            ]);
        }

        $cityIds = [];
        foreach ($this->cities() as $city) {
            $cityIds[$city['code']] = $helper->Iblock()->saveElement($citiesId, [
                'NAME' => $city['name'],
                'CODE' => $city['code'],
                'ACTIVE' => 'Y',
                'SORT' => $city['sort'],
            ]);
        }

        foreach ($this->positions() as $position) {
            $props = [
                'COMPANY' => $companyIds[$position['company']],
                'CITY' => array_map(static fn (string $code) => $cityIds[$code], $position['cities']),
            ];
            if ($position['salaryFrom'] !== null) {
                $props['SALARY_FROM'] = $position['salaryFrom'];
            }
            if ($position['salaryTo'] !== null) {
                $props['SALARY_TO'] = $position['salaryTo'];
            }

            $helper->Iblock()->saveElement($vacanciesId, [
                'NAME' => $position['name'],
                'CODE' => $position['code'],
                'ACTIVE' => 'Y',
                'SORT' => $position['sort'],
                'PREVIEW_TEXT' => $position['preview'],
                'PREVIEW_TEXT_TYPE' => 'text',
                'DETAIL_TEXT' => $position['detail'],
                'DETAIL_TEXT_TYPE' => 'text',
            ], $props);
        }
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $helper->Iblock()->deleteIblockIfExists('vacancies', 'workhub');
        $helper->Iblock()->deleteIblockIfExists('positions', 'workhub');
        $helper->Iblock()->deleteIblockIfExists('companies', 'workhub');
        $helper->Iblock()->deleteIblockIfExists('cities', 'workhub');
        $helper->Iblock()->deleteIblockTypeIfExists('workhub');
    }

    private function picture(string $code): array
    {
        $path = __DIR__ . '/assets/' . $code . '.png';
        $file = CFile::MakeFileArray($path);
        if (!is_array($file) || (int)($file['size'] ?? 0) <= 0) {
            throw new \RuntimeException('Не удалось прочитать файл ' . $path);
        }

        return $file;
    }

    private function companies(): array
    {
        return [
            [
                'code' => 'alfa-bank',
                'name' => 'Альфа-Банк',
                'sort' => 100,
                'preview' => 'Крупный частный банк: офисы по всей стране и цифровые сервисы для миллионов клиентов.',
                'detail' => 'Альфа-Банк развивает розничные и корпоративные продукты. В командах нужны специалисты поддержки, продаж и разработки. Офисы есть в крупных городах, часть задач можно закрывать удалённо.',
            ],
            [
                'code' => 'yandex',
                'name' => 'Яндекс',
                'sort' => 200,
                'preview' => 'Технологическая компания: поиск, доставка, облако и сервисы, которыми пользуются каждый день.',
                'detail' => 'В Яндексе собирают продукты для миллионов людей. Команды ищут инженеров, аналитиков и специалистов поддержки в Москве и Санкт-Петербурге.',
            ],
            [
                'code' => 'magnit',
                'name' => 'Магнит',
                'sort' => 300,
                'preview' => 'Сеть магазинов у дома. Нужны продавцы, водители и сотрудники распределительных центров.',
                'detail' => '«Магнит» — одна из крупнейших розничных сетей. Вакансии открыты в магазинах, на складах и в логистике. График и район подбирают на собеседовании.',
            ],
            [
                'code' => 'ozon',
                'name' => 'Ozon',
                'sort' => 400,
                'preview' => 'Маркетплейс и логистика: пункты выдачи, склады и доставка заказов по России.',
                'detail' => 'Ozon доставляет заказы по всей стране. На складах и в курьерской службе открыт набор: от кладовщиков до курьеров и водителей.',
            ],
            [
                'code' => 'dodo',
                'name' => 'Додо Пицца',
                'sort' => 500,
                'preview' => 'Сеть пиццерий. Ищем поваров, кассиров и бариста в городах присутствия.',
                'detail' => 'В пиццериях Додо готовят при гостях и работают небольшими сменами. Обучение проходит на месте, график обсуждают с управляющим.',
            ],
            [
                'code' => 'sber',
                'name' => 'Сбер',
                'sort' => 600,
                'preview' => 'Крупнейший банк страны. Офисные команды в регионах: финансы, клиентский сервис и продажи.',
                'detail' => 'Сбер нанимает специалистов в отделения и региональные офисы. Для офисных ролей важны аккуратность, опыт с документами и готовность разбираться в продуктах банка.',
            ],
        ];
    }

    private function cities(): array
    {
        return [
            ['code' => 'moskva', 'name' => 'Москва', 'sort' => 100],
            ['code' => 'sankt-peterburg', 'name' => 'Санкт-Петербург', 'sort' => 200],
            ['code' => 'kazan', 'name' => 'Казань', 'sort' => 300],
            ['code' => 'novosibirsk', 'name' => 'Новосибирск', 'sort' => 400],
            ['code' => 'ekaterinburg', 'name' => 'Екатеринбург', 'sort' => 500],
            ['code' => 'krasnodar', 'name' => 'Краснодар', 'sort' => 600],
        ];
    }

    private function positions(): array
    {
        return [
            [
                'code' => 'kurer',
                'name' => 'Курьер',
                'company' => 'ozon',
                'cities' => ['moskva', 'kazan'],
                'salaryFrom' => 90000,
                'salaryTo' => 140000,
                'sort' => 100,
                'preview' => 'Доставка заказов по району на автомобиле компании или личном транспорте. Выплаты каждую неделю.',
                'detail' => 'Курьер забирает заказы со склада и отвозит клиентам в своём районе. Смена длится 8–10 часов, маршрут строит приложение. Опыт не обязателен: на первой смене рядом будет наставник.',
            ],
            [
                'code' => 'voditel-ekspeditor',
                'name' => 'Водитель-экспедитор',
                'company' => 'magnit',
                'cities' => ['krasnodar'],
                'salaryFrom' => 80000,
                'salaryTo' => null,
                'sort' => 200,
                'preview' => 'Развоз товаров по магазинам сети. Категория B, знание города и аккуратная езда.',
                'detail' => 'Нужно возить паллеты между распределительным центром и магазинами. График 2/2, автомобиль компании, топливная карта. Права категории B и стаж от года.',
            ],
            [
                'code' => 'prodavets-kassir',
                'name' => 'Продавец-кассир',
                'company' => 'magnit',
                'cities' => ['moskva', 'kazan', 'novosibirsk'],
                'salaryFrom' => 55000,
                'salaryTo' => 75000,
                'sort' => 300,
                'preview' => 'Касса, выкладка и помощь покупателям в магазине у дома. Обучение в первые дни.',
                'detail' => 'Продавец встречает покупателей, пробивает товары и следит за полками. Смены утро/вечер, форма выдаётся. Опыт в торговле будет плюсом, но разберёмся и без него.',
            ],
            [
                'code' => 'programmist-php',
                'name' => 'Программист PHP',
                'company' => 'alfa-bank',
                'cities' => ['moskva'],
                'salaryFrom' => 250000,
                'salaryTo' => 400000,
                'sort' => 400,
                'preview' => 'Развитие внутренних сервисов банка на PHP. Гибридный формат: офис в Москве и удалённые дни.',
                'detail' => 'Команда делает сервисы для сотрудников и клиентов. В стеке PHP, очереди и PostgreSQL. Ищем человека, который уже вёл сервис в проде и умеет спокойно разбирать чужой код.',
            ],
            [
                'code' => 'analitik-dannyh',
                'name' => 'Аналитик данных',
                'company' => 'yandex',
                'cities' => ['moskva', 'sankt-peterburg'],
                'salaryFrom' => 200000,
                'salaryTo' => null,
                'sort' => 500,
                'preview' => 'Отчёты и витрины для продуктовых команд. SQL обязателен, Python будет преимуществом.',
                'detail' => 'Аналитик помогает командам понимать, как люди пользуются сервисом. Нужны уверенный SQL, аккуратные дашборды и умение объяснять цифры без лишнего жаргона.',
            ],
            [
                'code' => 'povar',
                'name' => 'Повар',
                'company' => 'dodo',
                'cities' => ['kazan'],
                'salaryFrom' => null,
                'salaryTo' => 90000,
                'sort' => 600,
                'preview' => 'Готовим пиццу при гостях. Смена на кухне, обучение рецептам с первого дня.',
                'detail' => 'Повар собирает пиццу по технологическим картам и следит за временем отдачи. Кухня открытая, форма и питание в смену. Санитарная книжка нужна к выходу.',
            ],
            [
                'code' => 'buhgalter',
                'name' => 'Бухгалтер',
                'company' => 'sber',
                'cities' => ['ekaterinburg'],
                'salaryFrom' => 90000,
                'salaryTo' => 130000,
                'sort' => 700,
                'preview' => 'Первичка, сверки и закрытие месяца в региональном офисе. Опыт в банке не обязателен.',
                'detail' => 'Бухгалтер ведёт первичные документы поставщиков и помогает закрывать месяц. Нужны 1С и внимательность к суммам. Офис в центре, график пятидневка.',
            ],
            [
                'code' => 'kladovshchik',
                'name' => 'Кладовщик',
                'company' => 'ozon',
                'cities' => ['novosibirsk'],
                'salaryFrom' => 70000,
                'salaryTo' => null,
                'sort' => 800,
                'preview' => 'Приёмка и сборка заказов на складе. Тёплый склад, развозка до смены.',
                'detail' => 'Кладовщик принимает паллеты, сканирует товары и собирает заказы по листу. Смена 11 часов, обед оплачивается. Физически работа спокойная, если привыкли к складу.',
            ],
            [
                'code' => 'menedzher-po-prodazham',
                'name' => 'Менеджер по продажам',
                'company' => 'alfa-bank',
                'cities' => ['moskva', 'sankt-peterburg'],
                'salaryFrom' => 120000,
                'salaryTo' => 180000,
                'sort' => 900,
                'preview' => 'Зарплатные проекты для компаний. Встречи с клиентами и сопровождение после сделки.',
                'detail' => 'Менеджер ведёт небольшую базу компаний: звонки, встречи и запуск зарплатного проекта. Оклад плюс процент. Опыт в продажах услуг от года.',
            ],
            [
                'code' => 'barista',
                'name' => 'Бариста',
                'company' => 'dodo',
                'cities' => ['krasnodar', 'kazan'],
                'salaryFrom' => 45000,
                'salaryTo' => 65000,
                'sort' => 1000,
                'preview' => 'Кофе и касса в пиццерии. Научим напиткам, если раньше не стояли за стойкой.',
                'detail' => 'Бариста готовит кофе, собирает заказы навынос и помогает на кассе в час пик. Смены по 8 часов, чаевые остаются сотруднику. Обучение занимает первую неделю.',
            ],
        ];
    }
}
