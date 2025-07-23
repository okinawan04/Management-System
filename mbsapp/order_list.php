<?php

// db.php でデータベース接続
include 'db.php';

// $selectedStore を初期化
$selectedStore = null; 

if (isset($_POST['selected_store'])) {

    $selectedStore = $_POST['selected_store'];

}elseif (isset($_GET['selected_store'])) { // GETパラメータもチェック

    $selectedStore = $_GET['selected_store'];

}

// GETパラメータからソート順を取得（デフォルトはNo.昇順）
$sort = $_GET['sort'] ?? 'no_asc';

// GETパラメータからキーワードを取得（デフォルトは空文字）
$keyword = $_GET['keyword'] ?? '';

// ソート順のホワイトリストと対応するORDER BY句
$sort_options = [
    'no_asc' => 'o.orders_ID ASC',
    'no_desc' => 'o.orders_ID DESC',
    'customer' => 'customer_name ASC, o.orders_ID ASC',
    'amount_desc' => 'o.total DESC',
    'amount_asc' => 'o.total ASC',
    'date_desc' => 'o.orderday DESC, o.orders_ID DESC',
    'date_asc' => 'o.orderday ASC, o.orders_ID ASC',
];

// 検索条件作成
$where = '';
$params = [':selectedStore' => $selectedStore]; // パラメータ配列を初期化
if (!empty($keyword)) {
    // ANDを追加し、検索条件を構築
    // 品名(od.title)の検索は、メインクエリの集計に影響を与えないようにEXISTS句を使用する
    $where = " AND (
            c.name LIKE :keyword
            OR c.phoneNo LIKE :keyword
            OR c.address LIKE :keyword
            OR c.chargeName LIKE :keyword
            OR o.total LIKE :keyword
            OR EXISTS (
                SELECT 1
                FROM orderdetail od_sub
                WHERE od_sub.yk_ordersID = o.orders_ID AND od_sub.title LIKE :keyword
            )
        )";
    $params[':keyword'] = '%' . $keyword . '%';
}

// ホワイトリストに存在しない値が指定された場合はデフォルト値を使用
$orderByClause = $sort_options[$sort] ?? $sort_options['no_asc'];

$orders = []; // 取得した注文データを格納する配列
$message = ''; // メッセージ表示用（必要に応じて）

// $selectedStore が空でないことを確認してからクエリを実行
if (!empty($selectedStore)) {
    // 注文データ取得（ordersテーブルとcustomerテーブルを結合して顧客名も取得）
    // orderdetailテーブルも結合し、納品状態を集計して〇△×で表示
    $sql = "
        SELECT
            o.orders_ID,
            c.name AS customer_name,
            c.chargeName AS charge,
            o.total,
            o.orderday,
            -- 納品状態を判定
            CASE
                WHEN COUNT(od.orderdetail_ID) = 0 THEN '×' -- 明細がない場合は '×'
                WHEN COUNT(od.orderdetail_ID) = SUM(CASE WHEN od.state = 'YES' THEN 1 ELSE 0 END) THEN '〇' -- 全て納品済み
                WHEN SUM(CASE WHEN od.state = 'YES' THEN 1 ELSE 0 END) = 0 THEN '×' -- 全て未納品
                ELSE '△' -- 一部納品済み
            END AS order_status
        FROM orders o
        LEFT JOIN customer c ON o.yk_customerID = c.customer_ID
        LEFT JOIN orderdetail od ON o.orders_ID = od.yk_ordersID
        WHERE
            c.storeName = :selectedStore
            $where
        GROUP BY o.orders_ID, c.name, c.chargeName, o.total, o.orderday
        ORDER BY $orderByClause
    ";

    try {
        $stmt = $pdo->prepare($sql);
        // executeにパラメータ配列を渡す
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC); // 連想配列で取得
    } catch (PDOException $e) {
        // エラーハンドリング
        error_log("注文データ取得エラー: " . $e->getMessage());
        $message = "注文データの取得中にエラーが発生しました。管理者にお問い合わせください。";
    }
} else {
    //selectedStoreに値が代入されていない場合
    $message = "表示する店舗が選択されていません。";
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>注文書一覧</title>
    <link rel="stylesheet" href="仮画面/top/order/order_list.css">
</head>

<body>
    <header>
        <div class="logo-container">
            <div class="logo">
                <?= htmlspecialchars($selectedStore ?: '緑橋書店') ?>
            </div>
            <div class="subtitle">注文書一覧</div>
        </div>
        <div class="header-buttons">
            <!-- 作成ボタンが押下されたら、顧客選択画面に遷移する -->
            <form action="customer_choise.php" method="post" style="display: inline;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
                <input type="hidden" name="from" value="order_list.php">
                <button type="submit" class="header-btn">作成</button>
            </form>
            <button class="header-btn" onclick="location.href='top.php'">戻る</button>
        </div>
    </header>
    
    <main>
        <div class="control-panel">
            <form method="GET" action="order_list.php" style="display: inline-block;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars((string)$selectedStore) ?>">
                <label>
                    並べ替え：
                    <select name="sort" id="sortSelect" onchange="this.form.submit()">
                        <option value="no_asc" <?= $sort === 'no_asc' ? 'selected' : '' ?>>No.昇順</option>
                        <option value="no_desc" <?= $sort === 'no_desc' ? 'selected' : '' ?>>No.降順</option>
                        <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>金額多い順</option>
                        <option value="amount_asc" <?= $sort === 'amount_asc' ? 'selected' : '' ?>>金額少ない順</option>
                        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>登録日昇順</option>
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>登録日降順</option>
                    </select>
                </label>
                <input type="text" class="search-box" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="キーワードを入力">
                <button class="search-btn" type="submit">検索</button>
            </form>
        </div>

        <div class="table-container">
            <table class="order-table">
                <thead>
                    <tr>
                        <th class="nocol">No</th>
                        <th>顧客名</th>
                        <th>担当者名</th>
                        <th>合計金額</th>
                        <th>作成日</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody id="orderTbody">
                    <?php foreach ($orders as $order): $currencyMark = '￥'; ?>
                        <tr>
                            <td class="nocol">
                                <a href="order_check.php?no=<?= htmlspecialchars($order['orders_ID']) ?>&selected_store=<?= htmlspecialchars($selectedStore) ?>">
                                    <?= htmlspecialchars($order['orders_ID']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['charge'] ?? '') ?></td>
                            <td><?= $currencyMark . number_format(isset($order['total']) ? $order['total'] : 0) ?></td>
                            <td><?= htmlspecialchars($order['orderday'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['order_status'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
</body>

</html>