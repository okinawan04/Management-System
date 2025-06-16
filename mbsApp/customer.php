<?php
// index.php
include 'get_customer.php';
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
            <div class="logo">緑橋書店</div>
            <div class="subtitle">顧客情報一覧</div>
        </div>
        <div class="header-buttons">
            <form id="importForm" action="import_customer.php" method="post" enctype="multipart/form-data" style="display:inline;">
                <input type="file" id="excelFile" name="excelFile" accept=".xlsx,.xls" style="display:none;" onchange="document.getElementById('importForm').submit();">
                <button type="button" class="header-btn" onclick="document.getElementById('excelFile').click();">更新</button>
            </form>
            <button class="header-btn" onclick="location.href='top.php'">戻る</button>
        </div>
    </header>

    <main>
        <div class="control-panel">
            <label>
                並べ替え：
                <select id="sortSelect">
                    <option value="no_asc">No.昇順</option>
                    <option value="no_desc">No.降順</option>
                    <option value="name">顧客名順</option>
                    <option value="leadtime_asc">リードタイム早い順</option>
                    <option value="leadtime_desc">リードタイム遅い順</option>
                    <option value="sales_desc">売上高い順</option>
                    <option value="sales_asc">売上低い順</option>
                </select>
            </label>
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
                            <td class="nocol"><?= htmlspecialchars($customer['customer_ID'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
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
