<?php
// db.php でデータベース接続
include 'db.php';

$selectedStore = null;

// 注文IDをPOSTまたはGETから取得
$order_id = 0;
if (isset($_POST['no'])) {
    $order_id = intval($_POST['no']);
} elseif (isset($_GET['no'])) {
    $order_id = intval($_GET['no']);
}

// ここで selectedStore を GET または POST から取得する
if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) {
    $selectedStore = $_GET['selected_store'];
}


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
    WHERE o.orders_ID = :order_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['order_id' => $order_id]);
$order = $stmt->fetch();

// 注文明細取得を追加
$sql_detail = "
    SELECT 
        title,      -- 品名
        quantity,   -- 数量
        value,      -- 単価
        description -- 摘要
    FROM orderdetail
    WHERE yk_ordersID = :order_id
";
$stmt_detail = $pdo->prepare($sql_detail);
$stmt_detail->execute(['order_id' => $order_id]);
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
    <title>注文書確認画面</title>
    <link rel="stylesheet" href="仮画面/top/order/order_check.css">
</head>

<body>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div id="banner" style="background:#4caf50;color:#fff;padding:10px;text-align:center;position:fixed;top:0;left:0;width:100%;z-index:1000;">
            注文書が保存されました
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
            <div class="subtitle">注文書確認画面</div>
        </div>
        <div class="header-buttons">
            <!-- 編集・削除ボタン等は必要に応じて -->
             <button class="header-btn" id="printButton">印刷</button>
             <a href="order_edit.php?no=<?= $order_id ?>&selected_store=<?= htmlspecialchars($selectedStore) ?>" class="header-btn order_edit">編集</a>
             
             <button class="header-btn" id="deleteButton">削除</button>
             <form id="deleteOrderForm" action="order_delete.php" method="POST" style="display:none;">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
            </form>
            <form id="backToOrderListForm" action="order_list.php" method="POST" style="display:inline;">
                    <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
                    <button class="header-btn" type="submit">戻る</button>
            </form>
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
                            <td class="row-number"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($d['title']) ?></td>
                            <td><?= htmlspecialchars($d['quantity']) ?></td>
                            <td><?= htmlspecialchars(number_format($d['value'])) ?></td>
                            <td><?= htmlspecialchars($d['description'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <tr>
                        <td></td>
                        <td colspan="1"></td>
                        <td>
                            <div class="bottom-label">合計金額</div>
                        </td>
                        <td>
                            <input type="text" value="<?= number_format($order['total']) ?>" readonly>
                        </td>
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
            if (confirm('本当にこの注文書を削除しますか？\nこの操作は元に戻せません。')) {
                document.getElementById('deleteOrderForm').submit();
            }
        });
    </script>
</body>

</html>