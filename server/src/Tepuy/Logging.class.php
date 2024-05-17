<?php
namespace Tepuy;

class Logging {

    const LVL_ALL = 10;
    const LVL_DETAIL = 20;
    const LVL_DEBUG = 30;

    public static function trace($level, $text, $o = null) {
        global $CFG;

        if ($level <= self::LVL_ALL) {
            self::print($text, $o);
        } else {

            if ($CFG->debugdisplay) {

                if ($level <= self::LVL_DETAIL && $CFG->debug >= 15) {
                    self::print($text, $o);
                } else if ($CFG->debug >= 32767) {
                    self::print($text, $o);
                }
            }
        }

    }

    private static function print($text, $o) {
        echo "\n" . date('Y-m-d H:i:s') . '::: ' . $text;

        if ($o) {
            echo "\n-----------------------------\n";
            var_dump($o);
            echo "-----------------------------";
        }
    }

}
