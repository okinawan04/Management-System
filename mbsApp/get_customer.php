<?php
// fetch_data.php
include 'db.php';

$sql = "
    SELECT
        c.customer_ID,
        c.name AS customer_name,
        COALESCE(SUM(dd.quantity * od.value), 0) AS total_amount,
        AVG(DATEDIFF(d.deliveryday, o.orderday)) AS avg_lead_time
    FROM
        customer c
    LEFT JOIN
        orders o ON c.customer_ID = o.yk_customerID
    LEFT JOIN
        deliverys d ON o.orders_ID = d.yk_ordersID
    LEFT JOIN
        deliverydetail dd ON d.deliverys_ID = dd.yk_deliverysID
    LEFT JOIN
        orderdetail od ON dd.yk_orderdetailID = od.orderdetail_ID
    GROUP BY
        c.customer_ID
    ORDER BY
        total_amount DESC
";

$stmt = $pdo->query($sql);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
