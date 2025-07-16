<?php
// db.php でデータベース接続
include 'db.php';

require 'vendor/autoload.php'; // PhpSpreadsheetのオートロード
use PhpOffice\PhpSpreadsheet\IOFactory;


if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
    $filePath = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // 1行目はヘッダーの場合、2行目から
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        // 例: [顧客ID, 顧客名, 合計購入金額, 平均リードタイム]
        $stmt = $pdo->prepare('INSERT INTO customer (customer_ID, storeName, name, chargeName, address, phoneNo, description, remarks, registration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE storeName = VALUES(storeName), name = VALUES(name), chargeName = VALUES(chargeName), address = VALUES(address), phoneNo = VALUES(phoneNo), description = VALUES(description), remarks = VALUES(remarks), registration = VALUES(registration)');
        $stmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8]]);
    }
    header('Location: customer.php');
    exit;
} else {
    echo "ファイルのアップロードに失敗しました";
}