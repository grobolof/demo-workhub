<?php

namespace Sprint\Migration;

use CUser;
use CIBlock;

class Version20261014030000 extends Version
{
    protected $description = 'WorkHub: отдельные типы инфоблоков и пользователь поддержки';

    protected $author = 'workhub';

    private const TYPES = [
        'system' => [
            'SORT' => 100,
            'ru' => 'Система',
            'en' => 'System',
            'iblocks' => ['banners', 'requests'],
        ],
        'seekers' => [
            'SORT' => 200,
            'ru' => 'Соискатели',
            'en' => 'Job seekers',
            'iblocks' => ['resumes'],
        ],
        'employers' => [
            'SORT' => 300,
            'ru' => 'Работодатели',
            'en' => 'Employers',
            'iblocks' => ['vacancies', 'companies', 'cities'],
        ],
    ];

    public function up()
    {
        $helper = $this->getHelperManager();

        foreach (self::TYPES as $typeId => $type) {
            $helper->Iblock()->saveIblockType([
                'ID' => $typeId,
                'SECTIONS' => 'N',
                'IN_RSS' => 'N',
                'SORT' => $type['SORT'],
                'LANG' => [
                    'ru' => [
                        'NAME' => $type['ru'],
                        'SECTION_NAME' => 'Разделы',
                        'ELEMENT_NAME' => 'Элементы',
                    ],
                    'en' => [
                        'NAME' => $type['en'],
                        'SECTION_NAME' => 'Sections',
                        'ELEMENT_NAME' => 'Elements',
                    ],
                ],
            ]);
        }

        $iblockIds = [];
        foreach (self::TYPES as $typeId => $type) {
            foreach ($type['iblocks'] as $code) {
                $iblockIds[$code] = $this->moveIblock($helper, $code, $typeId);
            }
        }

        if (!CIBlock::GetList([], ['TYPE' => 'workhub', 'CHECK_PERMISSIONS' => 'N'])->Fetch()) {
            $helper->Iblock()->deleteIblockTypeIfExists('workhub');
        }

        foreach (['news', 'vacancies'] as $typeId) {
            $rows = CIBlock::GetList([], ['TYPE' => $typeId, 'CHECK_PERMISSIONS' => 'N']);
            while ($row = $rows->Fetch()) {
                $helper->Iblock()->deleteIblock((int)$row['ID']);
            }
            $helper->Iblock()->deleteIblockTypeIfExists($typeId);
        }

        $groupId = $helper->UserGroup()->saveGroup('support', [
            'ACTIVE' => 'Y',
            'NAME' => 'Поддержка',
            'DESCRIPTION' => 'Инфоблоки системы, соискателей и работодателей, highload-блоки favourites и responses',
            'C_SORT' => 220,
        ]);
        $this->saveSupportUser($groupId);
        \App\Demo\Restorer::grantSupportRights($groupId);

        foreach ($iblockIds as $iblockId) {
            $permissions = $helper->Iblock()->getGroupPermissions($iblockId);
            $permissions[$groupId] = 'W';
            $helper->Iblock()->setGroupPermissions($iblockId, $permissions);
        }

        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock недоступен');
        }
        foreach (['Favourites', 'Responses', 'ResumeFavourites'] as $name) {
            $hlblockId = $helper->Hlblock()->getHlblockId($name);
            if ($hlblockId <= 0) {
                throw new \RuntimeException('Highload-блок ' . $name . ' не найден');
            }
            $helper->Hlblock()->saveGroupPermissions($hlblockId, [
                'support' => 'W',
            ]);
        }

        if (isset($GLOBALS['CACHE_MANAGER']) && is_object($GLOBALS['CACHE_MANAGER'])) {
            $GLOBALS['CACHE_MANAGER']->CleanAll();
        }
        if (function_exists('BXClearCache')) {
            BXClearCache(true);
        }
    }

    public function down()
    {
        $helper = $this->getHelperManager();

        $user = CUser::GetByLogin('support')->Fetch();
        if (!empty($user['ID'])) {
            CUser::Delete((int)$user['ID']);
        }

        $groupId = $helper->UserGroup()->getGroupId('support');
        if ($groupId) {
            if (\Bitrix\Main\Loader::includeModule('highloadblock')) {
                foreach (['Favourites', 'Responses', 'ResumeFavourites'] as $name) {
                    $hlblockId = $helper->Hlblock()->getHlblockId($name);
                    if ($hlblockId > 0) {
                        $helper->Hlblock()->saveGroupPermissions($hlblockId, []);
                    }
                }
            }
            foreach (self::TYPES as $type) {
                foreach ($type['iblocks'] as $code) {
                    $iblock = $helper->Iblock()->getIblock($code, 'employers')
                        ?: $helper->Iblock()->getIblock($code, 'seekers')
                        ?: $helper->Iblock()->getIblock($code, 'system')
                        ?: $helper->Iblock()->getIblock($code, 'workhub');
                    if (empty($iblock['ID'])) {
                        continue;
                    }
                    $permissions = $helper->Iblock()->getGroupPermissions((int)$iblock['ID']);
                    unset($permissions[$groupId]);
                    $helper->Iblock()->setGroupPermissions((int)$iblock['ID'], $permissions);
                }
            }
            $helper->UserGroup()->deleteGroup('support');
        }

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

        foreach (self::TYPES as $type) {
            foreach ($type['iblocks'] as $code) {
                $this->moveIblock($helper, $code, 'workhub');
            }
        }

        foreach (array_keys(self::TYPES) as $typeId) {
            if (!CIBlock::GetList([], ['TYPE' => $typeId, 'CHECK_PERMISSIONS' => 'N'])->Fetch()) {
                $helper->Iblock()->deleteIblockTypeIfExists($typeId);
            }
        }
    }

    private function moveIblock($helper, string $code, string $typeId): int
    {
        $iblock = $helper->Iblock()->getIblock($code, $typeId);
        if (empty($iblock['ID'])) {
            foreach (['workhub', 'system', 'seekers', 'employers'] as $from) {
                $iblock = $helper->Iblock()->getIblock($code, $from);
                if (!empty($iblock['ID'])) {
                    break;
                }
            }
        }
        if (empty($iblock['ID'])) {
            throw new \RuntimeException('Инфоблок «' . $code . '» не найден');
        }

        if ((string)$iblock['IBLOCK_TYPE_ID'] !== $typeId) {
            $helper->Iblock()->updateIblock((int)$iblock['ID'], [
                'IBLOCK_TYPE_ID' => $typeId,
            ]);
        }

        return (int)$iblock['ID'];
    }

    private function saveSupportUser(int $groupId): void
    {
        $fields = [
            'LOGIN' => 'support',
            'EMAIL' => 'support@mail.ru',
            'NAME' => 'Константин',
            'LAST_NAME' => 'Раскольников',
            'SECOND_NAME' => 'Георгиевич',
            'ACTIVE' => 'Y',
            'GROUP_ID' => [$groupId],
            'PASSWORD' => 'support',
            'CONFIRM_PASSWORD' => 'support',
        ];

        $user = new CUser();
        $existing = CUser::GetByLogin('support')->Fetch();
        if (!empty($existing['ID'])) {
            if (!$user->Update((int)$existing['ID'], $fields)) {
                throw new \RuntimeException(strip_tags((string)$user->LAST_ERROR));
            }

            return;
        }

        if (!$user->Add($fields)) {
            throw new \RuntimeException(strip_tags((string)$user->LAST_ERROR));
        }
    }
}
