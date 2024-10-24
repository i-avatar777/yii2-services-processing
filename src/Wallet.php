<?php


namespace iAvatar777\services\Processing;

use common\services\Curr;
use cs\services\VarDumper;
use Exception;
use yii\db\ActiveRecord;

/**
 * 1. Что делает?
 * Описывает класс кошелька
 *
 * 2. какие есть свойства объекта?
 *
 * @property int    $id             Идентификатор кошелька
 * @property float  amount          Кол-во денег в кошельке
 * @property int    currency_id     Валюта
 * @property int    is_deleted      Флаг. Кошелек удален? 0 - рабочий кошелек. 1 - кошелек удален. 0 - по умолчанию.
 * @property string comment         Валюта
 * @property string address         Адрес счета
 *
 * 3. кикими функциями обладает?
 * Имеет только три функции
 * - Ввести деньги из внешней системы
 * - Вывести деньги на внешнюю систему
 * - Перевести средства внутри системы
 *
 * Во время каждой функции создается транзакция и операции для нее
 * Операций для "Ввести" и "Вывести" всего одна (начисление или вычитание) так как не указывается второй кошелек,
 * так как он вне системы
 *
 * Class Wallet
 *
 *
 * @package app\models\Piramida
 */
class Wallet extends ActiveRecord
{
    const EVENT_TRANSACTION = 'transaction';
    const EVENT_EMISSION = 'emission';
    const EVENT_BURN = 'burn';

    /** @var  Currency */
    private $_currency;

    public static function tableName()
    {
        return 'wallet';
    }

    public static function getDb()
    {
        return \Yii::$app->dbWallet;
    }

    public function getAmount()
    {
        $c = $this->getCurrency();

        return bcdiv($this->amount, bcpow(10, $c->decimals), $c->decimals);
    }

    public function getAddress()
    {
        return 'W_' . str_repeat('0', 20 - strlen($this->id)) . $this->id;
    }

    public function getAddressShort()
    {
        $id = (string)$this->id;
        if (strlen($id) > 4) {
            $last4 = substr($id, strlen($id) - 4);
        } else {
            $last4 = str_repeat('0', 4 - strlen($id)) . $id;
        }

        return 'W_...' . $last4;
    }

    public function getAmountWithDecimals()
    {
        $currency = $this->getCurrency();
        if (is_null($currency)) VarDumper::dump($this);

        return $this->amount / pow(10, $currency->decimals);
    }

    /**
     * @return Currency
     */
    public function getCurrency()
    {
        return Curr::getInstance()->int($this->currency_id);
    }

    /**
     * @return Currency
     */
    public function _getCurrency($id)
    {
        return Currency::findOne($id);
    }

    /**
     * @param array $fields
     *
     * @return self
     */
    public static function addNew($fields)
    {
        if (!isset($fields['amount'])) $fields['amount'] = 0;
        $i = new self($fields);
        $ret = $i->save();
        if (!$ret) throw new \Exception('\common\models\piramida\Wallet::add');
        $i->id = self::getDb()->lastInsertID;
        $address = 'W_0x' . hash('sha256', $i->id);
        $i->address = $address;
        $ret = $i->save();
        if (!$ret) throw new \Exception('\common\models\piramida\Wallet::add');

        return $i;
    }

    public function rules()
    {
        return [
            [['amount'], 'required'],
            [['amount'], 'integer'],
            [['currency_id'], 'integer'],
            [['comment'], 'string', 'max' => 255],
        ];
    }

    /**
     * увеличивает свой кошелек и сохраняет
     *
     * @param float                               $amount      может быть только положительным
     * @param Transaction                         $transaction транзакция которой принадлежит эта операция
     * @param string|null                         $comment     комментарий для операции
     *
     *
     * @throws \Exception
     */
    public function add($amount, $transaction, $comment = null)
    {
        if ($amount < 0) throw new Exception('Нельзя прибавлять отрицательную сумму');
        $this->amount += $amount;
        $res          = $this->save();


        return $res;
    }

    /**
     * увеличивает свой кошелек и сохраняет
     *
     * @param float       $amount  может быть только положительным
     * @param string|null $comment комментарий для операции
     *
     * @return Transaction
     *
     * @throws \Exception
     */
    public function in($amount, $comment = null)
    {
        if ($amount < 0) throw new Exception('Нельзя прибавлять отрицательную сумму');
        $this->amount += $amount;
        $res          = $this->save();
        \Yii::info(\yii\helpers\VarDumper::dumpAsString([$res, $this->errors]), 'wallet\\wallet\\add');

        $fields = [
            'to'        => $this->id,
            'datetime'  => microtime(true),
            'amount'    => $amount,
        ];
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if ($comment) $fields['comment'] = $comment;
            $t = Transaction::add($fields);

            $this->add($amount, $t, '');

            $currency = $this->getCurrency();
            $currency->amount += $amount;
            $currency->save();
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $t;
    }

    /**
     * уменьшает свой кошелек и сохраняет
     *
     * @param float                               $amount      может быть только положительным
     * @param Transaction $transaction транзакция которой принадлежит эта операция
     * @param string|null                         $comment     комментарий для операции
     *
     *
     * @throws Exception
     */
    public function sub($amount, $transaction, $comment = null)
    {
        if ($amount < 0) throw new Exception('Нельзя вычитать отрицательную сумму');
        if ($this->amount < $amount) throw new Exception('Попытка снять денег больше чем есть на счету');
        $this->amount -= $amount;


        return $this->save();
    }

    /**
     * уменьшает свой кошелек и сохраняет
     *
     * @param float       $amount  может быть только положительным
     * @param string|null $comment комментарий для операции
     *
     * @return Transaction
     *
     * @throws Exception
     */
    public function out($amount, $comment = null)
    {
        if ($amount < 0) throw new Exception('Нельзя вычитать отрицательную сумму');
        if ($this->amount < $amount) throw new Exception('Попытка снять денег больше чем есть на счету');
        $this->amount -= $amount;
        $this->save();

        $fields = [
            'from'      => $this->id,
            'datetime'  => microtime(true),
            'amount'    => $amount,
        ];

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if ($comment) $fields['comment'] = $comment;
            $t = Transaction::add($fields);

            $this->sub($amount, $t, '');

            $currency = $this->getCurrency();
            $currency->amount -= $amount;
            $currency->save();
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $t;
    }

    /**
     * Осоуществляет элементарную операцию движения средств с созданием транзакции.
     * Вычитает с кошелька источника.
     * Добавляет кошельку получателя.
     * Запиcывает трензакционную запись о переводе.
     *
     * @param int | Wallet | array $to      int - Идентификатор счета i_am_avatar_prod_wallet.wallet.id
     *                                                              array - условие поиска в таблице i_am_avatar_prod_wallet.wallet
     *                                                              \common\models\Piramida\Wallet - счет куда переводить
     * @param int                                          $amount                  может быть только положительным
     * @param string | array                               comment  комментарий для перевода. Если строка, то
     *                                                              подразумевается что это один коментарий для трех
     *                                                              типов, иначе в массиве можно указать разные
     *                                                              комментарии для трех типов комментариев
     *                                                              [
     *                                                              'from'        => string
     *                                                              'to'          => string
     *                                                              'transaction' => string
     *                                                              ]
     * @param int                                          $type    тип транзакции
     *
     * @return Transaction
     *
     * @throws \Exception
     */
    public function move($to, $amount, $comment = null, $type = null)
    {
        if ($this->is_deleted) {
            throw new Exception('Нельзя перевести деньги из удаленного кошелька');
        }
        if (!is_object($to)) $to = self::findOne($to);
        /** @var Wallet $to */
        if ($to->currency_id != $this->currency_id) throw new Exception('Неверная валюта в кошельке назначения');
        if ($to->is_deleted) throw new Exception('Нельзя перевести монеты на удаленнный кошелек');
        if ($to->id == $this->id) throw new Exception('Нельзя перевести деньги самому себе');

        if ($amount < 0) throw new Exception('Нельзя вычитать отрицательную сумму');
        if ($this->amount < $amount) throw new Exception('Попытка снять денег больше чем есть на счету');

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // делаю перевод
            $fields = [
                'from'      => $this->id,
                'to'        => $to->id,
                'amount'    => $amount,
                'type'      => $type,
            ];
            if (is_array($comment)) {
                $commentTransaction = $comment['transaction'];
            } else {
                $commentTransaction = $comment;
            }
            if ($commentTransaction) {
                $fields['comment'] = $commentTransaction;
            }
            $t = Transaction::add($fields);

            $this->sub($amount, $t, '');
            $to->add($amount, $t, '');

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        return $t;
    }

    public function delete()
    {
        $this->is_deleted = 1;
        $this->save();

        return true;
    }
}