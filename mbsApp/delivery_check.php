<?php
include 'db.php';

$delivery_id = isset($_GET['no']) ? intval($_GET['no']) : 0;

// 納品情報取得
$sql = "
    SELECT 
        d.deliverys_ID,
        d.deliveryday,
        c.name AS customer_name,
        c.chargeName AS charge,
        o.orders_ID,
        o.total
    FROM deliverys d
    LEFT JOIN orders o ON d.yk_ordersID = o.orders_ID
    LEFT JOIN customer c ON o.yk_customerID = c.customer_ID
    WHERE d.deliverys_ID = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$delivery_id]);
$delivery = $stmt->fetch();

if (!$delivery) {
    echo "納品情報が見つかりません";
    exit;
}

// 明細情報取得
$sql_details = "
    SELECT 
        od.title,
        dd.quantity,
        od.value
    FROM deliverydetail dd
    LEFT JOIN orderdetail od ON dd.yk_orderdetailID = od.orderdetail_ID
    WHERE dd.yk_deliverysID = ?
";
$stmt_details = $pdo->prepare($sql_details);
$stmt_details->execute([$delivery['deliverys_ID']]);
$details = $stmt_details->fetchAll();

$total = 0;
foreach ($details as $d) {
    $total += ($d['quantity'] ?? 0) * ($d['value'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>納品書確認</title>
    <link rel="stylesheet" href="仮画面/top/delivery/delivery_check.css">
</head>
<body>
    <header>
        <div class="logo-container">
            <div class="logo">緑橋書店</div>
            <div class="subtitle">納品書確認</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" onclick="location.href='delivery_list.php'">戻る</button>
            <!-- 必要に応じて印刷・編集・削除ボタンを追加 -->
        </div>
    </header>

    <main>
        <div class="delivery_form">
            <div class="form-header">
                <div class="title">納品書</div>
                <input type="text" class="delivery-date" value="<?= htmlspecialchars($delivery['deliveryday'] ?? '') ?>" readonly>
            </div>

            <div class="recipient">
                <input type="text" class="recipient-name" value="<?= htmlspecialchars($delivery['customer_name'] ?? '') ?>" readonly>
                <span>様</span>
            </div>
            <div class="note">下記の通り納品いたしました</div>

            <table class="delivery-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>品名</th>
                        <th>数量</th>
                        <th>単価</th>
                        <th>金額（税込）</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $i => $d): ?>
                        <tr>
                            <td class="row-number"><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($d['title'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['quantity'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['value'] ?? '') ?></td>
                            <td><?= number_format(($d['quantity'] ?? 0) * ($d['value'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td></td>
                        <td class="bold">合計</td>
                        <td></td>
                        <td></td>
                        <td><input type="text" value="<?= number_format($total) ?>" readonly></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>