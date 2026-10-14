<?php

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class Version20261013223000 extends Version
{
    protected $description = 'WorkHub: роли, избранное, отклики и резюме';

    protected $author = 'workhub';

    public function up()
    {
        $this->highload();

        global $USER_FIELD_MANAGER;
        $USER_FIELD_MANAGER->CleanCache();
        if (!$USER_FIELD_MANAGER->GetUserType('workhub_user')) {
            throw new \RuntimeException('Тип поля «Привязка к пользователю» не зарегистрирован');
        }

        $helper = $this->getHelperManager();
        $helper->UserGroup()->saveGroup('seeker', [
            'ACTIVE' => 'Y',
            'NAME' => 'Соискатели',
            'DESCRIPTION' => 'Соискатели WorkHub',
            'C_SORT' => 200,
        ]);
        $helper->UserGroup()->saveGroup('employer', [
            'ACTIVE' => 'Y',
            'NAME' => 'Работодатели',
            'DESCRIPTION' => 'Работодатели WorkHub',
            'C_SORT' => 210,
        ]);
        Option::set('main', 'new_user_email_uniq_check', 'Y');

        $this->linkBlock($helper, 'Favourites', 'favourites', 'Избранные вакансии');
        $this->linkBlock($helper, 'Responses', 'responses', 'Отклики');

        $resumesId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'workhub',
            'LID' => ['s1'],
            'CODE' => 'resumes',
            'NAME' => 'Резюме',
            'SORT' => 500,
            'INDEX_ELEMENT' => 'N',
            'INDEX_SECTION' => 'N',
        ]);
        $helper->Iblock()->saveIblockFields($resumesId, [
            'NAME' => ['IS_REQUIRED' => 'Y'],
            'DETAIL_TEXT' => ['IS_REQUIRED' => 'Y'],
        ]);
        $helper->Iblock()->saveProperty($resumesId, [
            'NAME' => 'Заработная плата',
            'CODE' => 'SALARY',
            'PROPERTY_TYPE' => 'N',
            'IS_REQUIRED' => 'Y',
            'MULTIPLE' => 'N',
            'SORT' => 100,
        ]);
        $helper->Iblock()->saveProperty($resumesId, [
            'NAME' => 'Пользователь',
            'CODE' => 'USER',
            'PROPERTY_TYPE' => 'N',
            'USER_TYPE' => 'UserID',
            'IS_REQUIRED' => 'Y',
            'MULTIPLE' => 'N',
            'SORT' => 200,
        ]);
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $helper->Iblock()->deleteIblockIfExists('resumes', 'workhub');
        if (Loader::includeModule('highloadblock')) {
            $helper->Hlblock()->deleteHlblockIfExists('Favourites');
            $helper->Hlblock()->deleteHlblockIfExists('Responses');
        }
        $helper->UserGroup()->deleteGroup('seeker');
        $helper->UserGroup()->deleteGroup('employer');
        Option::set('main', 'new_user_email_uniq_check', 'N');
    }

    private function highload(): void
    {
        if (Loader::includeModule('highloadblock')) {
            return;
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/highloadblock/install/index.php';
        $module = new \highloadblock();
        if (!$module->InstallDB()) {
            throw new \RuntimeException('Не удалось установить модуль highloadblock');
        }
        Loader::includeModule('highloadblock');
    }

    private function linkBlock($helper, string $name, string $table, string $title): void
    {
        $hlblockId = $helper->Hlblock()->saveHlblock([
            'NAME' => $name,
            'TABLE_NAME' => $table,
            'LANG' => [
                'ru' => ['NAME' => $title],
                'en' => ['NAME' => $title],
            ],
        ]);
        $helper->Hlblock()->saveField($hlblockId, [
            'FIELD_NAME' => 'UF_VACANCY',
            'USER_TYPE_ID' => 'iblock_element',
            'XML_ID' => 'UF_VACANCY',
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
                'IBLOCK_ID' => 'workhub:vacancies',
                'DEFAULT_VALUE' => '',
                'ACTIVE_FILTER' => 'Y',
            ],
            'EDIT_FORM_LABEL' => ['ru' => 'Вакансия', 'en' => 'Vacancy'],
            'LIST_COLUMN_LABEL' => ['ru' => 'Вакансия', 'en' => 'Vacancy'],
            'LIST_FILTER_LABEL' => ['ru' => 'Вакансия', 'en' => 'Vacancy'],
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
        $this->uniquePair($table);
    }

    private function uniquePair(string $table): void
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableSql = $sqlHelper->quote($table);
        $index = $table . '_user_vacancy';
        $found = $connection->query("SHOW INDEX FROM {$tableSql} WHERE Key_name = '{$index}'")->fetch();
        if ($found) {
            return;
        }

        $connection->queryExecute(
            'CREATE UNIQUE INDEX ' . $sqlHelper->quote($index) . ' ON ' . $tableSql . ' (UF_USER, UF_VACANCY)'
        );
    }
}
