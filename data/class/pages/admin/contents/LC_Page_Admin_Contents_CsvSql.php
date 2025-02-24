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
 * CSV 出力項目設定(高度な設定)のページクラス.
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class LC_Page_Admin_Contents_CsvSql extends LC_Page_Admin_Ex
{
    /** @var array */
    public $tpl_subno_csv;
    /** @var string */
    public $sqlerr;
    /** @var array */
    public $arrColList;
    /** @var array */
    public $arrSqlList;
    /** @var array */
    public $arrTableList;

    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->tpl_mainpage = 'contents/csv_sql.tpl';
        $this->tpl_subno = 'csv';
        $this->tpl_subno_csv = 'csv_sql';
        $this->tpl_mainno = 'contents';
        $this->tpl_maintitle = 'コンテンツ管理';
        $this->tpl_subtitle = 'CSV出力設定';
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
        // パラメーター管理クラス
        $objFormParam = new SC_FormParam_Ex();
        // パラメーター設定
        $this->lfInitParam($objFormParam);
        $objFormParam->setParam($_POST);
        $objFormParam->setParam($_GET);
        $objFormParam->convParam();
        $this->arrForm = $objFormParam->getHashArray();
        switch ($this->getMode()) {
            // データの登録
            case 'confirm':
                $this->arrErr = $this->lfCheckConfirmError($objFormParam);
                if (SC_Utils_Ex::isBlank($this->arrErr)) {
                    // データの更新
                    $this->arrForm['sql_id'] = $this->lfUpdData($objFormParam->getValue('sql_id'), $objFormParam->getDbArray());
                    // 完了メッセージ表示
                    $this->tpl_onload = "alert('登録が完了しました。');";
                }
                break;
                // 確認画面
            case 'preview':
                $this->arrErr = $this->lfCheckPreviewError($objFormParam);
                if (SC_Utils_Ex::isBlank($this->arrErr)) {
                    $this->sqlerr = $this->lfCheckSQL($objFormParam->getValue('csv_sql'));
                }
                $this->setTemplate('contents/csv_sql_view.tpl');

                return;

                // 新規作成
            case 'new_page':
                // リロード
                SC_Response_Ex::reload();
                break;
                // データ削除
            case 'delete':
                $this->arrErr = $this->lfCheckDeleteError($objFormParam);
                if (SC_Utils_Ex::isBlank($this->arrErr)) {
                    $this->lfDelData($objFormParam->getValue('sql_id'));
                    SC_Response_Ex::reload();
                    SC_Response_Ex::actionExit();
                }
                break;
                // CSV出力
            case 'csv_output':
                $this->arrErr = $this->lfCheckOutputError($objFormParam);
                if (SC_Utils_Ex::isBlank($this->arrErr)) {
                    $this->lfDoCsvOutput($objFormParam->getValue('csv_output_id'));
                    SC_Response_Ex::actionExit();
                }
                break;
            default:
                $this->arrErr = $objFormParam->checkError();
                if (SC_Utils_Ex::isBlank($this->arrErr)) {
                    // 設定内容を取得する
                    $this->arrForm = $this->lfGetSqlData($objFormParam);
                    // カラム一覧を取得する
                    $this->arrColList = $this->lfGetColList($objFormParam->getValue('select_table'));
                }
                break;
        }

        // 登録済みSQL一覧取得
        $this->arrSqlList = $this->lfGetSqlList();
        // テーブル一覧を取得する
        $this->arrTableList = $this->lfGetTableList();
    }

    /**
     * パラメーター情報の初期化
     *
     * @param  SC_FormParam_Ex $objFormParam フォームパラメータークラス
     *
     * @return void
     */
    public function lfInitParam(&$objFormParam)
    {
        $objFormParam->addParam('SQL ID', 'sql_id', INT_LEN, 'n', ['NUM_CHECK', 'MAX_LENGTH_CHECK']);
        $objFormParam->addParam('CSV出力対象SQL ID', 'csv_output_id', INT_LEN, 'n', ['NUM_CHECK', 'MAX_LENGTH_CHECK'], '', false);
        $objFormParam->addParam('選択テーブル', 'select_table', STEXT_LEN, 'KVa', ['GRAPH_CHECK', 'MAX_LENGTH_CHECK'], '', false);
        $objFormParam->addParam('名称', 'sql_name', STEXT_LEN, 'KVa', ['MAX_LENGTH_CHECK', 'SPTAB_CHECK']);
        $objFormParam->addParam('SQL文', 'csv_sql', '30000', 'KVa', ['MAX_LENGTH_CHECK', 'SPTAB_CHECK']);
    }

    /**
     * SQL登録エラーチェック
     *
     * @param  SC_FormParam_Ex $objFormParam フォームパラメータークラス
     *
     * @return array エラー配列
     */
    public function lfCheckConfirmError(&$objFormParam)
    {
        // パラメーターの基本チェック
        $arrErr = $objFormParam->checkError();
        // 拡張エラーチェック
        $objErr = new SC_CheckError_Ex($objFormParam->getHashArray());
        $objErr->doFunc(['名称', 'sql_name'], ['EXIST_CHECK']);
        $objErr->doFunc(['SQL文', 'csv_sql', '30000'], ['EXIST_CHECK', 'MAX_LENGTH_CHECK']);
        $objErr->doFunc(['SQL文には読み込み関係以外のSQLコマンドおよび";"記号', 'csv_sql', $this->lfGetSqlDenyList()], ['PROHIBITED_STR_CHECK']);
        if (!SC_Utils_Ex::isBlank($objErr->arrErr)) {
            $arrErr = array_merge($arrErr, $objErr->arrErr);
        }
        // SQL文自体の確認、エラーが無い時のみ実行
        if (SC_Utils_Ex::isBlank($arrErr)) {
            $sql_error = $this->lfCheckSQL($objFormParam->getValue('csv_sql'));
            if (!SC_Utils_Ex::isBlank($sql_error)) {
                $arrErr['csv_sql'] = '※ SQL文が不正です。SQL文を見直してください';
            }
        }

        return $arrErr;
    }

    /**
     * SQL確認エラーチェック
     *
     * @param  SC_FormParam_Ex $objFormParam フォームパラメータークラス
     *
     * @return array エラー配列
     */
    public function lfCheckPreviewError(&$objFormParam)
    {
        // パラメーターの基本チェック
        $arrErr = $objFormParam->checkError();
        // 拡張エラーチェック
        $objErr = new SC_CheckError_Ex($objFormParam->getHashArray());
        $objErr->doFunc(['SQL文', 'csv_sql', '30000'], ['EXIST_CHECK', 'MAX_LENGTH_CHECK']);
        $objErr->doFunc(['SQL文には読み込み関係以外のSQLコマンドおよび";"記号', 'csv_sql', $this->lfGetSqlDenyList()], ['PROHIBITED_STR_CHECK']);
        if (!SC_Utils_Ex::isBlank($objErr->arrErr)) {
            $arrErr = array_merge($arrErr, $objErr->arrErr);
        }

        return $arrErr;
    }

    /**
     * SQL設定 削除エラーチェック
     *
     * @param  SC_FormParam_Ex $objFormParam フォームパラメータークラス
     *
     * @return array エラー配列
     */
    public function lfCheckDeleteError(&$objFormParam)
    {
        // パラメーターの基本チェック
        $arrErr = $objFormParam->checkError();
        // 拡張エラーチェック
        $objErr = new SC_CheckError_Ex($objFormParam->getHashArray());
        $objErr->doFunc(['SQL ID', 'sql_id'], ['EXIST_CHECK']);
        if (!SC_Utils_Ex::isBlank($objErr->arrErr)) {
            $arrErr = array_merge($arrErr, $objErr->arrErr);
        }

        return $arrErr;
    }

    /**
     * SQL設定 CSV出力エラーチェック
     *
     * @param  SC_FormParam_Ex $objFormParam フォームパラメータークラス
     *
     * @return array エラー配列
     */
    public function lfCheckOutputError(&$objFormParam)
    {
        // パラメーターの基本チェック
        $arrErr = $objFormParam->checkError();
        // 拡張エラーチェック
        $objErr = new SC_CheckError_Ex($objFormParam->getHashArray());
        $objErr->doFunc(['CSV出力対象SQL ID', 'csv_output_id'], ['EXIST_CHECK']);
        if (!SC_Utils_Ex::isBlank($objErr->arrErr)) {
            $arrErr = array_merge($arrErr, $objErr->arrErr);
        }

        return $arrErr;
    }

    /**
     * テーブル一覧を取得する.
     *
     * @return array テーブル名一覧
     */
    public function lfGetTableList()
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        // 実テーブル上のカラム設定を見に行く仕様に変更 ref #476
        $arrTable = $objQuery->listTables();
        if (SC_Utils_Ex::isBlank($arrTable)) {
            return [];
        }
        $arrRet = [];
        foreach ($arrTable as $table) {
            if (substr($table, 0, 4) == 'dtb_') {
                $arrRet[$table] = 'データテーブル: '.$table;
            } elseif (substr($table, 0, 4) == 'mtb_') {
                $arrRet[$table] = 'マスターテーブル: '.$table;
            }
        }

        return $arrRet;
    }

    /**
     * テーブルのカラム一覧を取得する.
     *
     * @return array  カラム一覧の配列
     */
    public function lfGetColList($table)
    {
        if (SC_Utils_Ex::isBlank($table)) {
            return [];
        }
        $objQuery = SC_Query_Ex::getSingletonInstance();
        // 実テーブル上のカラム設定を見に行く仕様に変更 ref #476
        $arrColList = $objQuery->listTableFields($table);
        $arrColList = SC_Utils_Ex::sfArrCombine($arrColList, $arrColList);

        return $arrColList;
    }

    /**
     * 登録済みSQL一覧を取得する.
     *
     * @param  string $where  Where句
     * @param  array  $arrVal 絞り込みデータ
     *
     * @return array  取得結果の配列
     */
    public function lfGetSqlList($where = '', $arrVal = [])
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $table = 'dtb_csv_sql';
        $objQuery->setOrder('sql_id');

        return $objQuery->select('*', $table, $where, $arrVal);
    }

    /**
     * 入力されたSQL文が正しく実行出来るかのチェックを行う.
     *
     * @param string SQL文データ(頭にSELECTは入れない)
     *
     * @return string エラー内容
     */
    public function lfCheckSQL($sql)
    {
        // FIXME: 意図的に new SC_Query しています。 force_runをtrueにする必要があるので.本当はqueryの引数で制御したい。ref SC_Query
        $objQuery = new SC_Query_Ex('', true);
        $err = '';
        $sql = 'SELECT '.$sql.' ';
        $objErrMsg = $objQuery->query($sql);
        if (PEAR::isError($objErrMsg)) {
            $err = $objErrMsg->message."\n".$objErrMsg->userinfo;
        }

        return $err;
    }

    /**
     * SQL詳細設定情報呼び出し (編集中データがある場合はそれを保持する）
     *
     * @param  SC_FormParam_Ex $objFormParam フォームパラメータークラス
     *
     * @return mixed 表示用パラメーター
     */
    public function lfGetSqlData(&$objFormParam)
    {
        // 編集中データがある場合
        if (!SC_Utils_Ex::isBlank($objFormParam->getValue('sql_name'))
            || !SC_Utils_Ex::isBlank($objFormParam->getValue('csv_sql'))
        ) {
            return $objFormParam->getHashArray();
        }
        $sql_id = $objFormParam->getValue('sql_id');
        if (!SC_Utils_Ex::isBlank($sql_id)) {
            $arrData = $this->lfGetSqlList('sql_id = ?', [$sql_id]);

            return $arrData[0];
        }

        return [];
    }

    /**
     * DBにデータを保存する.
     *
     * @param  int $sql_id 出力するデータのSQL_ID
     *
     * @return void
     */
    public function lfDoCsvOutput($sql_id)
    {
        $objCSV = new SC_Helper_CSV_Ex();

        $arrData = $this->lfGetSqlList('sql_id = ?', [$sql_id]);
        $sql = 'SELECT '.$arrData[0]['csv_sql'];

        $objCSV->sfDownloadCsvFromSql($sql, [], 'contents', null, true);
        SC_Response_Ex::actionExit();
    }

    /**
     * DBにデータを保存する.
     *
     * @param  int $sql_id    更新するデータのSQL_ID
     * @param  array   $arrSqlVal 更新データの配列
     *
     * @return int $sql_id SQL_IDを返す
     */
    public function lfUpdData($sql_id, $arrSqlVal)
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $table = 'dtb_csv_sql';
        $arrSqlVal['update_date'] = 'CURRENT_TIMESTAMP';
        if (SC_Utils_Ex::sfIsInt($sql_id)) {
            // データ更新
            $where = 'sql_id = ?';
            $objQuery->update($table, $arrSqlVal, $where, [$sql_id]);
        } else {
            // 新規作成
            $sql_id = $objQuery->nextVal('dtb_csv_sql_sql_id');
            $arrSqlVal['sql_id'] = $sql_id;
            $arrSqlVal['create_date'] = 'CURRENT_TIMESTAMP';
            $objQuery->insert($table, $arrSqlVal);
        }

        return $sql_id;
    }

    /**
     * 登録済みデータを削除する.
     *
     * @param  int $sql_id 削除するデータのSQL_ID
     *
     * @return bool 実行結果 true：成功
     */
    public function lfDelData($sql_id)
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $table = 'dtb_csv_sql';
        $where = 'sql_id = ?';
        if (SC_Utils_Ex::sfIsInt($sql_id)) {
            $objQuery->delete($table, $where, [$sql_id]);

            return true;
        }

        return false;
    }

    /**
     * SQL文に含めることを許可しないSQLキーワード
     * 基本的にEC-CUBEのデータを取得するために必要なコマンドしか許可しない。複数クエリも不可
     *
     * FIXME: キーワードの精査。危険な部分なのでプログラム埋め込みで実装しました。mtb化の有無判断必要。
     *
     * @return string[] 不許可ワード配列
     */
    public function lfGetSqlDenyList()
    {
        $arrList = [
            ';',
            'CREATE\s',
            'INSERT\s',
            'UPDATE\s',
            'DELETE\s',
            'ALTER\s',
            'ABORT\s',
            'ANALYZE\s',
            'CLUSTER\s',
            'COMMENT\s',
            'COPY\s',
            'DECLARE\s',
            'DISCARD\s',
            'DO\s',
            'DROP\s',
            'EXECUTE\s',
            'EXPLAIN\s',
            'GRANT\s',
            'LISTEN\s',
            'LOAD\s',
            'LOCK\s',
            'NOTIFY\s',
            'PREPARE\s',
            'REASSIGN\s',
            // 'REINDEX\s', // REINDEXは許可で良いかなと
            'RELEASE\sSAVEPOINT',
            'RENAME\s',
            'REST\s',
            'REVOKE\s',
            'SAVEPOINT\s',
            '\sSET\s', // OFFSETを誤検知しないように先頭・末尾に\sを指定
            'SHOW\s',
            'START\sTRANSACTION',
            'TRUNCATE\s',
            'UNLISTEN\s',
            'VACCUM\s',
            'HANDLER\s',
            'LOAD\sDATA\s',
            'LOAD\sXML\s',
            'REPLACE\s',
            'OPTIMIZE\s',
            'REPAIR\s',
            'INSTALL\sPLUGIN\s',
            'UNINSTALL\sPLUGIN\s',
            'BINLOG\s',
            'KILL\s',
            'RESET\s',
            'PURGE\s',
            'CHANGE\sMASTER',
            'START\sSLAVE',
            'STOP\sSLAVE',
            'MASTER\sPOS\sWAIT',
            'SIGNAL\s',
            'RESIGNAL\s',
            'RETURN\s',
            'USE\s',
            'HELP\s',
        ];

        return $arrList;
    }
}
