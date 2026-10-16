# トラブルシューティング

**`"chromedriver" binary not found`** — `vendor/bin/bdi detect drivers` を実行するか、
パッケージマネージャーで chromedriver をインストールしてください。

**`session not created: This version of ChromeDriver only supports Chrome version ...`** — Chrome が
更新されました。`vendor/bin/bdi detect drivers` をもう一度実行してください。

**`The port 9080 is already in use`** — ほかの Web サーバーか、停止しなかった以前の実行がそのポートを
使っています。それを停止するか、`PANTHER_WEB_SERVER_PORT` を設定してください。chromedriver (`9515`)
の場合は、`$managerOptions` で別の `port` を渡してください。

**ブラウザーに `Missing Database Table` と表示される** — Web サーバーがテスト用データソースを
使っていません。テストが `APP_ENV=test` で動いていること (環境変数にほかの `APP_ENV` が無いこと)、`config/.env.test`
(または実際の環境変数) の `DATABASE_URL` がテスト用データベースであること、テスト用データベースがインメモリの
SQLite データベースでないことを確認してください。

**ブラウザーとテストが別のファイルを使う (たとえば 2 つの SQLite データベース)** — 相対パスは作業ディレクトリから
解決されます。CakePHP のルータースクリプトは、テストの作業ディレクトリで Web サーバーを実行します
([相対パス](test-database.md#relative-paths))。自分のルータースクリプトでも `PANTHER_WORKING_DIR` に移動する
必要があり、ルータースクリプトがない場合 (`PANTHER_WEB_SERVER_ROUTER` が空) は、Web サーバーはドキュメントルートで
動きます。PHPUnit はプロジェクトルートから実行するか、絶対パスを使ってください。

**変数が Web サーバーに渡らない** — シェルで設定するか、`$_ENV` または `$_SERVER` に設定してください。
`putenv()` だけで設定した変数は渡されません。

**Web サーバーが、環境変数ではなく `.env` の値になる** — Web サーバーの環境変数は、`$_ENV` から先に、次に
`getenv()` から作られます。テストが `.env` ファイルを `$_ENV` に読み込み (たとえば josegonzalez/dotenv の `toEnv()`)、
PHP 自身が `$_ENV` を埋めない場合 (GitHub のランナーのように `variables_order` に `E` が無い場合)、CI のテスト用 DB の
URL のようにシェルで別の値を設定していても、Web サーバーには `.env` の値が渡ります。このような値は `env` オプションで渡して
ください: `static::createPantherClient(['env' => ['DATABASE_URL' => (string)env('DATABASE_URL')]])`。

**スクリーンショットの日本語が四角 (豆腐) で表示される** — GitHub のランナーのように、日本語 (CJK) のフォントが
入っていません。たとえば `sudo apt-get install -y fonts-noto-cjk` で入れてください ([CI](ci.md) をご覧ください)。

**ページに CSS が適用されない** — ファイルがドキュメントルートにないか、`PANTHER_WEB_SERVER_DIR` が
間違ったディレクトリを指しています。

**Web サーバーのログ** — CakePHP は通常の `LOGS` ディレクトリにログを書き込みます。
ビルトイン Web サーバー自体のリクエストと PHP のエラーは表示されません。それらを見るには、
`PANTHER_WORKING_DIR=$PWD php -S 127.0.0.1:9080 -t webroot vendor/pieceofcake2/panther/resources/router.php`
で手動で起動してください。
