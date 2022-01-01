<?php

namespace iAvatar777\services\Processing;

use cs\services\BitMask;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\VarDumper;

/**
 * 1. Что делает?
 * Хранит информацию о транзакции
 * Содержит
 * кошелек отправителя `from`
 * кошелек получателя `to`
 * сумма перевода `summa`
 * время перевода `datetime`
 * комментарий для транзакции comment например "принадлежит бизнес процессу ##", макс 1000 символов
 *
 * @property int    type
 * @property int    id
 * @property int    from
 * @property int    to
 * @property int    datetime мс
 * @property double amount
 * @property string address
 * @property string hash
 * @property string comment
 *
 * Class Transaction
 */
class Transaction extends ActiveRecord
{
    const TYPE_REQUEST_OUT = 1;


    public static function tableName()
    {
        return 'transactions';
    }


    public function getAddress()
    {
        return 'T_' . str_repeat('0', 20 - strlen($this->id)) . $this->id;
    }

    public function getAddressShort()
    {
        $id = (string)$this->id;
        if (strlen($id) > 4) {
            $last4 = substr($id, strlen($id) - 4);
        } else {
            $last4 = str_repeat('0', 4 - strlen($id)) . $id;
        }

        return 'T_...' . $last4;
    }

    public static function getDb()
    {
        return \Yii::$app->dbWallet;
    }

    public function rules()
    {
        return [
            [['amount', 'datetime'], 'required'],
            [['from', 'to', 'type'], 'integer'],
            ['datetime', 'integer'],
            ['address', 'string'],
            ['amount', 'validateAmount'],
        ];
    }

    public function validateAmount($a, $p)
    {
        if (!$this->hasErrors()) {
            if (!Application::isDouble($this->amount)) {
                $this->addError($a, 'Это не Double');
                return;
            }
        }
    }

    public function hash()
    {
        return hash('sha256', $this->comment . $this->from . $this->to . $this->amount . $this->type . $this->id . ((int)$this->datetime));
    }

    public static function add($fields)
    {
        $fields['datetime'] = (int)(microtime(true) * 1000);
        $iAm = new self($fields);
        $ret = $iAm->save();
        if (!$ret) throw new \Exception(VarDumper::dumpAsString($iAm));
        $iAm->id = self::getDb()->lastInsertID;
        $iAm->hash = $iAm->hash();
        $ret = $iAm->save();
        if (!$ret) throw new \Exception('\iAvatar777\services\Processing\Transaction::add');

        return $iAm;
    }
}