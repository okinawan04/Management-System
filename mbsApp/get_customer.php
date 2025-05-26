<?php
// fetch_data.php
include 'db.php';

$sql = "
    SELECT
        c.customer_ID,
        c.name AS customer_name,
        SUM(oi.quantity * oi.value) AS total_amount,
        AVG(DATEDIFF(d.deliveryday, o.orderday)) AS avg_lead_time
    FROM
        customer c
    JOIN
        orders o ON c.customer_ID = o.yk_customerID
    JOIN
        orderdetail oi ON o.orders_ID = oi.yk_ordersID
    LEFT JOIN
        deliverys d ON o.orders_ID = d.yk_ordersID
    GROUP BY
        c.customer_ID
    ORDER BY
        total_amount DESC
";

$stmt = $pdo->query($sql);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
