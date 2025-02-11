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

$HOME = realpath(__DIR__).'/../../..';
require_once $HOME.'/tests/class/Common_TestCase.php';

class SC_Date_getYearTest extends Common_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->objDate = new SC_Date_Ex();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ///////////////////////////////////////

    public function testGetYear要素の数が4の配列を返す()
    {
        $this->expected = 4;
        $this->actual = count($this->objDate->getYear());

        $this->verify('配列の長さ');
    }

    public function testGetYear最小値が今年の配列を返す()
    {
        $this->expected = date('Y');
        $this->actual = min($this->objDate->getYear());

        $this->verify('最小値；今年');
    }

    public function testGetYear最低値が引数の年の配列を返す()
    {
        $this->expected = '2007';
        $this->actual = min($this->objDate->getYear('2007'));

        $this->verify('引数が最低値');
    }

    public function testGetYear最低値がメンバー変数の年の配列を返す()
    {
        $this->expected = '2004';
        $this->objDate->setStartYear('2004');
        $this->actual = min($this->objDate->getYear());

        $this->verify('メンバー変数が最低値');
    }

    public function testGetYear最大値が3年後の配列を返す()
    {
        $this->expected = date('Y') + 3;
        $this->actual = max($this->objDate->getYear());

        $this->verify('最大値；3年後');
    }

    public function testGetYear最大値がメンバ変数の配列を返す()
    {
        $this->expected = '2020';
        $this->objDate->setStartYear('2018');
        $this->objDate->setEndYear('2020');
        $this->actual = max($this->objDate->getYear());

        $this->verify('メンバー変数が最大値');
    }

    public function testGetYearデフォルト値が含まれる配列を返す()
    {
        $result = in_array('----', $this->objDate->getYear(date('Y'), true));
        $this->assertTrue($result, 'デフォルト値');
    }

    public function testGetYearデフォルト値の引数に年を指定した配列を返す()
    {
        $year = date('Y');
        $this->expected = [$year => $year, $year + 1 => $year + 1, '----' => '----', $year + 2 => $year + 2, $year + 3 => $year + 3];
        $this->actual = $this->objDate->getYear(date('Y'), $year + 1);
        $this->assertSame($this->expected, $this->actual, 'デフォルト値');
    }

    public function testGetYear引数に指定したキーがデフォルト値となる配列を返す()
    {
        $this->expected = 'default';
        $return = $this->objDate->getYear(date('Y'), true, 'default');
        $this->actual = array_search('----', $return);
        $this->verify('デフォルトキー');
    }
}
