<?php

namespace Sprint\Migration;

class Version20261013235000 extends Version
{
    protected $description = 'WorkHub: инфоблок заявок';

    protected $author = 'workhub';

    public function up()
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'workhub',
            'LID' => ['s1'],
            'CODE' => 'requests',
            'NAME' => 'Заявки',
            'SORT' => 600,
            'INDEX_ELEMENT' => 'N',
            'INDEX_SECTION' => 'N',
        ]);
        $helper->Iblock()->saveIblockFields($iblockId, [
            'NAME' => ['IS_REQUIRED' => 'Y'],
        ]);
        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Email',
            'CODE' => 'EMAIL',
            'PROPERTY_TYPE' => 'S',
            'IS_REQUIRED' => 'Y',
            'MULTIPLE' => 'N',
            'SORT' => 100,
        ]);
        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Сообщение',
            'CODE' => 'MESSAGE',
            'PROPERTY_TYPE' => 'S',
            'ROW_COUNT' => 8,
            'COL_COUNT' => 60,
            'IS_REQUIRED' => 'Y',
            'MULTIPLE' => 'N',
            'SORT' => 200,
        ]);
        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Файлы',
            'CODE' => 'FILES',
            'PROPERTY_TYPE' => 'F',
            'IS_REQUIRED' => 'N',
            'MULTIPLE' => 'Y',
            'FILE_TYPE' => 'jpg, jpeg, png',
            'SORT' => 300,
        ]);
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $helper->Iblock()->deleteIblockIfExists('requests', 'workhub');
    }
}
