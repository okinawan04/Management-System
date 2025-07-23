<?php

// db.php でデータベース接続
include 'db.php';

//selectedStoreFromCustomerChoiseを初期化
$selectedStoreFromCustomerChoise = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_store'])) {
    $selectedStoreFromCustomerChoise = $_POST['selected_store'];
}

//preselectedCustomerIdを初期化
$preselectedCustomerId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_id'])) {
    $preselectedCustomerId = $_POST['customer_id'];
}

// 顧客IDを取得するクエリ
$sql = "SELECT customer_id, name FROM customer";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$customers = $stmt->fetchAll();

// 遷移元ページ情報（order_list.php か delivery_list.php か）もPOSTで受け取る
$sourceListPage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['source_list_page'])) {
    $sourceListPage = $_POST['source_list_page'];
}

// 「戻る」ボタンのリンク先を動的に決定
$backButtonAction = 'order_list.php'; // デフォルトの戻り先
$backButtonStoreId = '';
$backButtonFromPage = '';

if ($sourceListPage) {
    $backButtonAction = 'customer_choise.php';
    $backButtonFromPage = $sourceListPage;
    $backButtonStoreId = $selectedStoreFromCustomerChoise;
}

$returnToCustomerChoiseFrom = 'order.php';

?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>注文書作成</title>
    <link rel="stylesheet" href="仮画面/top/order/order_form.css">
</head>

<body>
    <header>
        <div class="logo-container">
            <div class="logo">
                緑橋書店
            </div>
            <div class="subtitle">注文書作成</div>
        </div>
        <div class="header-buttons">
            <button id="saveOrderBtn" class="header-btn" type="button">保存</button>
            <form id="backToCustomerChoiseForm" action="<?= htmlspecialchars($backButtonAction) ?>" method="POST" style="display:inline;">
                <?php if ($sourceListPage): ?>
                    <input type="hidden" name="from" value="<?= htmlspecialchars($backButtonFromPage) ?>">
                    <input type="hidden" name="selected_store" value="<?= htmlspecialchars($backButtonStoreId) ?>">
                <?php endif; ?>
                <button class="header-btn" type="submit">戻る</button>
            </form>


        </div>
    </header>

    <main>
        <form id="orderForm" action="insert_order.php" method="post">
            <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStoreFromCustomerChoise ?? '') ?>">
            <input type="hidden" name="source_list_page" value="<?= htmlspecialchars($sourceListPage ?? '') ?>">

            <div class="order-form">
                <div class="form-header">
                    <div class="title">注文書</div>
                </div>

                <div class="recipient">
                    <select name="customer_id" id="customer_id" onchange="toggleNewCustomerForm()" class="recipient-name" required disabled>
                        <option value="">顧客を選択</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>"
                                <?php if ($preselectedCustomerId == $customer['customer_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?> (ID: <?php echo htmlspecialchars($customer['customer_id']); ?>)
                            </option>
                        <?php endforeach; ?>

                    </select>
                    <input type="hidden" name="customer_id" value="<?= htmlspecialchars($preselectedCustomerId) ?>">
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
                    <tbody id="products">
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td class="bold">合計</td>
                            <td><span id="totalQuantity">0</span></td>
                            <td><input type="number" name="total" readonly></td>
                            <td><textarea name="remarks" placeholder="備考欄"></textarea></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </form>
    </main>
    <script>
    window.onload = function() {
        const customerSelect = document.getElementById('customer_id');
        const preselectedId = '<?php echo $preselectedCustomerId; ?>';
        if (preselectedId) {
            customerSelect.value = preselectedId;
        }
        // 初期表示で10行追加
        for (let i = 0; i < 10; i++) {
            const products = document.getElementById('products');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="row-number">${i + 1}</td>
                <td><input type="text" name="title[]"></td>
                <td><input type="number" name="quantity[]" min="1"></td>
                <td><input type="number" name="value[]" min="0" step="1"></td>
                <td>
                    <input type="text" name="description[]">
                </td>
            `;
            products.appendChild(tr);
            setupEventListeners(tr); // 新しい行にイベントリスナーを設定
        }
        updateRowNumbers(); // 初期ロード時にも行番号を更新
        calcTotal();
    };

    function calcTotal() {
        let totalAmount = 0; // 合計金額
        let totalQuantity = 0; // 合計数量
        const values = document.getElementsByName('value[]');
        const quantities = document.getElementsByName('quantity[]');

        for (let i = 0; i < values.length; i++) {
            // 品名が入力されている行のみ計算対象とする
            const titleInput = document.querySelectorAll('input[name="title[]"]')[i];
            const titleValue = titleInput ? titleInput.value.trim() : '';

            if (titleValue !== '') { // 品名がある場合のみ数量と単価をチェック
                const v = parseFloat(values[i].value) || 0;
                const q = parseInt(quantities[i].value) || 0;

                totalAmount += v * q;
                totalQuantity += q;
            }
        }
        document.querySelector('input[name="total"]').value = totalAmount;
        document.getElementById('totalQuantity').textContent = totalQuantity; // 合計数量を更新
    }

    function setupEventListeners(row) {
        row.querySelectorAll('input[name="value[]"], input[name="quantity[]"], input[name="title[]"]').forEach(el => {
            el.addEventListener('input', calcTotal);
        });
    }

    function updateRowNumbers() {
        const rows = document.querySelectorAll('#products tr');
        let num = 1;
        rows.forEach(row => {
            const rowNumberCell = row.querySelector('.row-number');
            if (rowNumberCell) {
                rowNumberCell.textContent = num++;
            }
        });
    }

    document.getElementById('saveOrderBtn').addEventListener('click', function() {
        const productsTable = document.getElementById('products');
        const rows = productsTable.querySelectorAll('tr');

        const rowsToRemove = [];
        let validationError = false;

        // 顧客選択のバリデーション
        const customerSelect = document.getElementById('customer_id');
        if (customerSelect.value === "") {
            alert('顧客を選択してください。');
            return;
        }

        rows.forEach((row, index) => {
            const titleInput = row.querySelector('input[name="title[]"]');
            const quantityInput = row.querySelector('input[name="quantity[]"]');
            const valueInput = row.querySelector('input[name="value[]"]');

            const titleValue = titleInput ? titleInput.value.trim() : '';
            const quantityValue = quantityInput ? quantityInput.value.trim() : '';
            const valueValue = valueInput ? valueInput.value.trim() : '';

            // 品名が入力されていて、数量または単価が空の場合にエラー
            if (titleValue !== '' && (quantityValue === '' || valueValue === '')) {
                alert(`${index + 1}行目の品名が入力されていますが、数量または単価が未入力です。`);
                validationError = true;
                return;
            }

            // 全ての入力フィールドが空の場合、その行を削除対象とする
            if (titleValue === '' && quantityValue === '' && valueValue === '') {
                rowsToRemove.push(row);
            }
        });

        if (validationError) {
            return;
        }

        rowsToRemove.forEach(row => row.remove());

        updateRowNumbers();
        calcTotal();

        document.getElementById('orderForm').requestSubmit();
    });
</script>
</body>

</html>