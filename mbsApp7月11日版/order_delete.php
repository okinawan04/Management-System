<?php
// db.php でデータベース接続
include 'db.php'; 

// POSTリクエストかどうかを確認
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $selectedStore = isset($_POST['selected_store']) ? $_POST['selected_store'] : null;

    if ($order_id > 0) {
        try {
            // トランザクションを開始（エラー発生時に両方の削除を取り消すため）
            $pdo->beginTransaction();

            // 1. orderdetail テーブルから関連する明細を削除
            $sql_delete_detail = "DELETE FROM orderdetail WHERE yk_ordersID = :order_id";
            $stmt_detail = $pdo->prepare($sql_delete_detail);
            $stmt_detail->execute([':order_id' => $order_id]);

            // 2. orders テーブルから注文を削除
            $sql_delete_order = "DELETE FROM orders WHERE orders_ID = :order_id";
            $stmt_order = $pdo->prepare($sql_delete_order);
            $stmt_order->execute([':order_id' => $order_id]);

            // トランザクションをコミット
            $pdo->commit();

            // 削除成功後、order_list.php にリダイレクト
            // selected_store を引き継ぐ
            $redirect_url = 'order_list.php';
            if ($selectedStore !== null) {
                $redirect_url .= '?selected_store=' . urlencode($selectedStore);
            }
            header("Location: " . $redirect_url);
            exit();

        } catch (PDOException $e) {
            // エラーが発生した場合はロールバック
            $pdo->rollBack();
            // エラーメッセージを表示またはログに記録
            error_log("注文削除エラー: " . $e->getMessage());
            echo "注文の削除中にエラーが発生しました。もう一度お試しください。";
            // 開発中は詳細なエラーを表示することもできます
            // echo "エラー: " . $e->getMessage();
        }
    } else {
        echo "無効な注文IDです。";
    }
} else {
    // POSTリクエスト以外で直接アクセスされた場合
    header("Location: order_list.php"); // order_list.php へリダイレクト
    exit();
}
?>