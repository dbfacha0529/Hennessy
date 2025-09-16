<?php
require_once(dirname(__FILE__) . './config/config.php');

// DB接続用関数
function dbConnect() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // エラーを例外で投げる
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch() の結果を連想配列にする
                PDO::ATTR_EMULATE_PREPARES => false // SQLインジェクション対策のためにエミュレーションを無効化
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // エラー処理（本番環境では詳細を出さないようにする）
        exit('DB接続エラー: ' . $e->getMessage());
    }
}

//引数で与えられた配列を元にプルダウンリストを生成する
function arrayToSelect($name, $array, $selected=null, $class='form-select'){
    $html = "<select class='{$class}' name='{$name}'>";
    foreach($array as $key => $value){
        $isSelected = ($key == $selected) ? " selected" : "";
        $html .= "<option value='{$key}'{$isSelected}>{$value}</option>";
    }
    $html .= "</select>";
    return $html;
}
