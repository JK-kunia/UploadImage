<?php

namespace MyApp;

class ImageUploader {

  private $_ImageFileName;
  private $_imageType;

  public function upload() {
    try {
      //アップロードしたときのエラーチェック
      $this->_validateUpload();

      //画像タイプのチェック
      $ext = $this->_validateImageType();

      //保存
      $savePath = $this->_save($ext);

      //必要ならサムネイルの作る
      $this->_createThumbnail($savePath);

      $_SESSION['success'] = 'アップロード成功！';//セッションにメッセージを入れてる
    } catch (\Exception $e) {
      $_SESSION['error'] = $e->getMessage(); //セッションにエラーメッセージ
      // exit;
    }
    //投稿が終わったあとにindex.phpを再読込してしまうと二重投稿になってしまうんでそれを防ぐためのリダイレクトをする
    header('Location: http://' . $_SERVER['HTTP_HOST']);
    exit;
  }

  public function getResults() {
    $success = null; //nullで初期化
    $error = null; //nullで初期化
    if (isset($_SESSION['success'])) { //セッションの中身があるなら
      $success = $_SESSION['success'];//successの中身はSESSIONの中身だよ
      unset($_SESSION['success']);//メッセージの存在意義がなくなるので消してあげる
    }
    if (isset($_SESSION['error'])) { //セッションの中身があるなら
      $error = $_SESSION['error'];//errorの中身はSESSIONの中身だよ
      unset($_SESSION['error']);//メッセージの存在意義がなくなるので消してあげる
    }
    return[$success, $error]; //配列で渡してあげればindex.phpの方でもlistを受け取ってくれる
  }

  public function getImages() {
    $images = [];
    $files = [];
    $imageDir = opendir(IMAGES_DIR);
    while (false !== ($file = readdir($imageDir))) {
      if ($file === '.' || $file === '..') {
        continue;
      }
      $files[] = $file;
      if (file_exists(THUMBNAIL_DIR . '/' . $file)) {
        $images[] = basename(THUMBNAIL_DIR) . '/' . $file;
      } else {
        $images[] = basename(IMAGES_DIR) . '/' . $file;
      }
    }
    array_multisort($files, SORT_DESC, $images);
    return $images;
  }


  private function _createThumbnail($savePath) {
    $imageSize = getimagesize($savePath);
    $width = $imageSize[0]; //getimagesizeの0は幅
    $height = $imageSize[1];//1は高さを表す
    if ($width > THUMBNAIL_WIDTH) {
      $this->_createThumbnailMain($savePath, $width, $height);
    }
  }

  private function _createThumbnailMain($savePath , $width , $height) {
    switch($this->_imageType) {
      case IMAGETYPE_GIF:
        $srcImage = imagecreatefromgif($savePath);
      break;
      case IMAGETYPE_JPEG:
        $srcImage = imagecreatefromjpeg($savePath);
      break;
      case IMAGETYPE_PNG:
        $srcImage = imagecreatefrompng($savePath);
      break;
    }
    $thumbHeight = round($height * THUMBNAIL_WIDTH / $width);
    $thumbImage = imagecreatetruecolor(THUMBNAIL_WIDTH, $thumbHeight);
    imagecopyresampled($thumbImage, $srcImage, 0,0,0,0,THUMBNAIL_WIDTH, $thumbHeight , $width, $height);

    switch($this->_imageType) {
      case IMAGETYPE_GIF:
        imagegif($thumbImage, THUMBNAIL_DIR . '/' . $this->_imageFileName);
      break;
      case IMAGETYPE_JPEG:
        imagejpeg($thumbImage, THUMBNAIL_DIR . '/' . $this->_imageFileName);
      break;
      case IMAGETYPE_PNG:
        imagepng($thumbImage, THUMBNAIL_DIR . '/' . $this->_imageFileName);
      break;
    }
  }

  private function _save($ext) {
    $this->_imageFileName = sprintf(
      '%s_%s.%s',
      time(),
      sha1(uniqid(mt_rand(), true)), //経過ミリ秒,ランダムな文字列の名前
      $ext
    );
    $savePath = IMAGES_DIR . '/' . $this->_imageFileName;
    $res = move_uploaded_file($_FILES['image']['tmp_name'], $savePath);
    if ($res === false) {
      throw new \Exception('画像をアップできませんー');
    }
    return $savePath;
  }

  private function _validateImageType() {
    $this->_imageType = exif_imagetype($_FILES['image']['tmp_name']);
    switch($this->_imageType) {
      case IMAGETYPE_GIF:
        return 'gif';
      case IMAGETYPE_JPEG:
        return 'jpg';
      case IMAGETYPE_PNG:
        return 'png';
      default:
        throw new \Exception('PNG/JPEG/GIF だけだよ!');
    }
  }

  private function _validateUpload() {

    if (!isset($_FILES['image']) || !isset($_FILES['image']['error'])) {
      throw new \Exception('Upload Error!');
    }

    switch($_FILES['image']['error']) {
      case UPLOAD_ERR_OK:
        return true;
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        throw new \Exception('ファイルがでかすぎる！');
      default:
        throw new \Exception('エラー…: ' . $_FILES['image']['error']);
    }
  }
}
