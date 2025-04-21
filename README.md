# NHKニュースデジタルサイネージシステム(Digital Signage System for NHK News)

このリポジトリには、NHKのRSSフィードからニュースを取得し、デジタルサイネージとして表示するためのアプリケーションが含まれています。システムは2つの主要コンポーネントで構成されています：

This repository contains an application for fetching news from NHK's RSS feeds and displaying it as digital signage. The system consists of two main components:

1. **データ収集アプリケーション**: PythonスクリプトがNHKのRSSフィードからニュースを取得し、PostgreSQLデータベースに保存します
2. **表示アプリケーション**: PHPスクリプトがデータベースからニュースを読み取り、ウェブブラウザベースのデジタルサイネージとして表示します

-
1. **Data Collection Application**: A Python script that fetches news from NHK's RSS feeds and stores it in a PostgreSQL database.
2. **Display Application**: A PHP script that reads news from the database and displays it as web browser-based digital signage.

## 機能

- NHKの最新ヘッドラインニュースをRSSから自動取得
- データベースへのニュース記事の効率的な保存と更新
- カルーセル表示モードとリスト表示モードの両方をサポート
- カスタマイズ可能な更新頻度とスライド間隔
- レスポンシブデザインによる様々な画面サイズへの対応
- リアルタイム時計表示

## システム要件

- Python 3.6以上
- PHP 7.2以上（PDO拡張機能有効）
- PostgreSQL 10以上
- Webサーバー（Apache、Nginx等）

## 必要なPythonパッケージ

- feedparser
- psycopg2-binary
- python-dotenv

## インストール方法

### 1. リポジトリのクローン

```bash
git clone https://github.com/yourusername/nhk-news-signage.git
cd nhk-news-signage
```

### 2. Pythonの依存パッケージをインストール
debian系の場合は venv 環境でないとインストールできないかもしれません

### 3. データベースを設定

PostgreSQLで新しいデータベースを作成します:

```sql
CREATE DATABASE news_db;
```

### 4. 環境変数の設定

`.env`ファイルをプロジェクトルートに作成し、データベース接続情報を設定します:

```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=news_db
DB_USER=postgres
DB_PASSWORD=your_password
```

### 5. Webサーバーの設定

PHPファイルをWebサーバーのドキュメントルートに配置します（またはシンボリックリンクを作成）。

## 使用方法

### データ収集アプリケーション（Python）

#### 一回実行モード（Cron用）

このモードは一度だけ実行してニュースを取得し、終了します。Cronジョブで定期的に実行するのに適しています。

```bash
python nhk_rss_fetcher.py
```

#### Cronジョブの設定例

10分ごとに実行する場合:

```
*/10 * * * * /usr/bin/python3 /path/to/nhk_rss_fetcher.py >> /path/to/logfile.log 2>&1
```

### デジタルサイネージ表示（PHP）

Webブラウザで表示アプリケーションにアクセスします:

```
http://your-server/news_display.php
```

フルスクリーンモード（F11キー）で表示すると、デジタルサイネージとして最適です。

## カスタマイズ

### Pythonアプリケーション

`nhk_rss_fetcher.py`の上部にある設定を変更して、異なるRSSフィードを取得することができます。

### PHP表示アプリケーション

`news_display.php`の上部にある`$config`配列を編集して、以下の設定を変更できます:

- 画面の自動更新間隔（秒）
- 表示するニュース記事数
- スライド切替間隔（ミリ秒）
- 表示モード（カルーセル/リスト）
- 日付フォーマット

```php
$config = [
    'refresh_interval' => 300,  // 5分ごとに更新
    'news_count' => 5,
    'slide_interval' => 10000,  // 10秒ごとに切り替え
    'display_mode' => 'carousel',
    'date_format' => 'Y年m月d日 H:i'
];
```

## デプロイメント例

### Raspberry Piでのデジタルサイネージ

1. Raspberry Pi OSをインストール
2. 必要なソフトウェアをインストール:
   ```bash 
   sudo apt-get update
   sudo apt-get install apache2 php php-pgsql postgresql python3 python3-pip
   ```
3. このリポジトリをクローン
4. 依存パッケージをインストール
5. CronジョブとWebサーバーを設定
6. Raspberry Piを起動時に自動的にブラウザを開くように設定

## トラブルシューティング

### 文字化けが発生する場合

- PostgreSQLデータベースのエンコーディングがUTF-8に設定されていることを確認
- PHPスクリプトの `SET NAMES UTF8` が正しく実行されていることを確認
- Pythonスクリプトで `client_encoding='UTF8'` が設定されていることを確認

### データが取得できない場合

- `.env` ファイルの設定が正しいか確認
- PostgreSQLサーバーが稼働していることを確認
- ログファイルでエラーメッセージを確認

## ライセンス

MIT

## 謝辞

このアプリケーションはNHKが提供するRSSフィードを利用しています。コンテンツの著作権はNHKに帰属します。
