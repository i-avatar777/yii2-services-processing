<?php

namespace iAvatar777\services\Processing;

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
 * @property string password        хеш пароля
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

    public function getAddress()
    {
        return 'W_' . str_repeat('0', 8 - strlen($this->id)) . $this->id;
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
        $result = bcdiv($this->amount, bcpow(10, $currency->decimals), $currency->decimals);

        return $result;
    }

    /**
     * @return Currency
     */
    public function getCurrency()
    {
        if (is_null($this->_currency)) {
            $this->_currency = $this->_getCurrency($this->currency_id);
        }

        return $this->_currency;
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
     * @throws
     */
    public static function addNew($fields)
    {
        if (!isset($fields['amount'])) $fields['amount'] = '0';
        $i = new self($fields);
        $ret = $i->save();
        if (!$ret) throw new \Exception(\yii\helpers\VarDumper::dumpAsString($i->errors));
        $i->id = self::getDb()->lastInsertID;

        return $i;
    }

    public function rules()
    {
        return [
            [['amount'], 'required'],
            [['amount'], 'string', 'max' => 30],
            [['currency_id'], 'integer'],
            [['comment'], 'string', 'max' => 255],
        ];
    }

    /**
     * увеличивает свой кошелек и сохраняет (операция приход)
     *
     * @param string                              $amount      может быть только положительным
     * @param Transaction $transaction транзакция которой принадлежит эта операция
     * @param string|null                         $comment     комментарий для операции
     *
     * @return Operation
     *
     * @throws \Exception
     */
    public function add($amount, $transaction, $comment = null)
    {
        if (bccomp($amount, 0) == -1) throw new Exception('Нельзя прибавлять отрицательную сумму');
        $before       = $this->amount;
        $this->amount = bcadd($this->amount, $amount);
        $after        = $this->amount;
        $res          = $this->save();
        \Yii::info(\yii\helpers\VarDumper::dumpAsString([$res, $this->errors]), 'wallet\\wallet\\add');
        $fields = [
            'wallet_id'      => $this->id,
            'type'           => Operation::TYPE_IN,
            'datetime'       => microtime(true),
            'before'         => $before,
            'after'          => $after,
            'amount'         => $amount,
            'transaction_id' => $transaction->id,
        ];
        if ($comment) $fields['comment'] = $comment;
        $o = Operation::add($fields);

        return $o;
    }

    /**
     * увеличивает свой кошелек и сохраняет (операция эмиссия)
     *
     * @param string      $amount  может быть только положительным
     * @param string|null $comment комментарий для операции
     *
     * @return Operation
     *
     * @throws \Exception
     */
    public function in($amount, $comment = null)
    {
        if (bccomp($amount, 0) == -1) throw new Exception('Нельзя прибавлять отрицательную сумму');
        $before       = $this->amount;
        $this->amount = bcadd($this->amount, $amount);
        $after        = $this->amount;
        $res          = $this->save();
        $fields = [
            'wallet_id' => $this->id,
            'type'      => Operation::TYPE_EMISSION,
            'datetime'  => microtime(true),
            'before'    => $before,
            'after'     => $after,
            'amount'    => $amount,
        ];
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if ($comment) $fields['comment'] = $comment;
            $o = Operation::add($fields);

            $currency = $this->getCurrency();
            $currency->amount = bcadd($currency->amount, $amount);
            $currency->save();
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $o;
    }

    /**
     * уменьшает свой кошелек и сохраняет
     *
     * @param float                               $amount      может быть только положительным
     * @param Transaction $transaction транзакция которой принадлежит эта операция
     * @param string|null                         $comment     комментарий для операции
     *
     * @return Operation
     *
     * @throws Exception
     */
    public function sub($amount, $transaction, $comment = null)
    {
        if (bccomp($amount, 0) == -1) throw new Exception('Нельзя вычитать отрицательную сумму');
        if (bccomp($this->amount, $amount) == -1) throw new Exception('Попытка снять денег больше чем есть на счету');
        $before       = $this->amount;
        $this->amount = bcsub($this->amount, $amount);
        $after        = $this->amount;
        $this->save();
        $fields = [
            'wallet_id'      => $this->id,
            'type'           => Operation::TYPE_OUT,
            'datetime'       => microtime(true),
            'before'         => $before,
            'after'          => $after,
            'amount'         => $amount,
            'transaction_id' => $transaction->id,
        ];
        if ($comment) $fields['comment'] = $comment;
        $o = Operation::add($fields);

        return $o;
    }

    /**
     * Сжигает, уменьшает свой кошелек и сохраняет
     *
     * @param float       $amount  может быть только положительным
     * @param string|null $comment комментарий для операции
     *
     * @return Operation
     *
     * @throws Exception
     */
    public function out($amount, $comment = null)
    {
        if (bccomp($amount, 0) == -1) throw new Exception('Нельзя вычитать отрицательную сумму');
        if (bccomp($this->amount, $amount) == -1) throw new Exception('Попытка снять денег больше чем есть на счету');
        $before       = $this->amount;
        $this->amount = bcsub($this->amount, $amount);
        $after        = $this->amount;
        $this->save();
        $fields = [
            'wallet_id' => $this->id,
            'type'      => Operation::TYPE_BURN,
            'datetime'  => microtime(true),
            'before'    => $before,
            'after'     => $after,
            'amount'    => $amount,
        ];

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if ($comment) $fields['comment'] = $comment;
            $o = Operation::add($fields);

            $currency = $this->getCurrency();
            $currency->amount = bcsub($currency->amount, $amount);
            $currency->save();
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
        return $o;
    }

    /**
     * Осуществляет элементарную операцию движения средств с созданием транзакции.
     * Вычитает с кошелька источника.
     * Добавляет кошельку получателя.
     * Запиcывает трензакционную запись о переводе.
     *
     * @param int | Wallet | array $to      int - Идентификатор счета dbWallet.wallet.id
     *                                                              array - условие поиска в таблице dbWallet.wallet
     *                                                              Wallet - счет куда переводить
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
        $amount = (int) $amount;
        if (!is_object($to)) $to = self::findOne($to);
        /** @var Wallet $to */
        if ($to->currency_id != $this->currency_id) throw new Exception('Неверная валюта в кошельке назначения');
        if ($to->is_deleted) throw new Exception('Нельзя перевести монеты на удаленнный кошелек');
        if ($to->id == $this->id) throw new Exception('Нельзя перевести деньги самому себе');
        if ($amount == 0) throw new Exception('Нельзя перевести 0');
        if (bccomp($amount, 0) == -1) throw new Exception('Нельзя перевести отрицательную сумму');
        if (bccomp($this->amount, $amount) == -1) throw new Exception('Попытка снять денег больше чем есть на счету');

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
                $commentFrom        = $comment['from'];
                $commentTo          = $comment['to'];
            } else {
                $commentTransaction = $comment;
                $commentFrom        = $comment;
                $commentTo          = $comment;
            }
            if ($commentTransaction) {
                $fields['comment'] = $commentTransaction;
            }
            $t = Transaction::add($fields);
            $this->sub($amount, $t, $commentFrom);
            $to->add($amount, $t, $commentTo);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        return $t;
    }

    /**
     * Осуществляет элементарную операцию движения средств с созданием транзакции.
     * Вычитает с кошелька источника.
     * Добавляет кошельку получателя.
     * Запиcывает трензакционную запись о переводе.
     *
     * @param int | Wallet | array $to      int - Идентификатор счета dbWallet.wallet.id
     *                                                              array - условие поиска в таблице dbWallet.wallet
     *                                                              Wallet - счет куда переводить
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
     * @return array
     * [
     *  'transaction'   => '\iAvatar777\services\Processing\Transaction'
     *  'operation_add' => '\iAvatar777\services\Processing\Operation'
     *  'operation_sub' => '\iAvatar777\services\Processing\Operation'
     * ]
     *
     * @throws \Exception
     */
    public function move2($to, $amount, $comment = null, $type = null)
    {
        if ($this->is_deleted) {
            throw new Exception('Нельзя перевести деньги из удаленного кошелька');
        }
        $amount = (int) $amount;
        if (!is_object($to)) $to = self::findOne($to);
        /** @var Wallet $to */
        if ($to->currency_id != $this->currency_id) throw new Exception('Неверная валюта в кошельке назначения');
        if ($to->is_deleted) throw new Exception('Нельзя перевести монеты на удаленнный кошелек');
        if ($to->id == $this->id) throw new Exception('Нельзя перевести деньги самому себе');
        if ($amount == 0) throw new Exception('Нельзя перевести 0');
        if (bccomp($amount, 0) == -1) throw new Exception('Нельзя перевести отрицательную сумму');
        if (bccomp($this->amount, $amount) == -1) throw new Exception('Попытка снять денег больше чем есть на счету');

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
                $commentFrom        = $comment['from'];
                $commentTo          = $comment['to'];
            } else {
                $commentTransaction = $comment;
                $commentFrom        = $comment;
                $commentTo          = $comment;
            }
            if ($commentTransaction) {
                $fields['comment'] = $commentTransaction;
            }
            $t = Transaction::add($fields);
            $operation_sub = $this->sub($amount, $t, $commentFrom);
            $operation_add = $to->add($amount, $t, $commentTo);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        return [
            'transaction' => $t,
            'operation_add' => $operation_add,
            'operation_sub' => $operation_sub,
        ];
    }


    public function delete()
    {
        if ($this->amount > 0) {
            // Перевожу на дежурный кошелек
            $defaultWallet = WalletDefault::findOne(['currency_id' => $this->currency_id]);
            if (is_null($defaultWallet)) throw new Exception('Нет кошелька по умолчанию');
            $this->move($defaultWallet->wallet_id, $this->amount, 'Возврат вследствии удаления кошелька');
        }

        $this->is_deleted = 1;
        $this->save();

        return true;
    }
}