import feedparser
import psycopg2
import datetime
import logging
from time import mktime
from typing import Dict, Any, List
import os
from dotenv import load_dotenv

# ロギング設定
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler()]
)
logger = logging.getLogger('nhk_rss_fetcher')

# 環境変数を.envファイルから読み込む
load_dotenv()

# NHK RSSのURL
NHK_RSS_URL = "https://www.nhk.or.jp/rss/news/cat0.xml"

# PostgreSQL接続情報（環境変数から取得）
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = os.getenv('DB_PORT', '5432')
DB_NAME = os.getenv('DB_NAME', 'news_db')
DB_USER = os.getenv('DB_USER', 'postgres')
DB_PASSWORD = os.getenv('DB_PASSWORD', 'password')

def get_db_connection():
    """PostgreSQLへの接続を確立する"""
    try:
        # client_encodingを'UTF8'に設定
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD,
            client_encoding='UTF8'  # 明示的にUTF-8を指定
        )
        return conn
    except psycopg2.Error as e:
        logger.error(f"データベース接続エラー: {e}")
        raise

def create_tables(conn):
    """必要なテーブルを作成する"""
    try:
        with conn.cursor() as cur:
            cur.execute('''
            CREATE TABLE IF NOT EXISTS news_items (
                id SERIAL PRIMARY KEY,
                guid TEXT UNIQUE,
                title TEXT NOT NULL,
                link TEXT NOT NULL,
                description TEXT,
                published_date TIMESTAMP,
                fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            ''')
            conn.commit()
            logger.info("テーブルの確認/作成が完了しました")
    except psycopg2.Error as e:
        logger.error(f"テーブル作成エラー: {e}")
        conn.rollback()
        raise

def fetch_rss_feed(url: str) -> Dict[str, Any]:
    """指定されたURLからRSSフィードを取得する"""
    try:
        logger.info(f"RSSフィードを取得中: {url}")
        feed = feedparser.parse(url)
        # ログにエントリー数を出力
        logger.info(f"取得したエントリー数: {len(feed.entries)}")
        return feed
    except Exception as e:
        logger.error(f"RSSフィード取得エラー: {e}")
        raise

def truncate_news_table(conn):
    """news_itemsテーブルの全データを削除する"""
    try:
        with conn.cursor() as cur:
            cur.execute("TRUNCATE TABLE news_items RESTART IDENTITY")
            conn.commit()
            logger.info("既存のニュースデータを削除しました")
    except psycopg2.Error as e:
        logger.error(f"テーブル削除エラー: {e}")
        conn.rollback()
        raise

def save_entries_to_db(conn, entries: List[Dict[str, Any]]):
    """RSSエントリーをデータベースに保存する"""
    inserted_count = 0
    
    try:
        with conn.cursor() as cur:
            for entry in entries:
                # 日付を変換
                if 'published_parsed' in entry and entry.published_parsed:
                    published_date = datetime.datetime.fromtimestamp(mktime(entry.published_parsed))
                else:
                    published_date = datetime.datetime.now()
                
                # ニュース記事をデータベースに挿入
                try:
                    # すべてのテキストフィールドがUnicode文字列であることを確認
                    guid = str(entry.get('id', entry.get('link', '')))
                    title = str(entry.get('title', ''))
                    link = str(entry.get('link', ''))
                    description = str(entry.get('description', ''))
                    
                    cur.execute('''
                    INSERT INTO news_items (guid, title, link, description, published_date)
                    VALUES (%s, %s, %s, %s, %s)
                    ''', (
                        guid,
                        title,
                        link,
                        description,
                        published_date
                    ))
                    
                    inserted_count += 1
                        
                except psycopg2.Error as e:
                    logger.error(f"エントリー保存エラー: {e}, エントリー: {entry.get('title', '')}")
                    conn.rollback()
                    continue
                except Exception as e:
                    logger.error(f"予期せぬエラー: {e}, エントリー: {entry.get('title', '')}")
                    continue
            
            conn.commit()
            logger.info(f"保存完了: {inserted_count}件のエントリーを保存しました")
    except psycopg2.Error as e:
        logger.error(f"データベース操作エラー: {e}")
        conn.rollback()
        raise

def main():
    """
    メイン実行関数 - 1回だけ実行してプログラム終了
    1. テーブルが存在しなければ作成
    2. 既存のデータをすべて削除
    3. 新しいデータを挿入
    """
    logger.info("NHKニュースRSS取得・保存スクリプトを開始します（データ入れ替えモード）")
    
    try:
        conn = get_db_connection()
        
        # テーブルが存在することを確認（なければ作成）
        create_tables(conn)
        
        # 既存データをすべて削除
        truncate_news_table(conn)
        
        # 新しいデータを取得して保存
        feed = fetch_rss_feed(NHK_RSS_URL)
        save_entries_to_db(conn, feed.entries)
        
        logger.info("データの入れ替えが完了しました")
    except Exception as e:
        logger.error(f"処理中にエラーが発生しました: {e}")
        return 1  # エラー終了
    finally:
        if 'conn' in locals() and conn:
            conn.close()
            logger.info("データベース接続を閉じました")
    
    return 0  # 正常終了

if __name__ == "__main__":
    exit(main())
