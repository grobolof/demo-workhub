<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;
use CIBlock;
use CIBlockElement;
use CFile;

class RequestService
{
    private const MAX_FILES = 10;

    private int $iblock = 0;

    /** @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     *  @return array{id: int}
     */
    public function create(string $email, string $subject, string $message, array $files): array
    {
        $email = mb_strtolower(trim($email));
        $subject = trim($subject);
        $message = trim($message);
        if (!check_email($email, true)) {
            throw new ApiException('Укажите корректный email');
        }
        if ($subject === '') {
            throw new ApiException('Укажите тему');
        }
        if (mb_strlen($subject) > 255) {
            throw new ApiException('Тема не должна быть длиннее 255 символов');
        }
        if ($message === '') {
            throw new ApiException('Укажите сообщение');
        }
        if (mb_strlen($message) > 10000) {
            throw new ApiException('Сообщение слишком длинное');
        }

        $prepared = $this->files($files);
        $values = [
            'EMAIL' => $email,
            'MESSAGE' => $message,
        ];
        if ($prepared !== []) {
            $values['FILES'] = $prepared;
        }

        $element = new CIBlockElement();
        $id = $element->Add([
            'IBLOCK_ID' => $this->iblockId(),
            'NAME' => $subject,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => $values,
        ]);
        if (!$id) {
            throw new ApiException(ApiException::plain((string)$element->LAST_ERROR));
        }

        return ['id' => (int)$id];
    }

    /** @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     *  @return list<array{VALUE: array<string, mixed>}>
     */
    private function files(array $files): array
    {
        $prepared = [];
        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new ApiException('Не удалось загрузить файл');
            }

            $name = basename(str_replace('\\', '/', $file['name']));
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
                throw new ApiException('Можно прикрепить только файлы PNG, JPG и JPEG');
            }
            if ($file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
                throw new ApiException('Не удалось прочитать файл');
            }

            $info = @getimagesize($file['tmp_name']);
            if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                throw new ApiException('Можно прикрепить только изображения PNG, JPG и JPEG');
            }
            if (count($prepared) >= self::MAX_FILES) {
                throw new ApiException('Можно прикрепить не больше 10 файлов');
            }

            $stored = CFile::MakeFileArray($file['tmp_name']);
            if (!is_array($stored)) {
                throw new ApiException('Не удалось прочитать файл');
            }
            $stored['name'] = $name;
            $prepared[] = ['VALUE' => $stored];
        }

        return $prepared;
    }

    private function iblockId(): int
    {
        if ($this->iblock > 0) {
            return $this->iblock;
        }

        $row = CIBlock::GetList([], [
            'TYPE' => 'system',
            'CODE' => 'requests',
            'CHECK_PERMISSIONS' => 'N',
        ])->Fetch();
        if (!$row) {
            throw new ApiException('Инфоблок «Заявки» ещё не создан. Выполните миграции.', 500);
        }

        $this->iblock = (int)$row['ID'];

        return $this->iblock;
    }
}
