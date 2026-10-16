# テスト用データベース

<a id="the-test-environment"></a>

## テスト環境

Web サーバーは別の PHP プロセスです。フィクスチャを読み込むには、Web サーバーの `default` 接続が、
フィクスチャが読み込まれるデータベース (`test` データソースのデータベース) でなければなりません。
Symfony と同じように、これは `test` 環境で決まります。pieceofcake2/app のテスト (`vendor/bin/phpunit` と
`bin/cake test`) は、デフォルトで `APP_ENV=test` で動きます。`APP_ENV` が設定されていなければ、CakePHP が
`.env` ファイルを読み込む前に `tests/bootstrap.php` がこれを設定します。

```php
if ((string)getenv('APP_ENV') === '' && empty($_SERVER['APP_ENV'])) {
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
    $_SERVER['APP_ENV'] = 'test';
}
```

テストは `APP_ENV=test` で実行され、Panther が起動する Web サーバーはテストのプロセスの環境を引き継ぐので、
Web サーバーも `APP_ENV=test` で動きます。pieceofcake2/app は、両方のプロセスで `config/.env.test` (Symfony の
`.env.{APP_ENV}` ファイル) を読み込みます。このファイルはコミットされていて、`DATABASE_URL` をテスト用
データベースに設定します。Symfony と同じように変数は 1 つで、`DATABASE_URL` は現在の環境のデータベースです。

```dotenv
# config/.env.test
DATABASE_URL="mysql://user:password@localhost:3306/test_database_name?encoding=utf8mb4"
```

`config/database.php` は `DATABASE_URL` を両方の接続に渡すので、Web サーバーの `default` 接続は
フィクスチャのデータベースになります。

```php
public function __construct()
{
    $this->default['dsn'] = env('DATABASE_URL');
    $this->test['dsn'] = env('DATABASE_URL');
}
```

自分のテスト用データベースは、`config/.env.test.local` (Git では無視されます) か、`config/.env.test` より
優先される実際の環境変数で設定してください (CI はこの方法で `DATABASE_URL` を設定します)。環境変数は
ファイルより優先されるので、テストを実行するシェルで開発用の `APP_ENV` や `DATABASE_URL` を export しないで
ください。テスト用データベースは、両方のプロセスから開けるデータベースで
なければなりません。つまり、データベースサーバーか SQLite のファイルで、`:memory:` は使えません。`'dsn'`
キーには pieceofcake2/cakephp 2.12.7 以降が必要です。

`PANTHER_APP_ENV` が必要になるのは、Web サーバーにテストとは異なる `APP_ENV` を渡すときだけです。Panther は
その値を `APP_ENV` として Web サーバーに渡します。

以前のスケルトンから作成したアプリケーションでは、pieceofcake2/app の `config/core.php` の先頭にある `.env`
の読み込み、`tests/bootstrap.php` と `bin/cake` の `APP_ENV` のデフォルト、`config/.env.test`、上の
コンストラクターをコピーしてください。

<a id="fixtures-shared-with-the-web-server"></a>

## Web サーバーと共有するフィクスチャ

`PantherTestCase` は `CakeTestCase` です。`$fixtures` は各テストの前にテスト用データソースに読み込まれ、
Web サーバーの `default` 接続は同じデータベースなので、Web サーバーはそれを読み込みます。ブラウザーで
保存したデータもテスト用データベースに入るので、テストではモデルを使ってそれを確認できます。

Web サーバーは、テストのプロセスの環境変数を引き継ぎます。シェルの環境変数と、`$_ENV` または
`$_SERVER` にある変数です (Symfony Process は `putenv()` だけで設定された変数を渡しません)。実際の環境変数は
この方法で Web サーバーに渡され、Web サーバーでも `config/.env.test` より優先されます。たとえば
`DATABASE_URL` を `mysql://root:root@127.0.0.1:3306/test_app` に設定するか、両方の
プロセスが開く SQLite ファイルなら `sqlite:///tmp/tests/end_to_end.sqlite` (プロジェクトルートからの相対パス)
に設定します。
