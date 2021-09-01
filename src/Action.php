<?php


namespace iAvatar777\services\Processing;

use yii\helpers\VarDumper;

class Action extends \yii\base\Action
{
    const MARK_GREEN = 1;
    const MARK_RED   = 2;

    public  $isLog = true;

    /**
     * Возвращает значение параметра isLog который может передаваться через консольную строку запуска процедуры
     * По умолчанию если параметр не указан то подразумевается что параметр = true
     * пример консоли `yii statistic/start isLog=false`
     *
     * @return bool
     */
    protected function getIsLog()
    {
        $isLog = 'true';
        $params = $_SERVER['argv'];
        foreach ($params as $param) {
            $arr = explode('=', $param);
            if (count($arr) == 2) {
                if ($arr[0] == 'isLog') {
                    $isLog = $arr[1];
                }
            }
        }
        if ($isLog == 'false') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Уставнавливает переменную контроллера `$this->isLog` на значение которое указывается в консоли
     * пример консоли `yii statistic/start isLog=false`
     */
    protected function setIsLog()
    {
        $this->isLog = $this->getIsLog();
    }

    /**
     * Выводит сообщение об успехе и завершает приложение с кодом 0
     *
     * @param string $data
     */
    public function success($data)
    {
        $this->logSuccess($data);
        \Yii::$app->end();
    }

    /**
     * Выводит сообщение об ошибке и завершает приложение с соответствующим кодом
     *
     * @param int $id
     * @param string $data
     */
    public function error($id, $data)
    {
        $this->logDanger($data);
        \Yii::$app->end($id);
    }

    public function logSuccess($text)
    {
        $this->log($text, self::MARK_GREEN);
    }

    /**
     * Выводит сообщение в STDOUT зеленого цвета с присоединением в конец строки `\Yii::$app->formatter->asDecimal(microtime(true) - $time, 2)`
     * завершает приложение с кодом 0
     *
     * @param string $text
     * @param double $time
     */
    public function successTime($text, $time)
    {
        $this->log($text . \Yii::$app->formatter->asDecimal(microtime(true) - $time, 2), self::MARK_GREEN);
        \Yii::$app->end();
    }

    public function logDanger($text)
    {
        $this->log($text, self::MARK_RED);
    }

    /**
     * Выводит собщение на экран
     *
     * @param $text
     * @param null|int $mark self::MARK_*
     *
     * @return string
     */
    public function log($text, $mark = null)
    {
        if ($this->isLog) {
            if (!is_string($text))  {
                $text = VarDumper::dumpAsString($text);
            }
            if ($mark) {
                if ($mark == self::MARK_GREEN) {
                    $text = self::markGreen($text);
                }
                if ($mark == self::MARK_RED) {
                    $text = self::markRed($text);
                }
                echo $text . "\n";
            } else {
                echo iconv('utf-8', 'windows-1251', $text) . "\n";
            }
        }
    }

    /**
     * Выводит сообщение в STDOUT с присоединением в конец строки `\Yii::$app->formatter->asDecimal(microtime(true) - $time, 2)`
     *
     * @param string $text
     * @param double $time
     */
    public function logTime($text, $time)
    {
        $this->log($text . \Yii::$app->formatter->asDecimal(microtime(true) - $time, 2));
    }

    private function markGreen($text)
    {
        $text = "\x1b[32m" . iconv('utf-8', 'windows-1251', $text) . "\x1b[37m";

        return $text;
    }

    private function markRed($text)
    {
        $text = "\x1b[31m" . iconv('utf-8', 'windows-1251', $text) . "\x1b[37m";

        return $text;
    }
}