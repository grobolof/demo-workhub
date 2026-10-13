<?php

namespace Sprint\Migration;

use App\Search\VacancyIndex;
use CFile;

class Version20261013210000 extends Version
{
    protected $description = 'WorkHub: инфоблок «Должности» переименован в «Вакансии», добавлен инфоблок «Баннеры»';

    protected $author = 'workhub';

    public function up()
    {
        $helper = $this->getHelperManager();

        $positions = $helper->Iblock()->getIblock('positions', 'workhub');
        if (!empty($positions['ID'])) {
            $helper->Iblock()->updateIblock((int)$positions['ID'], [
                'NAME' => 'Вакансии',
                'CODE' => 'vacancies',
            ]);
        }

        $bannersId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'workhub',
            'LID' => ['s1'],
            'CODE' => 'banners',
            'NAME' => 'Баннеры',
            'SORT' => 400,
            'INDEX_ELEMENT' => 'N',
            'INDEX_SECTION' => 'N',
        ]);
        $helper->Iblock()->saveIblockFields($bannersId, [
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
            'PREVIEW_PICTURE' => ['IS_REQUIRED' => 'Y'],
        ]);

        foreach ($this->banners() as $banner) {
            $helper->Iblock()->saveElement($bannersId, [
                'NAME' => $banner['name'],
                'CODE' => $banner['code'],
                'ACTIVE' => 'Y',
                'SORT' => $banner['sort'],
                'PREVIEW_PICTURE' => $this->picture($banner['file']),
            ]);
        }

        try {
            VacancyIndex::rebuild();
        } catch (\Throwable $exception) {
            $this->outWarning('Индекс вакансий будет собран при первом поиске: ' . $exception->getMessage());
        }
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $helper->Iblock()->deleteIblockIfExists('banners', 'workhub');

        $vacancies = $helper->Iblock()->getIblock('vacancies', 'workhub');
        if (!empty($vacancies['ID'])) {
            $helper->Iblock()->updateIblock((int)$vacancies['ID'], [
                'NAME' => 'Должности',
                'CODE' => 'positions',
            ]);
        }
    }

    private function picture(string $file): array
    {
        $path = __DIR__ . '/assets/banners/' . $file;
        $uploaded = CFile::MakeFileArray($path);
        if (!is_array($uploaded) || (int)($uploaded['size'] ?? 0) <= 0) {
            throw new \RuntimeException('Не удалось прочитать файл ' . $path);
        }

        return $uploaded;
    }

    private function banners(): array
    {
        return [
            [
                'code' => 'office',
                'name' => 'Офис: команда за работой',
                'file' => 'office.jpg',
                'sort' => 100,
            ],
            [
                'code' => 'delivery',
                'name' => 'Доставка: курьер в городе',
                'file' => 'delivery.jpg',
                'sort' => 200,
            ],
            [
                'code' => 'shop',
                'name' => 'Магазин: работа в торговом зале',
                'file' => 'shop.jpg',
                'sort' => 300,
            ],
        ];
    }
}
