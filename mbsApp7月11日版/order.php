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
                    <select name="customer_id" id="customer_id" onchange="toggleNewCustomerForm()" class="recipient-name" required>
                        <option value="">顧客を選択</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>"
                                <?php if ($preselectedCustomerId == $customer['customer_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?> (ID: <?php echo htmlspecialchars($customer['customer_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="new">新規顧客として登録</option>
                    </select>
                    <span>様</span>
                </div>
                <div id="newCustomerForm" style="display:none; margin-top:10px;">
                    <label>顧客名:</label>
                    <input type="text" name="new_customer_name" id="new_customer_name"><br>
                    <label>住所:</label>
                    <input type="text" name="new_customer_address" id="new_customer_address"><br>
                    <label>電話番号:</label>
                    <input type="text" name="new_customer_phoneNo" id="new_customer_phone"><br>
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
                        <tr>
                            <td class="row-number">1</td>
                            <td><input type="text" name="title[]" required></td>
                            <td><input type="number" name="quantity[]" min="1" required></td>
                            <td><input type="number" name="value[]" min="0" step="0.01" required></td>
                            <td>
                                <input type="text" name="description[]">
                                <button type="button" class="removeRowBtn" style="margin-left:5px;">削除</button>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td><textarea name="remarks" placeholder="備考欄"></textarea></td>
                            <td></td>
                            <td></td>
                            <td>
                                <div class="bottom-label">合計金額</div>
                                <input type="number" name="total" readonly>
                            </td>
                            
                        </tr>
                    </tbody>
                </table>
                <button type="button" id="addRowBtn" style="margin:10px 0 0 0;">＋ 商品を追加</button>
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
            calcTotal(); 
        };

        function calcTotal() {
            let total = 0;
            const values = document.getElementsByName('value[]');
            const quantities = document.getElementsByName('quantity[]');
            for (let i = 0; i < values.length; i++) {
                const v = parseFloat(values[i].value) || 0;
                const q = parseInt(quantities[i].value) || 0;
                total += v * q;
            }
            document.querySelector('input[name="total"]').value = total;
        }

        document.querySelectorAll('input[name="value[]"], input[name="quantity[]"]').forEach(el => {
            el.addEventListener('input', calcTotal);
        });

        document.getElementById('addRowBtn').addEventListener('click', function() {
            const products = document.getElementById('products');
            const rows = products.querySelectorAll('tr');
            const remarksRow = rows[rows.length - 1]; 
            let rowNum = 1;
            for (let i = 0; i < rows.length - 1; i++) {
                const num = parseInt(rows[i].querySelector('.row-number')?.textContent);
                if (!isNaN(num)) rowNum = num + 1;
            }
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="row-number">${rowNum}</td>
                <td><input type="text" name="title[]" required></td>
                <td><input type="number" name="quantity[]" min="1" required></td>
                <td><input type="number" name="value[]" min="0" step="0.01" required></td>
                <td>
                    <input type="text" name="description[]">
                    <button type="button" class="removeRowBtn" style="margin-left:5px;">削除</button>
                </td>
            `;
            products.insertBefore(tr, remarksRow);

            tr.querySelectorAll('input[name="value[]"], input[name="quantity[]"]').forEach(el => {
                el.addEventListener('input', calcTotal);
            });

            tr.querySelector('.removeRowBtn').addEventListener('click', function() {
                tr.remove();
                updateRowNumbers();
                calcTotal();
            });
        });

        document.querySelectorAll('.removeRowBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tr = btn.closest('tr');
                tr.remove();
                updateRowNumbers();
                calcTotal();
            });
        });

        function updateRowNumbers() {
            const rows = document.querySelectorAll('#products .row-number');
            let num = 1;
            rows.forEach(row => {
                row.textContent = num++;
            });
        }

        // ヘッダーの保存ボタンがクリックされたら、メインのフォームを送信
        document.getElementById('saveOrderBtn').addEventListener('click', function() {
            document.getElementById('orderForm').submit();
        });

    </script>
</body>
</html>
