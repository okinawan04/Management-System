<?php

$selectedStore = ''; // デフォルト値として空文字列で初期化

// POSTリクエストで 'selected_store' が送信された場合のみ処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_store'])) {
    $selectedStore = $_POST['selected_store'];
} else {
    
}

?>