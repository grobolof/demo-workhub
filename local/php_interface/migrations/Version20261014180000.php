<?php

namespace Sprint\Migration;

use Bitrix\Main\Loader;

class Version20261014180000 extends Version
{
    protected $description = 'WorkHub: группе «Поддержка» доступ к highload-блоку «Избранные резюме»';

    protected $author = 'workhub';

    private const NAME = 'ResumeFavourites';

    public function up()
    {
        $this->grant(['support' => 'W']);
    }

    public function down()
    {
        $this->grant([]);
    }

    private function grant(array $permissions): void
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock недоступен');
        }

        $helper = $this->getHelperManager()->Hlblock();
        $hlblockId = $helper->getHlblockId(self::NAME);
        if ($hlblockId <= 0) {
            throw new \RuntimeException('Highload-блок ' . self::NAME . ' не найден');
        }

        $helper->saveGroupPermissions($hlblockId, $permissions);

        if (isset($GLOBALS['CACHE_MANAGER']) && is_object($GLOBALS['CACHE_MANAGER'])) {
            $GLOBALS['CACHE_MANAGER']->CleanAll();
        }
        if (function_exists('BXClearCache')) {
            BXClearCache(true);
        }
    }
}
