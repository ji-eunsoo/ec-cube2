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
 * お届け先追加 のページクラス.
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class LC_Page_Mypage_DeliveryAddr extends LC_Page_Ex
{
    /** @var array */
    public $validUrl;
    /** @var string */
    public $ParentPage;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        $this->skip_load_page_layout = true;
        parent::init();
        $this->tpl_title = 'お届け先の追加･変更';
        $masterData = new SC_DB_MasterData_Ex();
        $this->arrPref = $masterData->getMasterData('mtb_pref');
        $this->arrCountry = $masterData->getMasterData('mtb_country');
        $this->httpCacheControl('nocache');
        $this->validUrl = [
            MYPAGE_DELIVADDR_URLPATH,
            DELIV_URLPATH,
            MULTIPLE_URLPATH,
        ];
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    public function process()
    {
        parent::process();
        $this->action();
        $this->sendResponse();
    }

    /**
     * Page のAction.
     *
     * @return void
     */
    public function action()
    {
        $objCustomer = new SC_Customer_Ex();
        $objAddress = new SC_Helper_Address_Ex();
        $ParentPage = MYPAGE_DELIVADDR_URLPATH;

        // GETでページを指定されている場合には指定ページに戻す
        if (isset($_GET['page'])) {
            $ParentPage = htmlspecialchars($_GET['page'], ENT_QUOTES);
        } elseif (isset($_POST['ParentPage'])) {
            $ParentPage = htmlspecialchars($_POST['ParentPage'], ENT_QUOTES);
        }

        // 正しい遷移かをチェック
        if (!SC_Utils_Ex::isBlank($ParentPage) && !in_array($ParentPage, $this->validUrl)) {
            // 遷移が正しくない場合、デフォルトであるマイページの配送先追加の画面を設定する
            $ParentPage = MYPAGE_DELIVADDR_URLPATH;
        }

        $this->ParentPage = $ParentPage;

        /*
         * ログイン判定 及び 退会判定
         * 未ログインでも, 複数配送設定ページからのアクセスの場合は表示する
         *
         * TODO 購入遷移とMyPageで別クラスにすべき
         */
        if (!$objCustomer->isLoginSuccess(true) && $ParentPage != MULTIPLE_URLPATH) {
            $this->tpl_onload = "eccube.changeParentUrl('".$ParentPage."'); window.close();";
        }

        // other_deliv_id のあるなしで追加か編集か判定しているらしい
        $_SESSION['other_deliv_id'] = $_REQUEST['other_deliv_id'] ?? '';

        // パラメーター管理クラス,パラメーター情報の初期化
        $objFormParam = new SC_FormParam_Ex();
        $objAddress->setFormParam($objFormParam);
        $objFormParam->setParam($_POST);

        switch ($this->getMode()) {
            // 入力は必ずedit
            case 'edit':
                $this->arrErr = $objAddress->errorCheck($objFormParam);
                // 入力エラーなし
                if (empty($this->arrErr)) {
                    // TODO ここでやるべきではない
                    if (in_array($_POST['ParentPage'], $this->validUrl)) {
                        $this->tpl_onload = "eccube.changeParentUrl('".$this->getLocation($_POST['ParentPage'])."'); window.close();";
                    } else {
                        SC_Utils_Ex::sfDispSiteError(CUSTOMER_ERROR);
                    }

                    if ($objCustomer->isLoginSuccess(true)) {
                        $this->lfRegistData($objAddress, $objFormParam, $objCustomer->getValue('customer_id'));
                    } else {
                        $this->lfRegistDataNonMember($objFormParam);
                    }

                    if (SC_Display_Ex::detectDevice() === DEVICE_TYPE_MOBILE) {
                        // モバイルの場合、元のページに遷移
                        SC_Response_Ex::sendRedirect($this->getLocation($_POST['ParentPage']));
                        SC_Response_Ex::actionExit();
                    }
                }
                break;
            case 'multiple':
                // 複数配送先用
                break;
            default:
                if (isset($_GET['other_deliv_id']) && $_GET['other_deliv_id'] != '') {
                    $arrOtherDeliv = $objAddress->getAddress($_SESSION['other_deliv_id'], $objCustomer->getValue('customer_id'));

                    // 不正アクセス判定
                    if (!$objCustomer->isLoginSuccess(true) || !$arrOtherDeliv) {
                        SC_Utils_Ex::sfDispSiteError(CUSTOMER_ERROR);
                    }

                    // 別のお届け先情報取得
                    $objFormParam->setParam($arrOtherDeliv);
                }
                break;
        }

        $this->arrForm = $objFormParam->getFormParamList();
        if (SC_Display_Ex::detectDevice() === DEVICE_TYPE_MOBILE) {
            $this->tpl_mainpage = 'mypage/delivery_addr.tpl';
        } else {
            $this->setTemplate('mypage/delivery_addr.tpl');
        }
    }

    /* 登録実行 */

    /**
     * @param SC_Helper_Address_Ex $objAddress
     * @param SC_FormParam $objFormParam
     */
    public function lfRegistData($objAddress, $objFormParam, $customer_id)
    {
        $arrRet = $objFormParam->getHashArray();
        $sqlval = $objFormParam->getDbArray();

        $sqlval['other_deliv_id'] = $arrRet['other_deliv_id'];
        $sqlval['customer_id'] = $customer_id;

        if (!$objAddress->registAddress($sqlval)) {
            SC_Utils_Ex::sfDispSiteError(FREE_ERROR_MSG, '', false, '別のお届け先を登録できませんでした。');
            SC_Response_Ex::actionExit();
        }
    }

    /**
     * @param SC_FormParam $objFormParam
     */
    public function lfRegistDataNonMember($objFormParam)
    {
        $arrRegist = [];
        $arrRegistColumn = $objFormParam->getDbArray();
        foreach ($arrRegistColumn as $key => $val) {
            $arrRegist['shipping_'.$key] = $val;
        }
        if (count($_SESSION['shipping']) >= DELIV_ADDR_MAX) {
            SC_Utils_Ex::sfDispSiteError(FREE_ERROR_MSG, '', false, '別のお届け先最大登録数に達しています。');
        } else {
            $_SESSION['shipping'][] = $arrRegist;
        }
    }
}
