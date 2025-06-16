<?php
include 'db.php';

$order_id = isset($_GET['no']) ? intval($_GET['no']) : 0;

// 注文情報取得
$sql = "
    SELECT 
        o.orders_ID,
        c.name AS customer_name,
        c.chargeName AS charge,
        o.total,
        o.orderday,
        o.state
    FROM orders o
    LEFT JOIN customer c ON o.yk_customerID = c.customer_ID
    WHERE o.orders_ID = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// 注文明細取得を追加
$sql_detail = "
    SELECT 
        title,      -- 品名
        quantity,   -- 数量
        value,      -- 単価
        description -- 摘要
    FROM orderdetail
    WHERE yk_ordersID = ?
";
$stmt_detail = $pdo->prepare($sql_detail);
$stmt_detail->execute([$order_id]);
$details = $stmt_detail->fetchAll();

if (!$order) {
    echo "注文情報が見つかりません";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>注文書確認</title>
    <link rel="stylesheet" href="仮画面/top/order/order_form.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <div class="logo">緑橋書店</div>
            <div class="subtitle">注文書確認</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" onclick="location.href='order_list.php'">戻る</button>
            <!-- 編集・削除ボタン等は必要に応じて -->
        </div>
    </header>

    <main>
        <div class="order-form">
            <div class="form-header">
                <div class="title">注文書</div>
                <input type="text" class="order-date" value="<?= htmlspecialchars($order['orderday']) ?>" readonly>
            </div>

            <div class="recipient">
                <input type="text" class="recipient-name" value="<?= htmlspecialchars($order['customer_name']) ?>" readonly>
                <span>様</span>
            </div>
            <div class="note">下記の通りにご注文申し上げます</div>

            <table class="order-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>品名</th>
                        <th>数量</th>
                        <th>単価</th>
                        <th>摘要</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- 注文明細をここに表示（order_detailテーブルがある場合） -->
                    
                    <?php foreach ($details as $i => $d): ?>
                        <tr>
                            <td class="row-number"><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($d['title']) ?></td>
                            <td><?= htmlspecialchars($d['quantity']) ?></td>
                            <td><?= htmlspecialchars($d['value']) ?></td>
                            <td><?= htmlspecialchars($d['description']?? '' ) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr>
                        <td></td>
                        <td colspan="2"></td>
                        <td>
                            <div class="bottom-label">合計金額</div>
                            <input type="text" value="<?= number_format($order['total']) ?>" readonly>
                        </td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>