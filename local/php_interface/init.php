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

$eventManager->addEventHandler('main', 'OnBuildGlobalMenu', static function (array &$globalMenu, array &$moduleMenu): void {
    if (!\App\Demo\Restorer::allowed()) {
        return;
    }

    $moduleMenu[] = [
        'parent_menu' => 'global_menu_settings',
        'section' => 'workhub',
        'sort' => 1000,
        'text' => 'Восстановить демо',
        'title' => 'Очистить данные и заново загрузить демо-фикстуры',
        'url' => 'workhub_demo_restore.php?lang=' . LANGUAGE_ID,
        'icon' => 'sys_menu_icon',
        'page_icon' => 'sys_menu_icon',
        'items_id' => 'menu_workhub_demo',
    ];
});
