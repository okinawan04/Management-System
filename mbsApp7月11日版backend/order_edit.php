<?php
// db.php でデータベース接続
include 'db.php';

$selectedStore = null;
$order_id = isset($_GET['no']) ? intval($_GET['no']) : 0;

// --- 注文データ更新のためのPOSTリクエスト処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $selectedStore = isset($_POST['selected_store']) ? $_POST['selected_store'] : null;

    if ($order_id > 0) {
        try {
            $pdo->beginTransaction();

            // 注文明細の更新
            if (isset($_POST['detail_id']) && is_array($_POST['detail_id'])) {
                foreach ($_POST['detail_id'] as $index => $detail_id) {
                    $title = $_POST['title'][$index] ?? '';
                    $quantity = $_POST['quantity'][$index] ?? 0;
                    $value = $_POST['value'][$index] ?? 0;
                    $description = $_POST['description'][$index] ?? '';

                    $update_detail_sql = "
                        UPDATE orderdetail
                        SET title = ?, quantity = ?, value = ?, description = ?
                        WHERE orderdetail_ID = ? AND yk_ordersID = ?
                    ";
                    $stmt = $pdo->prepare($update_detail_sql);
                    $stmt->execute([
                        $title,
                        $quantity,
                        $value,
                        $description,
                        intval($detail_id), // 整数型であることを確認
                        $order_id
                    ]);
                }
            }

            //  ordersテーブルのtotalを再計算して更新
            //明細が更新された場合に合計金額も最新の状態に保つためです。
            $recalculate_total_sql = "
                SELECT SUM(quantity * value) AS new_total
                FROM orderdetail
                WHERE yk_ordersID = ?
            ";
            $stmt_total = $pdo->prepare($recalculate_total_sql);
            $stmt_total->execute([$order_id]);
            $new_total = $stmt_total->fetchColumn();

            $update_order_total_sql = "
                UPDATE orders
                SET total = ?
                WHERE orders_ID = ?
            ";
            $stmt_update_total = $pdo->prepare($update_order_total_sql);
            $stmt_update_total->execute([$new_total, $order_id]);


            $pdo->commit();
            // 成功メッセージと店舗情報を付けてorder_check.phpへリダイレクト
            header("Location: order_check.php?no=" . $order_id . "&selected_store=" . urlencode($selectedStore) . "&success=1");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("注文編集エラー: " . $e->getMessage());
            // エラーメッセージを付けてリダイレクト
            header("Location: order_check.php?no=" . $order_id . "&selected_store=" . urlencode($selectedStore) . "&error=1");
            exit;
        }
    }
}
// --- POSTリクエスト処理ここまで ---


// --- 初期表示またはPOST処理後のデータ取得 ---

// selectedStoreをGETまたはPOSTから取得（初期ページロード時またはPOSTリダイレクト後）
if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) {
    $selectedStore = $_GET['selected_store'];
}

// 注文情報取得
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
    WHERE o.orders_ID = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// 注文明細取得（更新のために orderDetail_ID も取得）
$sql_detail = "
    SELECT 
        orderdetail_ID,  -- これを追加して各明細行を識別できるようにする
        title,           -- 品名
        quantity,        -- 数量
        value,           -- 単価
        description      -- 摘要
    FROM orderdetail
    WHERE yk_ordersID = ?
    ORDER BY orderdetail_ID ASC -- 一貫した行順序を保つためにIDでソート
";
$stmt_detail = $pdo->prepare($sql_detail);
$stmt_detail->execute([$order_id]);
$details = $stmt_detail->fetchAll();

if (!$order) {
    echo "注文情報が見つかりません";
    exit;
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>注文書編集画面</title>
    <link rel="stylesheet" href="仮画面/top/order/order_form.css">
</head>

<body>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div id="banner" style="background:#4caf50;color:#fff;padding:10px;text-align:center;position:fixed;top:0;left:0;width:100%;z-index:1000;">
            注文書が保存されました
        </div>
        <script>
            setTimeout(() => {
                document.getElementById('banner').style.display = 'none';
            }, 5000);
        </script>
    <?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
        <div id="banner" style="background:#f44336;color:#fff;padding:10px;text-align:center;position:fixed;top:0;left:0;width:100%;z-index:1000;">
            注文書の保存中にエラーが発生しました。
        </div>
        <script>
            setTimeout(() => {
                document.getElementById('banner').style.display = 'none';
            }, 5000);
        </script>
    <?php endif; ?>

    <header>
        <div class="logo-container">
            <div class="logo">緑橋書店</div>
            <div class="subtitle">注文書編集画面</div>
        </div>
        <div class="header-buttons">
            <form id="backForm" action="order_check.php" method="POST" style="display:inline;">
                <input type="hidden" name="no" value="<?= htmlspecialchars($order_id) ?>">
                <input type="hidden" name="selected_store" value="<?= htmlspecialchars((string)$selectedStore) ?>">
                <button class="header-btn" type="submit">戻る</button>
            </form>
            
            <button class="header-btn" type="submit" form="editOrderForm">保存</button>
        </div>
    </header>

    <main>
        <form id="editOrderForm" action="order_edit.php" method="POST">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
            <input type="hidden" name="selected_store" value="<?= htmlspecialchars((string)$selectedStore) ?>">
            <input type="hidden" name="update_order" value="1"> 

            <div class="order-form">
                <div class="form-header">
                    <div class="title">注文書</div>
                    <input type="text" class="order-date" value="<?= htmlspecialchars($order['orderday']) ?>" readonly>
                </div>

                <div class="recipient">
                    <input type="text" class="recipient-name" value="<?= htmlspecialchars($order['customer_name']) ?>" readonly>
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
                    <tbody>
                        <?php foreach ($details as $i => $d): ?>
                            <tr>
                                <td class="row-number"><?= $i + 1 ?></td>
                                <input type="hidden" name="detail_id[<?= $i ?>]" value="<?= htmlspecialchars($d['orderdetail_ID']) ?>">
                                
                                <td><input type="text" name="title[<?= $i ?>]" value="<?= htmlspecialchars($d['title']) ?>"></td>
                                <td><input type="number" name="quantity[<?= $i ?>]" value="<?= htmlspecialchars($d['quantity']) ?>"></td>
                                <td><input type="number" name="value[<?= $i ?>]" value="<?= htmlspecialchars($d['value']) ?>"></td>
                                <td><input type="text" name="description[<?= $i ?>]" value="<?= htmlspecialchars($d['description'] ?? '') ?>"></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td></td>
                            <td colspan="2"></td>
                            <td>
                                <div class="bottom-label">合計金額</div>
                                <input type="text" value="<?= number_format($order['total']) ?>" readonly>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
    </main>
</body>

</html>