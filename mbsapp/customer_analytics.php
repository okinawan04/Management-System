<?php
// db.php でデータベース接続
require_once 'db.php';

// 顧客詳細情報を格納する変数
$customerDetails = null; 

// 顧客の注文履歴を格納する変数
$customerOrders = []; 

// エラーメッセージ
$errorMessage = '';      

 

if (isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} elseif (isset($_GET['selected_store'])) {
    $selectedStore = $_GET['selected_store'];
}

// URLにcustomer_idが渡されているかチェック
if (isset($_GET['customer_id']) && !empty($_GET['customer_id'])) {
    $customer_id = htmlspecialchars($_GET['customer_id'], ENT_QUOTES, 'UTF-8');

    try {
        

        // --- 顧客詳細情報の取得 ---
        // 'customer' テーブルから customer_ID に基づいて情報を取得
        
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE customer_ID = ?");
        $stmt->execute([$customer_id]);
        $customerDetails = $stmt->fetch(PDO::FETCH_ASSOC); // 連想配列として結果を取得

        if ($customerDetails) {
            // --- 顧客の注文履歴の取得 ---
            // ordersテーブルとdeliverysテーブルをorder_IDで結合します
            $stmt = $pdo->prepare("
                SELECT
                    o.orders_ID,
                    d.total,
                    o.orderday,
                    d.deliveryday,
                    COALESCE(DATEDIFF(d.deliveryday, o.orderday), 0) AS lead_time
                FROM
                    orders AS o
                INNER JOIN
                    deliverys AS d ON o.orders_ID = d.yk_ordersID
                WHERE
                    o.yk_customerID = ?
                ORDER BY
                    o.orderday DESC;
            ");
            $stmt->execute([$customer_id]);
            $customerOrders = $stmt->fetchAll(PDO::FETCH_ASSOC); // すべての注文履歴を取得
        } else {
            $errorMessage = '指定された顧客が見つかりませんでした。';
        }

    } catch (PDOException $e) {
        // データベース接続やクエリ実行中のエラーをログに記録
        error_log("Database error on customer_analytics.php: " . $e->getMessage());
        $errorMessage = 'データの取得中にエラーが発生しました。システム管理者に連絡してください。';
    }
} else {
    $errorMessage = '表示する顧客が指定されていません。顧客一覧に戻って選択してください。';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8" />
    <title>統計情報分析画面</title>
    <link rel="stylesheet" href="./仮画面/top/customer/analytics.css" />
    <!-- Chart.jsライブラリを読み込む -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <div class="logo">
                    <?= htmlspecialchars($selectedStore ?: '緑橋書店') ?>
                </div>
                <div class="subtitle">統計情報分析</div>
            </div>
            <div class="header-buttons">
                <form action="customer.php" method="POST" style="display:inline;">
                    <input type="hidden" name="selected_store" value="<?= htmlspecialchars($selectedStore) ?>">
                    <button class="header-btn" type="submit">戻る</button>
                </form>
            </div>
        </header>

        <main>
            <?php if ($errorMessage): // エラーメッセージがある場合、それを表示 ?>
                <p style="color: red; text-align: center;"><?= htmlspecialchars($errorMessage); ?></p>
            <?php elseif ($customerDetails): // 顧客情報が正常に取得できた場合、詳細を表示 ?>
                <section class="info">
                    <div class="table-container">
                        <table class="customer-info">
                            <tr>
                                <th>顧客名</th>
                                <td><?= htmlspecialchars($customerDetails['name'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>担当者名</th>
                                <td><?= htmlspecialchars($customerDetails['chargeName'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>住所</th>
                                <td><?= htmlspecialchars($customerDetails['address'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>電話番号</th>
                                <td><?= htmlspecialchars($customerDetails['phoneNo'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="table-container">
                        <table class="delivery-info">
                            <tr>
                                <th>配達先条件</th>
                                <td><?= htmlspecialchars($customerDetails['description'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>備考</th>
                                <td><?= htmlspecialchars($customerDetails['remarks'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <tr>
                                <th>登録日</th>
                                <td><?= htmlspecialchars($customerDetails['registration'] ?? '記載なし', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        </table>
                    </div>
                </section>

                <section class="content">
                    <div class="graph">
                        <h2>発注別リードタイムと売上</h2>
                        <!-- グラフを描画するためのcanvas要素を設置 -->
                        <canvas id="analyticsChart"></canvas>
                    </div>

                    <div class="history">
                        <h2>履歴</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>注文書番号</th>
                                    <th>合計金額 （円）</th>
                                    <th>注文日</th>
                                    <th>届け日</th>
                                    <th>リードタイム</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($customerOrders)): // 注文履歴がある場合、一覧表示 ?>
                                    <?php foreach ($customerOrders as $order): $currencyMark = '￥'; ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['orders_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?=  $currencyMark . number_format($order['total'] ?? 0); ?></td>
                                            <td><?= htmlspecialchars($order['orderday'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($order['deliveryday'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($order['lead_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>日間</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: // 注文履歴がない場合 ?>
                                    <tr>
                                        <td colspan="5">この顧客の注文履歴はありません。</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- グラフを描画するためのJavaScriptコード -->
    <script>
        // PHPから注文データをJSON形式で受け取ります
        // array_reverse()でグラフのX軸を時系列（古い順）にするため、配列を逆順に
        const ordersData = <?= json_encode(array_reverse($customerOrders)); ?>;

        // Chart.jsでグラフを描画
        //注文データが正常に取得できていて、かつordersDataに値があるかの条件判定
        if (ordersData && ordersData.length > 0) {
            const ctx = document.getElementById('analyticsChart').getContext('2d');

            // グラフ用のデータを作成

            //目盛り（注文D）の作成
            const labels = ordersData.map(order => `注文ID: ${order.orders_ID}`);

            //リードタイムの抽出
            const leadTimeData = ordersData.map(order => order.lead_time);

            //税込み合計金額のデータの抽出
            const totalAmountData = ordersData.map(order => order.total);

            //グラフインスタンスの生成
            new Chart(ctx, {
                type: 'bar', // 基本のグラフタイプをbar(棒グラフ)に設定
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar', // このデータセットは棒グラフです
                            label: 'リードタイム (日)',
                            data: leadTimeData,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)', // 青色
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y', // 左のY軸を使用します
                        },
                        {
                            type: 'line', // このデータセットは折れ線グラフです
                            label: '税合計金額 (円)',
                            data: totalAmountData,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)', // 赤色
                            borderColor: 'rgba(255, 99, 132, 1)',
                            tension: 0.1, // 線の滑らかさ
                            fill: false, // 線の下を塗りつぶしません
                            yAxisID: 'y1', // 右のY軸を使用します
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 90,
                                minRotation: 45,
                                autoSkip: true,
                            }
                        },
                        y: { // 左のY軸 (リードタイム用)
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'リードタイム (日)' }
                        },
                        y1: { // 右のY軸 (金額用)
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: '税合計金額 (円)' },
                            grid: { drawOnChartArea: false },
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>