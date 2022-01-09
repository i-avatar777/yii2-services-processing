<?php

namespace iAvatar777\services\Processing;

use app\services\Subscribe;
use cs\Application;
use cs\services\BitMask;
use cs\web\Exception;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\VarDumper;

/**
 * Элементарная операция с кошельком
 * Всего их две:
 * - снять деньги
 * - положить деньги
 * За это отвечают поле `type` и константы self::IN и self::OUT
 * содержит информацию о кошельке с которым производится операция
 *
 * @property int    id
 * @property int    wallet_id      - идентификатор кошелька
 * @property int    transaction_id - транзакция к которой принадлежит данная операция
 * @property int    type           - тип лперации
 * @property int    datetime       - время операции мс
 * @property float  before         - размер кошелька до операции
 * @property float  after          - размер кошелька после операции
 * @property float  amount         - денежный размер операции
 * @property string address        -
 * @property string hash
 * @property string comment        - комментарий для операции, чтобы владельцу кошелька было понятно что это за
 *           операция
 *
 *
 * Class Operation
 */
class Operation extends ActiveRecord
{
    const TYPE_EMISSION = 2;
    const TYPE_IN = 1;
    const TYPE_OUT = -1;
    const TYPE_BURN = -2;

    public static function tableName()
    {
        return 'operations';
    }

    public function getAddress()
    {
        return 'O_' . str_repeat('0', 20 - strlen($this->id)) . $this->id;
    }

    public function getAddressShort()
    {
        $id = (string)$this->id;
        if (strlen($id) > 4) {
            $last4 = substr($id, strlen($id) - 4);
        } else {
            $last4 = str_repeat('0', 4 - strlen($id)) . $id;
        }

        return 'O_...' . $last4;
    }

    public static function getDb()
    {
        return \Yii::$app->dbWallet;
    }

    public function rules()
    {
        return [
            [['wallet_id', 'before', 'after', 'amount', 'type', 'datetime'], 'required'],
            [['wallet_id', 'transaction_id', 'type'], 'integer'],

            ['datetime', 'integer'],

            ['before', 'validateAmount'],
            ['after', 'validateAmount'],
            ['amount', 'validateAmount'],

            ['comment', 'string'],
            ['address', 'string', 'max' => 70],
            ['hash', 'string', 'max' => 64],
        ];
    }

    /**
     * @param int|double|string $value
     *
     * @return bool
     */
    public static function isDouble($value)
    {
        if (is_integer($value)) return true;
        if (self::isInteger($value)) return true;
        if (preg_match('/[0-9-.]/', $value)) return true;

        return false;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function isInteger($value)
    {
        if (is_integer($value)) return true;
        if (is_array($value)) return false;
        if (preg_match('/[0-9-]/', $value)) return true;

        return false;
    }

    public function validateAmount($a, $p)
    {
        if (!$this->hasErrors()) {
            if (!self::isDouble($this->amount)) {
                $this->addError($a, 'Это не Double');
                return;
            }
        }
    }

    public function hash()
    {
        return hash('sha256', $this->comment . $this->before . $this->after . $this->amount . $this->type . $this->id . $this->wallet_id . $this->transaction_id . ((int)$this->datetime));
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
        if (!$ret) throw new \Exception('\iAvatar777\services\Processing\Operation::add');

        return $iAm;
    }


}