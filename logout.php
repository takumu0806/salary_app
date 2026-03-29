<?php
// logout.php
session_start();

// セッション変数をすべて解除する（空にする）
$_SESSION = [];

// セッションを切断するにはセッションクッキーも削除する
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 最終的に、セッションを完全に破壊する
session_destroy();

// ログイン画面（index.php）へリダイレクト（移動）する
header("Location: index.php");
exit;
?>