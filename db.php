<?php
// db.php

// 1. パスワードなどが書かれたファイルを読み込む
require_once __DIR__ . '/env.php';

try {
    // 2. env.php で定義した定数を使って接続文字列を作る
    // （※下記はPostgreSQLの場合。MySQLの場合は 'mysql:host=' に変更してください）
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    
    // 3. PDOで接続
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    // エラーが起きたら例外（Exception）を投げる設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 取得するデータを常に「連想配列（キー名付きの配列）」にする設定
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 接続に失敗した場合はエラーメッセージを出して処理を止める
    echo "データベース接続エラー: " . $e->getMessage();
    exit;
}