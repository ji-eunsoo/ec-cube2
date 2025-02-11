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

/**
 * システム情報 のページクラス.
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class LC_Page_Admin_System_System extends LC_Page_Admin_Ex
{
    /** @var string */
    public $arrSystemInfo;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->tpl_mainpage = 'system/system.tpl';
        $this->tpl_subno = 'system';
        $this->tpl_mainno = 'system';
        $this->tpl_maintitle = 'システム設定';
        $this->tpl_subtitle = 'システム情報';
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    public function process()
    {
        $this->action();
        $this->sendResponse();
    }

    /**
     * Page のアクション.
     *
     * @return void
     */
    public function action()
    {
        $objFormParam = new SC_FormParam_Ex();

        $this->initForm($objFormParam, $_GET);
        switch ($this->getMode()) {
            // PHP INFOを表示
            case 'info':
                phpinfo();
                SC_Response_Ex::actionExit();
                break;

            default:
                break;
        }

        $this->arrSystemInfo = $this->getSystemInfo();
    }

    /**
     * フォームパラメーター初期化.
     *
     * @param  SC_FormParam_Ex $objFormParam
     * @param  array  $arrParams    $_GET値
     *
     * @return void
     */
    public function initForm(&$objFormParam, &$arrParams)
    {
        $objFormParam->addParam('mode', 'mode', INT_LEN, '', ['ALPHA_CHECK', 'MAX_LENGTH_CHECK']);
        $objFormParam->setParam($arrParams);
    }

    /**
     * システム情報を取得する.
     *
     * @return array システム情報
     */
    public function getSystemInfo()
    {
        $objDB = SC_DB_DBFactory_Ex::getInstance();

        $arrSystemInfo = [
            ['title' => 'EC-CUBE',     'value' => ECCUBE_VERSION],
            ['title' => 'サーバーOS',    'value' => php_uname()],
            ['title' => 'DBサーバー',    'value' => $objDB->sfGetDBVersion()],
            ['title' => 'WEBサーバー',   'value' => $_SERVER['SERVER_SOFTWARE']],
        ];

        $value = PHP_VERSION.' ('.implode(', ', get_loaded_extensions()).')';
        $arrSystemInfo[] = ['title' => 'PHP', 'value' => $value];

        if (extension_loaded('GD') || extension_loaded('gd')) {
            $arrValue = [];
            foreach (gd_info() as $key => $val) {
                $arrValue[] = "$key => $val";
            }
            $value = '有効 ('.implode(', ', $arrValue).')';
        } else {
            $value = '無効';
        }
        $arrSystemInfo[] = ['title' => 'GD', 'value' => $value];
        $arrSystemInfo[] = ['title' => 'HTTPユーザーエージェント', 'value' => $_SERVER['HTTP_USER_AGENT']];

        return $arrSystemInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function sendAdditionalHeader()
    {
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }
}
