<?php
// NHKニュースデジタルサイネージアプリケーション
// PostgreSQLに保存されたニュースを取得して表示します

// データベース接続設定
$db_config = [
    'host'     => 'localhost',
    'port'     => '5432',
    'dbname'   => 'news_db',
    'user'     => 'postgres',
    'password' => 'password'
];

// アプリケーション設定
$config = [
    // 画面の自動更新間隔（秒）
    'refresh_interval' => 300,
    
    // 一度に表示するニュース記事数
    'news_count' => 5,
    
    // ニュース切り替え間隔（ミリ秒）
    'slide_interval' => 10000,
    
    // 表示モード: 'carousel'（カルーセル表示） または 'list'（リスト表示）
    'display_mode' => 'carousel',
    
    // 日付フォーマット
    'date_format' => 'Y年m月d日 H:i'
];

// データベースからニュースを取得
function fetchNews($db_config, $limit = 5) {
    try {
        // PostgreSQL接続文字列の作成
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
            $db_config['host'],
            $db_config['port'],
            $db_config['dbname'],
            $db_config['user'],
            $db_config['password']
        );
        
        // PDO接続の確立
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//        $pdo->exec("SET NAMES UTF8");
        
        // 最新のニュース記事を取得
        $stmt = $pdo->prepare("
            SELECT id, title, description, link, published_date
            FROM news_items
            ORDER BY published_date DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // エラーログを記録（実運用では適切なロギングに変更）
        error_log('データベース接続エラー: ' . $e->getMessage());
        return [];
    }
}

// ニュースデータの取得
$news_items = fetchNews($db_config, $config['news_count']);

// HTMLエスケープ関数
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 日付をフォーマット
function formatDate($date_str, $format) {
    $date = new DateTime($date_str);
    return $date->format($format);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHKニュースデジタルサイネージ</title>
    <meta http-equiv="refresh" content="<?php echo $config['refresh_interval']; ?>">
    <style>
        body {
            font-family: 'Meiryo', 'Hiragino Kaku Gothic Pro', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            color: #333;
            overflow: hidden;
        }
        .container {
            max-width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        header {
            background-color: #c00;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 2rem;
            font-weight: bold;
        }
        .date-time {
            font-size: 1.5rem;
        }
        .news-container {
            height: calc(100vh - 5rem);
            position: relative;
        }
        
        /* カルーセル表示モード */
        .carousel-container {
            height: 100%;
            position: relative;
        }
        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            padding: 2rem;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        .carousel-slide.active {
            opacity: 1;
            z-index: 1;
        }
        .carousel-title {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            font-weight: bold;
        }
        .carousel-description {
            font-size: 1.8rem;
            line-height: 1.5;
            flex-grow: 1;
            overflow: hidden;
        }
        .carousel-date {
            font-size: 1.5rem;
            color: #666;
            text-align: right;
            margin-top: 1rem;
        }
        
        /* リスト表示モード */
        .news-list {
            height: 100%;
            overflow: auto;
            padding: 1rem;
        }
        .news-item {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: white;
            border-left: 5px solid #c00;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .news-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .news-description {
            font-size: 1.2rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        .news-date {
            font-size: 1rem;
            color: #666;
            text-align: right;
        }
        
        /* フッター */
        footer {
            background-color: #c00;
            color: white;
            text-align: center;
            padding: 0.5rem;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        
        /* 表示がない場合のスタイル */
        .no-news {
            font-size: 2rem;
            text-align: center;
            margin-top: 5rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">NHKニュース</div>
            <div class="date-time" id="current-time">読み込み中...</div>
        </header>
        
        <div class="news-container">
            <?php if (empty($news_items)): ?>
                <div class="no-news">現在、表示できるニュースがありません</div>
            <?php elseif ($config['display_mode'] === 'carousel'): ?>
                <!-- カルーセル表示モード -->
                <div class="carousel-container">
                    <?php foreach ($news_items as $index => $item): ?>
                        <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>" id="slide-<?php echo $index; ?>">
                            <div class="carousel-title"><?php echo h($item['title']); ?></div>
                            <div class="carousel-description"><?php echo h($item['description']); ?></div>
                            <div class="carousel-date">
                                <?php echo formatDate($item['published_date'], $config['date_format']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- リスト表示モード -->
                <div class="news-list">
                    <?php foreach ($news_items as $item): ?>
                        <div class="news-item">
                            <div class="news-title"><?php echo h($item['title']); ?></div>
                            <div class="news-description"><?php echo h($item['description']); ?></div>
                            <div class="news-date">
                                <?php echo formatDate($item['published_date'], $config['date_format']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <footer>
            NHKのRSSフィードを元に表示しています
        </footer>
    </div>

    <script>
        // 現在時刻を表示する関数
        function updateClock() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                weekday: 'long',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('current-time').textContent = now.toLocaleDateString('ja-JP', options);
        }
        
        // カルーセルのスライド切り替え関数
        function startCarousel() {
            const slides = document.querySelectorAll('.carousel-slide');
            if (slides.length <= 1) return;
            
            let currentSlide = 0;
            
            setInterval(() => {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }, <?php echo $config['slide_interval']; ?>);
        }
        
        // 初期化
        window.onload = function() {
            updateClock();
            setInterval(updateClock, 1000);
            
            <?php if ($config['display_mode'] === 'carousel' && !empty($news_items)): ?>
                startCarousel();
            <?php endif; ?>
        };
    </script>
</body>
</html>
