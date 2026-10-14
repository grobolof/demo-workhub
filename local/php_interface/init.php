<?php

use App\Iblock\ElementGuard;
use App\Search\VacancyIndex;
use App\UserField\UserLink;
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler('main', 'OnUserTypeBuildList', [UserLink::class, 'getUserTypeDescription']);

$eventManager->addEventHandler('iblock', 'OnStartIBlockElementAdd', [ElementGuard::class, 'onStart']);
$eventManager->addEventHandler('iblock', 'OnStartIBlockElementUpdate', [ElementGuard::class, 'onStart']);
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementAdd', [ElementGuard::class, 'onBeforeSave']);
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementUpdate', [ElementGuard::class, 'onBeforeSave']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementAdd', [VacancyIndex::class, 'onAfterSave']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementUpdate', [VacancyIndex::class, 'onAfterSave']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementDelete', [VacancyIndex::class, 'onAfterDelete']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementSetPropertyValuesEx', [VacancyIndex::class, 'onAfterProperties']);

$eventManager->addEventHandler('main', 'OnBeforeProlog', static function (): void {
    if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
        return;
    }

    $script = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    if (!str_ends_with($script, '/user_edit.php')) {
        return;
    }

    global $USER;
    if (!is_object($USER) || !$USER->IsAuthorized() || $USER->IsAdmin()) {
        return;
    }
    if (!\App\Demo\Restorer::userListViewOnly($USER)) {
        return;
    }

    LocalRedirect('/bitrix/admin/user_admin.php?lang=' . LANGUAGE_ID);
});

$eventManager->addEventHandler('main', 'OnAdminListDisplay', static function ($list): void {
    if (!is_object($list) || (string)($list->table_id ?? '') !== 'tbl_user') {
        return;
    }

    global $USER;
    if (!is_object($USER) || !\App\Demo\Restorer::userListViewOnly($USER)) {
        return;
    }

    foreach ($list->aRows as $row) {
        $row->aActions = [];
        $row->link = '';
        foreach ($row->aFields as $fieldId => $field) {
            if (($field['view']['type'] ?? '') !== 'html' || !isset($field['view']['value'])) {
                continue;
            }
            $row->aFields[$fieldId]['view']['value'] = preg_replace(
                '#<a\b[^>]*>(.*?)</a>#is',
                '$1',
                (string)$field['view']['value']
            );
        }
    }
});

$eventManager->addEventHandler('main', 'OnBuildGlobalMenu', static function (array &$globalMenu, array &$moduleMenu): void {
    if (!\App\Demo\Restorer::allowed()) {
        return;
    }

    foreach ($moduleMenu as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $parent = (string)($item['parent_menu'] ?? '');
        $itemUrl = (string)($item['url'] ?? '');
        if ($parent === 'global_menu_settings' && str_contains($itemUrl, 'workhub_demo_restore.php')) {
            unset($moduleMenu[$index]);
        }
    }

    $url = 'workhub_demo_restore.php?lang=' . LANGUAGE_ID;
    $globalMenu['global_menu_workhub_demo'] = [
        'menu_id' => 'workhub_demo',
        'text' => 'Восс. демо',
        'title' => 'Очистить данные и заново загрузить демо-фикстуры',
        'url' => $url,
        'sort' => 600,
        'icon' => 'sys_menu_icon',
        'page_icon' => 'sys_menu_icon',
        'items_id' => 'global_menu_workhub_demo',
        'items' => [
            [
                'text' => 'Восстановить демо',
                'title' => 'Очистить данные и заново загрузить демо-фикстуры',
                'url' => $url,
                'sort' => 100,
                'icon' => 'sys_menu_icon',
                'page_icon' => 'sys_menu_icon',
            ],
        ],
    ];

    global $APPLICATION;
    if (!is_object($APPLICATION)) {
        return;
    }

    $APPLICATION->AddHeadString(
        '<style>'
        . 'a.adm-main-menu-item.adm-workhub_demo .adm-main-menu-item-icon{background-position:center -1582px;height:47px}'
        . 'a.adm-workhub_demo:hover .adm-main-menu-item-icon,'
        . 'a.adm-main-menu-item-active.adm-workhub_demo .adm-main-menu-item-icon{background-position:center -1638px;height:47px}'
        . '</style>'
        . '<script>document.addEventListener("DOMContentLoaded",function(){'
        . 'var item=document.getElementById("global_menu_workhub_demo");'
        . 'if(!item){return;}'
        . 'item.onclick=function(){window.location.href=item.href;return false;};'
        . '});</script>',
        true
    );
});
