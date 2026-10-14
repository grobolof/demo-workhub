<?php

namespace Sprint\Migration;

class Version20261014170000 extends Version
{
    protected $description = 'WorkHub: группе «Поддержка» только просмотр списка пользователей';

    protected $author = 'workhub';

    public function up()
    {
        $groupId = (int)$this->getHelperManager()->UserGroup()->getGroupId('support');
        if ($groupId <= 0) {
            throw new \RuntimeException('Группа «Поддержка» не найдена');
        }

        \App\Demo\Restorer::grantSupportRights($groupId);
    }

    public function down()
    {
        $groupId = (int)$this->getHelperManager()->UserGroup()->getGroupId('support');
        if ($groupId > 0) {
            \CGroup::SetModulePermission($groupId, 'main', false);
        }

        $row = \Bitrix\Main\TaskTable::getList([
            'filter' => ['=NAME' => 'workhub_support_view_users', '=MODULE_ID' => 'main'],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();
        if (!empty($row['ID'])) {
            \CTask::Delete((int)$row['ID']);
        }
    }
}
