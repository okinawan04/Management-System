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

// ソート順のホワイトリストと対応するORDER BY句
$sort_options = [
    'no_asc' => 'c.customer_ID ASC',
    'no_desc' => 'c.customer_ID DESC'
];

// ホワイトリストに存在しない値が指定された場合はデフォルト値を使用
$orderByClause = $sort_options[$sort] ?? $sort_options['no_asc'];


$results = []; // 顧客データを格納する配列

$sql = "
    SELECT
        c.customer_ID,
        c.name AS customer_name,
        c.phoneNo AS customer_phone,
        c.address AS customer_address
    FROM
        customer c

    WHERE
        c.storeName = :selectedStore
    
    ORDER BY
        $orderByClause
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':selectedStore', $selectedStore, PDO::PARAM_STR);
$stmt->execute();
$results = $stmt->fetchAll();

$allowed_pages = ['order_list.php', 'delivery_list.php'];
$return_page = 'top.php'; // デフォルトの戻り先

// GET/POSTの両方で 'from' を受け取れるようにする
//formの中のname属性がfromの内容
$sourcePage = $_POST['from'] ?? $_GET['from'] ?? null;
if ($sourcePage) {
    if (in_array($sourcePage, $allowed_pages)) {
        $return_page = $sourcePage;
    }
}

// 遷移先のベースとなる詳細ページを決定
$target_detail_page = '';

if ($return_page === 'order_list.php') {
    $target_detail_page = 'order.php';
} elseif ($return_page === 'delivery_list.php') {
    $target_detail_page = 'delivery.php';
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>顧客選択画面</title>
    <link rel="stylesheet" href="仮画面/top/customer/choose_cust.css">
</head>

<body>
    <header>
        <div class="logo-container">
            <div class="logo">
                <?= htmlspecialchars($selectedStore ?: '緑橋書店') ?>
            </div>
            <div class="subtitle">顧客選択画面</div>
        </div>
        <div class="header-buttons">
            <form id="returnForm" action="<?= htmlspecialchars($return_page) ?>" method="POST" style="display:inline;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
                <button type="submit" class="header-btn">戻る</button>
            </form>
        </div>
    </header>

    <main>

        <div class="control-panel">
            <form method="GET" action="customer_choise.php" style="display: inline-block;">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars((string)$selectedStore) ?>">
                <input type="hidden" name="from" value="<?= htmlspecialchars((string)$return_page) ?>">
                <label>
                    並べ替え：
                    <select name="sort" onchange="this.form.submit()">
                        <option value="no_asc" <?= $sort === 'no_asc' ? 'selected' : '' ?>>No.昇順</option>
                        <option value="no_desc" <?= $sort === 'no_desc' ? 'selected' : '' ?>>No.降順</option>
                    </select>
                </label>
            </form>
            <input type="text" class="search-box" id="searchBox" placeholder="キーワードを入力" />
            <button class="search-btn" onclick="filterTable()">検索</button>
        </div>

        <div class="table-container">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th class="nocol">No</th>
                        <th>顧客名</th>
                        <th>電話番号</th>
                        <th>住所</th>
                    </tr>
                </thead>
                <tbody id="customerTbody">
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td class="nocol">
                                    <a href="#" onclick="selectCustomer('<?= htmlspecialchars($result['customer_ID'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <?= htmlspecialchars($result['customer_ID'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($result['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($result['customer_phone'] ?? ''); ?></td>
                                <td>
                                    <?= htmlspecialchars($result['customer_address'] ?? ''); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">顧客データがありません。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <form id="customerDetailForm" method="POST" style="display:none;">
        <input type="hidden" name="customer_id" id="customerIdInput">
        <input type="hidden" name="source_list_page" value="<?= htmlspecialchars($return_page) ?>">
        <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
    </form>
    <script>
        const searchBox = document.getElementById('searchBox');
        searchBox.addEventListener('keyup', filterTable);

        function filterTable() {
            const keyword = searchBox.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#customerTbody tr');
            rows.forEach(row => {
                const name = row.cells[1] ? row.cells[1].textContent.toLowerCase() : '';
                const phone = row.cells[2] ? row.cells[2].textContent.toLowerCase() : '';
                const address = row.cells[3] ? row.cells[3].textContent.toLowerCase() : '';
                const rowText = name + phone + address;

                if (rowText.includes(keyword)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function selectCustomer(customerId) {
            const form = document.getElementById('customerDetailForm');
            const customerIdInput = document.getElementById('customerIdInput');

            customerIdInput.value = customerId;

            // 遷移先の詳細ページをPHPで決定した変数から取得
            const targetPage = '<?= htmlspecialchars($target_detail_page) ?>';

            if (targetPage) {
                form.action = targetPage;
                form.submit();
            } else {
                alert('遷移先の詳細ページが設定されていません。');
            }
        }
    </script>
</body>

</html>