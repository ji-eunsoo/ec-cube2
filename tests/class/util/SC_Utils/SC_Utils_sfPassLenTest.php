<?php

$HOME = realpath(__DIR__).'/../../../..';
require_once $HOME.'/tests/class/Common_TestCase.php';
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

/**
 * SC_Utils::sfPassLen()のテストクラス.
 *
 * @author Hiroko Tamagawa
 *
 * @version $Id$
 */
class SC_Utils_getSfPassLenTest extends Common_TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ///////////////////////////////////////
    public function testSfPassLen入力が0の場合0バイト文字列が返る()
    {
        $input = '';
        $this->expected = '';
        $this->actual = SC_Utils::sfPassLen($input);

        $this->verify();
    }

    public function testSfPassLen入力が正の数の場合入力と同じ文字数のアスタリスクが返る()
    {
        $input = 17;
        $this->expected = '*****************';
        $this->actual = SC_Utils::sfPassLen($input);

        $this->verify();
    }

    // ////////////////////////////////////////
}
