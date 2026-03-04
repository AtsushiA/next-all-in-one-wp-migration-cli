# Next All-in-One WP Migration CLI

All-in-One WP Migration プラグインを WP-CLI から操作できる拡張プラグインです。

---

## 目次

- [概要](#概要)
- [要件](#要件)
- [インストール](#インストール)
- [WP-CLI コマンド一覧](#wp-cli-コマンド一覧)
  - [export](#export)
  - [import](#import)
  - [restore](#restore)
  - [url-restore](#url-restore)
  - [backup](#backup)
  - [backup list](#backup-list)
  - [backup delete](#backup-delete)
- [アーキテクチャ](#アーキテクチャ)
  - [エクスポートパイプライン](#エクスポートパイプライン)
  - [インポートパイプライン](#インポートパイプライン)
- [ファイル構造](#ファイル構造)
- [実装詳細](#実装詳細)
- [制約事項](#制約事項)

---

## 概要

### 既存コマンドとの関係

| コマンド | 提供元 | 動作 |
|---|---|---|
| `wp ai1wm export` | all-in-one-wp-migration | Unlimited Extension が必要なスタブ |
| `wp ai1wm import` | all-in-one-wp-migration | Unlimited Extension が必要なスタブ |
| `wp ai1wm backup` | all-in-one-wp-migration | Unlimited Extension が必要なスタブ |
| `wp ai1wm-cli export` | 本プラグイン | 実際にエクスポートを実行 |
| `wp ai1wm-cli import` | 本プラグイン | 任意パスの .wpress ファイルをインポート |
| `wp ai1wm-cli restore` | 本プラグイン | バックアップ一覧のファイル名でリストア |
| `wp ai1wm-cli url-restore` | 本プラグイン | リモート URL からダウンロードしてリストア |
| `wp ai1wm-cli backup` | 本プラグイン | バックアップを作成（export の別名） |
| `wp ai1wm-cli backup list` | 本プラグイン | バックアップ一覧を表示 |
| `wp ai1wm-cli backup delete` | 本プラグイン | バックアップファイルを削除 |

---

## 要件

- WordPress 5.0 以上
- PHP 7.4 以上
- WP-CLI 2.0 以上
- **all-in-one-wp-migration プラグイン（v7.x）が有効化されていること**

---

## インストール

1. 本プラグインを `wp-content/plugins/next-all-in-one-wp-migration-cli/` に配置する
2. `all-in-one-wp-migration` プラグインが有効化されていることを確認する
3. WordPress 管理画面またはWP-CLIでプラグインを有効化する

```bash
wp plugin activate next-all-in-one-wp-migration-cli
```

---

## WP-CLI コマンド一覧

### export

サイトのバックアップ（`.wpress` ファイル）を作成する。

```bash
wp ai1wm-cli export [--output=<path>] [--exclude-media] [--exclude-themes] [--exclude-plugins] [--exclude-db]
```

#### オプション

| オプション | 説明 | デフォルト |
|---|---|---|
| `--output=<path>` | 出力先ファイルパス。指定しない場合はバックアップディレクトリに保存 | なし |
| `--exclude-media` | メディアファイルを除外する | false |
| `--exclude-themes` | テーマファイルを除外する | false |
| `--exclude-plugins` | プラグインファイルを除外する | false |
| `--exclude-db` | データベースを除外する | false |

#### 使用例

```bash
# フルバックアップを作成
wp ai1wm-cli export

# バックアップを指定パスに保存
wp ai1wm-cli export --output=/var/backups/site.wpress

# メディアとデータベースを除外してバックアップ
wp ai1wm-cli export --exclude-media --exclude-db

# バックアップ後に外部ストレージへコピー（シェルスクリプト例）
wp ai1wm-cli export --output=/tmp/backup.wpress && scp /tmp/backup.wpress user@server:/backups/
```

---

### import

`.wpress` バックアップファイルからサイトを復元する。ファイルシステム上の任意パスを指定できる。

```bash
wp ai1wm-cli import <file> [--yes]
```

#### 引数

| 引数 | 説明 |
|---|---|
| `<file>` | インポートする `.wpress` ファイルのパス（絶対パスまたは相対パス） |

#### オプション

| オプション | 説明 |
|---|---|
| `--yes` | 確認プロンプトをスキップして即座に実行する |

#### 使用例

```bash
# インポートを実行（確認プロンプトあり）
wp ai1wm-cli import /var/backups/site.wpress

# 確認をスキップして即座にインポート（スクリプト自動化用）
wp ai1wm-cli import /var/backups/site.wpress --yes
```

---

### restore

バックアップディレクトリに保存された `.wpress` ファイルをファイル名だけ指定してリストアする。

`wp ai1wm-cli backup list` で確認したファイル名をそのまま渡せばよく、フルパスの指定は不要。`import` と異なりファイルのコピーを行わないため、大容量バックアップでも効率的。

```bash
wp ai1wm-cli restore <filename> [--yes]
```

#### 引数

| 引数 | 説明 |
|---|---|
| `<filename>` | リストアする `.wpress` のファイル名（`backup list` の出力をそのまま使用可） |

#### オプション

| オプション | 説明 |
|---|---|
| `--yes` | 確認プロンプトをスキップして即座に実行する |

#### 使用例

```bash
# バックアップ一覧でファイル名を確認
wp ai1wm-cli backup list

# ファイル名だけ指定してリストア
wp ai1wm-cli restore mysite-20260301-120000-abc123.wpress

# 確認をスキップして即座にリストア（スクリプト自動化用）
wp ai1wm-cli restore mysite-20260301-120000-abc123.wpress --yes
```

#### `import` との違い

| | `import` | `restore` |
|---|---|---|
| ファイル指定 | フルパス or 相対パス | ファイル名のみ |
| ファイルのコピー | あり（storage へコピー） | なし（バックアップディレクトリから直接読む） |
| 用途 | 外部ファイルの持ち込み | `backup list` で確認したバックアップの復元 |

---

### url-restore

リモート URL から `.wpress` ファイルをダウンロードしてリストアする。ファイルはメモリに展開せずディスクへ直接ストリーミング保存するため、大容量アーカイブにも対応。

```bash
wp ai1wm-cli url-restore <url> [--yes] [--timeout=<seconds>]
```

#### 引数

| 引数 | 説明 |
|---|---|
| `<url>` | ダウンロードする `.wpress` ファイルの HTTP / HTTPS URL |

#### オプション

| オプション | 説明 | デフォルト |
|---|---|---|
| `--yes` | 確認プロンプトをスキップして即座に実行する | false |
| `--timeout=<seconds>` | ダウンロードのタイムアウト秒数 | 300 |

#### 使用例

```bash
# URL を指定してリストア（確認プロンプトあり）
wp ai1wm-cli url-restore https://example.com/backups/site.wpress

# 確認をスキップして即座にリストア
wp ai1wm-cli url-restore https://example.com/backups/site.wpress --yes

# タイムアウトを延ばしてリストア（低速回線・大容量ファイル向け）
wp ai1wm-cli url-restore https://example.com/backups/site.wpress --timeout=600
```

#### 処理フロー

1. URL の HTTP/HTTPS スキームと `.wpress` 拡張子を検証
2. `wp_safe_remote_get()` でストレージディレクトリにストリーミングダウンロード
3. HTTP レスポンスコードとファイルサイズを確認
4. `import` と同じパイプライン（priority 10）でリストア実行

---

### backup

サイトのバックアップ（`.wpress` ファイル）を作成する。`export` と同等の機能。

```bash
wp ai1wm-cli backup [--output=<path>] [--exclude-media] [--exclude-themes] [--exclude-plugins] [--exclude-db]
```

#### オプション

| オプション | 説明 | デフォルト |
|---|---|---|
| `--output=<path>` | 出力先ファイルパス。指定しない場合はバックアップディレクトリに保存 | なし |
| `--exclude-media` | メディアファイルを除外する | false |
| `--exclude-themes` | テーマファイルを除外する | false |
| `--exclude-plugins` | プラグインファイルを除外する | false |
| `--exclude-db` | データベースを除外する | false |

#### 使用例

```bash
# フルバックアップを作成
wp ai1wm-cli backup

# バックアップを指定パスに保存
wp ai1wm-cli backup --output=/var/backups/site.wpress

# メディアとデータベースを除外
wp ai1wm-cli backup --exclude-media --exclude-db
```

---

### backup list

バックアップディレクトリ内のバックアップ一覧を表示する。

```bash
wp ai1wm-cli backup list [--format=<format>]
```

#### オプション

| オプション | 説明 | 選択肢 |
|---|---|---|
| `--format=<format>` | 出力フォーマット | `table`（デフォルト）, `json`, `csv` |

#### 使用例

```bash
# テーブル形式で一覧表示
wp ai1wm-cli backup list

# JSON 形式で出力（スクリプト連携用）
wp ai1wm-cli backup list --format=json
```

#### 出力例

```
+-----------------------------------------------+---------------------+----------+
| filename                                      | created             | size     |
+-----------------------------------------------+---------------------+----------+
| mysite-20260301-120000-abc123.wpress          | 2026-03-01 12:00:00 | 256.4 MB |
| mysite-20260228-090000-def456.wpress          | 2026-02-28 09:00:00 | 251.1 MB |
+-----------------------------------------------+---------------------+----------+
```

---

### backup delete

指定したバックアップファイルを削除する。

```bash
wp ai1wm-cli backup delete <filename> [--yes]
```

#### 引数

| 引数 | 説明 |
|---|---|
| `<filename>` | 削除するバックアップのファイル名（拡張子込み） |

#### オプション

| オプション | 説明 |
|---|---|
| `--yes` | 確認プロンプトをスキップして即座に削除する |

#### 使用例

```bash
wp ai1wm-cli backup delete mysite-20260228-090000-def456.wpress --yes
```

---

## アーキテクチャ

### 概要図

```
WP-CLI コマンド (wp ai1wm-cli)
    │
    ├── export / backup コマンド
    │       └── Ai1wm_Export_Controller::export($params)
    │               └── apply_filters('ai1wm_export') パイプライン
    │                       └── 各 Export_* クラスの execute() を順次実行
    │
    ├── import コマンド
    │       └── ファイルを storage にコピー
    │               └── Ai1wm_Import_Controller::import($params, priority=10)
    │                       └── apply_filters('ai1wm_import') パイプライン
    │
    ├── restore コマンド（backup list のファイル名を指定）
    │       └── ストレージ作業ディレクトリのみ作成（ファイルコピーなし）
    │               └── Ai1wm_Import_Controller::import($params, priority=10, ai1wm_manual_restore=true)
    │                       └── AI1WM_BACKUPS_PATH から直接読み込み
    │
    └── url-restore コマンド（リモート URL を指定）
            └── wp_safe_remote_get() でストレージにストリーミングダウンロード
                    └── Ai1wm_Import_Controller::import($params, priority=10)
                            └── apply_filters('ai1wm_import') パイプライン
```

### WP-CLI 環境での動作

通常、エクスポート・インポートは複数の非同期 HTTP リクエストに分割して実行される（タイムアウト回避のため）。`WP_CLI` 定数が定義されている場合、コントローラーは HTTP リクエストを送らず**同一プロセス内でパイプラインを一括実行**する実装になっている。

```php
// all-in-one-wp-migration の export-controller.php より
if ( defined( 'WP_CLI' ) ) {
    if ( ! defined( 'DOING_CRON' ) ) {
        continue; // HTTP リクエストなしでループを継続
    }
}
```

---

### エクスポートパイプライン

`apply_filters('ai1wm_export')` に登録された処理が優先度順に実行される。

| Priority | クラス | 処理内容 |
|---|---|---|
| 5 | `Ai1wm_Export_Init` | アーカイブ名・ストレージパスの初期化 |
| 10 | `Ai1wm_Export_Compatibility` | バージョン互換性チェック |
| 30 | `Ai1wm_Export_Archive` | `.wpress.tmp` アーカイブファイル作成 |
| 50 | `Ai1wm_Export_Config` | サイト設定のエクスポート |
| 60 | `Ai1wm_Export_Config_File` | `package.json` への設定ファイル書き込み |
| 100 | `Ai1wm_Export_Enumerate_Content` | コンテンツファイルの列挙 |
| 110 | `Ai1wm_Export_Enumerate_Media` | メディアファイルの列挙 |
| 120 | `Ai1wm_Export_Enumerate_Plugins` | プラグインファイルの列挙 |
| 130 | `Ai1wm_Export_Enumerate_Themes` | テーマファイルの列挙 |
| 140 | `Ai1wm_Export_Enumerate_Tables` | データベーステーブルの列挙 |
| 150 | `Ai1wm_Export_Content` | コンテンツのアーカイブ圧縮 |
| 160 | `Ai1wm_Export_Media` | メディアのアーカイブ圧縮 |
| 170 | `Ai1wm_Export_Plugins` | プラグインのアーカイブ圧縮 |
| 180 | `Ai1wm_Export_Themes` | テーマのアーカイブ圧縮 |
| 200 | `Ai1wm_Export_Database` | データベースのエクスポート |
| 220 | `Ai1wm_Export_Database_File` | `database.sql` の書き込み・圧縮 |
| 250 | `Ai1wm_Export_Download` | `.wpress.tmp` → `.wpress` にリネーム |
| 300 | `Ai1wm_Export_Clean` | 一時ファイルのクリーンアップ |

> **Note**: `Export_Download`（Priority 250）は HTTP ダウンロードを送信しない。ファイルを `.wpress.tmp` から `.wpress` にリネームするのみ。

---

### インポートパイプライン

| Priority | クラス | 処理内容 |
|---|---|---|
| 5 | `Ai1wm_Import_Upload` | HTTP ファイルアップロード処理（**CLIではスキップ**）|
| 10 | `Ai1wm_Import_Compatibility` | バージョン互換性チェック |
| 50 | `Ai1wm_Import_Validate` | `.wpress` ファイルの検証 |
| 70 | `Ai1wm_Import_Check_Compression` | 圧縮形式の確認 |
| 75 | `Ai1wm_Import_Check_Encryption` | 暗号化の確認 |
| 100 | `Ai1wm_Import_Confirm` | インポート確認ステップ |
| 150 | `Ai1wm_Import_Blogs` | マルチサイト設定 |
| 170 | `Ai1wm_Import_Permalinks` | パーマリンク設定 |
| 200 | `Ai1wm_Import_Enumerate` | アーカイブ内ファイルの列挙 |
| 250 | `Ai1wm_Import_Content` | コンテンツの展開・配置 |
| 270 | `Ai1wm_Import_Mu_Plugins` | MU プラグインの配置 |
| 295 | `Ai1wm_Import_Database_File` | データベースファイルの準備 |
| 300 | `Ai1wm_Import_Database` | データベースのインポート |
| 310 | `Ai1wm_Import_Users` | ユーザー情報の処理 |
| 330 | `Ai1wm_Import_Options` | WordPress オプション更新 |
| 350 | `Ai1wm_Import_Done` | インポート完了処理 |
| 400 | `Ai1wm_Import_Clean` | 一時ファイルのクリーンアップ |

> **Note**: `Import_Upload`（Priority 5）は `$_FILES` を前提とした HTTP アップロード処理のため、CLI からは実行できない。本プラグインではインポート前にファイルを storage ディレクトリへ手動コピーし、Priority 10 から処理を開始することでこのステップをスキップする。

---

## ファイル構造

```
next-all-in-one-wp-migration-cli/
├── next-all-in-one-wp-migration-cli.php       # メインプラグインファイル
├── README.md                                  # 本ドキュメント
├── .gitignore
└── lib/
    └── command/
        ├── class-ai1wm-cli-command.php        # export / import / restore / url-restore
        └── class-ai1wm-cli-backup-command.php # backup / backup list / backup delete
```

---

## 実装詳細

### エクスポート実装（`export` / `backup`）

```php
$params = [
    'priority'   => 5,
    'secret_key' => get_option( 'ai1wm_secret_key' ),
    'options'    => [
        'no-media'    => false,
        'no-themes'   => false,
        'no-plugins'  => false,
        'no-database' => false,
    ],
];

$result = Ai1wm_Export_Controller::export( $params );
$backup_path = AI1WM_BACKUPS_PATH . '/' . $result['archive'];
```

### インポート実装（`import` / `url-restore`）

外部ファイルまたはダウンロードしたファイルを storage ディレクトリへ配置し、Priority 10 から実行。

```php
$storage = uniqid();
$archive = basename( $file );
copy( $file, AI1WM_STORAGE_PATH . '/' . $storage . '/' . $archive );

$params = [
    'priority'   => 10,
    'secret_key' => get_option( 'ai1wm_secret_key' ),
    'archive'    => $archive,
    'storage'    => $storage,
    'cli_args'   => [ 'yes' => $skip_confirm ],
];

Ai1wm_Import_Controller::import( $params );
```

### リストア実装（`restore`）

`ai1wm_manual_restore=true` によりファイルコピーなしで `AI1WM_BACKUPS_PATH` から直接読み込む。

```php
$storage = uniqid();
wp_mkdir_p( AI1WM_STORAGE_PATH . '/' . $storage );

$params = [
    'priority'             => 10,
    'secret_key'           => get_option( 'ai1wm_secret_key' ),
    'archive'              => $filename,
    'storage'              => $storage,
    'ai1wm_manual_restore' => true,
    'cli_args'             => [ 'yes' => $skip_confirm ],
];

Ai1wm_Import_Controller::import( $params );
```

### URL ダウンロード実装（`url-restore`）

`wp_safe_remote_get()` の `stream` オプションでメモリを使わずディスクへ直接保存。

```php
$response = wp_safe_remote_get( $url, [
    'timeout'  => $timeout,  // デフォルト 300 秒
    'stream'   => true,      // ファイルに直接書き込み（メモリ節約）
    'filename' => $dest_path,
] );
```

---

## 制約事項

| 項目 | 内容 |
|---|---|
| **親プラグイン必須** | `all-in-one-wp-migration` が有効化されていないと動作しない |
| **コマンド名の競合** | `wp ai1wm` は親プラグインが使用するため、本プラグインは `wp ai1wm-cli` を使用 |
| **マルチサイト非対応** | マルチサイト環境での WP-CLI 実行は有料の Multisite Extension が必要 |
| **暗号化バックアップ** | 暗号化された `.wpress` ファイルのインポートは有料拡張機能が必要 |
| **url-restore の URL** | HTTP/HTTPS のみ対応。URL パスに `.wpress` 拡張子が含まれている必要がある |
| **Import_Confirm プロンプト** | `--yes` なしで実行すると `Import_Confirm` ステップでも確認プロンプトが表示される。`cli_args['yes']` を params に渡すことで自動スキップ |
| **backup コマンドのサブコマンド制約** | WP-CLI の制約により `__invoke` とサブコマンドを共存できないため、`backup` コマンド内で手動ルーティングしている |
