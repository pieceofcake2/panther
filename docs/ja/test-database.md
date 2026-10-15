# テスト用データベース

<a id="the-databasephp-switch"></a>

## database.php での切り替え

Web サーバーは別の PHP プロセスです。フィクスチャを読み込むには、フィクスチャが読み込まれるデータソース
`test` を使う必要があります。Symfony と同じように `PANTHER_APP_ENV=test` を設定すると、Panther は
Web サーバーに環境変数 `APP_ENV=test` を渡します。`APP_ENV` が `test` のときは、`DATABASE_CONFIG` の
コンストラクターで `test` データソースを `default` として使います。

```php
public function __construct()
{
    if (env('APP_ENV') === 'test') {
        $this->default = $this->test;
    }
}
```

pieceofcake2/app の `config/database.php.default` にはこの行があります。テスト用のデータソースは、
両方のプロセスから開けるデータベースでなければなりません。つまり、データベースサーバーか SQLite の
ファイルで、`:memory:` は使えません。

<a id="fixtures-shared-with-the-web-server"></a>

## Web サーバーと共有するフィクスチャ

`PantherTestCase` は `CakeTestCase` です。`$fixtures` は各テストの前にテスト用データソースに読み込まれ、
`APP_ENV` が `test` なので Web サーバーはそれを読み込みます。ブラウザーで保存したデータも
テスト用データソースに入るので、テストではモデルを使ってそれを確認できます。

Web サーバーは、テストのプロセスの環境変数を引き継ぎます。シェルの環境変数と、`$_ENV` または
`$_SERVER` にある変数です (Symfony Process は `putenv()` だけで設定された変数を渡しません)。
pieceofcake2/app の `DB_TEST_*` 変数は、この方法で Web サーバーに渡されます。
