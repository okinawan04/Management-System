<?php
// db.php でデータベース接続
include 'db.php'; 

// POSTリクエストかどうかを確認
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
    $selectedStore = isset($_POST['selected_store']) ? $_POST['selected_store'] : null;

    if ($delivery_id > 0) {
        try {
            // トランザクションを開始
            $pdo->beginTransaction();

            // 1. 削除対象の納品明細に関連する注文明細IDを取得
            $sql_get_orderdetails = "SELECT yk_orderdetailID FROM deliverydetail WHERE yk_deliverysID = :delivery_id";
            $stmt_get = $pdo->prepare($sql_get_orderdetails);
            $stmt_get->execute([':delivery_id' => $delivery_id]);
            $order_detail_ids = $stmt_get->fetchAll(PDO::FETCH_COLUMN);

            // 2. 取得した注文明細のstateを'NO'に更新
            // これにより、削除された納品書に含まれていた商品が再度納品対象になる
            if (!empty($order_detail_ids)) {
                $placeholders = implode(',', array_fill(0, count($order_detail_ids), '?'));
                $sql_update_orderdetail = "UPDATE orderdetail SET state = 'NO' WHERE orderdetail_ID IN ($placeholders)";
                $stmt_update = $pdo->prepare($sql_update_orderdetail);
                $stmt_update->execute($order_detail_ids);
            }

            // 3. deliverydetail テーブルから関連する明細を削除
            $sql_delete_detail = "DELETE FROM deliverydetail WHERE yk_deliverysID = :delivery_id";
            $stmt_detail = $pdo->prepare($sql_delete_detail);
            $stmt_detail->execute([':delivery_id' => $delivery_id]);

            // 4. deliverys テーブルから納品書を削除
            $sql_delete_delivery = "DELETE FROM deliverys WHERE deliverys_ID = :delivery_id";
            $stmt_delivery = $pdo->prepare($sql_delete_delivery);
            $stmt_delivery->execute([':delivery_id' => $delivery_id]);

            // トランザクションをコミット
            $pdo->commit();

            // 削除成功後、delivery_list.php にリダイレクトし、selected_store を引き継ぐ
            $redirect_url = 'delivery_list.php';
            if ($selectedStore !== null) {
                $redirect_url .= '?selected_store=' . urlencode($selectedStore);
            }
            header("Location: " . $redirect_url);
            exit();

        } catch (PDOException $e) {
            // エラーが発生した場合はロールバック
            $pdo->rollBack();
            // エラーメッセージを表示またはログに記録
            error_log("納品書削除エラー: " . $e->getMessage());
            echo "納品書の削除中にエラーが発生しました。もう一度お試しください。";
            // 開発中は詳細なエラーを表示することもできます
            // echo "エラー: " . $e->getMessage();
        }
    } else {
        echo "無効な納品IDです。";
    }
} else {
    // POSTリクエスト以外で直接アクセスされた場合
    header("Location: delivery_list.php"); // delivery_list.php へリダイレクト
    exit();
}
?>