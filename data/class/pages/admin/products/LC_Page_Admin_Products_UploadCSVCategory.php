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
 * カテゴリ登録CSVのページクラス
 *
 * LC_Page_Admin_Products_UploadCSV をカスタマイズする場合はこのクラスを編集する.
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class LC_Page_Admin_Products_UploadCSVCategory extends LC_Page_Admin_Ex
{
    /** エラー情報 **/
    public $arrErr;

    /** 表示用項目 **/
    public $arrTitle;

    /** 結果行情報 **/
    public $arrRowResult;

    /** エラー行情報 **/
    public $arrRowErr;

    /** TAGエラーチェックフィールド情報 */
    public $arrTagCheckItem;

    /** テーブルカラム情報 (登録処理用) **/
    public $arrRegistColumn;

    /** 登録フォームカラム情報 **/
    public $arrFormKeyList;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->tpl_mainpage = 'products/upload_csv_category.tpl';
        $this->tpl_mainno = 'products';
        $this->tpl_subno = 'upload_csv_category';
        $this->tpl_maintitle = '商品管理';
        $this->tpl_subtitle = 'カテゴリ登録CSV';
        $this->csv_id = '5';

        $masterData = new SC_DB_MasterData_Ex();
        $this->arrAllowedTag = $masterData->getMasterData('mtb_allowed_tag');
        $this->arrTagCheckItem = [];
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
        // CSV管理ヘルパー
        $objCSV = new SC_Helper_CSV_Ex();
        // CSV構造読み込み
        $arrCSVFrame = $objCSV->sfGetCsvOutput($this->csv_id);

        // CSV構造がインポート可能かのチェック
        if (!$objCSV->sfIsImportCSVFrame($arrCSVFrame)) {
            // 無効なフォーマットなので初期状態に強制変更
            $arrCSVFrame = $objCSV->sfGetCsvOutput($this->csv_id, '', [], 'no');
            $this->tpl_is_format_default = true;
        }
        // CSV構造は更新可能なフォーマットかのフラグ取得
        $this->tpl_is_update = $objCSV->sfIsUpdateCSVFrame($arrCSVFrame);

        // CSVファイルアップロード情報の初期化
        $objUpFile = new SC_UploadFile_Ex(CSV_TEMP_REALDIR, CSV_TEMP_REALDIR);
        $this->lfInitFile($objUpFile);

        // パラメーター情報の初期化
        $objFormParam = new SC_FormParam_Ex();
        $this->lfInitParam($objFormParam, $arrCSVFrame);

        $this->max_upload_csv_size = SC_Utils_Ex::getUnitDataSize(CSV_SIZE);

        $objFormParam->setHtmlDispNameArray();
        $this->arrTitle = $objFormParam->getHtmlDispNameArray();

        switch ($this->getMode()) {
            case 'csv_upload':
                $this->doUploadCsv($objFormParam, $objUpFile);
                break;
            default:
                break;
        }
    }

    /**
     * 登録/編集結果のメッセージをプロパティへ追加する
     *
     * @param  int $line_count 行数
     * @param  string  $message    メッセージ
     *
     * @return void
     */
    public function addRowResult($line_count, $message)
    {
        $this->arrRowResult[] = $line_count.'行目：'.$message;
    }

    /**
     * 登録/編集結果のエラーメッセージをプロパティへ追加する
     *
     * @param  int $line_count 行数
     * @param  string  $message    メッセージ
     *
     * @return void
     */
    public function addRowErr($line_count, $message)
    {
        $this->arrRowErr[] = $line_count.'行目：'.$message;
    }

    /**
     * CSVアップロードを実行する
     *
     * @param  SC_FormParam  $objFormParam
     * @param  SC_UploadFile $objUpFile
     *
     * @return void
     */
    public function doUploadCsv(&$objFormParam, &$objUpFile)
    {
        // ファイルアップロードのチェック
        $objUpFile->makeTempFile('csv_file');
        $arrErr = $objUpFile->checkExists();
        if (count($arrErr) > 0) {
            $this->arrErr = $arrErr;

            return;
        }
        // 一時ファイル名の取得
        $filepath = $objUpFile->getTempFilePath('csv_file');
        // CSVファイルの文字コード変換
        $enc_filepath = SC_Utils_Ex::sfEncodeFile($filepath, CHAR_CODE, CSV_TEMP_REALDIR);
        // CSVファイルのオープン
        $fp = fopen($enc_filepath, 'r');
        // 失敗した場合はエラー表示
        if (!$fp) {
            SC_Utils_Ex::sfDispError('');
        }

        // 登録先テーブル カラム情報の初期化
        $this->lfInitTableInfo();

        // 登録フォーム カラム情報
        $this->arrFormKeyList = $objFormParam->getKeyList();

        // 登録対象の列数
        $col_max_count = $objFormParam->getCount();
        // 行数
        $line_count = 0;

        $objQuery = SC_Query_Ex::getSingletonInstance();
        $objQuery->begin();

        $errFlag = false;

        while (!feof($fp)) {
            $arrCSV = fgetcsv($fp, CSV_LINE_MAX);
            // 行カウント
            $line_count++;
            // ヘッダ行はスキップ
            if ($line_count == 1) {
                continue;
            }
            // 空行はスキップ
            if (empty($arrCSV)) {
                continue;
            }
            // 列数が異なる場合はエラー
            $col_count = count($arrCSV);
            if ($col_max_count != $col_count) {
                $this->addRowErr($line_count, '※ 項目数が'.$col_count.'個検出されました。項目数は'.$col_max_count.'個になります。');
                $errFlag = true;
                break;
            }
            // シーケンス配列を格納する。
            $objFormParam->setParam($arrCSV, true);
            // 入力値の変換
            $objFormParam->convParam();
            // <br>なしでエラー取得する。
            $arrCSVErr = $this->lfCheckError($objFormParam);

            // 入力エラーチェック
            if (count($arrCSVErr) > 0) {
                foreach ($arrCSVErr as $err) {
                    $this->addRowErr($line_count, $err);
                }
                $errFlag = true;
                break;
            }

            $category_id = $this->lfRegistCategory($objQuery, $line_count, $objFormParam);
            $this->addRowResult($line_count, 'カテゴリID：'.$category_id.' / カテゴリ名：'.$objFormParam->getValue('category_name'));
        }

        // 実行結果画面を表示
        $this->tpl_mainpage = 'products/upload_csv_category_complete.tpl';

        fclose($fp);

        if ($errFlag) {
            $objQuery->rollback();

            return;
        }

        $objQuery->commit();

        // カテゴリ件数を更新
        $objDb = new SC_Helper_DB_Ex();
        $objDb->sfCountCategory($objQuery);

        return;
    }

    /**
     * ファイル情報の初期化を行う.
     *
     * @param SC_UploadFile $objUpFile
     *
     * @return void
     */
    public function lfInitFile(SC_UploadFile &$objUpFile)
    {
        $objUpFile->addFile('CSVファイル', 'csv_file', ['csv'], CSV_SIZE, true, 0, 0, false);
    }

    /**
     * 入力情報の初期化を行う.
     *
     * @param SC_FormParam $objFormParam
     * @param array $arrCSVFrame CSV構造設定配列
     *
     * @return void
     */
    public function lfInitParam(SC_FormParam &$objFormParam, &$arrCSVFrame)
    {
        // 固有の初期値調整
        $arrCSVFrame = $this->lfSetParamDefaultValue($arrCSVFrame);
        // CSV項目毎の処理
        foreach ($arrCSVFrame as $item) {
            if ($item['status'] == CSV_COLUMN_STATUS_FLG_DISABLE) {
                continue;
            }
            // サブクエリ構造の場合は AS名 を使用
            if (preg_match_all('/\(.+\) as (.+)$/i', $item['col'], $match, PREG_SET_ORDER)) {
                $col = $match[0][1];
            } else {
                $col = $item['col'];
            }
            // HTML_TAG_CHECKは別途実行なので除去し、別保存しておく
            if (str_contains(strtoupper($item['error_check_types']), 'HTML_TAG_CHECK')) {
                $this->arrTagCheckItem[] = $item;
                $error_check_types = str_replace('HTML_TAG_CHECK', '', $item['error_check_types']);
            } else {
                $error_check_types = $item['error_check_types'];
            }
            $arrErrorCheckTypes = explode(',', $error_check_types);
            foreach ($arrErrorCheckTypes as $key => $val) {
                if (trim($val) == '') {
                    unset($arrErrorCheckTypes[$key]);
                } else {
                    $arrErrorCheckTypes[$key] = trim($val);
                }
            }
            // パラメーター登録
            $objFormParam->addParam(
                $item['disp_name'],
                $col,
                defined($item['size_const_type']) ? constant($item['size_const_type']) : $item['size_const_type'],
                $item['mb_convert_kana_option'],
                $arrErrorCheckTypes,
                $item['default'] ?? null,
                $item['rw_flg'] != CSV_COLUMN_RW_FLG_READ_ONLY
            );
        }
    }

    /**
     * 入力チェックを行う.
     *
     * @param SC_FormParam $objFormParam
     *
     * @return array
     */
    public function lfCheckError(SC_FormParam &$objFormParam)
    {
        // 入力データを渡す。
        $arrRet = $objFormParam->getHashArray();
        $objErr = new SC_CheckError_Ex($arrRet);
        $objErr->arrErr = $objFormParam->checkError(false);
        // HTMLタグチェックの実行
        foreach ($this->arrTagCheckItem as $item) {
            $objErr->doFunc([$item['disp_name'], $item['col'], $this->arrAllowedTag], ['HTML_TAG_CHECK']);
        }
        // このフォーム特有の複雑系のエラーチェックを行う
        if (count($objErr->arrErr) == 0) {
            $objErr->arrErr = $this->lfCheckErrorDetail($arrRet, $objErr->arrErr);
        }

        return $objErr->arrErr;
    }

    /**
     * 保存先テーブル情報の初期化を行う.
     *
     * @return void
     */
    public function lfInitTableInfo()
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $this->arrRegistColumn = $objQuery->listTableFields('dtb_category');
    }

    /**
     * カテゴリ登録を行う.
     *
     * FIXME: 登録の実処理自体は、LC_Page_Admin_Products_Categoryと共通化して欲しい。
     *
     * @param  SC_Query       $objQuery SC_Queryインスタンス
     * @param  string|int $line     処理中の行数
     *
     * @return int        カテゴリID
     */
    public function lfRegistCategory($objQuery, $line, &$objFormParam)
    {
        // 登録データ対象取得
        $arrList = $objFormParam->getDbArray();
        // 登録時間を生成(DBのCURRENT_TIMESTAMPだとcommitした際、全て同一の時間になってしまう)
        $arrList['update_date'] = $this->lfGetDbFormatTimeWithLine($line);

        // 登録情報を生成する。
        // テーブルのカラムに存在しているもののうち、Form投入設定されていないデータは上書きしない。
        $sqlval = SC_Utils_Ex::sfArrayIntersectKeys($arrList, $this->arrRegistColumn);

        // 必須入力では無い項目だが、空文字では問題のある特殊なカラム値の初期値設定
        $sqlval = $this->lfSetCategoryDefaultData($sqlval);

        if ($sqlval['category_id'] != '') {
            // 同じidが存在すればupdate存在しなければinsert
            $where = 'category_id = ?';
            $category_exists = $objQuery->exists('dtb_category', $where, [$sqlval['category_id']]);
            if ($category_exists) {
                // UPDATEの実行
                $where = 'category_id = ?';
                $objQuery->update('dtb_category', $sqlval, $where, [$sqlval['category_id']]);
            } else {
                $sqlval['create_date'] = $arrList['update_date'];
                // 新規登録
                $this->registerCategory(
                    $sqlval['parent_category_id'],
                    $sqlval['category_name'],
                    $_SESSION['member_id'],
                    $sqlval['category_id']
                );
            }
            $category_id = $sqlval['category_id'];
        // TODO: 削除時処理
        } else {
            // 新規登録
            $category_id = $this->registerCategory(
                $sqlval['parent_category_id'],
                $sqlval['category_name'],
                $_SESSION['member_id']
            );
        }

        return $category_id;
    }

    /**
     * 初期値の設定
     *
     * @param  array $arrCSVFrame CSV構造配列
     *
     * @return array $arrCSVFrame CSV構造配列
     */
    public function lfSetParamDefaultValue(&$arrCSVFrame)
    {
        foreach ($arrCSVFrame as $key => $val) {
            switch ($val['col']) {
                case 'parent_category_id':
                    $arrCSVFrame[$key]['default'] = '0';
                    break;
                case 'del_flg':
                    $arrCSVFrame[$key]['default'] = '0';
                    break;
                default:
                    break;
            }
        }

        return $arrCSVFrame;
    }

    /**
     * データ登録前に特殊な値の持ち方をする部分のデータ部分の初期値補正を行う
     *
     * @param array $sqlval 商品登録情報配列
     *
     * @return array $sqlval 登録情報配列
     */
    public function lfSetCategoryDefaultData(&$sqlval)
    {
        if ($sqlval['del_flg'] == '') {
            $sqlval['del_flg'] = '0'; // 有効
        }
        if ($sqlval['creator_id'] == '') {
            $sqlval['creator_id'] = $_SESSION['member_id'];
        }
        if ($sqlval['parent_category_id'] == '') {
            $sqlval['parent_category_id'] = (string) '0';
        }

        return $sqlval;
    }

    /**
     * このフォーム特有の複雑な入力チェックを行う.
     *
     * @param array $item 確認対象データ
     * @param array $arrErr エラー配列
     *
     * @return array エラー配列
     */
    public function lfCheckErrorDetail($item, $arrErr)
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        /*
        // カテゴリIDの存在チェック
        if (!$this->lfIsDbRecord('dtb_category', 'category_id', $item)) {
            $arrErr['category_id'] = '※ 指定のカテゴリIDは、登録されていません。';
        }
        */
        // 親カテゴリIDの存在チェック
        if (array_search('parent_category_id', $this->arrFormKeyList) !== false
            && $item['parent_category_id'] != ''
            && $item['parent_category_id'] != '0'
            && !SC_Helper_DB_Ex::sfIsRecord('dtb_category', 'category_id', [$item['parent_category_id']])
        ) {
            $arrErr['parent_category_id'] = '※ 指定の親カテゴリID('.$item['parent_category_id'].')は、存在しません。';
        }
        // 削除フラグのチェック
        if (array_search('del_flg', $this->arrFormKeyList) !== false
            && $item['del_flg'] != ''
        ) {
            if (!($item['del_flg'] == '0' || $item['del_flg'] == '1')) {
                $arrErr['del_flg'] = '※ 削除フラグは「0」(有効)、「1」(削除)のみが有効な値です。';
            }
        }
        // 重複チェック 同じカテゴリ内に同名の存在は許可されない
        $parent_category_id = '';
        if (array_search('category_name', $this->arrFormKeyList) !== false
            && $item['category_name'] != ''
        ) {
            $parent_category_id = $item['parent_category_id'];
            if ($parent_category_id == '') {
                $parent_category_id = (string) '0';
            }
            $where = 'parent_category_id = ? AND category_id <> ? AND category_name = ?';
            $exists = $objQuery->exists(
                'dtb_category',
                $where,
                [
                    $parent_category_id,
                    $item['category_id'],
                    $item['category_name'],
                ]
            );
            if ($exists) {
                $arrErr['category_name'] = '※ 既に同名のカテゴリが存在します。';
            }
        }
        // 登録数上限チェック
        $where = 'del_flg = 0';
        $count = $objQuery->count('dtb_category', $where);
        if ($count >= CATEGORY_MAX) {
            $item['category_name'] = '※ カテゴリの登録最大数を超えました。';
        }

        if (array_search('parent_category_id', $this->arrFormKeyList) !== false
                && $item['parent_category_id'] != '') {
            $level = $objQuery->get('level', 'dtb_category', 'category_id = ?', [$parent_category_id]);
            if ($level >= LEVEL_MAX) {
                $arrErr['parent_category_id'] = '※ '.LEVEL_MAX.'階層以上の登録はできません。';
            }
        }

        return $arrErr;
    }

    /**
     * カテゴリを登録する
     *
     * @param int 親カテゴリID
     * @param string カテゴリ名
     * @param int 作成者のID
     * @param int 指定カテゴリID
     *
     * @return int カテゴリID
     */
    public function registerCategory($parent_category_id, $category_name, $creator_id, $category_id = null)
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();

        $rank = null;
        if ($parent_category_id == 0) {
            // ROOT階層で最大のランクを取得する。
            $where = 'parent_category_id = ?';
            $rank = $objQuery->max('rank', 'dtb_category', $where, [$parent_category_id]) + 1;
        } else {
            // 親のランクを自分のランクとする。
            $where = 'category_id = ?';
            $rank = $objQuery->get('rank', 'dtb_category', $where, [$parent_category_id]);
            // 追加レコードのランク以上のレコードを一つあげる。
            $where = 'rank >= ?';
            $arrRawSql = [
                'rank' => '(rank + 1)',
            ];
            $objQuery->update('dtb_category', [], $where, [$rank], $arrRawSql);
        }

        $where = 'category_id = ?';
        // 自分のレベルを取得する(親のレベル + 1)
        $level = $objQuery->get('level', 'dtb_category', $where, [$parent_category_id]) + 1;

        $arrCategory = [];
        $arrCategory['category_name'] = $category_name;
        $arrCategory['parent_category_id'] = $parent_category_id;
        $arrCategory['create_date'] = 'CURRENT_TIMESTAMP';
        $arrCategory['update_date'] = 'CURRENT_TIMESTAMP';
        $arrCategory['creator_id'] = $creator_id;
        $arrCategory['rank'] = $rank;
        $arrCategory['level'] = $level;
        // カテゴリIDが指定されていればそれを利用する
        if (isset($category_id)) {
            $arrCategory['category_id'] = $category_id;
            // シーケンスの調整
            $seq_count = $objQuery->currVal('dtb_category_category_id');
            if ($seq_count < $arrCategory['category_id']) {
                $objQuery->setVal('dtb_category_category_id', $arrCategory['category_id'] + 1);
            }
        } else {
            $arrCategory['category_id'] = $objQuery->nextVal('dtb_category_category_id');
        }
        $objQuery->insert('dtb_category', $arrCategory);

        return $arrCategory['category_id'];
    }

    /**
     * 指定された行番号をmicrotimeに付与してDB保存用の時間を生成する。
     * トランザクション内のCURRENT_TIMESTAMPは全てcommit()時の時間に統一されてしまう為。
     *
     * @param  string $line_no 行番号
     *
     * @return string $time DB保存用の時間文字列
     */
    public function lfGetDbFormatTimeWithLine($line_no = '')
    {
        $time = date('Y-m-d H:i:s');
        // 秒以下を生成
        if ($line_no != '') {
            $microtime = sprintf('%06d', $line_no);
            $time .= ".$microtime";
        }

        return $time;
    }

    /**
     * 指定されたキーと値の有効性のDB確認
     *
     * @param  string  $table   テーブル名
     * @param  string  $keyname キー名
     * @param  array   $item    入力データ配列
     *
     * @return bool true:有効なデータがある false:有効ではない
     */
    public function lfIsDbRecord($table, $keyname, $item)
    {
        if (array_search($keyname, $this->arrFormKeyList) !== false  // 入力対象である
            && $item[$keyname] != ''   // 空ではない
            && !SC_Helper_DB_Ex::sfIsRecord($table, $keyname, (array) $item[$keyname]) // DBに存在するか
        ) {
            return false;
        }

        return true;
    }
}
