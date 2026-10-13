<?php

declare(strict_types=1);

namespace App\UserField;

use Bitrix\Main\UserField\Types\IntegerType;
use Bitrix\Main\UserTable;
use CUserTypeManager;

class UserLink extends IntegerType
{
    public const USER_TYPE_ID = 'workhub_user';

    public static function getDescription(): array
    {
        return [
            'DESCRIPTION' => 'Привязка к пользователю',
            'BASE_TYPE' => CUserTypeManager::BASE_TYPE_INT,
        ];
    }

    public static function checkFields(array $userField, $value): array
    {
        $errors = parent::checkFields($userField, $value);
        if ($errors !== [] || $value === '' || $value === null) {
            return $errors;
        }

        $user = UserTable::getRow([
            'filter' => ['=ID' => (int)$value],
            'select' => ['ID'],
        ]);
        if (!$user) {
            $errors[] = [
                'id' => $userField['FIELD_NAME'],
                'text' => 'Пользователь не найден',
            ];
        }

        return $errors;
    }
}
