<?php
// db.php でデータベース接続
include 'db.php';

$selectedStore = null; // $selectedStore を初期化
if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) { // GETパラメータもチェック (例: 直接URLでアクセスされた場合など)
    $selectedStore = $_GET['selected_store'];
}

// GETパラメータからソート順を取得（デフォルトはNo.昇順）
$sort = $_GET['sort'] ?? 'no_asc';

// GETパラメータからキーワードを取得（デフォルトは空文字）
$keyword = $_GET['keyword'] ?? '';


// ソート順のホワイトリストと対応するORDER BY句
$sort_options = [
    'no_asc' => 'd.deliverys_ID ASC',
    'no_desc' => 'd.deliverys_ID DESC',
    'customer' => 'customer_name ASC, d.deliverys_ID ASC',
    'amount_desc' => 'd.total DESC',
    'amount_asc' => 'd.total ASC',
    'date_desc' => 'd.deliveryday DESC, d.deliverys_ID DESC',
    'date_asc' => 'd.deliveryday ASC, d.deliverys_ID ASC',
];

// 検索条件作成
$where = '';
$params = [':selectedStore' => $selectedStore]; // パラメータ配列を初期化
if (!empty($keyword)) {
    // ANDを追加し、検索条件を構築
    // 品名(od.title)の検索は、メインクエリに影響を与えないようにEXISTS句を使用する
    $where = " AND (
            c.name LIKE :keyword
            OR c.phoneNo LIKE :keyword
            OR c.address LIKE :keyword
            OR c.chargeName LIKE :keyword
            OR d.total LIKE :keyword
            OR EXISTS (
                SELECT 1
                FROM deliverydetail dd_sub
                JOIN orderdetail od_sub ON dd_sub.yk_orderdetailID = od_sub.orderdetail_ID
                WHERE dd_sub.yk_deliverysID = d.deliverys_ID AND od_sub.title LIKE :keyword
            )
        )";
    $params[':keyword'] = '%' . $keyword . '%';
}

// ホワイトリストに存在しない値が指定された場合はデフォルト値を使用
$orderByClause = $sort_options[$sort] ?? $sort_options['no_asc'];

$deliverys = []; // 取得した納品データを格納する配列
$message = ''; // メッセージ表示用

if (!empty($selectedStore)) {
    // 納品データ取得（deliverysテーブルとorders/customerテーブルを結合して顧客名・担当者名も取得）
    // 品名検索はEXISTS句で行うため、メインクエリでのdeliverydetailのJOINは不要になり、SELECT DISTINCTも不要
    $sql = "
        SELECT
            d.deliverys_ID,
            c.name AS customer_name,
            c.chargeName AS charge,
            d.total,
            d.deliveryday
        FROM deliverys d
        LEFT JOIN orders o ON d.yk_ordersID = o.orders_ID
        LEFT JOIN customer c ON o.yk_customerID = c.customer_ID
        WHERE
            c.storeName = :selectedStore
            $where
        ORDER BY $orderByClause
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $deliverys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("納品データ取得エラー: " . $e->getMessage());
        $message = "納品データの取得中にエラーが発生しました。";
    }
} else {
    $message = "表示する店舗が選択されていません。";
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>納品書一覧</title>
    <link rel="stylesheet" href="仮画面/top/delivery/delivery_list.css">
</head>

<body>
    <header>
        <div class="logo-container">
            <div class="logo">
                <?= htmlspecialchars($selectedStore ?: '緑橋書店') ?>
            </div>
            <div class="subtitle">納品書一覧</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" onclick="location.href='top.php'">戻る</button>
            <!-- 作成ボタンが押下されたら、顧客選択画面に遷移する -->
            <form action="customer_choise.php" method="post" style="display: inline;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
                <input type="hidden" name="from" value="delivery_list.php">
                <button type="submit" class="header-btn">作成</button>
            </form>
        </div>
    </header>

    <main>
        <div class="control-panel">
            <form method="GET" action="delivery_list.php" style="display: inline-block;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars((string)$selectedStore) ?>">
                <label>
                    並べ替え：
                    <select name="sort" id="sortSelect" onchange="this.form.submit()">
                        <option value="no_asc" <?= $sort === 'no_asc' ? 'selected' : '' ?>>No.昇順</option>
                        <option value="no_desc" <?= $sort === 'no_desc' ? 'selected' : '' ?>>No.降順</option>
                        <option value="customer" <?= $sort === 'customer' ? 'selected' : '' ?>>顧客名順</option>
                        <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>金額多い順</option>
                        <option value="amount_asc" <?= $sort === 'amount_asc' ? 'selected' : '' ?>>金額少ない順</option>
                        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>作成日昇順</option>
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>作成日降順</option>
                    </select>
                </label>
                <input type="text" class="search-box" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="キーワードを入力">
                <button class="search-btn" type="submit">検索</button>
            </form>
        </div>

        <div class="table-container">
            <table class="delivery-table">
                <thead>
                    <tr>
                        <th class="nocol">No</th>
                        <th>顧客名</th>
                        <th>担当者名</th>
                        <th>税合計金額</th>
                        <th>作成日</th>
                    </tr>
                </thead>
                <tbody id="deliveryTbody">
                    <?php foreach ($deliverys as $delivery): ?>
                        <tr>
                            <td class="nocol">
                                <a href="delivery_check.php?no=<?= htmlspecialchars($delivery['deliverys_ID']) ?>&selected_store=<?= htmlspecialchars($selectedStore) ?>">
                                    <?= htmlspecialchars($delivery['deliverys_ID']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($delivery['customer_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($delivery['charge'] ?? '') ?></td>
                            <td><?= number_format(isset($delivery['total']) ? $delivery['total'] : 0) ?></td>
                            <td><?= htmlspecialchars($delivery['deliveryday'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>