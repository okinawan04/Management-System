<?php
// index.php
include 'get_customer.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>顧客別 購入金額とリードタイム</title>
    <style>
        table {
            width: 80%;
            margin: 20px auto;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<h1 style="text-align: center;">顧客別 購入金額とリードタイム</h1>

<table>
    <thead>
        <tr>
            <th>顧客名</th>
            <th>合計購入金額 (円)</th>
            <th>平均リードタイム (日)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($customers as $customer): ?>
            <tr>
                <td><?php echo htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo number_format($customer['total_amount']); ?></td>
                <td><?php echo number_format($customer['avg_lead_time'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
