<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>トップ</title>
    <link rel="stylesheet" href="./仮画面//top/style.css">
</head>
<body>
    <form id="mainForm" method="post" action="">
        <header>
            <div class="logo" id="store-logo">緑橋書店</div>
            <div class="control-panel">
                <label>
                    店舗選択
                    <select id="store-selector" name="selected_store">
                        <option value="緑橋本店">緑橋本店</option>
                        <option value="深江橋店">深江橋店</option>
                        <option value="今里店">今里店</option>
                    </select>
                </label>
            </div>
        </header>

        <main class="triangle-layout">
            <button type="submit" name="target_page" value="customer.php" class="main-btn customer">顧客情報一覧</button>
            <button type="submit" name="target_page" value="order_list.php" class="main-btn order">注文書一覧</button>
            <button type="submit" name="target_page" value="delivery_list.php" class="main-btn delivery">納品書一覧</button>
        </main>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const selector = document.getElementById('store-selector');
            const logo = document.getElementById('store-logo');
            const mainForm = document.getElementById('mainForm');
            const buttons = mainForm.querySelectorAll('button[type="submit"]');

            // ページロード時にセレクトボックスの初期値に応じてロゴを更新
            logo.textContent = selector.value;

            // 店舗選択時のロゴ更新処理
            selector.addEventListener('change', () => {
                logo.textContent = selector.value;
            });

            // 各画面遷移ボタンがクリックされた時の処理
            buttons.forEach(button => {
                button.addEventListener('click', (event) => {
                    // クリックされたボタンのvalue属性（遷移先のURL）をフォームのactionに設定
                    mainForm.action = event.target.value;
                    // submitボタンのデフォルト動作でフォームが送信されます
                });
            });
        });
    </script>
</body>
</html>