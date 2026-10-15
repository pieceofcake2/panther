# Panther ドキュメント

[Symfony Panther](https://symfony.com/doc/current/testing/end_to_end.html)、Chrome、PHPUnit 9.6 を使った、
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) アプリケーションのための End-to-End テストです。
Symfony のアプリケーションと同じ方法でセットアップします。

- [テストを書く](writing-tests.md)
- [設定](configuration.md)
- [テスト用データベース](test-database.md)
- [GitHub Actions での CI](ci.md)
- [トラブルシューティング](troubleshooting.md)

<a id="what-it-is"></a>

## 概要

[Panther](https://symfony.com/doc/current/testing/end_to_end.html) は、Symfony の End-to-End テスト用の
ライブラリです。その `PantherTestCase` は、PHP のビルトイン Web サーバーでアプリケーションを起動し、
WebDriver プロトコルを通して本物の Chrome (または Firefox) で操作します。このパッケージは、それと同じことを
CakePHP 2 アプリケーションで行います。

| Panther | このパッケージ |
| --- | --- |
| `Symfony\Component\Panther\PantherTestCase` | `PieceOfCake2\Panther\TestSuite\PantherTestCase`。Panther の `PantherTestCaseTrait` を使う `CakeTestCase` |
| `Symfony\Component\Panther\ServerExtension` | `PieceOfCake2\Panther\ServerExtension`。`PantherTestCase` のための同じ拡張 |
| `WebTestAssertionsTrait` のアサーション | `PieceOfCake2\Panther\TestSuite\WebTestAssertionsTrait`。Symfony の FrameworkBundle なしで使える同じアサーション |
| `PANTHER_WEB_SERVER_DIR` のデフォルトは `public/` | デフォルトは `webroot/` (または `app/webroot/`) |
| `PANTHER_WEB_SERVER_ROUTER` のデフォルトはなし | デフォルトは [CakePHP のルータースクリプト](configuration.md#the-cakephp-router-script) |
| `PANTHER_APP_ENV=test` で Web サーバーが `.env.test` を読み込む | `PANTHER_APP_ENV=test` で `config/database.php` が `test` データソースを使う |

それ以外はすべて Panther そのものです。`Client` (`request()`、`clickLink()`、`submitForm()`、`waitFor()`、
`takeScreenshot()` など)、`createPantherClient()` とそのオプション、そして `PANTHER_*` 環境変数です。
[Symfony のドキュメント](https://symfony.com/doc/current/testing/end_to_end.html) がそのまま当てはまります。

End-to-End テストは、`ControllerTestCase` や `IntegrationTestTrait` を使ったコントローラーのテストより
遅くなります。JavaScript、レイアウト、複数のページにまたがる操作の流れなど、ブラウザーが必要なものに
使ってください。

<a id="requirements"></a>

## 動作要件

- PHP 8.0 以降 (PHP 8.0 では Panther 2.1、PHP 8.1 以降では Panther 2.4)
- pieceofcake2/cakephp 2.12 以降
- PHPUnit 9.6
- Google Chrome または Chromium と、同じメジャーバージョンの chromedriver

<a id="installation"></a>

## インストール

```bash
composer require --dev pieceofcake2/panther dbrekelmans/bdi
vendor/bin/bdi detect drivers
```

`vendor/bin/bdi detect drivers` は、お使いの Chrome に合った chromedriver を `drivers/` にダウンロード
します。Panther は `chromedriver` を `PATH` から探し、次に `drivers/` と `vendor/bin/` から探すので、
パッケージマネージャーでインストールした chromedriver (`brew install chromedriver`、
`apt-get install chromium-chromedriver`) も使えます。`/drivers/` を `.gitignore` に追加してください。

<a id="quick-start"></a>

## クイックスタート

1. `phpunit.xml.dist` で拡張を登録し、Web サーバーのデータソースを設定します
   ([設定](configuration.md#phpunitxmldist))。

   ```xml
   <php>
       <server name="PANTHER_APP_ENV" value="test"/>
       <server name="PANTHER_ERROR_SCREENSHOT_DIR" value="./tmp/error-screenshots"/>
   </php>
   <extensions>
       <extension class="PieceOfCake2\Panther\ServerExtension"/>
   </extensions>
   ```

2. Web サーバーの中では `config/database.php` がそのデータソースを `default` として使うようにします
   ([テスト用データベース](test-database.md#the-databasephp-switch))。
3. `tests/TestCase/EndToEnd/` に、`PieceOfCake2\Panther\TestSuite\PantherTestCase` を継承するテストを
   書きます ([テストを書く](writing-tests.md))。
4. テストを実行します。

   ```bash
   vendor/bin/phpunit                          # the App suite only (defaultTestSuite)
   vendor/bin/phpunit --testsuite EndToEnd     # the End-to-End tests
   vendor/bin/phpunit --testsuite App,EndToEnd # everything
   ```

   Symfony のアプリケーションと同じように `vendor/bin/phpunit` ですべてを実行するには、
   `defaultTestSuite="App"` を削除してください。

pieceofcake2/app には、すでにこの設定が含まれています。

<a id="origin"></a>

## 由来

このパッケージは [pieceofcake2](https://github.com/pieceofcake2) が作成し、メンテナンスしています。
他のプロジェクトのフォークではなく、他のプロジェクトをもとにしたものでもありません。
Composer の依存パッケージとして [Symfony Panther](https://github.com/symfony/panther) の上に構築されており、
pieceofcake2/e2e-test を置き換えるものです。

<a id="license"></a>

## ライセンス

MIT ライセンスです。[LICENSE](../../LICENSE) をご覧ください。
