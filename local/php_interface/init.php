<?php

use App\Iblock\ElementGuard;
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler('iblock', 'OnStartIBlockElementAdd', [ElementGuard::class, 'onStart']);
$eventManager->addEventHandler('iblock', 'OnStartIBlockElementUpdate', [ElementGuard::class, 'onStart']);
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementAdd', [ElementGuard::class, 'onBeforeSave']);
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockElementUpdate', [ElementGuard::class, 'onBeforeSave']);
