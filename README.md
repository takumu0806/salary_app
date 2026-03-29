給与管理・計算アプリ (Salary App)
個人の給与、特に時給制のアルバイトや手当、控除（所得税等）を詳細に管理・計算するためのWebアプリケーションです。
スマホからの入力を意識したレスポンシブデザインと、プライバシーを守りつつ推移を確認できる動的なグラフ機能を備えています。

🚀 主な機能
ダッシュボード:

直近6ヶ月の給与推移グラフ（Chart.jsによるアニメーションスライド機能付き）。

今年の累計支給額・平均月給の自動算出。

プライバシーモード: 金額やグラフをぼかし・伏字にする表示切り替え機能。

高度な給与計算:

項目別切り上げルール: 項目ごとに計算（単価×時間）し、小数点が出た場合は即座に切り上げ。

源泉徴収自動計算: 支給額から10.21%をワンタップで算出。

フローティング操作: スマホ入力時、画面下部に常に合計金額と登録ボタンを表示。

項目管理:

「時給」「固定手当」「控除（マイナス）」の3種類を自由に追加・編集。

論理削除対応により、過去の給与データの整合性を維持。

履歴・詳細: 過去の給与データの閲覧、再編集、削除が可能。

認証機能: ユーザー登録およびパスワードハッシュ化によるログイン管理。

🛠 技術スタック
Language: PHP 8.x

Database: PostgreSQL / MySQL (PDO接続)

Frontend: Bootstrap 5, Bootstrap Icons

Charts: Chart.js v4.x

📦 セットアップ
1. リポジトリをクローン
Bash

git clone https://github.com/あなたのユーザー名/salary_app.git
cd salary_app
2. 環境設定ファイルの作成
env.example.php をコピーして env.php を作成し、ご自身のデータベース情報を入力してください。
※ env.php は .gitignore によりGit管理から除外されます。

Bash

cp env.example.php env.php
3. データベースの構築
以下のSQLを実行して、必要なテーブルを作成してください。

SQL

-- ユーザーテーブル
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL
);

-- 給与項目テーブル
CREATE TABLE salary_items (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    item_name VARCHAR(100) NOT NULL,
    default_hourly_rate INTEGER DEFAULT 0,
    item_type VARCHAR(20) DEFAULT 'hourly', -- 'hourly', 'fixed', 'deduction'
    is_deleted BOOLEAN DEFAULT FALSE
);

-- 月次給与親テーブル
CREATE TABLE monthly_salaries (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    target_year INTEGER NOT NULL,
    target_month INTEGER NOT NULL,
    total_amount INTEGER NOT NULL,
    UNIQUE(user_id, target_year, target_month)
);

-- 給与明細詳細テーブル
CREATE TABLE salary_details (
    id SERIAL PRIMARY KEY,
    monthly_salary_id INTEGER REFERENCES monthly_salaries(id),
    salary_item_id INTEGER REFERENCES salary_items(id),
    hours_worked NUMERIC(10, 2),
    calculated_amount INTEGER NOT NULL
);
🔒 セキュリティについて
データベース接続情報（パスワード等）は env.php に隔離し、公開されないように設定しています。

パスワードは password_hash 関数を用いて安全に暗号化されて保存されます。

ブラウザからの env.php への直接アクセスは .htaccess によって制限することを推奨します。