<?php
// db.php でデータベース接続
include 'db.php';

$delivery_id = isset($_GET['no']) ? intval($_GET['no']) : 0;

if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) {
    $selectedStore = $_GET['selected_store'];
}

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

// 合計数量を計算
$totalQuantity = 0;
foreach ($details as $detail) {
    $totalQuantity += $detail['quantity'];
}

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
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div id="banner" style="background:#4caf50;color:#fff;padding:10px;text-align:center;position:fixed;top:0;left:0;width:100%;z-index:1000;">
            納品書が保存されました
        </div>
        <script>
            setTimeout(() => {
                document.getElementById('banner').style.display = 'none';
            }, 5000);
        </script>
    <?php endif; ?>

    <header>
        <div class="logo-container">
            <div class="logo">緑橋書店</div>
            <div class="subtitle">納品書確認</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" id="printButton">印刷</button>
            <a href="delivery_edit.php?no=<?= $delivery_id ?>&selected_store=<?= htmlspecialchars($selectedStore) ?>" class="header-btn delivery_edit">編集</a>
            <button class="header-btn" id="deleteButton">削除</button>
            <form id="deletedeliveryForm" action="delivery_delete.php" method="POST" style="display:none;">
                <input type="hidden" name="delivery_id" value="<?= htmlspecialchars($delivery_id) ?>">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
            </form>

            <form action="delivery_list.php" method="POST" style="display:inline;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
                <button class="header-btn" type="submit">戻る</button>
            </form>
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
                        <th>単価</th>
                        <th>数量</th>
                        <th>金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $i => $d): ?>
                        <tr>
                            <td class="row-number"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($d['title'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['value'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['quantity'] ?? '') ?></td>
                            <td><?= number_format(($d['quantity'] ?? 0) * ($d['value'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- 合計行 -->
                    <tr>
                        <td></td>
                        <td class="bold" colspan="2">合計</td>
                        <td><?= htmlspecialchars($totalQuantity) ?></td> <!-- 合計数量 -->
                        <td><input type="text" value="<?= number_format($total) ?>" readonly></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        // 印刷ボタン処理
        document.getElementById("printButton").addEventListener("click", function() {
            window.print();
        });

        // 削除ボタン処理
        document.getElementById('deleteButton').addEventListener('click', function() {
            if (confirm('本当にこの納品書を削除しますか？\nこの操作は元に戻せません。')) {
                document.getElementById('deletedeliveryForm').submit();
            }
        });
    </script>
</body>

</html>