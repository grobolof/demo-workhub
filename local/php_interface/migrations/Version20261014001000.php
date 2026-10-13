<?php

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class Version20261014001000 extends Version
{
    protected $description = 'WorkHub: компания работодателя, место и график вакансии, избранные резюме';

    protected $author = 'workhub';

    public function up()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock недоступен');
        }

        global $USER_FIELD_MANAGER;
        $USER_FIELD_MANAGER->CleanCache();
        if (!$USER_FIELD_MANAGER->GetUserType('workhub_user')) {
            throw new \RuntimeException('Тип поля «Привязка к пользователю» не зарегистрирован');
        }

        $helper = $this->getHelperManager();
        $companies = $helper->Iblock()->getIblock('companies', 'workhub');
        $vacancies = $helper->Iblock()->getIblock('vacancies', 'workhub');
        if (empty($companies['ID']) || empty($vacancies['ID'])) {
            throw new \RuntimeException('Инфоблоки WorkHub не найдены. Выполните предыдущие миграции.');
        }

        $companiesId = (int)$companies['ID'];
        $vacanciesId = (int)$vacancies['ID'];

        $helper->Iblock()->saveIblockFields($companiesId, [
            'PREVIEW_TEXT' => ['IS_REQUIRED' => 'N'],
            'DETAIL_TEXT' => ['IS_REQUIRED' => 'N'],
            'PREVIEW_PICTURE' => ['IS_REQUIRED' => 'N'],
            'DETAIL_PICTURE' => ['IS_REQUIRED' => 'N'],
        ]);
        $helper->Iblock()->saveProperty($companiesId, [
            'NAME' => 'ИНН',
            'CODE' => 'INN',
            'PROPERTY_TYPE' => 'S',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'N',
            'SORT' => 100,
        ]);
        $helper->Iblock()->saveProperty($companiesId, [
            'NAME' => 'Пользователь',
            'CODE' => 'USER',
            'PROPERTY_TYPE' => 'N',
            'USER_TYPE' => 'UserID',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'N',
            'SORT' => 200,
        ]);

        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'Город',
            'CODE' => 'CITY',
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => 'workhub:cities',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'Y',
            'SORT' => 200,
        ]);
        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'Место работы',
            'CODE' => 'WORKPLACE',
            'PROPERTY_TYPE' => 'L',
            'LIST_TYPE' => 'L',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'N',
            'SORT' => 500,
            'VALUES' => [
                ['VALUE' => 'Офис', 'XML_ID' => 'office', 'SORT' => 100, 'DEF' => 'N'],
                ['VALUE' => 'Гибрид', 'XML_ID' => 'hybrid', 'SORT' => 200, 'DEF' => 'N'],
                ['VALUE' => 'Удалённо', 'XML_ID' => 'remote', 'SORT' => 300, 'DEF' => 'N'],
            ],
        ]);
        $helper->Iblock()->saveProperty($vacanciesId, [
            'NAME' => 'График работы',
            'CODE' => 'SCHEDULE',
            'PROPERTY_TYPE' => 'L',
            'LIST_TYPE' => 'L',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'N',
            'SORT' => 600,
            'VALUES' => [
                ['VALUE' => '5/2', 'XML_ID' => '5-2', 'SORT' => 100, 'DEF' => 'N'],
                ['VALUE' => 'Плавающий', 'XML_ID' => 'floating', 'SORT' => 200, 'DEF' => 'N'],
                ['VALUE' => 'Вахта', 'XML_ID' => 'shift', 'SORT' => 300, 'DEF' => 'N'],
            ],
        ]);

        $hlblockId = $helper->Hlblock()->saveHlblock([
            'NAME' => 'ResumeFavourites',
            'TABLE_NAME' => 'resume_favourites',
            'LANG' => [
                'ru' => ['NAME' => 'Избранные резюме'],
                'en' => ['NAME' => 'Favourite resumes'],
            ],
        ]);
        $helper->Hlblock()->saveField($hlblockId, [
            'FIELD_NAME' => 'UF_RESUME',
            'USER_TYPE_ID' => 'iblock_element',
            'XML_ID' => 'UF_RESUME',
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'Y',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'N',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => [
                'DISPLAY' => 'LIST',
                'LIST_HEIGHT' => 8,
                'IBLOCK_ID' => 'workhub:resumes',
                'DEFAULT_VALUE' => '',
                'ACTIVE_FILTER' => 'Y',
            ],
            'EDIT_FORM_LABEL' => ['ru' => 'Резюме', 'en' => 'Resume'],
            'LIST_COLUMN_LABEL' => ['ru' => 'Резюме', 'en' => 'Resume'],
            'LIST_FILTER_LABEL' => ['ru' => 'Резюме', 'en' => 'Resume'],
        ]);
        $helper->Hlblock()->saveField($hlblockId, [
            'FIELD_NAME' => 'UF_USER',
            'USER_TYPE_ID' => 'workhub_user',
            'XML_ID' => 'UF_USER',
            'SORT' => 200,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'Y',
            'SHOW_FILTER' => 'I',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'N',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => [
                'SIZE' => 20,
                'MIN_VALUE' => 1,
                'MAX_VALUE' => 0,
                'DEFAULT_VALUE' => '',
            ],
            'EDIT_FORM_LABEL' => ['ru' => 'Пользователь', 'en' => 'User'],
            'LIST_COLUMN_LABEL' => ['ru' => 'Пользователь', 'en' => 'User'],
            'LIST_FILTER_LABEL' => ['ru' => 'Пользователь', 'en' => 'User'],
        ]);
        $this->uniquePair('resume_favourites', 'resume_favourites_user_resume', 'UF_USER', 'UF_RESUME');
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        if (Loader::includeModule('highloadblock')) {
            $helper->Hlblock()->deleteHlblockIfExists('ResumeFavourites');
        }

        $companies = $helper->Iblock()->getIblock('companies', 'workhub');
        if (!empty($companies['ID'])) {
            $companiesId = (int)$companies['ID'];
            $helper->Iblock()->deletePropertyIfExists($companiesId, 'INN');
            $helper->Iblock()->deletePropertyIfExists($companiesId, 'USER');
            $helper->Iblock()->saveIblockFields($companiesId, [
                'PREVIEW_TEXT' => ['IS_REQUIRED' => 'Y'],
                'DETAIL_TEXT' => ['IS_REQUIRED' => 'Y'],
                'PREVIEW_PICTURE' => ['IS_REQUIRED' => 'Y'],
                'DETAIL_PICTURE' => ['IS_REQUIRED' => 'Y'],
            ]);
        }

        $vacancies = $helper->Iblock()->getIblock('vacancies', 'workhub');
        if (!empty($vacancies['ID'])) {
            $vacanciesId = (int)$vacancies['ID'];
            $helper->Iblock()->deletePropertyIfExists($vacanciesId, 'WORKPLACE');
            $helper->Iblock()->deletePropertyIfExists($vacanciesId, 'SCHEDULE');
            $helper->Iblock()->saveProperty($vacanciesId, [
                'NAME' => 'Город',
                'CODE' => 'CITY',
                'PROPERTY_TYPE' => 'E',
                'LINK_IBLOCK_ID' => 'workhub:cities',
                'IS_REQUIRED' => 'Y',
                'MULTIPLE' => 'Y',
                'SORT' => 200,
            ]);
        }
    }

    private function uniquePair(string $table, string $index, string $left, string $right): void
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableSql = $sqlHelper->quote($table);
        $found = $connection->query("SHOW INDEX FROM {$tableSql} WHERE Key_name = '{$index}'")->fetch();
        if ($found) {
            return;
        }

        $connection->queryExecute(
            'CREATE UNIQUE INDEX ' . $sqlHelper->quote($index)
            . ' ON ' . $tableSql . ' (' . $sqlHelper->quote($left) . ', ' . $sqlHelper->quote($right) . ')'
        );
    }
}
