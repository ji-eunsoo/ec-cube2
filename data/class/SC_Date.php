<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

/* 日時表示用クラス */
class SC_Date
{
    public $start_year;
    public $month;
    public $day;
    public $end_year;

    public static $arrHoliday;
    public static $arrRegularHoliday;

    // コンストラクタ
    public function __construct($start_year = '', $end_year = '')
    {
        if ($start_year) {
            $this->setStartYear($start_year);
        }
        if ($end_year) {
            $this->setEndYear($end_year);
        }
    }

    public function setStartYear($year)
    {
        $this->start_year = $year;
    }

    public function getStartYear()
    {
        return $this->start_year;
    }

    /**
     * @param string $endYear
     */
    public function setEndYear($endYear)
    {
        $this->end_year = $endYear;
    }

    public function getEndYear()
    {
        return $this->end_year;
    }

    public function setMonth($month)
    {
        $this->month = $month;
    }

    public function setDay($day)
    {
        $this->day = $day;
    }

    /**
     * 年プルダウン用の配列を返す
     * FIXME $default_year に一致いる行が無かった場合、先頭か末尾に付加すべきと思われる。
     *
     * @param string      $year         XMLファイル名
     * @param bool|string $default_year
     *     false  「選択なし」は含めない。
     *     true   「選択なし」は含める。
     *     string 「選択なし」は指定された値の下に付加する。
     * @param string $default_key
     */
    public function getYear($year = '', $default_year = false, $default_key = '----')
    {
        if ($year) {
            $this->setStartYear($year);
        }

        $year = $this->start_year;
        if (!$year) {
            $year = date('Y');
        }

        $end_year = $this->end_year;
        if (!$end_year) {
            $end_year = (date('Y') + 3);
        }

        $year_array = [];

        if ($default_year === true) {
            $year_array[$default_key] = '----';
        }

        for ($i = $year; $i <= $end_year; $i++) {
            $year_array[$i] = $i;
            if ($default_year !== true && strlen($default_year) >= 1 && $i == $default_year) {
                $year_array[$default_key] = '----';
            }
        }

        return $year_array;
    }

    public function getZeroYear($year = '')
    {
        if ($year) {
            $this->setStartYear($year);
        }

        $year = $this->start_year;
        if (!$year) {
            $year = date('Y');
        }

        $end_year = $this->end_year;
        if (!$end_year) {
            $end_year = (date('Y') + 3);
        }

        $year_array = [];

        for ($i = $year; $i <= $end_year; $i++) {
            $key = substr($i, -2);
            $year_array[$key] = $key;
        }

        return $year_array;
    }

    public function getZeroMonth()
    {
        $month_array = [];
        for ($i = 1; $i <= 12; $i++) {
            $val = sprintf('%02d', $i);
            $month_array[$val] = $val;
        }

        return $month_array;
    }

    public function getMonth($default = false)
    {
        $month_array = [];

        if ($default) {
            $month_array[''] = '--';
        }

        for ($i = 0; $i < 12; $i++) {
            $month_array[$i + 1] = $i + 1;
        }

        return $month_array;
    }

    public function getDay($default = false)
    {
        $day_array = [];

        if ($default) {
            $day_array[''] = '--';
        }

        for ($i = 0; $i < 31; $i++) {
            $day_array[$i + 1] = $i + 1;
        }

        return $day_array;
    }

    public function getHour()
    {
        $hour_array = [];
        for ($i = 0; $i <= 23; $i++) {
            $hour_array[$i] = $i;
        }

        return $hour_array;
    }

    public function getMinutes()
    {
        $minutes_array = [];
        for ($i = 0; $i <= 59; $i++) {
            $minutes_array[$i] = $i;
        }

        return $minutes_array;
    }

    public function getMinutesInterval()
    {
        $minutes_array = ['00' => '00', '30' => '30'];

        return $minutes_array;
    }

    /**
     * 休日の判定.
     *
     * @param  int $year
     * @param  int $month
     * @param  int $day
     *
     * @return bool 休日の場合はtrue
     */
    public function isHoliday($year, $month, $day)
    {
        if (is_null(SC_Date_Ex::$arrHoliday)) {
            $this->setArrHoliday();
        }
        if (is_null(SC_Date_Ex::$arrRegularHoliday)) {
            $this->setRegularHoliday();
        }

        if (!empty(SC_Date_Ex::$arrHoliday[$month])) {
            if (in_array($day, SC_Date_Ex::$arrHoliday[$month])) {
                return true;
            }
        }
        if (!empty(SC_Date_Ex::$arrRegularHoliday)) {
            $day = date('w', mktime(0, 0, 0, $month, $day, $year));
            if (in_array($day, SC_Date_Ex::$arrRegularHoliday)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 休日情報をスタティック変数にセット.
     *
     * @return void
     */
    private function setArrHoliday()
    {
        $objHoliday = new SC_Helper_Holiday_Ex();
        $holiday = $objHoliday->getList();
        $arrHoliday = [];
        foreach ($holiday as $val) {
            $arrHoliday[$val['month']][] = $val['day'];
        }
        SC_Date_Ex::$arrHoliday = $arrHoliday;
    }

    /**
     * 定休日情報をスタティック変数にセット.
     *
     * @return void
     */
    private function setRegularHoliday()
    {
        $arrInfo = SC_Helper_DB_Ex::sfGetBasisData();
        SC_Date_Ex::$arrRegularHoliday = explode('|', $arrInfo['regular_holiday_ids']);
    }
}
