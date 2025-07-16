<?php

// db.php でデータベース接続（$pdoが利用可能になる想定）
include 'db.php';

// $selectedStore を初期化
$selectedStore = null; 

if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) { // GETパラメータもチェック (並び替えなどで使用)
    $selectedStore = $_GET['selected_store'];
}

// GETパラメータからソート順を取得（デフォルトは売上高い順）
$sort = $_GET['sort'] ?? 'sales_desc';

// ソート順のホワイトリストと対応するORDER BY句
$sort_options = [
    'no_asc' => 'c.customer_ID ASC',
    'no_desc' => 'c.customer_ID DESC',
    'name' => 'customer_name ASC',
    'leadtime_asc' => 'avg_lead_time ASC, c.customer_ID ASC',
    'leadtime_desc' => 'avg_lead_time DESC, c.customer_ID ASC',
    'sales_desc' => 'total_amount DESC, c.customer_ID ASC',
    'sales_asc' => 'total_amount ASC, c.customer_ID ASC',
];

// ホワイトリストに存在しない値が指定された場合はデフォルト値を使用
$orderByClause = $sort_options[$sort] ?? $sort_options['sales_desc'];

$customers = []; // 顧客データを格納する配列
$message = ''; // メッセージ表示用（必要に応じて）

// $selectedStore が空でないことを確認してからクエリを実行
if (!empty($selectedStore)) {
    $sql = "
        SELECT
            c.customer_ID,
            c.name AS customer_name,
            COALESCE(SUM(dd.quantity * od.value), 0) AS total_amount,
            AVG(DATEDIFF(d.deliveryday, o.orderday)) AS avg_lead_time
        FROM
            customer c
        LEFT JOIN
            orders o ON c.customer_ID = o.yk_customerID
        LEFT JOIN
            deliverys d ON o.orders_ID = d.yk_ordersID
        LEFT JOIN
            deliverydetail dd ON d.deliverys_ID = dd.yk_deliverysID
        LEFT JOIN
            orderdetail od ON dd.yk_orderdetailID = od.orderdetail_ID
        WHERE
            c.storeName = :selectedStore
        GROUP BY
            c.customer_ID, c.name
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