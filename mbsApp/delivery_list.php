<?php
// filepath: c:\Users\234051\Desktop\mbsApp\delivery_list.php
include 'db.php';

// 納品データ取得（deliverysテーブルとorders/customerテーブルを結合して顧客名・担当者名も取得）
$sql = "
    SELECT 
        d.deliverys_ID,
        c.name AS customer_name,
        c.chargeName AS charge,
        d.total,
        d.deliveryday
    FROM deliverys d
    LEFT JOIN deliverydetail dd ON d.deliverys_ID = dd.yk_deliverysID
    LEFT JOIN orders o ON d.yk_ordersID = o.orders_ID
    LEFT JOIN customer c ON o.yk_customerID = c.customer_ID
    ORDER BY d.deliverys_ID ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$deliverys = $stmt->fetchAll();
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
            <div class="logo">緑橋書店</div>
            <div class="subtitle">納品書一覧</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" onclick="location.href='top.php'">戻る</button>
            <button class="header-btn" onclick="location.href='delivery.php'">作成</button>
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
                                <a href="delivery_check.php?no=<?= htmlspecialchars($delivery['deliverys_ID']) ?>">
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
    <script>
        // 検索機能（簡易版）
        function filterTable() {
            const keyword = document.getElementById('searchBox').value.trim();
            const rows = document.querySelectorAll('#deliveryTbody tr');
            rows.forEach(row => {
                const text = row.textContent;
                row.style.display = (keyword === '' || text.includes(keyword)) ? '' : 'none';
            });
        }
    </script>
</body>

</html>