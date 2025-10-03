<?php
session_start();

// セッションの全データを削除
$_SESSION = array();

// セッションクッキーも削除
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// セッション破棄
session_destroy();

// ログインページへリダイレクト
header('Location: index.php');
exit;
?>