<?php
// db.php でデータベース接続
include 'db.php';

$delivery_id = isset($_GET['no']) ? intval($_GET['no']) : 0;
$selectedStore = $_POST['selected_store'] ?? $_GET['selected_store'] ?? null;

// 編集画面に遷移した時点で、該当納品書の明細を未納品状態に戻す
if ($delivery_id > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // この納品書に紐づくdeliverydetailのorderdetail_IDを取得
    $sql = "SELECT yk_orderdetailID FROM deliverydetail WHERE yk_deliverysID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$delivery_id]);
    $orderdetail_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($orderdetail_ids) {
        // 対象orderdetailのstateをNOに更新
        $in = str_repeat('?,', count($orderdetail_ids) - 1) . '?';
        $sql = "UPDATE orderdetail SET state = 'NO' WHERE orderdetail_ID IN ($in)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($orderdetail_ids);
    }
}

// 納品情報取得
$sql = "
    SELECT 
        d.deliverys_ID,
        d.deliveryday,
        c.name AS customer_name,
        c.customer_ID,
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

$customer_id = $delivery['customer_ID'];
$order_id = $delivery['orders_ID'];

// 注文全明細を取得
// 対象顧客の全注文明細（orderdetail）を取得
$sql_all = "
    SELECT 
        od.orderdetail_ID,
        od.title,
        od.quantity AS order_quantity,
        od.value,
        od.state,
        o.orders_ID
    FROM orderdetail od
    JOIN orders o ON od.yk_ordersID = o.orders_ID
    WHERE o.yk_customerID = ?
";
$stmt_all = $pdo->prepare($sql_all);
$stmt_all->execute([$customer_id]);
$all_details = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// deliverydetail情報を取得（この納品書に含まれる明細）
$sql_details = "
    SELECT 
        deliverydetail_ID,
        yk_orderdetailID,
        quantity
    FROM deliverydetail
    WHERE yk_deliverysID = ?
";
$stmt_details = $pdo->prepare($sql_details);
$stmt_details->execute([$delivery_id]);
$delivery_details = [];
foreach ($stmt_details->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $delivery_details[$row['yk_orderdetailID']] = [
        'deliverydetail_ID' => $row['deliverydetail_ID'],
        'quantity' => $row['quantity']
    ];
}

// POST時の更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
    $delivery_id = intval($_POST['delivery_id']);
    $selectedStore = $_POST['selected_store'] ?? null;
    $customer_id = $_POST['customer_id'] ?? null;

    $selected_rows = $_POST['selected_rows'] ?? [];
    $order_detail_ids = $_POST['order_detail_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    // トランザクション開始
    $pdo->beginTransaction();
    try {
        // 既存deliverydetailを全削除
        $pdo->prepare("DELETE FROM deliverydetail WHERE yk_deliverysID = ?")->execute([$delivery_id]);

        // 選択された明細を再登録
        foreach ($selected_rows as $idx) {
            $detailId = $order_detail_ids[$idx];
            $qty = isset($quantities[$idx]) ? intval($quantities[$idx]) : 0;
            if ($qty > 0) {
                $stmt = $pdo->prepare("INSERT INTO deliverydetail (yk_deliverysID, yk_orderdetailID, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$delivery_id, $detailId, $qty]);
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

        // orderdetailのstate更新
        foreach ($order_detail_ids as $idx => $detailId) {
            // 納品済数量の合計を取得
            $stmt = $pdo->prepare("SELECT SUM(quantity) FROM deliverydetail WHERE yk_orderdetailID = ?");
            $stmt->execute([$detailId]);
            $delivered = $stmt->fetchColumn();

            // 注文数量を取得
            $stmt = $pdo->prepare("SELECT quantity FROM orderdetail WHERE orderdetail_ID = ?");
            $stmt->execute([$detailId]);
            $ordered = $stmt->fetchColumn();

            // 納品済数量が注文数量と等しい場合のみstateをYESに
            if ($delivered == $ordered) {
                $stmt = $pdo->prepare("UPDATE orderdetail SET state = 'YES' WHERE orderdetail_ID = ?");
                $stmt->execute([$detailId]);
            } else {
                $stmt = $pdo->prepare("UPDATE orderdetail SET state = 'NO' WHERE orderdetail_ID = ?");
                $stmt->execute([$detailId]);
            }
        }

        $pdo->commit();
        header("Location: delivery_check.php?no=$delivery_id&selected_store=" . urlencode($selectedStore) . "&success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "エラー: " . $e->getMessage();
    }
}

// 明細リスト作成（納品書作成画面と同じ構成：全注文明細＋この納品書の明細を合体）
$rows = [];
foreach ($all_details as $d) {
    // この納品書での納品済数量
    $this_delivery_qty = isset($delivery_details[$d['orderdetail_ID']]) ? (int)$delivery_details[$d['orderdetail_ID']]['quantity'] : 0;

    // 全納品済数量（他納品書分も含む）
    $stmt_sum = $pdo->prepare("SELECT SUM(quantity) FROM deliverydetail WHERE yk_orderdetailID = ?");
    $stmt_sum->execute([$d['orderdetail_ID']]);
    $delivered_qty = $stmt_sum->fetchColumn();
    $delivered_qty = is_null($delivered_qty) ? 0 : (int)$delivered_qty;

    // 未納品数（この納品書での数量も含めてよい）
    $remain_qty = $d['order_quantity'] - $delivered_qty + $this_delivery_qty;
    if ($remain_qty < 0) $remain_qty = 0;

    // 「未納品」状態のものも必ずリストアップ
    $checked = $this_delivery_qty > 0 ? true : false;

    $rows[] = [
        'orderdetail_ID' => $d['orderdetail_ID'],
        'title' => $d['title'],
        'order_quantity' => $d['order_quantity'],
        'value' => $d['value'],
        'delivered_quantity' => $this_delivery_qty,
        'checked' => $checked,
        'remain_qty' => $remain_qty
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>納品書編集</title>
    <link rel="stylesheet" href="仮画面/top/delivery/delivery_form.css">
</head>
<body>
<header>
    <div class="logo-container">
        <div class="logo">緑橋書店</div>
        <div class="subtitle">納品書編集</div>
    </div>
    <div class="header-buttons">
        <input type="submit" form="editDeliveryForm" class="header-btn" name="update_delivery" value="保存">
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
        <form id="editDeliveryForm" method="post">
            <input type="hidden" name="delivery_id" value="<?= htmlspecialchars($delivery_id) ?>">
            <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
            <input type="hidden" name="update_delivery" value="1">
            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">
            <div class="recipient">
                <span class="recipient-name"><?= htmlspecialchars($delivery['customer_name'] ?? '') ?></span>
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
                        <th>金額（税込）</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sum_qty = 0;
                    $sum_total = 0;
                    foreach ($rows as $i => $row):
                        $qty = $row['checked'] ? (int)$row['delivered_quantity'] : ($row['remain_qty'] ?? 0);
                        $max_qty = $row['checked'] ? $row['order_quantity'] : ($row['remain_qty'] ?? 0);
                        $checked = $row['checked'] ? 'checked' : '';
                        $disabled = $checked ? '' : 'disabled';
                        $total = $row['value'] * $qty;
                        $sum_qty += $qty;
                        $sum_total += $total;
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="selected_rows[]" value="<?= $i ?>" <?= $checked ?>>
                            <input type="hidden" name="order_detail_ids[]" value="<?= $row['orderdetail_ID'] ?>">
                        </td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td>￥<?= $row['value'] ?></td>
                        <td>
                            <input type="number" name="quantities[]" value="<?= $qty ?>" min="0" max="<?= $max_qty ?>" style="width:60px;" <?= $disabled ?>>
                            <span style="font-size:12px;color:#888;">(残:<?= $max_qty ?>)</span>
                        </td>
                        <td><?= $row['value'] * $qty ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- 合計行 -->
                    <tr>
                        <td></td>
                        <td colspan="2" class="bold">合計</td>
                        <td><input type="number" id="sum_qty" value="<?= $sum_qty ?>" readonly></td>
                        <td><span id="sum_total">￥<?= htmlspecialchars(number_format($sum_total)) ?></span></td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</main>
<script>
    // 数量変更やチェックボックス変更時に合計・金額を再計算
    document.addEventListener('DOMContentLoaded', function() {
        // 数量変更時
        document.querySelectorAll('.delivery-table tbody input[name="quantities[]"]').forEach(function(input) {
            input.addEventListener('input', recalcTotals);
        });
        // チェックボックス変更時
        document.querySelectorAll('.delivery-table tbody input[type="checkbox"][name="selected_rows[]"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', recalcTotals);
        });

        function recalcTotals() {
            let sum_qty = 0;
            let sum_total = 0;
            // 明細行を再取得
            document.querySelectorAll('.delivery-table tbody tr').forEach(function(row) {
                const checkbox = row.querySelector('input[type="checkbox"][name="selected_rows[]"]');
                const qtyInput = row.querySelector('input[name="quantities[]"]');
                const valueCell = row.querySelectorAll('td')[2];
                const totalCell = row.querySelectorAll('td')[4];
                if (!checkbox || !qtyInput || !valueCell || !totalCell) return;

                // チェックが入っている行だけ計算
                if (checkbox.checked) {
                    const qty = parseInt(qtyInput.value) || 0;
                    const value = parseInt(valueCell.textContent.replace('￥', '').replace(/,/g, '')) || 0;
                    const total = value * qty;
                    sum_qty += qty;
                    sum_total += total;
                    totalCell.textContent = '￥' + total.toLocaleString(); 
                    qtyInput.disabled = false;
                } else {
                    totalCell.textContent = 0;
                    qtyInput.disabled = true;
                }
            });
            document.getElementById('sum_qty').value = sum_qty;
            document.getElementById('sum_total').textContent = '￥' + sum_total.toLocaleString();
        }

        // 初期表示時にも計算
        recalcTotals();
    });
</script>
</body>
</html>