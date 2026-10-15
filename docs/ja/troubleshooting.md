# トラブルシューティング

**`"chromedriver" binary not found`** — `vendor/bin/bdi detect drivers` を実行するか、
パッケージマネージャーで chromedriver をインストールしてください。

**`session not created: This version of ChromeDriver only supports Chrome version ...`** — Chrome が
更新されました。`vendor/bin/bdi detect drivers` をもう一度実行してください。

**`The port 9080 is already in use`** — ほかの Web サーバーか、停止しなかった以前の実行がそのポートを
使っています。それを停止するか、`PANTHER_WEB_SERVER_PORT` を設定してください。chromedriver (`9515`)
の場合は、`$managerOptions` で別の `port` を渡してください。

**ブラウザーに `Missing Database Table` と表示される** — Web サーバーがテスト用データソースを
使っていません。`phpunit.xml.dist` の `PANTHER_APP_ENV` と `config/database.php` の `APP_ENV` の行を確認し、
テスト用データソースがインメモリの SQLite データベースでないことを確認してください。

**変数が Web サーバーに渡らない** — シェルで設定するか、`$_ENV` または `$_SERVER` に設定してください。
`putenv()` だけで設定した変数は渡されません。

**ページに CSS が適用されない** — ファイルがドキュメントルートにないか、`PANTHER_WEB_SERVER_DIR` が
間違ったディレクトリを指しています。

**Web サーバーのログ** — CakePHP は通常の `LOGS` ディレクトリにログを書き込みます。
ビルトイン Web サーバー自体のリクエストと PHP のエラーは表示されません。それらを見るには、
`php -S 127.0.0.1:9080 -t webroot vendor/pieceofcake2/panther/resources/router.php`
で手動で起動してください。
