<?php
// db.php でデータベース接続
include 'db.php';

$delivery_id = isset($_GET['no']) ? intval($_GET['no']) : 0;
$selectedStore = $_GET['selected_store'] ?? null;

// POST時の更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
    $delivery_id = intval($_POST['delivery_id']);
    $selectedStore = $_POST['selected_store'] ?? null;

    if (isset($_POST['detail_id']) && is_array($_POST['detail_id'])) {
        foreach ($_POST['detail_id'] as $i => $detail_id) {
            $enabled = isset($_POST['enabled'][$i]) ? 1 : 0;
            $quantity = intval($_POST['quantity'][$i] ?? 0);

            if ($enabled) {
                // チェックあり：数量のみ更新
                $sql = "UPDATE deliverydetail SET quantity = ? WHERE deliverydetail_ID = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$quantity, intval($detail_id)]);
            } else {
                // チェックなし：deliverydetail削除＆紐づくorderdetailのstateを'NO'に
                // 1. 紐づくorderdetail_ID取得
                $sql = "SELECT yk_orderdetailID FROM deliverydetail WHERE deliverydetail_ID = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([intval($detail_id)]);
                $orderdetail_id = $stmt->fetchColumn();

                // 2. deliverydetail削除
                $sql = "DELETE FROM deliverydetail WHERE deliverydetail_ID = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([intval($detail_id)]);

                // 3. 注文明細のstateをNOに
                if ($orderdetail_id) {
                    $sql = "UPDATE orderdetail SET state = 'NO' WHERE orderdetail_ID = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([intval($orderdetail_id)]);
                }
            }
        }
    }

    // 合計金額を再計算
    $sql = "SELECT SUM(dd.quantity * od.value) AS new_total
            FROM deliverydetail dd
            LEFT JOIN orderdetail od ON dd.yk_orderdetailID = od.orderdetail_ID
            WHERE dd.yk_deliverysID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$delivery_id]);
    $new_total = $stmt->fetchColumn();
    if ($new_total === false) $new_total = 0;

    // deliverysテーブルのtotalを更新
    $sql = "UPDATE deliverys SET total = ? WHERE deliverys_ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_total, $delivery_id]);

    // 完了後リダイレクト
    header("Location: delivery_check.php?no=$delivery_id&selected_store=" . urlencode($selectedStore) . "&success=1");
    exit;
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
        dd.deliverydetail_ID,
        od.title,
        dd.quantity,
        od.value,
        od.orderdetail_ID
    FROM deliverydetail dd
    LEFT JOIN orderdetail od ON dd.yk_orderdetailID = od.orderdetail_ID
    WHERE dd.yk_deliverysID = ?
";
$stmt_details = $pdo->prepare($sql_details);
$stmt_details->execute([$delivery['deliverys_ID']]);
$details = $stmt_details->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>納品書編集</title>
    <link rel="stylesheet" href="仮画面/top/delivery/delivery_edit.css">
</head>
<body>
<header>
    <div class="logo-container">
        <div class="logo">緑橋書店</div>
        <div class="subtitle">納品書編集</div>
    </div>
    <div class="header-buttons">
         <button class="header-btn" type="submit" form="editDeliveryForm">保存</button>
        <form action="delivery_check.php" method="GET" style="display:inline;">
            <input type="hidden" name="no" value="<?= htmlspecialchars($delivery_id) ?>">
            <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
            <button class="header-btn" type="submit">戻る</button>
        </form>
       
    </div>
</header>
<main>
    <div class="delivery_form">
        <div class="form-header">
            <div class="title">納品書</div>
            <input type="text" class="delivery-date" value="<?= htmlspecialchars($delivery['deliveryday'] ?? '') ?>" readonly>
        </div>
        <form id="editDeliveryForm" action="delivery_edit.php?no=<?= $delivery_id ?>&selected_store=<?= urlencode($selectedStore) ?>" method="POST">
            <input type="hidden" name="delivery_id" value="<?= htmlspecialchars($delivery_id) ?>">
            <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
            <input type="hidden" name="update_delivery" value="1">

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
                    <?php
                    $sum_qty = 0;
                    $sum_value = 0;
                    $sum_total = 0;
                    foreach ($details as $i => $d):
                        $qty = (int)($d['quantity'] ?? 0);
                        $value = (int)($d['value'] ?? 0);
                        $total = $qty * $value;
                        $sum_qty += $qty;
                        $sum_value += $value;
                        $sum_total += $total;
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="enabled[<?= $i ?>]" value="1" <?= $qty > 0 ? 'checked' : '' ?>>
                            <input type="hidden" name="detail_id[<?= $i ?>]" value="<?= htmlspecialchars($d['deliverydetail_ID']) ?>">
                        </td>
                        <td><?= htmlspecialchars($d['title'] ?? '') ?></td>
                        <td>
                            <input type="number" name="quantity[<?= $i ?>]" value="<?= $qty ?>" min="0" style="width:60px;">
                        </td>
                        <td><?= $value ?></td>
                        <td class="row-total"><?= $total ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- 合計行 -->
                    <tr>
                        <td></td>
                        <td class="bold">合計</td>
                        <td><input type="number" id="sum_qty" value="<?= $sum_qty ?>" readonly></td>
                        <td><input type="number" id="sum_value" value="<?= $sum_value ?>" readonly></td>
                        <td><input type="number" id="sum_total" value="<?= $sum_total ?>" readonly></td>
                    </tr>
                    <!-- 税率・消費税額 -->
                    <tr>
                        <td></td>
                        <td class="bottom-label">税率（％）</td>
                        <td>
                            <input type="number" id="tax_rate" name="tax_rate" value="10" min="0" max="100" style="width: 100%;">
                        </td>
                        <td class="bottom-label">消費税額</td>
                        <td>
                            <input type="number" id="tax_amount" name="tax_amount" value="<?= floor($sum_total * 0.1) ?>" readonly style="width: 100%;">
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td class="bottom-label">税込合計金額</td>
                        <td colspan="1">
                            <input type="number" id="total_with_tax" value="<?= $sum_total + floor($sum_total * 0.1) ?>" readonly style="width: 100%;">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</main>
<script>
    // 税率・数量変更時に再計算
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tax_rate').addEventListener('input', recalcTotals);

        document.querySelectorAll('.delivery-table tbody input[type="number"][name^="quantity"]').forEach(function(input) {
            input.addEventListener('input', recalcTotals);
        });
        document.querySelectorAll('.delivery-table tbody input[type="checkbox"][name^="enabled"]').forEach(function(input) {
            input.addEventListener('change', recalcTotals);
        });

        function recalcTotals() {
            let sum_qty = 0;
            let sum_value = 0;
            let sum_total = 0;
            document.querySelectorAll('.delivery-table tbody tr').forEach(function(row) {
                const checkbox = row.querySelector('input[type="checkbox"][name^="enabled"]');
                const qtyInput = row.querySelector('input[type="number"][name^="quantity"]');
                const valueCell = row.querySelectorAll('td')[3];
                const totalCell = row.querySelector('.row-total');
                if (!checkbox || !qtyInput || !valueCell || !totalCell) return;

                let qty = parseInt(qtyInput.value) || 0;
                let value = parseInt(valueCell.textContent) || 0;
                let enabled = checkbox.checked;

                if (!enabled) qty = 0;
                let total = value * qty;
                totalCell.textContent = total;

                sum_qty += qty;
                sum_value += value;
                sum_total += total;
            });
            document.getElementById('sum_qty').value = sum_qty;
            document.getElementById('sum_value').value = sum_value;
            document.getElementById('sum_total').value = sum_total;

            const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            const taxAmount = Math.floor(sum_total * (taxRate / 100));
            document.getElementById('tax_amount').value = taxAmount;
            document.getElementById('total_with_tax').value = sum_total + taxAmount;
        }
    });
</script>
</body>
</html>