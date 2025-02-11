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

/* アップロードファイル管理クラス */
class SC_UploadFile
{
    public $temp_dir;
    public $save_dir;

    /** ファイルinputタグのname */
    public $keyname = [];

    /** 横サイズ */
    public $width = [];

    /** 縦サイズ */
    public $height = [];

    /** 指定する拡張子 */
    public $arrExt = [];

    /** 保存されたファイル名 */
    public $temp_file = [];

    /** DBから読み出したファイル名 */
    public $save_file = [];

    /** 項目名 */
    public $disp_name = [];

    /** 制限サイズ */
    public $size = [];

    /** 必須の場合:true */
    public $necessary = [];

    /** 画像の場合:true */
    public $image = [];

    /** @var int */
    public $file_max;

    // ファイル管理クラス
    public function __construct($temp_dir, $save_dir)
    {
        $this->temp_dir = rtrim($temp_dir, '/').'/';
        $this->save_dir = rtrim($save_dir, '/').'/';
        $this->file_max = 0;
    }

    // ファイル情報追加
    public function addFile($disp_name, $keyname, $arrExt, $size, $necessary = false, $width = 0, $height = 0, $image = true)
    {
        $this->disp_name[] = $disp_name;
        $this->keyname[] = $keyname;
        $this->width[] = $width;
        $this->height[] = $height;
        $this->arrExt[] = $arrExt;
        $this->size[] = $size;
        $this->necessary[] = $necessary;
        $this->image[] = $image;
    }
    // サムネイル画像の作成

    /**
     * @param string $dst_file
     */
    public function makeThumb($src_file, $width, $height, $dst_file)
    {
        $objThumb = new gdthumb();
        $ret = $objThumb->Main($src_file, $width, $height, $dst_file);

        if ($ret[0] != 1) {
            // エラーメッセージの表示
            echo $ret[1];
            exit;
        }

        return basename($ret[1]);
    }

    // アップロードされたファイルを保存する。

    /**
     * @param bool $rename
     */
    public function makeTempFile($keyname, $rename = IMAGE_RENAME)
    {
        $objErr = new SC_CheckError_Ex();
        $cnt = 0;
        $check = $this->checkUploadError($keyname, $objErr);
        if ($check) {
            foreach ($this->keyname as $val) {
                // 一致したキーのファイルに情報を保存する。
                if ($val == $keyname) {
                    // 拡張子チェック
                    $objErr->doFunc([$this->disp_name[$cnt], $keyname, $this->arrExt[$cnt]], ['FILE_EXT_CHECK']);
                    // ファイルサイズチェック
                    $objErr->doFunc([$this->disp_name[$cnt], $keyname, $this->size[$cnt]], ['FILE_SIZE_CHECK']);
                    // エラーがない場合
                    if (!isset($objErr->arrErr[$keyname])) {
                        // 画像ファイルの場合
                        if ($this->image[$cnt]) {
                            // 保存用の画像名を取得する
                            $dst_file = $this->lfGetTmpImageName($rename, $keyname);
                            $this->temp_file[$cnt] = $this->makeThumb($_FILES[$keyname]['tmp_name'], $this->width[$cnt], $this->height[$cnt], $dst_file);
                        // 画像ファイル以外の場合
                        } else {
                            // 一意なファイル名を作成する。
                            if ($rename) {
                                $uniqname = date('mdHi').'_'.uniqid('').'.';
                                $this->temp_file[$cnt] = preg_replace("/^.*\./", $uniqname, $_FILES[$keyname]['name']);
                            } else {
                                $this->temp_file[$cnt] = $_FILES[$keyname]['name'];
                            }
                            if (move_uploaded_file($_FILES[$keyname]['tmp_name'], $this->temp_dir.$this->temp_file[$cnt])) {
                                GC_Utils_Ex::gfPrintLog($_FILES[$keyname]['name'].' -> '.$this->temp_dir.$this->temp_file[$cnt]);
                            } else {
                                $objErr->arrErr[$keyname] = '※ ファイルのアップロードに失敗しました。<br />';
                                GC_Utils_Ex::gfPrintLog('File Upload Error!: '.$_FILES[$keyname]['name'].' -> '.$this->temp_dir.$this->temp_file[$cnt]);
                            }
                        }
                    }
                }
                $cnt++;
            }
        }

        return $objErr->arrErr[$keyname] ?? '';
    }

    // アップロードされたダウンロードファイルを保存する。
    public function makeTempDownFile($keyname = 'down_file')
    {
        $objErr = new SC_CheckError_Ex();
        $cnt = 0;
        $check = $this->checkUploadError($keyname, $objErr);
        if ($check) {
            foreach ($this->keyname as $val) {
                // 一致したキーのファイルに情報を保存する。
                if ($val == $keyname) {
                    // 拡張子チェック
                    $objErr->doFunc([$this->disp_name[$cnt], $keyname, $this->arrExt[$cnt]], ['FILE_EXT_CHECK']);
                    // ファイルサイズチェック
                    $objErr->doFunc([$this->disp_name[$cnt], $keyname, $this->size[$cnt]], ['FILE_SIZE_CHECK']);
                    // エラーがない場合
                    if (!isset($objErr->arrErr[$keyname])) {
                        // 一意なファイル名を作成する。
                        $uniqname = date('mdHi').'_'.uniqid('').'.';
                        $this->temp_file[$cnt] = preg_replace("/^.*\./", $uniqname, $_FILES[$keyname]['name']);
                        $result = copy($_FILES[$keyname]['tmp_name'], $this->temp_dir.$this->temp_file[$cnt]);
                        GC_Utils_Ex::gfPrintLog($result.' -> '.$this->temp_dir.$this->temp_file[$cnt]);
                        SC_Utils_Ex::extendTimeOut();
                    }
                }
                $cnt++;
            }
        }

        return $objErr->arrErr[$keyname] ?? '';
    }

    // 画像を削除する。
    public function deleteFile($keyname)
    {
        $objImage = new SC_Image_Ex($this->temp_dir);
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if ($val == $keyname) {
                // 一時ファイルの場合削除する。
                if ($this->temp_file[$cnt] != '') {
                    $objImage->deleteImage($this->temp_file[$cnt], $this->temp_dir);
                }
                $this->temp_file[$cnt] = '';
                $this->save_file[$cnt] = '';
            }
            $cnt++;
        }
    }

    // 画像を削除する。
    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function deleteKikakuFile($keyname)
    {
        $objImage = new SC_Image_Ex($this->temp_dir);
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if ($val == $keyname) {
                // 一時ファイルの場合削除する。
                if ($this->temp_file[$cnt] != '') {
                    $objImage->deleteImage($this->temp_file[$cnt], $this->temp_dir);
                }
                $this->temp_file[$cnt] = '';
                // $this->save_file[$cnt] = '';
            }
            $cnt++;
        }
    }

    // 一時ファイルパスを取得する。

    /**
     * @param string $keyname
     */
    public function getTempFilePath($keyname)
    {
        $cnt = 0;
        $filepath = '';
        foreach ($this->keyname as $val) {
            if ($val == $keyname) {
                if ($this->temp_file[$cnt] != '') {
                    $filepath = $this->temp_dir.$this->temp_file[$cnt];
                }
            }
            $cnt++;
        }

        return $filepath;
    }

    // 一時ファイルを保存ディレクトリに移す
    public function moveTempFile()
    {
        $objImage = new SC_Image_Ex($this->temp_dir);

        for ($cnt = 0; $cnt < count($this->keyname); $cnt++) {
            if (isset($this->temp_file[$cnt]) && $this->temp_file[$cnt] != '') {
                $objImage->moveTempImage($this->temp_file[$cnt], $this->save_dir);

                // すでに保存ファイルがあった場合は削除する。
                if (isset($this->save_file[$cnt])
                    && $this->save_file[$cnt] != ''
                    && !preg_match('|^sub/|', $this->save_file[$cnt])
                ) {
                    $objImage->deleteImage($this->save_file[$cnt], $this->save_dir);
                }
            }
        }
    }

    // ダウンロード一時ファイルを保存ディレクトリに移す
    public function moveTempDownFile()
    {
        $objImage = new SC_Image_Ex($this->temp_dir);
        for ($cnt = 0; $cnt < count($this->keyname); $cnt++) {
            if (isset($this->temp_file[$cnt]) && $this->temp_file[$cnt] != '') {
                $objImage->moveTempImage($this->temp_file[$cnt], $this->save_dir);
                // すでに保存ファイルがあった場合は削除する。
                if (isset($this->save_file[$cnt])
                    && $this->save_file[$cnt] != ''
                    && !preg_match('|^sub/|', $this->save_file[$cnt])
                ) {
                    $objImage->deleteImage($this->save_file[$cnt], $this->save_dir);
                }
            }
        }
    }

    // HIDDEN用のファイル名配列を返す
    public function getHiddenFileList()
    {
        $cnt = 0;
        $arrRet = [];
        foreach ($this->keyname as $val) {
            if (isset($this->temp_file[$cnt])) {
                $arrRet['temp_'.$val] = $this->temp_file[$cnt];
            }
            if (isset($this->save_file[$cnt]) && $this->save_file[$cnt] != '') {
                $arrRet['save_'.$val] = $this->save_file[$cnt];
            }
            $cnt++;
        }

        return $arrRet;
    }

    // HIDDENで送られてきたファイル名を取得する
    public function setHiddenFileList($arrPOST)
    {
        $cnt = 0;
        foreach ($this->keyname as $val) {
            $key = 'temp_'.$val;
            if (isset($arrPOST[$key]) && !empty($arrPOST[$key])) {
                $this->temp_file[$cnt] = $arrPOST[$key];
            }
            $key = 'save_'.$val;
            if (isset($arrPOST[$key]) && !empty($arrPOST[$key])) {
                $this->save_file[$cnt] = $arrPOST[$key];
            }
            $cnt++;
        }
    }

    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function setHiddenKikakuFileList($arrPOST)
    {
        $cnt = 0;
        foreach ($this->keyname as $val) {
            $key = 'temp_'.$val;
            if (isset($arrPOST[$key])) {
                $this->temp_file[$cnt] = $arrPOST[$key];
            }
            $key = 'save_'.$val;
            if (isset($arrPOST[$key]) && !empty($arrPOST[$key])) {
                $this->save_file[$cnt] = $arrPOST[$key];
            }
            $cnt++;
        }
    }

    // フォームに渡す用のファイル情報配列を返す
    public function getFormFileList($temp_url = null, $save_url = null, $real_size = false)
    {
        $arrRet = [];
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if (isset($this->temp_file[$cnt]) && $this->temp_file[$cnt] != '') {
                $real_filepath =
                $arrRet[$val]['real_filepath'] = $this->temp_dir.$this->temp_file[$cnt];
                if (is_null($temp_url)) {
                    $arrRet[$val]['filepath'] = ROOT_URLPATH.substr($real_filepath, strlen(HTML_REALDIR));
                } else {
                    // パスのスラッシュ/が連続しないようにする。
                    $arrRet[$val]['filepath'] = rtrim($temp_url, '/').'/'.$this->temp_file[$cnt];
                }
            } elseif (isset($this->save_file[$cnt]) && $this->save_file[$cnt] != '') {
                $real_filepath =
                $arrRet[$val]['real_filepath'] = $this->save_dir.$this->save_file[$cnt];
                if (is_null($save_url)) {
                    $arrRet[$val]['filepath'] = ROOT_URLPATH.substr($real_filepath, strlen(HTML_REALDIR));
                } else {
                    // パスのスラッシュ/が連続しないようにする。
                    $arrRet[$val]['filepath'] = rtrim($save_url, '/').'/'.$this->save_file[$cnt];
                }
            }
            if (isset($arrRet[$val]['filepath']) && !empty($arrRet[$val]['filepath'])) {
                if ($real_size) {
                    $width = 0;
                    $height = 0;
                    if (is_file($arrRet[$val]['real_filepath'])) {
                        [$width, $height] = getimagesize($arrRet[$val]['real_filepath']);
                    }
                    // ファイル横幅
                    $arrRet[$val]['width'] = $width;
                    // ファイル縦幅
                    $arrRet[$val]['height'] = $height;
                } else {
                    // ファイル横幅
                    $arrRet[$val]['width'] = $this->width[$cnt];
                    // ファイル縦幅
                    $arrRet[$val]['height'] = $this->height[$cnt];
                }
                // 表示名
                $arrRet[$val]['disp_name'] = $this->disp_name[$cnt];
            }
            $cnt++;
        }

        return $arrRet;
    }

    // フォームに渡す用のダウンロードファイル情報を返す
    public function getFormDownFile()
    {
        $arrRet = '';
        for ($cnt = 0; $cnt < count($this->keyname); $cnt++) {
            if (isset($this->temp_file[$cnt]) && $this->temp_file[$cnt] != '') {
                $arrRet = $this->temp_file[$cnt];
            } elseif (isset($this->save_file[$cnt]) && $this->save_file[$cnt] != '') {
                $arrRet = $this->save_file[$cnt];
            }
        }

        return $arrRet;
    }

    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function getFormKikakuDownFile()
    {
        $arrRet = [];
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if (isset($this->temp_file[$cnt])) {
                $arrRet[$val] = $this->temp_file[$cnt];
            } elseif (isset($this->save_file[$cnt]) && $this->save_file[$cnt] != '') {
                $arrRet[$val] = $this->save_file[$cnt];
            }
            $cnt++;
        }

        return $arrRet;
    }

    // DB保存用のファイル名配列を返す
    public function getDBFileList()
    {
        $cnt = 0;
        $dbFileList = [];
        foreach ($this->keyname as $val) {
            if (isset($this->temp_file[$cnt]) && $this->temp_file[$cnt] != '') {
                $dbFileList[$val] = $this->temp_file[$cnt];
            } else {
                $dbFileList[$val] = $this->save_file[$cnt] ?? '';
            }
            $cnt++;
        }

        return $dbFileList;
    }

    // DBで保存されたファイル名配列をセットする
    public function setDBFileList($arrVal)
    {
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if (isset($arrVal[$val]) && $arrVal[$val] != '') {
                $this->save_file[$cnt] = $arrVal[$val];
            }
            $cnt++;
        }
    }

    // DBで保存されたダウンロードファイル名をセットする
    public function setDBDownFile($arrVal)
    {
        if (isset($arrVal['down_realfilename']) && $arrVal['down_realfilename'] != '') {
            $this->save_file[0] = $arrVal['down_realfilename'];
        }
    }

    // DBで保存されたダウンロードファイル名をセットする(setDBDownFileと統合予定)
    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function setPostFileList($arrPost)
    {
        for ($cnt = 0; $cnt < count($this->keyname); $cnt++) {
            if (isset($arrPost['temp_down_realfilename:'.($cnt + 1)])) {
                $this->temp_file[$cnt] = $arrPost['temp_down_realfilename:'.($cnt + 1)];
            }
        }
    }

    // 画像をセットする
    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function setDBImageList($arrVal)
    {
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if ($arrVal[$val] != '' && $val == 'tv_products_image') {
                $this->save_file[$cnt] = $arrVal[$val];
            }
            $cnt++;
        }
    }

    // DB上のファイルの内削除要求があったファイルを削除する。
    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function deleteDBFile($arrVal)
    {
        $objImage = new SC_Image_Ex($this->temp_dir);
        $cnt = 0;
        foreach ($this->keyname as $val) {
            if ($arrVal[$val] != '') {
                if ($this->save_file[$cnt] == '' && !preg_match('|^sub/|', $arrVal[$val])) {
                    $objImage->deleteImage($arrVal[$val], $this->save_dir);
                }
            }
            $cnt++;
        }
    }

    // DB上のダウンロードファイルの内削除要求があったファイルを削除する。
    public function deleteDBDownFile($arrVal)
    {
        $objImage = new SC_Image_Ex($this->temp_dir);
        $cnt = 0;
        if ($arrVal['down_realfilename'] != '') {
            if (!isset($this->save_file[$cnt]) && !preg_match('|^sub/|', $arrVal['down_realfilename'])) {
                $objImage->deleteImage($arrVal['down_realfilename'], $this->save_dir);
            }
        }
    }

    // 必須判定
    public function checkExists($keyname = '')
    {
        $cnt = 0;
        $arrRet = [];
        foreach ($this->keyname as $val) {
            if ($val == $keyname || $keyname == '') {
                // 必須であればエラーチェック
                if ($this->necessary[$cnt] == true) {
                    if (!isset($this->save_file[$cnt])) {
                        $this->save_file[$cnt] = '';
                    }
                    if (!isset($this->temp_file[$cnt])) {
                        $this->temp_file[$cnt] = '';
                    }
                    if ($this->save_file[$cnt] == ''
                        && $this->temp_file[$cnt] == ''
                    ) {
                        $arrRet[$val] = '※ '.$this->disp_name[$cnt].'がアップロードされていません。<br>';
                    }
                }
            }
            $cnt++;
        }

        return $arrRet;
    }

    // 拡大率を指定して画像保存
    /**
     * @deprecated 本体で使用されていないため非推奨
     */
    public function saveResizeImage($keyname, $to_w, $to_h)
    {
        $path = '';

        // keynameの添付ファイルを取得
        $arrImageKey = array_flip($this->keyname);
        $file = $this->temp_file[$arrImageKey[$keyname]];
        $filepath = $this->temp_dir.$file;

        $path = $this->makeThumb($filepath, $to_w, $to_h, '');

        // ファイル名だけ返す
        return basename($path);
    }

    /**
     * 一時保存用のファイル名を生成する
     *
     * @param  string $rename
     * @param  int    $keyname
     *
     * @return string
     */
    public function lfGetTmpImageName($rename, $keyname = '', $uploadfile = '')
    {
        if ($rename === true) {
            // 一意なIDを取得し、画像名をリネームし保存
            $uniqname = date('mdHi').'_'.uniqid('');
        } else {
            // アップロードした画像名で保存
            $uploadfile = strlen($uploadfile) > 0 ? $uploadfile : $_FILES[$keyname]['name'];
            $uniqname = preg_replace('/(.+)\.(.+?)$/', '$1', $uploadfile);
        }
        $dst_file = $this->temp_dir.$uniqname;

        return $dst_file;
    }

    /**
     * ファイルのアップロードのエラーを確認
     *
     * @param string $keyname ファイルinputタグのname
     * @param SC_CheckError $objErr SC_CheckErrorインスタンス
     *
     * @return bool
     */
    public function checkUploadError($keyname, SC_CheckError &$objErr)
    {
        $index = array_search($keyname, $this->keyname);

        switch ($_FILES[$keyname]['error']) {
            case UPLOAD_ERR_OK:
                return true;
                break;
            case UPLOAD_ERR_NO_FILE:
                $objErr->arrErr[$keyname] = '※ '
                    .$this->disp_name[$index]
                    .'が選択されていません。'
                    .'<br />';
                break;
            case UPLOAD_ERR_INI_SIZE:
                $objErr->arrErr[$keyname] = '※ '
                    .$this->disp_name[$index]
                    .'のアップロードに失敗しました。'
                    .'(.htaccessファイルのphp_value upload_max_filesizeを調整してください)'
                    .'<br />';
                break;
            default:
                $objErr->arrErr[$keyname] = '※ '
                    .$this->disp_name[$index]
                    .'のアップロードに失敗しました。'
                    .'エラーコードは['.$_FILES[$keyname]['error'].']です。'
                    .'<br />';
                break;
        }

        return false;
    }
}
