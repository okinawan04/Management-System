<?php
// filepath: c:\Users\234051\Desktop\mbsApp\order_list.php
include 'db.php';

// 注文データ取得（ordersテーブルとcustomerテーブルを結合して顧客名も取得）
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
    ORDER BY o.orders_ID ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll();
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
            <div class="logo">緑橋書店</div>
            <div class="subtitle">注文書一覧</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" onclick="location.href='top.php'">戻る</button>
            <button class="header-btn" onclick="location.href='order.php'">作成</button>
        </div>
    </header>

    <main>
        <div class="control-panel">
            <label>
                並べ替え：
                <select id="sortSelect">
                    <option value="no_asc">No.昇順</option>
                    <option value="no_desc">No.降順</option>
                    <option value="customer">顧客名順</option>
                    <option value="amount_desc">金額多い順</option>
                    <option value="amount_asc">金額少ない順</option>
                    <option value="date_asc">登録日昇順</option>
                    <option value="date_desc">登録日降順</option>
                </select>
            </label>
            <input type="text" class="search-box" id="searchBox" placeholder="キーワードを入力">
            <button class="search-btn" onclick="filterTable()">検索</button>
        </div>

        <div class="table-container">
            <table class="order-table">
                <thead>
                    <tr>
                        <th class="nocol">No</th>
                        <th>顧客名</th>
                        <th>担当者名</th>
                        <th>税合計金額</th>
                        <th>作成日</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody id="orderTbody">
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="nocol">
                                <a href="order_check.php?no=<?= htmlspecialchars($order['orders_ID']) ?>">
                                    <?= htmlspecialchars($order['orders_ID']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['charge'] ?? '')?></td>
                            <td><?= number_format(isset($order['total']) ? $order['total'] : 0) ?></td>
                            <td><?= htmlspecialchars($order['orderday']) ?></td>
                            <td><?= htmlspecialchars($order['state']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
    // 検索機能（簡易版）
    function filterTable() {
        const keyword = document.getElementById('searchBox').value.trim();
        const rows = document.querySelectorAll('#orderTbody tr');
        rows.forEach(row => {
            const text = row.textContent;
            row.style.display = (keyword === '' || text.includes(keyword)) ? '' : 'none';
        });
    }
    </script>
</body>
</html>