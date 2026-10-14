<?php

namespace Sprint\Migration;

class Version20261014160000 extends Version
{
    protected $description = 'WorkHub: русские названия highload-блоков избранного и откликов';

    protected $author = 'workhub';

    public function up()
    {
        $helper = $this->getHelperManager();
        $this->title($helper, 'Favourites', 'favourites', 'Избранные вакансии');
        $this->title($helper, 'Responses', 'responses', 'Отклики');
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $this->title($helper, 'Favourites', 'favourites', 'favourites');
        $this->title($helper, 'Responses', 'responses', 'responses');
    }

    private function title($helper, string $name, string $table, string $title): void
    {
        $helper->Hlblock()->saveHlblock([
            'NAME' => $name,
            'TABLE_NAME' => $table,
            'LANG' => [
                'ru' => ['NAME' => $title],
                'en' => ['NAME' => $title],
            ],
        ]);
    }
}
