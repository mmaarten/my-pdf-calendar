<?php

namespace My\PDFCalendar;

class Helpers
{
    public static function timeToHours($time)
    {
        list($h, $m, $s) = explode(':', $time);

        $h = intval($h);
        $m = intval($m);
        $s = intval($s);

        $return = $h;

        if ($m) {
            $return += $m / 60;
        }

        if ($s) {
            $return += $s / (60 * 60);
        }

        return $return;
    }

    public static function hoursToTime($decimal)
    {
        $hours = floor($decimal);
        $minutes = ($decimal - $hours) * 60;

        return str_pad($hours, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutes, 2, "0", STR_PAD_LEFT);
    }

    public static function scaleToFit($width, $height, $max_width, $max_height)
    {
        $scale = min($max_width / $width, $max_width / $height);

        $width *= $scale;
        $height *= $scale;

        return compact('width', 'height');
    }

    /**
     * @link https://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    public static function getDaysBetween($date_1, $date_2)
    {
        $date_1 = strtotime($date_1);
        $date_2 = strtotime($date_2);

        $diff = abs($date_1 - $date_2);

        return ceil($diff / (60 * 60 * 24));
    }
}
