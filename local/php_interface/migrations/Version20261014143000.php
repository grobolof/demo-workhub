<?php

namespace Sprint\Migration;

use App\Search\VacancyIndex;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CFile;

class Version20261014143000 extends Version
{
    protected $description = 'WorkHub: один город у вакансии, детальные поля компании и время отклика';

    protected $author = 'workhub';

    public function up()
    {
        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль инфоблоков недоступен');
        }

        $helper = $this->getHelperManager();
        $companies = $this->iblock($helper, 'companies');
        $vacancies = $this->iblock($helper, 'vacancies');
        $companiesId = (int)$companies['ID'];
        $vacanciesId = (int)$vacancies['ID'];

        $this->copyCompanyDetails($companiesId);
        $this->copyVacancyDetails($vacanciesId);
        $helper->Iblock()->saveIblockFields($vacanciesId, [
            'PREVIEW_TEXT' => ['IS_REQUIRED' => 'N'],
        ]);
        $this->singleCity($vacanciesId);
        $this->responseTime($helper);

        try {
            VacancyIndex::rebuild();
        } catch (\Throwable $exception) {
            $this->outWarning('Индекс вакансий будет собран при первом поиске: ' . $exception->getMessage());
        }
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        if (Loader::includeModule('highloadblock')) {
            $helper->Hlblock()->deleteField('Responses', 'UF_CREATED');
        }

        $vacancies = $helper->Iblock()->getIblock('vacancies', 'employers')
            ?: $helper->Iblock()->getIblock('vacancies', 'workhub');
        if (empty($vacancies['ID'])) {
            return;
        }

        $vacanciesId = (int)$vacancies['ID'];
        $helper->Iblock()->saveIblockFields($vacanciesId, [
            'PREVIEW_TEXT' => ['IS_REQUIRED' => 'Y'],
        ]);

        $property = CIBlockProperty::GetList([], [
            'IBLOCK_ID' => $vacanciesId,
            'CODE' => 'CITY',
        ])->Fetch();
        if (!$property || ($property['MULTIPLE'] ?? '') === 'Y') {
            return;
        }

        $editor = new CIBlockProperty();
        if (!$editor->Update((int)$property['ID'], [
            'MULTIPLE' => 'Y',
            'PROPERTY_TYPE' => 'E',
            'IBLOCK_ID' => $vacanciesId,
        ])) {
            throw new \RuntimeException('Не удалось вернуть множественный город: ' . $editor->LAST_ERROR);
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

    private function copyCompanyDetails(int $iblockId): void
    {
        $rows = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PREVIEW_TEXT', 'DETAIL_TEXT']
        );
        $element = new CIBlockElement();
        while ($row = $rows->Fetch()) {
            $fields = [];
            if ((int)$row['DETAIL_PICTURE'] <= 0 && (int)$row['PREVIEW_PICTURE'] > 0) {
                $file = CFile::MakeFileArray((int)$row['PREVIEW_PICTURE']);
                if (is_array($file)) {
                    $fields['DETAIL_PICTURE'] = $file;
                }
            }
            $detail = trim((string)$row['DETAIL_TEXT']);
            $preview = trim((string)$row['PREVIEW_TEXT']);
            if ($detail === '' && $preview !== '') {
                $fields['DETAIL_TEXT'] = $preview;
                $fields['DETAIL_TEXT_TYPE'] = 'text';
            }
            if ($fields !== []) {
                $element->Update((int)$row['ID'], $fields);
            }
        }
    }

    private function copyVacancyDetails(int $iblockId): void
    {
        $rows = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N'],
            false,
            false,
            ['ID', 'PREVIEW_TEXT', 'DETAIL_TEXT']
        );
        $element = new CIBlockElement();
        while ($row = $rows->Fetch()) {
            $detail = trim((string)$row['DETAIL_TEXT']);
            $preview = trim((string)$row['PREVIEW_TEXT']);
            if ($detail !== '' || $preview === '') {
                continue;
            }
            $element->Update((int)$row['ID'], [
                'DETAIL_TEXT' => $preview,
                'DETAIL_TEXT_TYPE' => 'text',
            ]);
        }
    }

    private function singleCity(int $iblockId): void
    {
        $property = CIBlockProperty::GetList([], [
            'IBLOCK_ID' => $iblockId,
            'CODE' => 'CITY',
        ])->Fetch();
        if (!$property) {
            throw new \RuntimeException('Свойство «Город» не найдено');
        }
        if (($property['MULTIPLE'] ?? 'N') !== 'Y') {
            return;
        }

        $iblock = CIBlock::GetByID($iblockId)->Fetch();
        $this->keepFirstCity($iblockId, (int)$property['ID'], (int)($iblock['VERSION'] ?? 1));

        $editor = new CIBlockProperty();
        if (!$editor->Update((int)$property['ID'], [
            'MULTIPLE' => 'N',
            'PROPERTY_TYPE' => 'E',
            'IBLOCK_ID' => $iblockId,
        ])) {
            throw new \RuntimeException('Не удалось сделать город одиночным: ' . $editor->LAST_ERROR);
        }
    }

    private function keepFirstCity(int $iblockId, int $propertyId, int $version): void
    {
        $connection = Application::getConnection();
        $table = $version === 2
            ? 'b_iblock_element_prop_m' . $iblockId
            : 'b_iblock_element_property';
        if (!$connection->isTableExists($table)) {
            return;
        }

        $rows = $connection->query(
            'SELECT ID, IBLOCK_ELEMENT_ID, VALUE FROM ' . $table
            . ' WHERE IBLOCK_PROPERTY_ID = ' . $propertyId
            . ' ORDER BY IBLOCK_ELEMENT_ID ASC, ID ASC'
        );
        $keep = [];
        $drop = [];
        while ($row = $rows->fetch()) {
            $elementId = (int)$row['IBLOCK_ELEMENT_ID'];
            $value = (int)$row['VALUE'];
            if ($value > 0 && !isset($keep[$elementId])) {
                $keep[$elementId] = (int)$row['ID'];
                continue;
            }
            $drop[] = (int)$row['ID'];
        }
        if ($drop === []) {
            return;
        }

        $connection->queryExecute(
            'DELETE FROM ' . $table . ' WHERE ID IN (' . implode(',', $drop) . ')'
        );
    }

    private function responseTime($helper): void
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock недоступен');
        }

        $hlblockId = $helper->Hlblock()->getHlblockId('Responses');
        if ($hlblockId <= 0) {
            throw new \RuntimeException('Highload-блок Responses не найден');
        }

        $helper->Hlblock()->saveField($hlblockId, [
            'FIELD_NAME' => 'UF_CREATED',
            'USER_TYPE_ID' => 'datetime',
            'XML_ID' => 'UF_CREATED',
            'SORT' => 300,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => [
                'DEFAULT_VALUE' => [
                    'TYPE' => 'NONE',
                    'VALUE' => '',
                ],
                'USE_SECOND' => 'Y',
                'USE_TIMEZONE' => 'N',
            ],
            'EDIT_FORM_LABEL' => ['ru' => 'Время отклика', 'en' => 'Response time'],
            'LIST_COLUMN_LABEL' => ['ru' => 'Время отклика', 'en' => 'Response time'],
            'LIST_FILTER_LABEL' => ['ru' => 'Время отклика', 'en' => 'Response time'],
        ]);

        $connection = Application::getConnection();
        $helperSql = $connection->getSqlHelper();
        $connection->queryExecute(
            'UPDATE ' . $helperSql->quote('responses')
            . ' SET UF_CREATED = ' . $helperSql->getCurrentDateTimeFunction()
            . ' WHERE UF_CREATED IS NULL'
        );
    }
}
