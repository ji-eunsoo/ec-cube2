<?php

/*
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 */

/**
 * CSV 関連 のヘルパークラス.
 *
 * @author EC-CUBE CO.,LTD.
 *
 * @version $Id$
 */
class SC_Helper_CSV
{
    /** 項目英名 */
    public $arrSubnavi;

    /** 項目名 */
    public $arrSubnaviName;

    /** @var resource */
    public $fpOutput;

    /** ヘッダーを出力するか (cbOutputCSV 用) */
    private $output_header = false;

    /**
     * デフォルトコンストラクタ.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * 項目情報を初期化する.
     *
     * @return void
     */
    public function init()
    {
        $this->arrSubnavi = [
            1 => 'product',
            2 => 'customer',
            3 => 'order',
            4 => 'review',
            5 => 'category',
        ];

        $this->arrSubnaviName = [
            1 => '商品管理',
            2 => '会員管理',
            3 => '受注管理',
            4 => 'レビュー',
            5 => 'カテゴリ',
        ];
    }

    /**
     * CSVファイルを送信する
     *
     * @param  int $csv_id      CSVフォーマットID
     * @param  string  $where       WHERE条件文
     * @param  array   $arrVal      プリペアドステートメントの実行時に使用される配列。配列の要素数は、クエリ内のプレースホルダの数と同じでなければなりません。
     * @param  string  $order       ORDER文
     * @param  bool $is_download true:ダウンロード用出力までさせる false:CSVの内容を返す(旧方式、メモリを食います。）
     *
     * @return bool|string   $is_download = true時 成功失敗フラグ(boolean) 、$is_downalod = false時 string
     */
    public function sfDownloadCsv($csv_id, $where = '', $arrVal = [], $order = '', $is_download = false)
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();

        // CSV出力タイトル行の作成
        $arrOutput = SC_Utils_Ex::sfSwapArray($this->sfGetCsvOutput($csv_id, 'status = '.CSV_COLUMN_STATUS_FLG_ENABLE));
        if (count($arrOutput) <= 0) {
            return false;
        } // 失敗終了
        $arrOutputCols = $arrOutput['col'];

        $cols = SC_Utils_Ex::sfGetCommaList($arrOutputCols, true);

        // 商品の場合
        $from = '';
        if ($csv_id == 1) {
            // この WHERE 句を足さないと無効な規格も出力される。現行仕様と合わせる為追加。
            $inner_where = 'dtb_products_class.del_flg = 0';
            $from = SC_Product_Ex::prdclsSQL($inner_where);
        // 会員の場合
        } elseif ($csv_id == 2) {
            $from = 'dtb_customer';
        // 注文の場合
        } elseif ($csv_id == 3) {
            $from = 'dtb_order';
        // レビューの場合
        } elseif ($csv_id == 4) {
            $from = 'dtb_review AS A INNER JOIN dtb_products AS B on A.product_id = B.product_id';
        // カテゴリの場合
        } elseif ($csv_id == 5) {
            $from = 'dtb_category';
        }

        $objQuery->setOrder($order);
        $sql = $objQuery->getSql($cols, $from, $where);

        return $this->sfDownloadCsvFromSql($sql, $arrVal, $this->arrSubnavi[$csv_id], $arrOutput['disp_name'], $is_download);
    }

    /**
     * CSV 項目を出力する.
     *
     * @param  int $csv_id CSV ID
     * @param  string  $where  SQL の WHERE 句
     * @param  array   $arrVal WHERE 句の要素
     * @param  string  $order  SQL の ORDER BY 句
     *
     * @return array   CSV 項目の配列
     */
    public function sfGetCsvOutput($csv_id = '', $where = '', $arrVal = [], $order = 'rank, no')
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();

        $cols = 'no, csv_id, col, disp_name, rank, status, rw_flg, mb_convert_kana_option, size_const_type, error_check_types';
        $table = 'dtb_csv';

        if (SC_Utils_Ex::sfIsInt($csv_id)) {
            if ($where == '') {
                $where = 'csv_id = ?';
            } else {
                $where = "$where AND csv_id = ?";
            }
            $arrVal[] = $csv_id;
        }
        $objQuery->setOrder($order);

        $arrRet = $objQuery->select($cols, $table, $where, $arrVal);

        return $arrRet;
    }

    /**
     * CSVが出力設定でインポート可能かのチェック
     *
     * @param array sfGetCsvOutputで取得した内容（またはそれと同等の配列)
     *
     * @return bool true:インポート可能、false:インポート不可
     */
    public function sfIsImportCSVFrame(&$arrCSVFrame)
    {
        $result = true;
        foreach ($arrCSVFrame as $val) {
            if ($val['status'] != CSV_COLUMN_STATUS_FLG_ENABLE
                && $val['rw_flg'] == CSV_COLUMN_RW_FLG_READ_WRITE
                && $val['error_check_types'] != ''
                && str_contains(strtoupper($val['error_check_types']), 'EXIST_CHECK')
            ) {
                // 必須フィールド
                $result = false;
            }
        }

        return $result;
    }

    /**
     * CSVが出力設定で更新可能かのチェック
     *
     * @param array sfGetCsvOutputで取得した内容（またはそれと同等の配列)
     *
     * @return bool true:更新可能、false:新規追加のみ不可
     */
    public function sfIsUpdateCSVFrame(&$arrCSVFrame)
    {
        $result = true;
        foreach ($arrCSVFrame as $val) {
            if ($val['status'] != CSV_COLUMN_STATUS_FLG_ENABLE
                && $val['rw_flg'] == CSV_COLUMN_RW_FLG_KEY_FIELD
            ) {
                // キーフィールド
                $result = false;
            }
        }

        return $result;
    }

    /**
     * CSVファイルのカウント数を得る.
     *
     * @param  resource $fp fopenを使用して作成したファイルポインタ
     *
     * @return int  CSV のカウント数
     */
    public function sfGetCSVRecordCount($fp)
    {
        $count = 0;
        while (!feof($fp)) {
            $arrCSV = fgetcsv($fp, CSV_LINE_MAX);
            $count++;
        }
        // ファイルポインタを戻す
        if (rewind($fp)) {
            return $count - 1;
        } else {
            return false;
        }
    }

    /**
     * CSV作成 テンポラリファイル出力 コールバック関数
     *
     * @param  mixed   $data 出力データ
     *
     * @return bool true (true:固定 false:中断)
     */
    public function cbOutputCSV($data)
    {
        // mb_convert_variablesで変換できない文字は"?"にする
        mb_substitute_character(SUBSTITUTE_CHAR);
        // 1行目のみヘッダーを出力する
        if ($this->output_header) {
            $header = array_keys($data);
            mb_convert_variables('cp932', CHAR_CODE, $header);
            fputcsv($this->fpOutput, $header);
            $this->output_header = false;
        }
        mb_convert_variables('cp932', CHAR_CODE, $data);
        fputcsv($this->fpOutput, $data);
        SC_Utils_Ex::extendTimeOut();

        return true;
    }

    /**
     * SQL文からクエリ実行し CSVファイルを送信する
     *
     * @param  int $sql         SQL文
     * @param  array   $arrVal      プリペアドステートメントの実行時に使用される配列。配列の要素数は、クエリ内のプレースホルダの数と同じでなければなりません。
     * @param  string       ファイル名の頭に付ける文字列
     * @param  array|null   ヘッダ出力列配列。null の場合、SQL 文の列名を出力する。
     * @param  bool      true:ダウンロード用出力までさせる false:CSVの内容を返す(旧方式、メモリを食います。）
     *
     * @return bool|string   $is_download = true時 成功失敗フラグ(boolean) 、$is_downalod = false時 string
     */
    public function sfDownloadCsvFromSql($sql, $arrVal = [], $file_head = 'csv', $arrHeader = null, $is_download = false)
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();

        if (!$is_download) {
            ob_start();
        }

        $this->fpOutput = &SC_Helper_CSV_Ex::fopen_for_output_csv();

        // mb_convert_variablesで変換できない文字は"?"にする
        mb_substitute_character(SUBSTITUTE_CHAR);
        // ヘッダー構築
        $this->output_header = false;
        if (is_array($arrHeader)) {
            mb_convert_variables('cp932', CHAR_CODE, $arrHeader);
            fputcsv($this->fpOutput, $arrHeader);
        } elseif (is_null($arrHeader)) {
            // ループバック内でヘッダーを出力する
            $this->output_header = true;
        }

        $objQuery->doCallbackAll([&$this, 'cbOutputCSV'], $sql, $arrVal);

        // コールバック内でヘッダー出力する場合、0行時にヘッダーを生成できない。
        // コールバックが呼ばれていない場合、念のため CRLF を出力しておく。
        // XXX WEB画面前提で、アラート表示する流れのほうが親切かもしれない。
        if ($this->output_header) {
            fwrite($this->fpOutput, "\r\n");
        }

        fclose($this->fpOutput);

        // CSV 用の HTTP ヘッダーを送出する。
        if ($is_download) {
            $file_name = $file_head.'_'.date('ymd_His').'.csv';
            SC_Response_Ex::headerForDownload($file_name);
            $return = true;
        }
        // 戻り値にCSVデータをセットする
        else {
            $return = ob_get_clean();
        }

        return $return;
    }

    /**
     * 前方互換用
     *
     * @deprecated 2.13.2 fputcsv を使うこと。(sfDownloadCsvFromSql や cbOutputCSV の実装を参照)
     */
    public function sfArrayToCsv($fields, $delimiter = ',', $enclosure = '"', $arrayDelimiter = '|')
    {
        trigger_error('前方互換用メソッドが使用されました。', E_USER_WARNING);
        if (strlen($delimiter) != 1) {
            trigger_error('delimiter must be a single character', E_USER_WARNING);

            return '';
        }

        if (strlen($enclosure) < 1) {
            trigger_error('enclosure must be a single character', E_USER_WARNING);

            return '';
        }

        foreach ($fields as $key => $value) {
            $field = &$fields[$key];

            // 配列を「|」区切りの文字列に変換する
            if (is_array($field)) {
                $field = implode($arrayDelimiter, $field);
            }

            /* enclose a field that contains a delimiter, an enclosure character, or a newline */
            if (is_string($field)
                && preg_match('/['.preg_quote($delimiter).preg_quote($enclosure).'\\s]/', $field)
            ) {
                $field = $enclosure.preg_replace('/'.preg_quote($enclosure).'/', $enclosure.$enclosure, $field).$enclosure;
            }
        }

        return implode($delimiter, $fields);
    }

    /**
     * 前方互換用
     *
     * @deprecated 2.13.2
     */
    public function lfDownloadCsv($arrData, $prefix = '')
    {
        trigger_error('前方互換用メソッドが使用されました。', E_USER_WARNING);
        if ($prefix == '') {
            $dir_name = SC_Utils_Ex::sfUpDirName();
            $file_name = $dir_name.date('ymdHis').'.csv';
        } else {
            $file_name = $prefix.date('ymdHis').'.csv';
        }
        SC_Response_Ex::headerForDownload($file_name);

        /* データを出力 */
        $fp = &SC_Helper_CSV_Ex::fopen_for_output_csv();
        foreach ($arrData as $lineArray) {
            fputcsv($fp, $lineArray);
        }
        fclose($fp);
    }

    /**
     * 前方互換用
     *
     * @deprecated 2.13.2
     */
    public function lfDownloadCSVFile($filepath, $prefix = '')
    {
        trigger_error('前方互換用メソッドが使用されました。', E_USER_WARNING);
        $file_name = $prefix.date('YmdHis').'.csv';
        SC_Response_Ex::headerForDownload($file_name);

        /* データを出力 */
        // file_get_contentsはメモリマッピングも自動的に使ってくれるので高速＆省メモリ
        echo file_get_contents($filepath);
    }

    /**
     * CSV 出力用のファイルポインタリソースを開く
     *
     * @return resource ファイルポインタリソース
     */
    public static function &fopen_for_output_csv($filename = 'php://output')
    {
        $fp = fopen($filename, 'w');

        stream_filter_append($fp, 'convert.eccube_lf2crlf');

        return $fp;
    }
}

/**
 * 改行コードを CRLF に変換するフィルター
 *
 * @author Seasoft 塚田将久 (新規作成)
 *
 * @version $Id$
 */
class php_user_filter_lf2crlf extends php_user_filter
{
    #[\ReturnTypeWillChange]
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = preg_replace("/[\r\n]+$/", "\r\n", $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}

stream_filter_register('convert.eccube_lf2crlf', 'php_user_filter_lf2crlf');
