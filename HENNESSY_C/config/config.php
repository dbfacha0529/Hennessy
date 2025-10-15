<?php
error_reporting(E_ALL & ~E_WARNING);
ini_set('date.timezone','Asia/Tokyo');

// データベース設定
define('DB_HOST','localhost');
define('DB_NAME','hennessy');
define('DB_USER','root');
define('DB_PASSWORD','');

// パス設定
// ドキュメントルートからの絶対パス
define('BASE_URL', '/HENNESSY/HENNESSY_C/');

// よく使うパスを定義
define('WEB_URL', BASE_URL . 'web/');
define('CSS_PATH', WEB_URL . 'css/');
define('TIMELINE_URL', BASE_URL . 'timeline/');

// 画像パス（HENNESSY/img/）
define('IMG_PATH', '/HENNESSY/img/');
define('IMG_ICON_PATH', IMG_PATH . 'icon/');
define('IMG_PANELS_PATH', IMG_PATH . 'panels/');
define('IMG_LOGO_PATH', IMG_PATH . 'logo/');

// 物理パス（サーバー上のファイルパス）
define('IMG_DIR', $_SERVER['DOCUMENT_ROOT'] . IMG_PATH);
define('IMG_ICON_DIR', IMG_DIR . 'icon/');
define('IMG_PANELS_DIR', IMG_DIR . 'panels/');
define('IMG_LOGO_DIR', IMG_DIR . 'logo/');