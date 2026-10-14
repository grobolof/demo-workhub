<?php

namespace Sprint\Migration;

use Bitrix\Main\Loader;
use CIBlockProperty;

class Version20261014174000 extends Version
{
    protected $description = 'WorkHub: обязательные ИНН, пользователь компании, город, место и график вакансии';

    protected $author = 'workhub';

    public function up()
    {
        $this->required('Y');
    }

    public function down()
    {
        $this->required('N');
    }

    private function required(string $flag): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль инфоблоков недоступен');
        }

        $helper = $this->getHelperManager();
        $companies = $this->iblock($helper, 'companies');
        $vacancies = $this->iblock($helper, 'vacancies');

        foreach (['INN', 'USER'] as $code) {
            $this->mark((int)$companies['ID'], $code, $flag);
        }
        foreach (['CITY', 'WORKPLACE', 'SCHEDULE'] as $code) {
            $this->mark((int)$vacancies['ID'], $code, $flag);
        }
    }

    private function iblock($helper, string $code): array
    {
        foreach (['employers', 'workhub'] as $type) {
            $iblock = $helper->Iblock()->getIblock($code, $type);
            if (!empty($iblock['ID'])) {
                return $iblock;
            }
        }

        throw new \RuntimeException('Инфоблок «' . $code . '» не найден');
    }

    private function mark(int $iblockId, string $code, string $flag): void
    {
        $property = CIBlockProperty::GetList([], [
            'IBLOCK_ID' => $iblockId,
            'CODE' => $code,
        ])->Fetch();
        if (!$property) {
            throw new \RuntimeException('Свойство «' . $code . '» не найдено');
        }
        if (($property['IS_REQUIRED'] ?? 'N') === $flag) {
            return;
        }

        $editor = new CIBlockProperty();
        if (!$editor->Update((int)$property['ID'], [
            'IS_REQUIRED' => $flag,
            'IBLOCK_ID' => $iblockId,
        ])) {
            throw new \RuntimeException('Не удалось обновить «' . $code . '»: ' . $editor->LAST_ERROR);
        }
    }
}
