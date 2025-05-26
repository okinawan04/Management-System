<?php
    include 'db.php';

// 顧客IDを取得するクエリ
$sql = "SELECT customer_id, name FROM customer";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>注文書作成</title>
    <link rel="stylesheet" href="仮画面/top/order/order_form.css">
    <style>
        /* 追加カスタムCSS（必要なら） */
    </style>
</head>
<body>
    <header>
        <div class="logo-container">
            <div class="logo">緑橋書店</div>
            <div class="subtitle">注文書作成</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" type="button" onclick="history.back()">戻る</button>
            <input type="submit" form="orderForm" class="header-btn" value="保存">
        </div>
    </header>

    <main>
        <form id="orderForm" action="insert_order.php" method="post">
            <div class="order-form">
                <div class="form-header">
                    <div class="title">注文書</div>
                </div>

                <div class="recipient">
                    <select name="customer_id" id="customer_id" onchange="toggleNewCustomerForm()" class="recipient-name" required>
                        <option value="">顧客を選択</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo htmlspecialchars($customer['customer_id']); ?>">
                                <?php echo htmlspecialchars($customer['name']); ?> (ID: <?php echo htmlspecialchars($customer['customer_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="new">新規顧客として登録</option>
                    </select>
                    <span>様</span>
                </div>
                <!--<div id="newCustomerForm" style="display:none; margin-top:10px;">
                    <label>顧客名:</label>
                    <input type="text" name="new_customer_name" id="new_customer_name"><br>
                    <label>住所:</label>
                    <input type="text" name="new_customer_address" id="new_customer_address"><br>
                    <label>電話番号:</label>
                    <input type="text" name="new_customer_phone" id="new_customer_phone"><br>
                </div> -->
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
        // 新規顧客フォームの表示/非表示（未使用のためコメントアウト）
        /** function toggleNewCustomerForm() {
            const customerSelect = document.getElementById('customer_id');
            const newCustomerForm = document.getElementById('newCustomerForm');
            if (customerSelect.value === "new") {
                newCustomerForm.style.display = "block";
                document.getElementById('new_customer_name').required = true;
                document.getElementById('new_customer_address').required = true;
                document.getElementById('new_customer_phone').required = true;
            } else {
                newCustomerForm.style.display = "none";
                document.getElementById('new_customer_name').required = false;
                document.getElementById('new_customer_address').required = false;
                document.getElementById('new_customer_phone').required = false;
            }
        } **/

        // 合計金額自動計算
        function calcTotal() {
            let total = 0;
            // 各商品の単価と数量を取得して合計を計算
            const values = document.getElementsByName('value[]');
            const quantities = document.getElementsByName('quantity[]');
            for (let i = 0; i < values.length; i++) {
                const v = parseFloat(values[i].value) || 0;
                const q = parseInt(quantities[i].value) || 0;
                total += v * q;
            }
            // 合計金額欄に反映
            document.querySelector('input[name="total"]').value = total;
        }
        // ページロード時、既存のinputにもイベントを付与
        document.querySelectorAll('input[name="value[]"], input[name="quantity[]"]').forEach(el => {
            el.addEventListener('input', calcTotal);
        });

        // 注文明細行の追加
        document.getElementById('addRowBtn').addEventListener('click', function() {
            const products = document.getElementById('products');
            const rows = products.querySelectorAll('tr');
            const remarksRow = rows[rows.length - 1]; // 備考・合計金額行
            let rowNum = 1;
            // 既存の最大行番号を取得
            for (let i = 0; i < rows.length - 1; i++) {
                const num = parseInt(rows[i].querySelector('.row-number')?.textContent);
                if (!isNaN(num)) rowNum = num + 1;
            }
            // 新しい行を作成
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
            // 備考行の直前に追加
            products.insertBefore(tr, remarksRow);

            // 新しいinputにも合計計算イベントを付与
            tr.querySelectorAll('input[name="value[]"], input[name="quantity[]"]').forEach(el => {
                el.addEventListener('input', calcTotal);
            });

            // 削除ボタンイベントを付与
            tr.querySelector('.removeRowBtn').addEventListener('click', function() {
                tr.remove();
                updateRowNumbers();
                calcTotal();
            });
        });

        // 初期表示の削除ボタンにもイベントを付与
        document.querySelectorAll('.removeRowBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tr = btn.closest('tr');
                tr.remove();
                updateRowNumbers();
                calcTotal();
            });
        });

        // 行番号を振り直す関数
        function updateRowNumbers() {
            const rows = document.querySelectorAll('#products .row-number');
            let num = 1;
            rows.forEach(row => {
                row.textContent = num++;
            });
        }
    </script>
</body>
</html>