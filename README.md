# 📚 受注・納品/顧客情報管理システム

このシステムは、本屋における受注・納品状況を管理し、顧客への到着までのリードタイムを可視化するためのWebアプリケーションです。

## 🧑‍💻 開発チーム
- 本プロジェクトは6人一組のチームで開発しています。

---

## 主に使うgitコマンド
- gitpull (ローカルのmainブランチを最新に)
- git merge origin/main(最新のmainブランチを自分のブランチに適用(自分のブランチで入力するよう気を付ける))
- git branch(新しくブランチをつくる)

## 🚀 主な機能

- 受注情報の登録・編集・削除
- 納品状況の更新・追跡
- 顧客へのリードタイムの自動計算と表示
- 商品別や顧客別のレポート出力

---

## 🛠️ 使用技術

- **フロントエンド**：HTML / CSS / JavaScript
- **バックエンド**：PHP（バージョン7.4以上推奨）
- **データベース**：MariaDB
- **Webサーバー**：Apache（XAMPP環境推奨）

---

## ⚙️ セットアップ手順

### ✅ 前提条件

- PHP（7.4以上）
- MariaDB（10.3以上）
- Apache Web Server（または XAMPP / MAMP / Laragon などの統合環境）

---

### 1. vscodeのおすすめ拡張機能

- Git Graph (Githubのブランチを可視化してくれる)
- Git Coplilot (vscode上で使えるコード生成AI、インストールと同時にGit Copilot Chatも入る)
- Live Server (右クリック→Open with Live Serverでコードの動きを確認できる)

### 2. リポジトリをクローン

```bash
git clone https://github.com/your-team/bookstore-system.git
cd bookstore-system
