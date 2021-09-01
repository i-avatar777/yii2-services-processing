<?php

namespace iAvatar777\services\Processing;

use cs\services\BitMask;
use cs\web\Exception;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * @property int    id
 * @property string login
 * @property string secret
 * @property string name
 * @property int    created_at
 */
class Login extends ActiveRecord
{
    public static function tableName()
    {
        return 'login';
    }

    public static function getDb()
    {
        return \Yii::$app->dbWallet;
    }

    public function rules()
    {
        return [
            ['id', 'integer'],
            ['created_at', 'integer'],
            ['login', 'string', 'max' => 64],
            ['name', 'string', 'max' => 100],
            ['secret', 'string', 'max' => 64],
        ];
    }

    public static function add($fields)
    {
        $iAm = new self($fields);
        $ret = $iAm->save();
        if (!$ret) throw new \Exception('\common\models\piramida\Login::add');
        $iAm->id = self::getDb()->lastInsertID;

        return $iAm;
    }
}