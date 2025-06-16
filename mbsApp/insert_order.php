<?php
    include 'db.php';
       
    
    $customer_id= $_POST['customer_id'];
    
    $total = $_POST['total'];

    $customer_name = $_POST['new_customer_name'] ?? '';
    $customer_address = $_POST['new_customer_address'] ?? '';
    $customer_phoneNo = $_POST['new_customer_phoneNo'] ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['customer_id'])) {
        $customer_id= $_POST['customer_id'];
    } else {
        $customer_id= null;
    }

    if ($customer_id === 'new') {
        $name = trim(string: $_POST['new_customer_name'] ?? '');
        $address = trim(string: $_POST['new_customer_address'] ?? '');
        $phoneNo = trim(string: $_POST['new_customer_phoneNo'] ?? '');
    

    
    if ($name === '' || $address === '' || $phoneNo === '') 
            die('新規顧客情報（名前・住所・電話番号）は必須です。');
    

    // 新規顧客登録','address', phoneNo) VALUES (?,?,?)");
        $stmt = $pdo->prepare("INSERT INTO customer (name, address, phoneNo) VALUES (?, ?, ?)");
        $stmt->execute(params: [$name, $address, $phoneNo]);
        $customer_id= $pdo->lastInsertId();

    } elseif (is_string($customer_id) && ctype_digit($customer_id)) {
        // 正常な既存顧客ID
        // OK
    } else {
        die('顧客IDが不正です。');
    }

    echo "顧客ID: " . htmlspecialchars($customer_id	) . " で注文処理を続行します。";
}

    // 注文作成
    $stmt = $pdo->prepare("INSERT INTO orders (state,total,yk_customerID) VALUES ('',:total, :customer_id)");

    $stmt->execute([
        ':total'=>$total,
        ':customer_id'=>$customer_id]); //sqlの実行

    $orderId = $pdo->lastInsertId();

    // 注文明細をループして登録
    $titles = $_POST['title'];
    $quantities = $_POST['quantity'];
    $values = $_POST['value'];
    $descriptions = $_POST['description'];

    $stmt = $pdo->prepare("INSERT INTO orderdetail (title, quantity, value,description, yk_ordersID) VALUES (:title, :quantity, :value,:description, :yk_orderid)");

    for ($i = 0; $i < count($titles); $i++) {
        $stmt->execute([
            ':title' => $titles[$i],
            ':quantity' => $quantities[$i],
            ':value' => $values[$i],
            ':description' => $descriptions[$i],
            ':yk_orderid' => $orderId
        ]);
    }
    header("Location: order.php?success=1");
    exit;

?>