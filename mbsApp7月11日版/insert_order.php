<?php
// db.php でデータベース接続
include 'db.php';

$customer_id = $_POST['customer_id'];
$total = $_POST['total'];


// order.php から渡された店舗情報と元のリストページ情報を取得
$selectedStore = $_POST['selected_store'] ?? null;
$sourceListPage = $_POST['source_list_page'] ?? 'order_list.php'; // デフォルト値を設定


 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['customer_id'])) {
        $customer_id= $_POST['customer_id'];
    } else {
        $customer_id= null;
    }

    } 

// 注文作成
$stmt = $pdo->prepare("INSERT INTO orders (state,total,yk_customerID) VALUES ('',:total, :customer_id)");

$stmt->execute([
    ':total' => $total,
    ':customer_id' => $customer_id
]); //sqlの実行

$orderId = $pdo->lastInsertId();

// 注文明細をループして登録
$titles = $_POST['title'];
$quantities = $_POST['quantity'];
$values = $_POST['value'];
$descriptions = $_POST['description'];

$stmt = $pdo->prepare("INSERT INTO orderdetail (title, quantity, value,description, yk_ordersID) VALUES (:title, :quantity, :value,:description, :yk_orderid)");

for ($i = 0; $i < count($titles); $i++) {
    $stmt->execute([
        ':title' => $titles[$i],
        ':quantity' => $quantities[$i],
        ':value' => $values[$i],
        ':description' => $descriptions[$i],
        ':yk_orderid' => $orderId
    ]);
}
header("Location: order_check.php?no=" . urlencode($orderId) . "&success=1" . "&selected_store=" . urlencode($selectedStore));
exit;
