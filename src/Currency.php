<?php

namespace iAvatar777\services\Processing;

use common\models\CurrencyIO;
use common\models\PaySystem;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Json;
use yii\helpers\VarDumper;

/**
 * @property int    id
 * @property string name
 * @property string code
 * @property string address
 * @property int    amount
 * @property int    decimals
 * @property int    decimals_view
 * @property int    decimals_view_shop
 */
class Currency extends ActiveRecord
{
    const VVB = 10;
    const SB = 12;
    const SBP = 11;
    const LETM = 10;
    const MARKET = 10;
    const LEGAT = 12;
    const LETN = 11;

    const PZM = 13;
    const ETH = 14;
    const USDT = 15;

    const EUR = 20;
    const RUB = 17;
    const UAH = 18;
    const BYN = 19;
    const INR = 27;
    const IDR = 28;
    const CNY = 29;

    const LTC = 21;
    const BTC = 22;
    const DASH = 23;
    const BNB = 24;
    const NEIRO = 25;
    const EGOLD = 26;
    const TRX = 30;
    const DAI = 31;
    const LOT = 33;
    const DOGE = 32;

    public static function tableName()
    {
        return 'currency';
    }

    /**
     * @param int | \common\models\avatar\Currency $id
     *
     * @return \common\models\piramida\Currency
     */
    public static function getFromExt($id)
    {
        /** @var \common\models\avatar\Currency $c */
        $c = null;
        if (!($id instanceof \common\models\avatar\Currency)) {
            $c = \common\models\avatar\Currency::findOne($id);
        } else {
            $c = $id;
        }
        $cio = CurrencyIO::findOne(['currency_ext_id' => $c->id]);
        $cInt = Currency::findOne($cio->currency_int_id);

        return $cInt;
    }

    /**
     * @param int $value
     * @param int | \common\models\piramida\Currency $id
     *
     * @return float
     */
    public static function getValueFromAtom($value, $id)
    {
        /** @var \common\models\piramida\Currency $c */
        $c = null;
        if (!($id instanceof \common\models\piramida\Currency)) {
            $c = \common\models\piramida\Currency::findOne($id);
        } else {
            $c = $id;
        }
        return bcdiv($value, pow(10, $c->decimals), $c->decimals);
    }

    /**
     * @param int | \common\models\avatar\Currency $id
     *
     * @return self
     * @throws
     */
    public static function initFromCurrencyExt($id)
    {
        $id1 = null;
        if ($id instanceof \common\models\avatar\Currency) {
            $id1 = $id->id;
        } else {
            if (Application::isInteger($id)) {
                $id1 = $id;
            } else {
                throw new \Exception('Не верно задан параметр');
            }
        }

        $cio = CurrencyIO::findOne(['currency_ext_id' => $id1]);

        return self::findOne($cio->currency_int_id);
    }

    /**
     * @param float $value
     * @param int | \common\models\piramida\Currency $id
     *
     * @return int
     */
    public static function getAtomFromValue($value, $id)
    {
        /** @var \common\models\piramida\Currency $c */
        $c = null;
        if ($id instanceof \common\models\piramida\Currency) {
            $c = $id;
        } else {
            if (Application::isInteger($id)) {
                $c = \common\models\piramida\Currency::findOne($id);
            } else {
                throw new \Exception('Не верно задан параметр');
            }
        }

        return bcmul($value, pow(10, $c->decimals));
    }

    /**
     * @param float $value
     * @param int $from
     * @param int $to
     *
     * @return int
     */
    public static function convertAtom($value, $from, $to)
    {
        /** @var \common\models\piramida\Currency $from */
        $fromObject = \common\models\piramida\Currency::findOne($from);
        $toObject = \common\models\piramida\Currency::findOne($to);
        if ($fromObject->decimals == $toObject->decimals) {
            return  $value;
        }
        if ($fromObject->decimals < $toObject->decimals) {
            $v = bcmul($value, pow(10, $toObject->decimals - $fromObject->decimals));
        }
        if ($fromObject->decimals > $toObject->decimals) {
            $v = bcdiv($value, pow(10, $fromObject->decimals - $toObject->decimals), $toObject->decimals);
        }

        return $v;
    }

    public function getAddress()
    {
        return 'C_' . str_repeat('0', 10 - strlen($this->id)) . $this->id;
    }

    public function getAddressShort()
    {
        $id = (string)$this->id;
        if (strlen($id) > 4) {
            $last4 = substr($id, strlen($id) - 4);
        } else {
            $last4 = str_repeat('0', 4 - strlen($id)) . $id;
        }

        return 'C_...' . $last4;
    }

    public static function getDb()
    {
        return \Yii::$app->dbWallet;
    }

    public function rules()
    {
        return [
            [[
                'decimals',
                'name',
                'code',
            ], 'required'],
            [[
                'decimals',
                'decimals_view',
                'amount',
            ], 'integer'],
            ['code', 'string', 'max' => 10],
            ['name', 'string', 'max' => 100],
            ['address', 'string', 'max' => 70],
        ];
    }

    public function convert($atom)
    {
        return $atom / (pow(10, $this->decimals));
    }


    /**
     * @param array $fields
     *
     * @return self
     * @throws
     */
    public static function add($fields)
    {
        if (!isset($fields['amount'])) $fields['amount'] = 0;
        $i = new self($fields);
        $i->save();

        return $i;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        $result = parent::save($runValidation, $attributeNames); // TODO: Change the autogenerated stub
        if ($this->isNewRecord) {
            $id = self::getDb()->lastInsertID;
            $this->id = $id;
        }

        return $result;
    }

}