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
 * カート のページクラス.
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class LC_Page_Cart extends LC_Page_Ex
{
    /** 商品規格情報の配列 */
    public $arrData;

    /** 動作モード */
    public $mode;
    /** @var array */
    public $cartKeys;
    /** @var int */
    public $tpl_all_total_inctax;
    /** @var string */
    public $tpl_prev_url;
    /** @var array */
    public $cartItems;
    /** @var int */
    public $tpl_deliv_free;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->tpl_title = '現在のカゴの中';
        $masterData = new SC_DB_MasterData_Ex();
        $this->arrProductType = $masterData->getMasterData('mtb_product_type');
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
     * Page のアクション.
     *
     * @return void
     */
    public function action()
    {
        // 決済処理中ステータスのロールバック
        $objPurchase = new SC_Helper_Purchase_Ex();
        $objPurchase->cancelPendingOrder(PENDING_ORDER_CANCEL_FLAG);

        $objCartSess = new SC_CartSession_Ex();
        $objSiteSess = new SC_SiteSession_Ex();
        $objCustomer = new SC_Customer_Ex();

        $objFormParam = $this->lfInitParam($_POST);
        $this->mode = $this->getMode();

        // モバイル対応
        if (SC_Display_Ex::detectDevice() == DEVICE_TYPE_MOBILE) {
            if (isset($_GET['cart_no'])) {
                $objFormParam->setValue('cart_no', $_GET['cart_no']);
            }
            if (isset($_GET['cartKey'])) {
                $objFormParam->setValue('cartKey', $_GET['cartKey']);
            }
        }

        $this->cartKeys = $objCartSess->getKeys();
        foreach ($this->cartKeys as $key) {
            // 商品購入中にカート内容が変更された。
            if ($objCartSess->getCancelPurchase($key)) {
                $this->tpl_message .= "商品購入中にカート内容が変更されましたので、お手数ですが購入手続きをやり直して下さい。\n";
            }
        }

        $cart_no = $objFormParam->getValue('cart_no');
        $cartKey = $objFormParam->getValue('cartKey');

        // エラーチェック
        $arrError = $objFormParam->checkError();
        if (isset($arrError) && !empty($arrError)) {
            SC_Utils_Ex::sfDispSiteError(CART_NOT_FOUND);
            SC_Response_Ex::actionExit();
        }

        $objFormParam4OpenCategoryTree =
            $this->lfInitParam4OpenCategoryTree($_REQUEST);
        if ($objFormParam4OpenCategoryTree->getValue('product_id')) {
            $arrQueryString = [
                'product_id' => $objFormParam4OpenCategoryTree->getValue(
                    'product_id'
                ),
            ];
        } else {
            $arrQueryString = [
                'category_id' => $objFormParam4OpenCategoryTree->getValue(
                    'category_id'
                ),
            ];
        }

        switch ($this->mode) {
            case 'confirm':
                // カート内情報の取得
                $cartList = $objCartSess->getCartList($cartKey);
                // カート商品が1件以上存在する場合
                if (count($cartList) > 0) {
                    // カートを購入モードに設定
                    $this->lfSetCurrentCart($objSiteSess, $objCartSess, $cartKey);

                    // 購入ページへ
                    SC_Response_Ex::sendRedirect(SHOPPING_URL);
                    SC_Response_Ex::actionExit();
                }
                break;
            case 'up':// 1個追加
                $objCartSess->upQuantity($cart_no, $cartKey);

                SC_Response_Ex::reload($arrQueryString, true);
                SC_Response_Ex::actionExit();
                break;
            case 'down':// 1個減らす
                $objCartSess->downQuantity($cart_no, $cartKey);

                SC_Response_Ex::reload($arrQueryString, true);
                SC_Response_Ex::actionExit();
                break;
            case 'setQuantity':// 数量変更
                $objCartSess->setQuantity($objFormParam->getValue('quantity'), $cart_no, $cartKey);

                SC_Response_Ex::reload($arrQueryString, true);
                SC_Response_Ex::actionExit();
                break;
            case 'delete':// カートから削除
                $objCartSess->delProduct($cart_no, $cartKey);

                SC_Response_Ex::reload($arrQueryString, true);
                SC_Response_Ex::actionExit();
                break;
            default:
                break;
        }
        $this->arrInfo = SC_Helper_DB_Ex::sfGetBasisData();
        $totalIncTax = 0;
        foreach ($this->cartKeys as $key) {
            // カート集計処理
            $this->tpl_message .= $objCartSess->checkProducts($key);
            $this->tpl_total_inctax[$key] = $objCartSess->getAllProductsTotal($key);
            $totalIncTax += $this->tpl_total_inctax[$key];
            $this->tpl_total_tax[$key] = $objCartSess->getAllProductsTax($key);
            // ポイント合計
            $this->tpl_total_point[$key] = $objCartSess->getAllProductsPoint($key);

            $this->arrData[$key] = $objCartSess->calculate($key, $objCustomer);

            // 送料無料チェック
            $this->arrData[$key]['is_deliv_free'] = $objCartSess->isDelivFree($key);

            // 送料無料までの金額を計算
            $this->tpl_deliv_free[$key] = $this->arrInfo['free_rule'] - $this->tpl_total_inctax[$key];
        }

        // 商品の合計金額をセット
        $this->tpl_all_total_inctax = $totalIncTax;

        $this->tpl_category_id =
            $objFormParam4OpenCategoryTree->getValue('category_id');
        $this->tpl_product_id =
            $objFormParam4OpenCategoryTree->getValue('product_id');

        // ログイン判定
        if ($objCustomer->isLoginSuccess(true)) {
            $this->tpl_login = true;
            $this->tpl_user_point = $objCustomer->getValue('point');
            $this->tpl_name = $objCustomer->getValue('name01');
        }

        // 前頁のURLを取得
        $this->lfGetCartPrevUrl($_SESSION, $_SERVER['HTTP_REFERER'] ?? '');
        $this->tpl_prev_url = (isset($_SESSION['cart_prev_url'])) ? $_SESSION['cart_prev_url'] : '';

        // 全てのカートの内容を取得する
        $this->cartItems = $objCartSess->getAllCartList();
    }

    /**
     * ユーザ入力値の処理
     *
     * @return SC_FormParam_Ex
     */
    public function lfInitParam($arrRequest)
    {
        $objFormParam = new SC_FormParam_Ex();
        $objFormParam->addParam('カートキー', 'cartKey', INT_LEN, 'n', ['NUM_CHECK', 'MAX_LENGTH_CHECK']);
        $objFormParam->addParam('カートナンバー', 'cart_no', INT_LEN, 'n', ['NUM_CHECK', 'MAX_LENGTH_CHECK']);
        // スマートフォン版での数量変更用
        $objFormParam->addParam('数量', 'quantity', INT_LEN, 'n', ['ZERO_CHECK', 'NUM_CHECK', 'MAX_LENGTH_CHECK']);
        // 値の取得
        $objFormParam->setParam($arrRequest);
        // 入力値の変換
        $objFormParam->convParam();

        return $objFormParam;
    }

    /**
     * PC版での開いているカテゴリーツリーの維持用の入力値
     *
     * @return SC_FormParam_Ex
     */
    public function lfInitParam4OpenCategoryTree($arrRequest)
    {
        $objFormParam = new SC_FormParam_Ex();

        $objFormParam->addParam(
            'カテゴリID',
            'category_id',
            INT_LEN,
            'n',
            ['NUM_CHECK', 'MAX_LENGTH_CHECK']
        );
        $objFormParam->addParam(
            '商品ID',
            'product_id',
            INT_LEN,
            'n',
            ['NUM_CHECK', 'MAX_LENGTH_CHECK']
        );

        // 値の取得
        $objFormParam->setParam($arrRequest);
        // 入力値の変換
        $objFormParam->convParam();

        return $objFormParam;
    }

    /**
     * order_temp_id の更新
     *
     * @return
     */
    public function lfUpdateOrderTempid($pre_uniqid, $uniqid)
    {
        $sqlval['order_temp_id'] = $uniqid;
        $where = 'order_temp_id = ?';
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $res = $objQuery->update('dtb_order_temp', $sqlval, $where, [$pre_uniqid]);
        if ($res != 1) {
            return false;
        }

        return true;
    }

    /**
     * 前頁のURLを取得
     *
     * @return void
     */
    public function lfGetCartPrevUrl(&$session, $referer)
    {
        // 妥当性チェック
        if (!SC_Utils_Ex::isInternalUrl($referer)) {
            return;
        }

        // 除外ページの場合、何もせず終了する。
        $arrExclude = [
            ROOT_URLPATH.'shopping/',
            ROOT_URLPATH.'cart/',
        ];

        // リファラーから path を切り出す。
        $netURL = new Net_URL($referer);
        $referer_path = $netURL->path;

        foreach ($arrExclude as $start) {
            if (str_starts_with($referer_path, $start)) {
                return;
            }
        }

        if (str_starts_with($referer_path, ROOT_URLPATH.'entry/')) {
            $referer = HTTPS_URL.'entry/kiyaku.php';
        }

        $session['cart_prev_url'] = $referer;
    }

    /**
     * カートを購入モードに設定
     *
     * @param SC_SiteSession_Ex $objSiteSess
     * @param SC_CartSession_Ex $objCartSess
     *
     * @return void
     */
    public function lfSetCurrentCart(&$objSiteSess, &$objCartSess, $cartKey)
    {
        // 正常に登録されたことを記録しておく
        $objSiteSess->setRegistFlag();
        $pre_uniqid = $objSiteSess->getUniqId();
        // 注文一時IDの発行
        $objSiteSess->setUniqId();
        $uniqid = $objSiteSess->getUniqId();
        // エラーリトライなどで既にuniqidが存在する場合は、設定を引き継ぐ
        if ($pre_uniqid != '') {
            $this->lfUpdateOrderTempid($pre_uniqid, $uniqid);
        }
        // カートを購入モードに設定
        $objCartSess->registerKey($cartKey);
        $objCartSess->saveCurrentCart($uniqid, $cartKey);
    }
}
