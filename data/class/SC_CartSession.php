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
 * カートセッション管理クラス
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class SC_CartSession
{
    /** ユニークIDを指定する. */
    public $key_tmp;

    /** カートのセッション変数. */
    public $cartSession;

    /* コンストラクタ */
    public function __construct($cartKey = 'cart')
    {
        if (!isset($_SESSION[$cartKey])) {
            $_SESSION[$cartKey] = [];
        }
        $this->cartSession = &$_SESSION[$cartKey];
    }

    // 商品購入処理中のロック

    /**
     * @param string $key_tmp
     * @param int $product_type_id
     */
    public function saveCurrentCart($key_tmp, $product_type_id)
    {
        $this->key_tmp = 'savecart_'.$key_tmp;
        // すでに情報がなければ現状のカート情報を記録しておく
        if (!isset($_SESSION[$this->key_tmp])) {
            $_SESSION[$this->key_tmp] = $this->cartSession[$product_type_id];
        }
        // 1世代古いコピー情報は、削除しておく
        foreach ($_SESSION as $key => $value) {
            if ($key != $this->key_tmp && preg_match('/^savecart_/', $key)) {
                unset($_SESSION[$key]);
            }
        }
    }

    // 商品購入中の変更があったかをチェックする。
    public function getCancelPurchase($product_type_id)
    {
        $ret = $this->cartSession[$product_type_id]['cancel_purchase'] ?? '';
        $this->cartSession[$product_type_id]['cancel_purchase'] = false;

        return $ret;
    }

    // 購入処理中に商品に変更がなかったかを判定

    /**
     * @param int $product_type_id
     */
    public function checkChangeCart($product_type_id)
    {
        $change = false;
        $max = $this->getMax($product_type_id);
        for ($i = 1; $i <= $max; $i++) {
            if (
                $this->cartSession[$product_type_id][$i]['quantity']
                != $_SESSION[$this->key_tmp][$i]['quantity']
            ) {
                $change = true;
                break;
            }
            if (
                $this->cartSession[$product_type_id][$i]['id']
                != $_SESSION[$this->key_tmp][$i]['id']
            ) {
                $change = true;
                break;
            }
        }
        if ($change) {
            // 一時カートのクリア
            unset($_SESSION[$this->key_tmp]);
            $this->cartSession[$product_type_id]['cancel_purchase'] = true;
        } else {
            $this->cartSession[$product_type_id]['cancel_purchase'] = false;
        }

        return $this->cartSession[$product_type_id]['cancel_purchase'];
    }

    // 次に割り当てるカートのIDを取得する
    public function getNextCartID($product_type_id)
    {
        $count = [];
        foreach ($this->cartSession[$product_type_id] as $key => $value) {
            $count[] = $this->cartSession[$product_type_id][$key]['cart_no'] ?? null;
        }

        return max($count) + 1;
    }

    // 値のセット

    /**
     * @param string $key
     * @param string $product_type_id
     */
    public function setProductValue($id, $key, $val, $product_type_id)
    {
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if (
                isset($this->cartSession[$product_type_id][$i]['id'])
                && $this->cartSession[$product_type_id][$i]['id'] == $id
            ) {
                $this->cartSession[$product_type_id][$i][$key] = $val;
            }
        }
    }

    // カート内商品の最大要素番号を取得する。
    public function getMax($product_type_id)
    {
        $max = 0;
        if (
            isset($this->cartSession[$product_type_id])
            && is_array($this->cartSession[$product_type_id])
            && count($this->cartSession[$product_type_id]) > 0
        ) {
            foreach ($this->cartSession[$product_type_id] as $key => $value) {
                if (is_numeric($key)) {
                    if ($max < $key) {
                        $max = $key;
                    }
                }
            }
        } else {
            $this->cartSession[$product_type_id] = [];
        }

        // カート内商品の最大要素番号までの要素が存在しない場合、要素を追加しておく
        for ($i = 0; $i <= $max; $i++) {
            if (!array_key_exists($i, $this->cartSession[$product_type_id])) {
                $this->cartSession[$product_type_id][$i] = [
                    'id' => null,
                    'cart_no' => null,
                    'price' => 0,
                    'quantity' => 0,
                    'productsClass' => [
                        'product_id' => null,
                        'product_class_id' => null,
                    ],
                ];
            }
        }

        return $max;
    }

    // カート内商品数量の合計
    public function getTotalQuantity($product_type_id)
    {
        $total = 0;
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            $total += (int) $this->cartSession[$product_type_id][$i]['quantity'];
        }

        return $total;
    }

    // 全商品の合計価格
    public function getAllProductsTotal($product_type_id, $pref_id = 0, $country_id = 0)
    {
        // 税込み合計
        $total = 0;
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if (!isset($this->cartSession[$product_type_id][$i]['price'])) {
                $this->cartSession[$product_type_id][$i]['price'] = '';
            }

            $price = $this->cartSession[$product_type_id][$i]['price'];

            if (!isset($this->cartSession[$product_type_id][$i]['quantity'])) {
                $this->cartSession[$product_type_id][$i]['quantity'] = '';
            }
            $quantity = $this->cartSession[$product_type_id][$i]['quantity'];
            $incTax = SC_Helper_TaxRule_Ex::sfCalcIncTax(
                $price,
                $this->cartSession[$product_type_id][$i]['productsClass']['product_id'],
                $this->cartSession[$product_type_id][$i]['productsClass']['product_class_id'],
                $pref_id,
                $country_id
            );

            $total += ($incTax * (int) $quantity);
        }

        return $total;
    }

    // 全商品の合計税金
    public function getAllProductsTax($product_type_id, $pref_id = 0, $country_id = 0)
    {
        // 税合計
        $total = 0;
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            $price = $this->cartSession[$product_type_id][$i]['price'];
            $quantity = $this->cartSession[$product_type_id][$i]['quantity'];
            $tax = SC_Helper_TaxRule_Ex::sfTax(
                $price,
                $this->cartSession[$product_type_id][$i]['productsClass']['product_id'],
                $this->cartSession[$product_type_id][$i]['productsClass']['product_class_id'],
                $pref_id,
                $country_id
            );

            $total += ($tax * (int) $quantity);
        }

        return $total;
    }

    // 全商品の合計ポイント

    /**
     * @param int $product_type_id
     */
    public function getAllProductsPoint($product_type_id)
    {
        // ポイント合計
        $total = 0;
        if (USE_POINT !== false) {
            $max = $this->getMax($product_type_id);
            for ($i = 0; $i <= $max; $i++) {
                $price = $this->cartSession[$product_type_id][$i]['price'];
                $quantity = $this->cartSession[$product_type_id][$i]['quantity'];

                if (!isset($this->cartSession[$product_type_id][$i]['point_rate'])) {
                    $this->cartSession[$product_type_id][$i]['point_rate'] = '';
                }
                $point_rate = $this->cartSession[$product_type_id][$i]['point_rate'];

                $point = SC_Utils_Ex::sfPrePoint($price, $point_rate);
                $total += ($point * (int) $quantity);
            }
        }

        return $total;
    }

    // カートへの商品追加
    public function addProduct($product_class_id, $quantity)
    {
        $objProduct = new SC_Product_Ex();
        $arrProduct = $objProduct->getProductsClass($product_class_id);
        $product_type_id = $arrProduct['product_type_id'] ?? null;
        $find = false;
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if ($this->cartSession[$product_type_id][$i]['id'] == $product_class_id) {
                $val = $this->cartSession[$product_type_id][$i]['quantity'] + $quantity;
                if (strlen($val) <= INT_LEN) {
                    $this->cartSession[$product_type_id][$i]['quantity'] += $quantity;
                }
                $find = true;
            }
        }
        if (!$find) {
            $this->cartSession[$product_type_id][$max + 1]['id'] = $product_class_id;
            $this->cartSession[$product_type_id][$max + 1]['quantity'] = $quantity;
            $this->cartSession[$product_type_id][$max + 1]['cart_no'] = $this->getNextCartID($product_type_id);
        }
    }

    /**
     * 前頁のURLを記録しておく
     *
     * @deprecated 2.18.0 本体では呼ばれない。
     */
    public function setPrevURL($url, $excludePaths = [])
    {
        // 前頁として記録しないページを指定する。
        $arrExclude = [
            '/shopping/',
        ];
        $arrExclude = array_merge($arrExclude, $excludePaths);
        $exclude = false;
        // ページチェックを行う。
        foreach ($arrExclude as $val) {
            if (preg_match('|'.preg_quote($val).'|', $url)) {
                $exclude = true;
                break;
            }
        }
        // 除外ページでない場合は、前頁として記録する。
        if (!$exclude) {
            $_SESSION['prev_url'] = $url;
        }
    }

    /**
     * 前頁のURLを取得する
     *
     * @deprecated 2.18.0 本体では利用していない。
     */
    public function getPrevURL()
    {
        return $_SESSION['prev_url'] ?? '';
    }

    // キーが一致した商品の削除
    /**
     * @deprecated 本体では使用していないメソッドです
     */
    public function delProductKey($keyname, $val, $product_type_id)
    {
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if ($this->cartSession[$product_type_id][$i][$keyname] == $val) {
                unset($this->cartSession[$product_type_id][$i]);
            }
        }
    }

    public function setValue($key, $val, $product_type_id)
    {
        $this->cartSession[$product_type_id][$key] = $val;
    }

    public function getValue($key, $product_type_id)
    {
        return $this->cartSession[$product_type_id][$key];
    }

    /**
     * セッション中の商品情報データの調整。
     * productsClass項目から、不必要な項目を削除する。
     */
    public function adjustSessionProductsClass(&$arrProductsClass)
    {
        $arrNecessaryItems = [
            'product_id' => true,
            'product_class_id' => true,
            'name' => true,
            'price02' => true,
            'point_rate' => true,
            'main_list_image' => true,
            'main_image' => true,
            'product_code' => true,
            'stock' => true,
            'stock_unlimited' => true,
            'sale_limit' => true,
            'class_name1' => true,
            'classcategory_name1' => true,
            'class_name2' => true,
            'classcategory_name2' => true,
        ];

        // 必要な項目以外を削除。
        foreach ($arrProductsClass as $key => $value) {
            if (!isset($arrNecessaryItems[$key])) {
                unset($arrProductsClass[$key]);
            }
        }
    }

    /**
     * getCartList用にcartSession情報をセットする
     *
     * @param  int $product_type_id 商品種別ID
     * @param  int $key
     *
     * @return void
     *
     * @deprecated 本体では使用していないメソッドです
     * MEMO: せっかく一回だけ読み込みにされてますが、税率対応の関係でちょっと保留
     */
    public function setCartSession4getCartList($product_type_id, $key)
    {
        $objProduct = new SC_Product_Ex();

        $this->cartSession[$product_type_id][$key]['productsClass']
            = &$objProduct->getDetailAndProductsClass($this->cartSession[$product_type_id][$key]['id']);

        $price = $this->cartSession[$product_type_id][$key]['productsClass']['price02'];
        $this->cartSession[$product_type_id][$key]['price'] = $price;

        $this->cartSession[$product_type_id][$key]['point_rate']
            = $this->cartSession[$product_type_id][$key]['productsClass']['point_rate'];

        $quantity = $this->cartSession[$product_type_id][$key]['quantity'];
        $incTax = SC_Helper_TaxRule_Ex::sfCalcIncTax(
            $price,
            $this->cartSession[$product_type_id][$key]['productsClass']['product_id'],
            $this->cartSession[$product_type_id][$key]['id']
        );

        $total = $incTax * $quantity;

        $this->cartSession[$product_type_id][$key]['price_inctax'] = $incTax;
        $this->cartSession[$product_type_id][$key]['total_inctax'] = $total;
    }

    /**
     * 商品種別ごとにカート内商品の一覧を取得する.
     *
     * @param  int $product_type_id 商品種別ID
     * @param  int $pref_id       税金計算用注文者都道府県ID
     * @param  int $country_id    税金計算用注文者国ID
     *
     * @return array   カート内商品一覧の配列
     */
    public function getCartList($product_type_id, $pref_id = 0, $country_id = 0)
    {
        $objProduct = new SC_Product_Ex();
        $max = $this->getMax($product_type_id);
        $arrRet = [];
        /*

        $const_name = '_CALLED_SC_CARTSESSION_GETCARTLIST_' . $product_type_id;
        if (defined($const_name)) {
            $is_first = true;
        } else {
            define($const_name, true);
            $is_first = false;
        }

*/
        for ($i = 0; $i <= $max; $i++) {
            if (
                isset($this->cartSession[$product_type_id][$i]['cart_no'])
                && $this->cartSession[$product_type_id][$i]['cart_no'] != ''
            ) {
                // 商品情報は常に取得
                // TODO: 同一インスタンス内では1回のみ呼ぶようにしたい
                // TODO: ここの商品の合計処理は getAllProductsTotalや getAllProductsTaxとで類似重複なので統一出来そう
                /*
                // 同一セッション内では初回のみDB参照するようにしている
                if (!$is_first) {
                    $this->setCartSession4getCartList($product_type_id, $i);
                }
*/

                $this->cartSession[$product_type_id][$i]['productsClass']
                    = &$objProduct->getDetailAndProductsClass($this->cartSession[$product_type_id][$i]['id']);

                $price = $this->cartSession[$product_type_id][$i]['productsClass']['price02'];
                $this->cartSession[$product_type_id][$i]['price'] = $price;

                $this->cartSession[$product_type_id][$i]['point_rate']
                    = $this->cartSession[$product_type_id][$i]['productsClass']['point_rate'];

                $quantity = $this->cartSession[$product_type_id][$i]['quantity'];

                $arrTaxRule = SC_Helper_TaxRule_Ex::getTaxRule(
                    $this->cartSession[$product_type_id][$i]['productsClass']['product_id'],
                    $this->cartSession[$product_type_id][$i]['productsClass']['product_class_id'],
                    $pref_id,
                    $country_id
                );
                $incTax = $price + SC_Helper_TaxRule_Ex::calcTax($price, $arrTaxRule['tax_rate'], $arrTaxRule['tax_rule'], $arrTaxRule['tax_adjust']);

                $total = $incTax * $quantity;
                $this->cartSession[$product_type_id][$i]['price_inctax'] = $incTax;
                $this->cartSession[$product_type_id][$i]['total_inctax'] = $total;
                $this->cartSession[$product_type_id][$i]['tax_rate'] = $arrTaxRule['tax_rate'];
                $this->cartSession[$product_type_id][$i]['tax_rule'] = $arrTaxRule['tax_rule'];
                $this->cartSession[$product_type_id][$i]['tax_adjust'] = $arrTaxRule['tax_adjust'];

                $arrRet[] = $this->cartSession[$product_type_id][$i];

                // セッション変数のデータ量を抑制するため、一部の商品情報を切り捨てる
                // XXX 上で「常に取得」するのだから、丸ごと切り捨てて良さそうにも感じる。
                $this->adjustSessionProductsClass($this->cartSession[$product_type_id][$i]['productsClass']);
            }
        }

        return $arrRet;
    }

    /**
     * 全てのカートの内容を取得する.
     *
     * @return array 全てのカートの内容
     */
    public function getAllCartList()
    {
        $results = [];
        $cartKeys = $this->getKeys();
        $i = 0;
        foreach ($cartKeys as $key) {
            $cartItems = $this->getCartList($key);
            foreach ($cartItems as $itemKey => $itemValue) {
                $cartItem = &$cartItems[$itemKey];
                $results[$key][$i] = &$cartItem;
                $i++;
            }
        }

        return $results;
    }

    /**
     * カート内にある商品規格IDを全て取得する.
     *
     * @param  int $product_type_id 商品種別ID
     *
     * @return array   商品規格ID の配列
     */
    public function getAllProductClassID($product_type_id)
    {
        $max = $this->getMax($product_type_id);
        $productClassIDs = [];
        for ($i = 0; $i <= $max; $i++) {
            if ($this->cartSession[$product_type_id][$i]['cart_no'] != '') {
                $productClassIDs[] = $this->cartSession[$product_type_id][$i]['id'];
            }
        }

        return $productClassIDs;
    }

    /**
     * 商品種別ID を指定して, カート内の商品を全て削除する.
     *
     * @param  int $product_type_id 商品種別ID
     *
     * @return void
     */
    public function delAllProducts($product_type_id)
    {
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            unset($this->cartSession[$product_type_id][$i]);
        }
    }

    // 商品の削除
    public function delProduct($cart_no, $product_type_id)
    {
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if ($this->cartSession[$product_type_id][$i]['cart_no'] == $cart_no) {
                unset($this->cartSession[$product_type_id][$i]);
            }
        }
    }

    // 数量の増加
    public function upQuantity($cart_no, $product_type_id)
    {
        $quantity = $this->getQuantity($cart_no, $product_type_id);
        if (strlen($quantity + 1) <= INT_LEN) {
            $this->setQuantity($quantity + 1, $cart_no, $product_type_id);
        }
    }

    // 数量の減少
    public function downQuantity($cart_no, $product_type_id)
    {
        $quantity = $this->getQuantity($cart_no, $product_type_id);
        if ($quantity > 1) {
            $this->setQuantity($quantity - 1, $cart_no, $product_type_id);
        }
    }

    /**
     * カート番号と商品種別IDを指定して, 数量を取得する.
     *
     * @param  int $cart_no       カート番号
     * @param  int $product_type_id 商品種別ID
     *
     * @return int 該当商品規格の数量
     */
    public function getQuantity($cart_no, $product_type_id)
    {
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if ($this->cartSession[$product_type_id][$i]['cart_no'] == $cart_no) {
                return $this->cartSession[$product_type_id][$i]['quantity'];
            }
        }

        return 0;
    }

    /**
     * カート番号と商品種別IDを指定して, 数量を設定する.
     *
     * @param int $quantity      設定する数量
     * @param int $cart_no       カート番号
     * @param int $product_type_id 商品種別ID
     *
     * @retrun void
     */
    public function setQuantity($quantity, $cart_no, $product_type_id)
    {
        $max = $this->getMax($product_type_id);
        for ($i = 0; $i <= $max; $i++) {
            if ($this->cartSession[$product_type_id][$i]['cart_no'] == $cart_no) {
                $this->cartSession[$product_type_id][$i]['quantity'] = $quantity;
            }
        }
    }

    /**
     * カート番号と商品種別IDを指定して, 商品規格IDを取得する.
     *
     * @param  int $cart_no       カート番号
     * @param  int $product_type_id 商品種別ID
     *
     * @return int 商品規格ID
     *
     * @deprecated 本体では使用していないメソッドです
     */
    public function getProductClassId($cart_no, $product_type_id)
    {
        for ($i = 0; $i < count($this->cartSession[$product_type_id]); $i++) {
            if ($this->cartSession[$product_type_id][$i]['cart_no'] == $cart_no) {
                return $this->cartSession[$product_type_id][$i]['id'];
            }
        }

        return 0;
    }

    /**
     * カート内の商品の妥当性をチェックする.
     *
     * エラーが発生した場合は, 商品をカート内から削除又は数量を調整し,
     * エラーメッセージを返す.
     *
     * 1. 商品種別に関連づけられた配送業者の存在チェック
     * 2. 削除/非表示商品のチェック
     * 3. 販売制限数のチェック
     * 4. 在庫数チェック
     *
     * @param  string $product_type_id 商品種別ID
     *
     * @return string エラーが発生した場合はエラーメッセージ
     */
    public function checkProducts($product_type_id)
    {
        $objProduct = new SC_Product_Ex();
        $objDelivery = new SC_Helper_Delivery_Ex();
        $arrDeliv = $objDelivery->getList($product_type_id);
        $tpl_message = '';

        // カート内の情報を取得
        $arrItems = $this->getCartList($product_type_id);
        foreach ($arrItems as &$arrItem) {
            $product = &$arrItem['productsClass'];
            /*
             * 表示/非表示商品のチェック
             */
            if (SC_Utils_Ex::isBlank($product) || $product['status'] != 1) {
                $this->delProduct($arrItem['cart_no'], $product_type_id);
                $tpl_message .= "※ 現時点で販売していない商品が含まれておりました。該当商品をカートから削除しました。\n";
            } else {
                /*
                 * 配送業者のチェック
                 */
                if (SC_Utils_Ex::isBlank($arrDeliv)) {
                    $tpl_message .= '※「'.$product['name'].'」はまだ配送の準備ができておりません。';
                    $tpl_message .= '恐れ入りますがお問い合わせページよりお問い合わせください。'."\n";
                    $this->delProduct($arrItem['cart_no'], $product_type_id);
                }

                /*
                 * 販売制限数, 在庫数のチェック
                 */
                $limit = $objProduct->getBuyLimit($product);
                if (!is_null($limit) && $arrItem['quantity'] > $limit) {
                    if ($limit > 0) {
                        $this->setProductValue($arrItem['id'], 'quantity', $limit, $product_type_id);
                        $total_inctax = $limit * SC_Helper_TaxRule_Ex::sfCalcIncTax(
                            $arrItem['price'],
                            $product['product_id'],
                            $arrItem['id']
                        );
                        $this->setProductValue($arrItem['id'], 'total_inctax', $total_inctax, $product_type_id);
                        $tpl_message .= '※「'.$product['name'].'」は販売制限(または在庫が不足)しております。';
                        $tpl_message .= "一度に数量{$limit}を超える購入はできません。\n";
                    } else {
                        $this->delProduct($arrItem['cart_no'], $product_type_id);
                        $tpl_message .= '※「'.$product['name']."」は売り切れました。\n";
                        continue;
                    }
                }
            }
        }

        return $tpl_message;
    }

    /**
     * 送料無料条件を満たすかどうかチェックする
     *
     * @param  int $product_type_id 商品種別ID
     *
     * @return bool 送料無料の場合 true
     */
    public function isDelivFree($product_type_id)
    {
        $objDb = new SC_Helper_DB_Ex();

        $subtotal = $this->getAllProductsTotal($product_type_id);

        // 送料無料の購入数が設定されている場合
        if (DELIV_FREE_AMOUNT > 0) {
            // 商品の合計数量
            $total_quantity = $this->getTotalQuantity($product_type_id);

            if ($total_quantity >= DELIV_FREE_AMOUNT) {
                return true;
            }
        }

        // 送料無料条件が設定されている場合
        $arrInfo = $objDb->sfGetBasisData();
        if ($arrInfo['free_rule'] > 0) {
            // 小計が送料無料条件以上の場合
            if ($subtotal >= $arrInfo['free_rule']) {
                return true;
            }
        }

        return false;
    }

    /**
     * カートの内容を計算する.
     *
     * カートの内容を計算し, 下記のキーを保持する連想配列を返す.
     *
     * - tax: 税額
     * - subtotal: カート内商品の小計
     * - deliv_fee: カート内商品の合計送料
     * - total: 合計金額
     * - payment_total: お支払い合計
     * - add_point: 加算ポイント
     *
     * @param int       $product_type_id 商品種別ID
     * @param SC_Customer   $objCustomer   ログイン中の SC_Customer インスタンス
     * @param int       $use_point     今回使用ポイント
     * @param int|array $deliv_pref    配送先都道府県ID. 複数に配送する場合は都道府県IDの配列
     * @param  int $charge           手数料
     * @param  int $discount         値引き
     * @param  int $deliv_id         配送業者ID
     * @param  int $order_pref       注文者の都道府県ID
     * @param  int $order_country_id 注文者の国
     *
     * @return array   カートの計算結果の配列
     */
    public function calculate(
        $product_type_id,
        &$objCustomer,
        $use_point = 0,
        $deliv_pref = '',
        $charge = 0,
        $discount = 0,
        $deliv_id = 0,
        $order_pref = 0,
        $order_country_id = 0
    ) {
        $results = [];
        $total_point = $this->getAllProductsPoint($product_type_id);
        // MEMO: 税金計算は注文者の住所基準
        $results['tax'] = $this->getAllProductsTax($product_type_id, $order_pref, $order_country_id);
        $results['subtotal'] = $this->getAllProductsTotal($product_type_id, $order_pref, $order_country_id);
        $results['deliv_fee'] = 0;

        // 商品ごとの送料を加算
        if (OPTION_PRODUCT_DELIV_FEE == 1) {
            $cartItems = $this->getCartList($product_type_id);
            foreach ($cartItems as $arrItem) {
                $results['deliv_fee'] += $arrItem['productsClass']['deliv_fee'] * $arrItem['quantity'];
            }
        }

        // 配送業者の送料を加算
        if (
            OPTION_DELIV_FEE == 1
            && !SC_Utils_Ex::isBlank($deliv_pref)
            && !SC_Utils_Ex::isBlank($deliv_id)
        ) {
            $results['deliv_fee'] += SC_Helper_Delivery_Ex::getDelivFee($deliv_pref, $deliv_id);
        }

        // 送料無料チェック
        if ($this->isDelivFree($product_type_id)) {
            $results['deliv_fee'] = 0;
        }

        // 合計を計算
        $results['total'] = $results['subtotal'];
        $results['total'] += $results['deliv_fee'];
        $results['total'] += $charge;
        $results['total'] -= $discount;

        // お支払い合計
        $results['payment_total'] = $results['total'] - $use_point * POINT_VALUE;

        // 加算ポイントの計算
        if (USE_POINT !== false) {
            $results['add_point'] = SC_Helper_DB_Ex::sfGetAddPoint($total_point, $use_point);
            if ($objCustomer != '') {
                // 誕生日月であった場合
                if ($objCustomer->isBirthMonth()) {
                    $results['birth_point'] = BIRTH_MONTH_POINT;
                    $results['add_point'] += $results['birth_point'];
                }
            }
            if ($results['add_point'] < 0) {
                $results['add_point'] = 0;
            }
        }

        return $results;
    }

    /**
     * カートが保持するキー(商品種別ID)を配列で返す.
     *
     * @return array 商品種別IDの配列
     */
    public function getKeys()
    {
        $keys = array_keys($this->cartSession);
        // 数量が 0 の商品種別は削除する
        foreach ($keys as $key) {
            $quantity = $this->getTotalQuantity($key);
            if ($quantity < 1) {
                unset($this->cartSession[$key]);
            }
        }

        return array_keys($this->cartSession);
    }

    /**
     * カートに設定された現在のキー(商品種別ID)を登録する.
     *
     * @param  int $key 商品種別ID
     *
     * @return void
     */
    public function registerKey($key)
    {
        $_SESSION['cartKey'] = $key;
    }

    /**
     * カートに設定された現在のキー(商品種別ID)を削除する.
     *
     * @return void
     */
    public function unsetKey()
    {
        unset($_SESSION['cartKey']);
    }

    /**
     * カートに設定された現在のキー(商品種別ID)を取得する.
     *
     * @return int 商品種別ID
     */
    public function getKey()
    {
        return $_SESSION['cartKey'] ?? null;
    }

    /**
     * 複数商品種別かどうか.
     *
     * @return bool カートが複数商品種別の場合 true
     */
    public function isMultiple()
    {
        return count($this->getKeys()) > 1;
    }

    /**
     * 引数の商品種別の商品がカートに含まれるかどうか.
     *
     * @param  int $product_type_id 商品種別ID
     *
     * @return bool 指定の商品種別がカートに含まれる場合 true
     */
    public function hasProductType($product_type_id)
    {
        return in_array($product_type_id, $this->getKeys());
    }
}
