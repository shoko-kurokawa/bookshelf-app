## BookShelf 書籍レビューアプリ

## 1. 概要

BookShelfは、書籍の登録・管理、レビュー投稿、お気に入り登録などを通じて、読書体験を記録・共有できるWebアプリケーションです。

基本版では、ユーザー認証、書籍・ジャンル管理、レビュー、お気に入り、レビューへのいいね、ランキング、REST APIを実装しました。

応用版では、書籍の検索・絞り込み、Google Books APIを利用したISBN検索、読書計画の管理、リマインダー通知、マイ読書レポート、Laravel SanctumによるAPI認証を追加しました。

Laravelを用いたWebアプリケーション開発を通じて、データベース設計、認証・認可、外部API連携、テストの実装までを一貫して行うことを目的としています。

## 2. ER図

<img src="https://github.com/user-attachments/assets/b2a92821-c59c-423d-96d2-b85a50ca1ead" alt="BookShelf ER図" width="800">

主要テーブル：

- users：ユーザー情報
- books：書籍情報
- genres：ジャンル情報
- book_genre：書籍とジャンルの中間テーブル
- reviews：書籍レビュー
- favorites：お気に入り
- review_likes：レビューへのいいね
- reading_plans：読書計画
- reading_plan_reminder_logs：読書計画のリマインダー履歴
- notifications：アプリ内通知

※一部省略している詳細なリレーション・制約はテーブル定義書を参照

## 3. 環境構築手順

### 前提条件

以下がインストールされていることを前提とします。

- Docker Desktop（Docker Composeを含む）
- Git
- ターミナル環境（Windowsの場合はWSL2などを推奨）

本アプリケーションはLaravel Sailを利用しており、PHP 8.5・MySQL 8.4のDocker環境で動作します。

### ① リポジトリをクローン

```bash
git clone git@github.com:shoko-kurokawa/bookshelf-app.git
cd bookshelf-app
```

### ② 環境変数の設定

`.env.example` をコピーして `.env` を作成します。

```bash
cp .env.example .env
```

Laravel Sailを利用するため、データベース接続先にはDocker Composeのサービス名 `mysql` を指定します。

初期設定では以下の値を使用します。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Google Books APIを利用する場合は、APIキーを `.env` に設定してください。

```dotenv
GOOGLE_BOOKS_API_KEY=取得したAPIキー
```

APIキーや本番環境の認証情報は、GitHubにコミットしないでください。

※Google Books APIの環境変数名は、アプリケーション側の設定と一致させてください。

### ③ PHP依存パッケージをインストール

ローカルにComposerがインストールされている場合：

```bash
composer install
```

Composerがインストールされていない場合は、公式Dockerイメージを使用します。

```bash
docker run --rm \
  -v "$(pwd):/app" \
  -w /app \
  composer:2 \
  sh -c 'git config --global --add safe.directory /app && composer install'
```

※上記はLinux・WSL2などのUnix系シェルを想定したコマンドです。

※Docker版のインストールコマンドは、依存関係の要件確認まで実施済みです。実際の `composer install` の成功は未検証です。

依存パッケージのインストール後、Laravel Sailでコンテナを起動します。

### ④ Dockerコンテナを起動

```bash
./vendor/bin/sail up -d
```

初回起動時は、Dockerイメージのビルドに時間がかかる場合があります。

### ⑤ アプリケーションキーを生成

```bash
./vendor/bin/sail artisan key:generate
```

### ⑥ データベースを構築

```bash
./vendor/bin/sail artisan migrate
```

初期データを登録します。

```bash
./vendor/bin/sail artisan db:seed
```

### ⑦ フロントエンドの依存パッケージをインストール

Laravel Sailの起動後、以下のコマンドを実行します。

```bash
./vendor/bin/sail npm install
```

開発時はViteの開発サーバーを起動します。

```bash
./vendor/bin/sail npm run dev
```

本番向けの静的ファイルを生成する場合は、以下を実行します。

```bash
./vendor/bin/sail npm run build
```

### ⑧ アプリケーションにアクセス

ブラウザから以下にアクセスします。

- アプリケーション：http://localhost
- ログイン：http://localhost/login
- 会員登録：http://localhost/register
- phpMyAdmin：http://localhost:8080

※`.env` でポート番号を変更している場合は、設定したポート番号を使用してください。

### ⑨ コンテナの停止

```bash
./vendor/bin/sail down
```

MySQLのデータはDockerボリュームに保存されるため、通常の `down` では削除されません。

※`down -v` はデータベースのボリュームも削除するため、注意してください。

## 4. Google Books APIの設定

本アプリケーションでは、Google Books APIを利用してISBNから書籍情報を検索する機能を実装しています。

APIの接続先およびAPIキーは、`config/services.php` で管理しています。

### 環境変数

`.env` に以下の設定を記載します。

```dotenv
GOOGLE_BOOKS_API_URL=https://www.googleapis.com/books/v1
GOOGLE_BOOKS_API_KEY=取得したAPIキー
```

| 環境変数               | 内容                        |
| ---------------------- | --------------------------- |
| `GOOGLE_BOOKS_API_URL` | Google Books APIのベースURL |
| `GOOGLE_BOOKS_API_KEY` | Google Books APIのAPIキー   |

`GOOGLE_BOOKS_API_URL` は、未設定の場合でもデフォルトのURLが使用されます。

APIキーを利用する場合は、Google Cloudで発行したキーを `.env` に設定してください。

APIキーなどの秘密情報はGitHubにコミットせず、`.env.example` には実際のキーを記載しないでください。

## 5. API仕様

BookShelfでは、書籍情報を取得・操作するREST APIを提供しています。

APIのベースURL：

```text
http://localhost/api/v1
```

### 書籍API

| HTTPメソッド | エンドポイント         | 内容                             | 認証 |
| ------------ | ---------------------- | -------------------------------- | ---- |
| GET          | `/api/v1/books`        | 書籍一覧取得                     | 不要 |
| GET          | `/api/v1/books/{book}` | 書籍詳細取得                     | 不要 |
| POST         | `/api/v1/books`        | 書籍登録                         | 必要 |
| PUT          | `/api/v1/books/{book}` | 書籍更新                         | 必要 |
| PATCH        | `/api/v1/books/{book}` | 書籍更新（必須項目の送信が必要） | 必要 |
| DELETE       | `/api/v1/books/{book}` | 書籍削除                         | 必要 |

`{book}` には書籍ID（数値）を指定します。

書籍の登録・更新・削除には、Laravel SanctumによるBearerトークン認証が必要です。

書籍の更新・削除は、対象書籍の所有者のみ実行できます。

※現在の実装では、PUT・PATCHともに必須項目をすべて送信する必要があります。PATCHによる一部項目のみの更新には対応していません。

### 書籍登録・更新時の入力項目

`POST /api/v1/books`、`PUT /api/v1/books/{book}`、`PATCH /api/v1/books/{book}` で使用する入力項目は以下のとおりです。

| 項目             | 必須 | 内容                        |
| ---------------- | ---- | --------------------------- |
| `title`          | ○    | 書籍タイトル（最大255文字） |
| `author`         | ○    | 著者名（最大255文字）       |
| `isbn`           | ○    | ISBN（13文字・重複不可）    |
| `published_date` | ○    | 出版日（日付形式）          |
| `description`    | 任意 | 書籍の説明                  |
| `image_url`      | 任意 | 画像URL（最大255文字）      |
| `genres`         | ○    | ジャンルIDの配列（1件以上） |

`genres` には、登録済みジャンルのIDを重複なく指定します。

`user_id` はリクエストで指定できません。認証済みユーザーのIDが自動的に設定されます。また、更新時に所有者を変更することはできません。

**リクエスト例**

```json
{
    "title": "Laravel入門",
    "author": "山田太郎",
    "isbn": "9781234567890",
    "published_date": "2026-11-01",
    "description": "Laravelの基礎を学ぶための書籍です。",
    "image_url": null,
    "genres": [1, 3]
}
```

※ISBNは13文字で検証していますが、現在のバリデーションでは数字のみ・チェックディジットの妥当性までは検証していません。

### 認証API

Laravel SanctumによるBearerトークン認証を使用しています。

| HTTPメソッド | エンドポイント   | 内容                           | 認証 |
| ------------ | ---------------- | ------------------------------ | ---- |
| POST         | `/api/v1/login`  | ログイン・アクセストークン取得 | 不要 |
| POST         | `/api/v1/logout` | ログアウト・現在のトークン失効 | 必要 |
| GET          | `/api/v1/user`   | 認証ユーザー情報取得           | 必要 |

### APIの利用方法

認証が必要なAPIでは、ログイン時に取得したトークンをHTTPヘッダーに指定します。

```http
Authorization: Bearer {access_token}
Accept: application/json
```

### 書籍一覧APIの検索・ページネーション

`GET /api/v1/books` は、検索・絞り込み・ページネーションに対応しています。

| パラメータ | 型      | 内容                                      |
| ---------- | ------- | ----------------------------------------- |
| `keyword`  | string  | 書籍タイトル・著者名の部分一致検索        |
| `genre_id` | integer | ジャンルIDによる絞り込み                  |
| `page`     | integer | ページ番号（1以上）                       |
| `per_page` | integer | 1ページの取得件数（1〜100、デフォルト20） |

**リクエスト例**

```http
GET /api/v1/books?keyword=Laravel&genre_id=3&per_page=10&page=1
```

検索条件は組み合わせて指定できます。

書籍一覧は登録日時の降順で取得し、同一日時の場合は書籍IDの降順で並び替えます。

検索条件が不正な場合は、HTTP 422を返します。

### エラーレスポンス

APIはJSON形式でレスポンスを返します。

主なHTTPステータスコード：

| ステータスコード | 内容                       |
| ---------------- | -------------------------- |
| 401              | 未認証                     |
| 403              | 操作権限なし               |
| 404              | 指定された書籍が存在しない |
| 422              | 入力内容が不正             |

書籍情報の取得は認証不要ですが、登録・更新・削除には認証が必要です。

## 6. テスト

PHPUnitとLaravelのFeature Testを使用して、主要機能の動作を検証しています。

### テストの実行

```bash
./vendor/bin/sail artisan test
```

### カバレッジの確認

Xdebugを有効にして実行します。

```bash
./vendor/bin/sail php -d xdebug.mode=coverage artisan test --coverage
```

### テスト結果

2026年11月9日時点の実行結果：

| 項目                 |  結果 |
| -------------------- | ----: |
| 成功テスト数         | 193件 |
| アサーション数       | 598件 |
| 全体コードカバレッジ | 89.2% |
| 失敗テスト数         |   0件 |

PHP 8.5におけるPDO関連の非推奨警告が2件発生していますが、テストの失敗はありません。

### 主なテスト対象

- ユーザー認証・API認証
- 書籍のCRUD・検索・フィルタ・並び替え
- 書籍の所有者権限
- ジャンル管理
- レビュー・お気に入り・いいね
- Google Books API連携（外部通信のモック）
- 読書計画・期限管理
- リマインダー通知
- マイ読書レポート
- リレーション・バリデーション

## 7. 開発環境URL

| サービス         | URL                       |
| ---------------- | ------------------------- |
| アプリケーション | http://localhost/         |
| ログイン画面     | http://localhost/login    |
| 会員登録画面     | http://localhost/register |
| phpMyAdmin       | http://localhost:8080     |

※Dockerのポート設定を変更している場合は、実際のURLに読み替えてください。

## 8. 開発上の工夫

### 認証・認可

Laravel FortifyによるWeb認証と、Laravel SanctumによるAPI認証を実装しました。

書籍の更新・削除などは、認証ユーザーとリソースの所有者を確認して制御しています。

### データベース設計

書籍とジャンルの多対多リレーション、お気に入りやレビューへのいいねの中間テーブルなどを設計しました。

リレーションを利用することで、関連データを効率的に取得できる構成としています。

### 外部API連携

Google Books APIを利用し、ISBNから書籍情報を検索できるようにしました。

外部APIの正常応答だけでなく、検索結果なし・通信エラーなどのケースもテストしています。

### 読書計画の状態管理

読書中・期限切れ・読了の状態を管理しています。

期限切れの計画を更新する際の状態変更や、同一書籍の読書中計画の重複防止など、状態に応じた処理を実装しました。

### テストによる品質確保

正常系に加えて、入力エラー、認証エラー、権限エラー、外部APIの異常系などを検証しました。

主要な機能についてFeature Testを作成し、コードカバレッジ80％以上を達成しています。

## 9. 使用技術

| 分類           | 技術                                 |
| -------------- | ------------------------------------ |
| バックエンド   | PHP 8.5.12 / Laravel 10              |
| 認証           | Laravel Fortify / Laravel Sanctum    |
| フロントエンド | Blade / Tailwind CSS 3 / Alpine.js 3 |
| ビルドツール   | Vite 5 / Laravel Vite Plugin 1       |
| HTTP通信       | Axios 1                              |
| データベース   | MySQL 8.4                            |
| 開発環境       | Docker / Laravel Sail / phpMyAdmin   |
| テスト         | PHPUnit 10                           |
| 外部API        | Google Books API                     |

### PHPのバージョン要件

`composer.json` では、PHP `^8.1` を指定しています。

開発環境ではPHP 8.5を使用していますが、PHP 8.5では一部の非推奨警告が発生する場合があります。

## 10. 作成者

黒川尚子（Shoko Kurokawa）
