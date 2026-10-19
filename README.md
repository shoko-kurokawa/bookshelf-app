## BookShelf 書籍レビューアプリ

## 概要

書籍レビューアプリを開発しました。
基本機能として、認証、書籍CRUD、レビュー、お気に入り、いいね、ジャンル管理、ランキング機能、公開API（認証なしのCRUD）を実装しました。
応用機能として、検索・フィルタ、ISBN検索（Google Books API連携）、マイ読書レポート、公開APIへのSantum認証追加を実装しました。

## ER図

（作成後リンク添付）

## 環境構築手順

（実装完了後追記）

## 使用技術

- PHP 8.5
- Laravel 10.x
- MySQL 8.4
- Laravel Sail
- Laravel Fortify
- Laravel Sanctum
- Vite
- Tailwind CSS^3.4.0 @tailwindcss/forms
- phpMyAdmin
- Docker

## 作成者

黒川尚子（Shoko Kurokawa）

## APIエンドポイント一覧

（実装完了後追記）
APIとして書籍情報の取得・登録・更新・削除を提供しています。

```text
GET /api/
POST /api/
PUT /api/
PATCH /api/
DELETE /api/
```

GETは認証不要です。
POST、PUT、PATCH、DELETEにはLaravel Sanctumによる認証が必要です。

## 開発環境URL

（実装完了後追記）

- アプリケーション：http://localhost/
- ログイン画面：
- 会員登録画面：
- phpMyAdmin: http://localhost:8080
