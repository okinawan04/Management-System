<?php
// db.php でデータベース接続
include 'db.php';

// $selectedStore を初期化
$selectedStore = null; 

if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) { // GETパラメータもチェック (並び替えなどで使用)
    $selectedStore = $_GET['selected_store'];
}

// GETパラメータからソート順を取得（デフォルトはNo.昇順）
$sort = $_GET['sort'] ?? 'no_asc';

$sort_options = [
    'no_asc' => 'c.customer_ID ASC',
    'no_desc' => 'c.customer_ID DESC',
    'name' => 'customer_name ASC, c.customer_ID ASC',
    'sales_desc' => 'total_amount DESC',
    'sales_asc' => 'total_amount ASC',
    'leadtime_asc' => 'avg_lead_time ASC',
    'leadtime_desc' => 'avg_lead_time DESC',
];

// ホワイトリストに存在しない値が指定された場合はデフォルト値を使用
$orderByClause = $sort_options[$sort] ?? $sort_options['no_asc'];

// 顧客データを格納する配列
$customers = []; 

// メッセージ表示用
$message = ''; 

// $selectedStore が空でないことを確認してからクエリを実行
if (!empty($selectedStore)) {
    $sql = "
        SELECT
            c.customer_ID,
            c.chargeName,
            c.name AS customer_name,
            GROUP_CONCAT(od.title SEPARATOR ', ') AS title,
            COALESCE(SUM(dd.quantity * od.value), 0) AS total_amount,
            AVG(DATEDIFF(d.deliveryday, o.orderday)) AS avg_lead_time
        FROM
            customer c
        LEFT JOIN orders o ON c.customer_ID = o.yk_customerID
        LEFT JOIN deliverys d ON o.orders_ID = d.yk_ordersID
        LEFT JOIN deliverydetail dd ON d.deliverys_ID = dd.yk_deliverysID
        LEFT JOIN orderdetail od ON dd.yk_orderdetailID = od.orderdetail_ID
        WHERE
            c.storeName = :selectedStore
        GROUP BY
            c.customer_ID
        ORDER BY
            $orderByClause
        ";

    try {
        
        $stmt = $pdo->prepare($sql);

        // bindValue() を prepare() の後に、execute() の前に呼び出す
        $stmt->bindValue(':selectedStore', $selectedStore, PDO::PARAM_STR);

        // execute() を呼び出す
        $stmt->execute();

        // 結果をfetchAllで取得
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // エラーハンドリング
        error_log("顧客データ取得エラー: " . $e->getMessage());
        $message = "顧客データの取得中にエラーが発生しました。管理者にお問い合わせください。";
        
    }
} else {
    $message = "表示する店舗が選択されていません。";
    
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>顧客情報一覧</title>
    <link rel="stylesheet" href="仮画面/top/customer/customer.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <div class="logo">
                <?= htmlspecialchars($selectedStore ?: '緑橋書店') ?>
            </div>
            <div class="subtitle">顧客情報一覧</div>
        </div>
        <div class="header-buttons">
            <form id="importForm" action="import_customer.php" method="post" enctype="multipart/form-data" style="display:inline;">
                <input type="file" id="excelFile" name="excelFile" accept=".xlsx,.xls" style="display:none;" onchange="document.getElementById('importForm').submit();">
                <button type="button" class="header-btn" onclick="document.getElementById('excelFile').click();">アップロード</button>
            </form>
            <button class="header-btn" onclick="location.href='top.php'">戻る</button>
        </div>
    </header>

    <main>
        <div class="control-panel">
            <form method="GET" action="customer.php" style="display: inline-block;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars((string)$selectedStore) ?>">
                <label>
                    並べ替え：
                    <select name="sort" id="sortSelect" onchange="this.form.submit()">
                        <option value="sales_desc" <?= $sort === 'sales_desc' ? 'selected' : '' ?>>売上高い順</option>
                        <option value="sales_asc" <?= $sort === 'sales_asc' ? 'selected' : '' ?>>売上低い順</option>
                        <option value="no_asc" <?= $sort === 'no_asc' ? 'selected' : '' ?>>No.昇順</option>
                        <option value="no_desc" <?= $sort === 'no_desc' ? 'selected' : '' ?>>No.降順</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>顧客名順</option>
                        <option value="leadtime_asc" <?= $sort === 'leadtime_asc' ? 'selected' : '' ?>>リードタイム早い順</option>
                        <option value="leadtime_desc" <?= $sort === 'leadtime_desc' ? 'selected' : '' ?>>リードタイム遅い順</option>
                    </select>
                </label>
            </form>
            <input type="text" class="search-box" id="searchBox" placeholder="キーワードを入力">
            <button class="search-btn" onclick="filterTable()">検索</button>
        </div>

        <div class="table-container">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th class="nocol">No</th>
                        <th>顧客名</th>
                        <th>合計購入金額 (円)</th>
                        <th>平均リードタイム (日)</th>
                    </tr>
                </thead>
                <tbody id="customerTbody">
                    <?php $no = 1; foreach ($customers as $customer): ?>
                        <tr>
                            <td class="nocol">
                                <a href="customer_analytics.php?customer_id=<?= htmlspecialchars($customer['customer_ID'], ENT_QUOTES, 'UTF-8'); ?>&selected_store=<?= htmlspecialchars($selectedStore) ?>">
                                    <?= htmlspecialchars($customer['customer_ID'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <span style="display:none;">
                                    <?= htmlspecialchars($customer['chargeName'] ?? '') ?>
                                    <?= htmlspecialchars($customer['title'] ?? '') ?>
                                </span>
                            </td>
                            <td><?= number_format($customer['total_amount']); ?></td>
                            <td>
                                <?= number_format($customer['avg_lead_time'] ?? 0, 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
    // 簡易検索・並べ替え（フロントのみ、PHP側でのソートは別途実装が必要）
     function filterTable() {
        const keyword = document.getElementById('searchBox').value.trim();
        const rows = document.querySelectorAll('#customerTbody tr');
        rows.forEach(row => {
            const text = row.textContent;
            row.style.display = (keyword === '' || text.includes(keyword)) ? '' : 'none';
        });
    } 
    </script>
</body>
</html>
