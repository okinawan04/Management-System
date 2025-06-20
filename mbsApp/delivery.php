<?php
// DB接続のみ外部ファイル化
include 'db.php';

// POST処理：納品登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deliver'])) {
    $selected_rows = $_POST['selected_rows'] ?? [];
    $order_detail_ids = $_POST['order_detail_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $customer_id = $_POST['customer_id'] ?? null;

    if (!empty($selected_rows) && $customer_id) {
        $pdo->beginTransaction();
        try {
            // まずorders_IDを取得
            $stmt = $pdo->prepare("SELECT orders_ID FROM orders WHERE yk_customerID = :customer_id ORDER BY orders_ID DESC LIMIT 1");
            $stmt->execute([':customer_id' => $customer_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                // 合計金額計算
                $total = 0;
                foreach ($selected_rows as $idx) {
                    $detailId = $order_detail_ids[$idx];
                    $qty = isset($quantities[$idx]) ? intval($quantities[$idx]) : 0;
                    $stmt2 = $pdo->prepare("SELECT value FROM orderdetail WHERE orderdetail_ID = :detail_id");
                    $stmt2->execute([':detail_id' => $detailId]);
                    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
                    $total += $qty * ($row['value'] ?? 0);
                }

                // deliverysに登録
                $stmt = $pdo->prepare("
                    INSERT INTO deliverys (yk_ordersID, total)
                    VALUES (:order_id, :total)
                ");
                $stmt->execute([
                    ':order_id' => $order['orders_ID'],
                    ':total' => $total
                ]);
                $deliverys_id = $pdo->lastInsertId();

                // deliverydetailに登録
                foreach ($selected_rows as $idx) {
                    $detailId = $order_detail_ids[$idx];
                    $qty = isset($quantities[$idx]) ? intval($quantities[$idx]) : 0;
                    // deliverydetailへINSERT
                    $stmt = $pdo->prepare("
                        INSERT INTO deliverydetail (yk_deliverysID, yk_orderdetailID, quantity)
                        VALUES (:deliverys_id, :orderdetail_id, :quantity)
                    ");
                    $stmt->execute([
                        ':deliverys_id' => $deliverys_id,
                        ':orderdetail_id' => $detailId,
                        ':quantity' => $qty
                    ]);

                    // ここで納品済数量の合計を取得
                    $stmt = $pdo->prepare("SELECT SUM(quantity) as sum_qty FROM deliverydetail WHERE yk_orderdetailID = :detail_id");
                    $stmt->execute([':detail_id' => $detailId]);
                    $delivered = $stmt->fetch(PDO::FETCH_ASSOC)['sum_qty'] ?? 0;

                    // 注文数量を取得
                    $stmt = $pdo->prepare("SELECT quantity FROM orderdetail WHERE orderdetail_ID = :detail_id");
                    $stmt->execute([':detail_id' => $detailId]);
                    $ordered = $stmt->fetch(PDO::FETCH_ASSOC)['quantity'] ?? 0;

                    // 納品済数量が注文数量と等しい場合のみstateをYESに
                    if ($delivered == $ordered) {
                        $stmt = $pdo->prepare("UPDATE orderdetail SET state = 'YES' WHERE orderdetail_ID = :detail_id");
                        $stmt->execute([':detail_id' => $detailId]);
                    }
                }

                $pdo->commit();
                $message = "納品処理が完了しました。";
            } else {
                throw new Exception("注文情報が見つかりません。");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "エラー: " . $e->getMessage();
        }
    } else {
        $error = "納品する明細を選択してください。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>納品書作成</title>
    <link rel="stylesheet" href="仮画面/top/delivery/delivery_form.css">
</head>

<body>
    <header>
        <div class="logo-container">
            <div class="logo">緑橋書店</div>
            <div class="subtitle">納品書作成</div>
        </div>
        <div class="header-buttons">
            <button class="header-btn" type="button" onclick="location.href='delivery_list.php'">戻る</button>
            <input type="submit" form="deliveryForm" class="header-btn" name="deliver" value="保存">
        </div>
    </header>

    <main>
        <div class="delivery_form">
            <div class="form-header">
                <div class="title">納品書</div>
            </div>
            <form id="deliveryForm" method="post">
                <div class="recipient">
                    <select name="customer_id" id="customer_id" class="recipient-name" required
                        onchange="document.getElementById('deliveryForm').submit();">
                        <option value="">顧客を選択</option>
                        <?php
                        $customers = $pdo->query("SELECT customer_ID, name FROM customer")->fetchAll();
                        $selected_id = $_POST['customer_id'] ?? $_GET['customer_id'] ?? '';
                        foreach ($customers as $c) {
                            $selected = ($selected_id == $c['customer_ID']) ? 'selected' : '';
                            echo "<option value=\"{$c['customer_ID']}\" $selected>{$c['name']}</option>";
                        }
                        ?>
                    </select>
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
                        // 顧客選択時のみ明細表示
                        $rows = [];
                        if (
                            ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_id']) && is_numeric($_POST['customer_id'])) ||
                            (isset($_GET['customer_id']) && is_numeric($_GET['customer_id']))
                        ) {
                            $customer_id = $_POST['customer_id'] ?? $_GET['customer_id'];
                            $stmt = $pdo->prepare("
                                SELECT od.orderdetail_ID, o.orders_ID, od.title, od.quantity, od.value, od.description
                                FROM orderdetail od
                                JOIN orders o ON od.yk_ordersID = o.orders_ID
                                WHERE o.yk_customerID = :customer_id AND od.state = 'NO'
                            ");
                            $stmt->execute([':customer_id' => $customer_id]);
                            $rows = $stmt->fetchAll();
                        }
                        ?>
                        <?php
                        $sum_qty = 0;
                        $sum_value = 0;
                        $sum_total = 0;
                        if ($rows):
                            $i = 1;
                            foreach ($rows as $row):
                                // 納品済数量を取得
                                $stmt2 = $pdo->prepare("SELECT SUM(quantity) FROM deliverydetail WHERE yk_orderdetailID = :detail_id");
                                $stmt2->execute([':detail_id' => $row['orderdetail_ID']]);
                                $delivered_qty = $stmt2->fetchColumn();
                                $delivered_qty = is_null($delivered_qty) ? 0 : (int)$delivered_qty;

                                // 未納品数
                                $remain_qty = $row['quantity'] - $delivered_qty;
                                if ($remain_qty < 0) $remain_qty = 0;
                        ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_rows[]" value="<?= $i - 1 ?>" checked>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['title']) ?>
                                        <input type="hidden" name="order_detail_ids[]" value="<?= $row['orderdetail_ID'] ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="quantities[]" value="<?= $remain_qty ?>" min="1" max="<?= $remain_qty ?>" style="width:60px;">
                                        <span style="font-size:12px;color:#888;">(残:<?= $remain_qty ?>)</span>
                                    </td>
                                    <td><?= $row['value'] ?></td>
                                    <td><?= $row['value'] * $remain_qty ?></td>

                                </tr>
                            <?php
                                $sum_qty += $remain_qty;
                                $sum_value += $row['value'];
                                $sum_total += $row['value'] * $remain_qty;
                                $i++;
                            endforeach;
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">表示できる納品明細がありません。</td>
                            </tr>
                        <?php endif; ?>
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
                            <td></td>
                            <td class="bottom-label">税込合計金額</td>
                            <td colspan="1">
                                <input type="number" id="total_with_tax" value="<?= $sum_total + floor($sum_total * 0.1) ?>" readonly style="width: 100%;">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!--<button type="submit" name="deliver" class="header-btn" style="margin-top:10px;">納品する</button> -->
            </form>
        </div>
    </main>
    <?php if (!empty($message)) echo "<p style='color:green;'>$message</p>"; ?>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <script>
        // 税率変更時に再計算
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('tax_rate').addEventListener('input', recalcTotals);

            // 数量変更時にも再計算
            document.querySelectorAll('.delivery-table tbody input[name="quantities[]"]').forEach(function(input) {
                input.addEventListener('input', recalcTotals);
            });

            // 合計・税額・税込合計を再計算
            function recalcTotals() {
                let sum_qty = 0;
                let sum_value = 0;
                let sum_total = 0;
                // 明細行を再取得
                document.querySelectorAll('.delivery-table tbody tr').forEach(function(row) {
                    if (!row.querySelector('.row-number')) return;
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 6) return;
                    // 数量はinputから取得
                    const qtyInput = cells[2].querySelector('input[type="number"]');
                    const qty = qtyInput ? parseInt(qtyInput.value) || 0 : 0;
                    const value = parseInt(cells[3].textContent) || 0;
                    const total = value * qty;
                    sum_qty += qty;
                    sum_value += value;
                    sum_total += total;
                    // 金額セルも更新
                    cells[4].textContent = total;
                });
                document.getElementById('sum_qty').value = sum_qty;
                document.getElementById('sum_value').value = sum_value;
                document.getElementById('sum_total').value = sum_total;

                // 税率・税額・税込合計
                const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
                const taxAmount = Math.floor(sum_total * (taxRate / 100));
                document.getElementById('tax_amount').value = taxAmount;
                document.getElementById('total_with_tax').value = sum_total + taxAmount;
            }
        });
    </script>
</body>

</html>