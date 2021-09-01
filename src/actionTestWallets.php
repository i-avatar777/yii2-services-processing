<?php

namespace iAvatar777\services\Processing;

use yii\base\Action;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\httpclient\Client;

/**
 * проверяет балансы процессинга
 */
class actionTestWallets extends \iAvatar777\services\Processing\Action
{
    public $validateHash = false;

    /**
     */
    public function run()
    {
        // Проверяю балансы операций
        // Проверяю Контрольную сумму в операциях
        foreach (\iAvatar777\services\Processing\Operation::find()->all() as $item) {
            $v = \yii\helpers\ArrayHelper::getValue($item, 'type');
            if ($v < 0) $i = -1;
            else $i = 1;
            $sum = $item['after'] - $item['before'] - ($i * $item['amount']);
            if ($sum != 0) {
                //$this->sendToTelegram('balance op_id='.$item->id . ' not summed');
            }
            if ($this->validateHash) {
                if ($item->hash() != $item->hash) {
                    self::logDanger('HASH op_id='.$item->id . ' not summed');
                }
            }
        }
        self::logSuccess( 'Operations succesed.');

        if ($this->validateHash) {
            // Проверяю Контрольную сумму в транзакциях
            foreach (\iAvatar777\services\Processing\Transaction::find()->all() as $item) {
                if ($item->hash() != $item->hash) {
                    self::logDanger( 'HASH tx_id=' . $item->id . ' not summed');
                }
            }
            self::logSuccess( 'Transactions succesed.' );
        }

        // Проверяю балансы в dbWallet.currency
        $data = (new \yii\db\Query())->createCommand(\Yii::$app->dbWallet)->setSql('select t1.am1,t1.currency_id,currency.amount, currency.amount - t1.am1 as balance FROM (
select sum(wallet.amount) as am1,currency_id from wallet GROUP BY currency_id
) as t1
INNER JOIN currency on (currency.id = t1.currency_id)')->queryAll();
        foreach ($data as $item) {
            if ($item['balance'] != 0) {
                self::logDanger( 'balance dbWallet.currency.id=' . $item['currency_id'] . ' not summed' . ' balance= ' . $item['balance']);
                //$this->sendToTelegram('balance dbWallet.currency.id=' . $item['currency_id'] . ' not summed' . ' balance= ' . $item['balance']);
            }
        }

        // Проверяю кошельки
        $rows = \iAvatar777\services\Processing\Wallet::find()->all();
        /** @var \iAvatar777\services\Processing\Wallet $item */
        foreach ($rows as $item) {
            $operationList = Operation::find()->where(['wallet_id' => $item['id']])->orderBy(['datetime' => SORT_ASC])->all();
            $sum = 0;
            /** @var \iAvatar777\services\Processing\Operation $o */
            foreach ($operationList as $o) {
                if (in_array($o->type, [Operation::TYPE_IN, Operation::TYPE_EMISSION])) {
                    $sum += $o->amount;
                }
                if (in_array($o->type, [Operation::TYPE_OUT, Operation::TYPE_BURN])) {
                    $sum -= $o->amount;
                }
            }
            if ($item['amount'] != $sum) {
                self::logDanger( 'balance wid=' . $item['id'] . ' not summed' . ' amount= ' . $item['amount'] . ' sum=' . $sum);
                //$this->sendToTelegram('balance wid=' . $item['id'] . ' not summed' . ' amount= ' . $item['amount'] . ' sum=' . $sum);
            }
        }
    }

}