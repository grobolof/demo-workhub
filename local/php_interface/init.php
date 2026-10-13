<?php

use App\Iblock\ElementGuard;
use App\Search\VacancyIndex;
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler('iblock', 'OnStartIBlockElementAdd', [ElementGuard::class, 'onStart']);
$eventManager->addEventHandler('iblock', 'OnStartIBlockElementUpdate', [ElementGuard::class, 'onStart']);
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementAdd', [ElementGuard::class, 'onBeforeSave']);
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementUpdate', [ElementGuard::class, 'onBeforeSave']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementAdd', [VacancyIndex::class, 'onAfterSave']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementUpdate', [VacancyIndex::class, 'onAfterSave']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementDelete', [VacancyIndex::class, 'onAfterDelete']);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementSetPropertyValuesEx', [VacancyIndex::class, 'onAfterProperties']);
