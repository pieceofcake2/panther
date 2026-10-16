# 設定

End-to-End テストは Symfony Panther と同じように、`PANTHER_*` 環境変数と `createPantherClient()` の
オプションで設定します。

<a id="phpunitxmldist"></a>

## phpunit.xml.dist

拡張を登録し、`EndToEnd` スイートを追加します
([docs/examples/phpunit.xml.dist](../examples/phpunit.xml.dist))。

```xml
<phpunit bootstrap="./tests/bootstrap.php" defaultTestSuite="App">
    <extensions>
        <extension class="PieceOfCake2\Panther\ServerExtension"/>
    </extensions>
    <listeners>
        <listener class="CakeFixtureInjector" file="./vendor/pieceofcake2/cakephp/src/Cake/TestSuite/Fixture/CakeFixtureInjector.php"/>
    </listeners>
    <testsuites>
        <testsuite name="App">
            <directory>./tests/TestCase/</directory>
            <exclude>./tests/TestCase/EndToEnd/</exclude>
        </testsuite>
        <testsuite name="EndToEnd">
            <directory>./tests/TestCase/EndToEnd/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

pieceofcake2/app のテストは、デフォルトで `APP_ENV=test` で動きます (`APP_ENV` が設定されていなければ、
CakePHP が `.env` ファイルを読み込む前に `tests/bootstrap.php` が設定します)。そのため、テストは `config/.env.test` を読み込み、Web サーバーもそれを引き継いで同じファイルを
読み込みます ([テスト用データベース](test-database.md#the-test-environment))。

このパッケージは Panther と同じように `$_SERVER` から設定を読み込むので、Symfony のドキュメントと同じく
設定は `<server>` 要素で指定します。シェルの変数も `$_SERVER` に入りますが、`<server>` 要素はそれを
置き換えます。`PANTHER_NO_HEADLESS=1` のようなマシンごとの値は、シェルか、`phpunit.xml.dist` を
コピーした `phpunit.xml` に書いてください。

拡張は、実行中の最初の End-to-End テストから最後のテストまで、Web サーバーとブラウザーを動かしておきます。
拡張がない場合は、Panther と同じようにテストクラスごとに停止します。テストが `createPantherClient()` を
呼ぶまで何も起動しないので、`App` スイートの実行ではブラウザーは起動しません。

<a id="local-overrides"></a>

## ローカルでの上書き

マシンごとの値は、シェルか、`phpunit.xml.dist` をコピーした `phpunit.xml` に書きます (PHPUnit は
`phpunit.xml` を優先して読み込みます。このファイルは Git に含めないでください)。

```bash
PANTHER_NO_HEADLESS=1 vendor/bin/phpunit --testsuite EndToEnd     # watch the browser
PANTHER_WEB_SERVER_PORT=9180 vendor/bin/phpunit --testsuite EndToEnd
```

<a id="environment-variables"></a>

## 環境変数

これらは Panther の変数で、意味も Panther と同じです。シェルで設定するか、`phpunit.xml.dist` の
`<server>` で設定します。

| 変数 | 意味 |
| --- | --- |
| `PANTHER_WEB_SERVER_DIR` | ドキュメントルート。デフォルトはプロジェクトの `webroot/` または `app/webroot/`。相対パスは `./` で始める必要があります |
| `PANTHER_WEB_SERVER_PORT` | Web サーバーのポート。デフォルトは `9080` |
| `PANTHER_WEB_SERVER_ROUTER` | ルータースクリプト。ドキュメントルートからの相対パスです。デフォルトは [CakePHP のルータースクリプト](#the-cakephp-router-script)。空にすると使いません |
| `PANTHER_READINESS_PATH` | Web サーバーが起動したことを確認するためにリクエストするパス。デフォルトは `/` |
| `PANTHER_EXTERNAL_BASE_URI` | この URL ですでに動いているアプリケーションをテストします。Web サーバーは起動しません |
| `PANTHER_APP_ENV` | Web サーバーに `APP_ENV` として渡されます。Web サーバーにテストとは異なる `APP_ENV` を渡すときだけ必要です。設定しなければ、Web サーバーはテストの `APP_ENV` を引き継ぎます |
| `PANTHER_ERROR_SCREENSHOT_DIR` | 失敗したテストのスクリーンショットを保存するディレクトリ。例: `./tmp/error-screenshots` |
| `PANTHER_ERROR_SCREENSHOT_ATTACH` | スクリーンショットを JUnit の添付ファイル形式で出力します |
| `PANTHER_NO_HEADLESS` | ブラウザーのウィンドウを表示します |
| `PANTHER_DEVTOOLS` | ブラウザーの DevTools を開きます |
| `PANTHER_NO_REDUCED_MOTION` | アニメーションの軽減 (reduced motion) を要求しません (Panther 2.2 以降) |
| `PANTHER_NO_SANDBOX` | Chrome のサンドボックスを無効にします (コンテナ、CI) |
| `PANTHER_CHROME_ARGUMENTS` | Chrome の追加の引数。スペースで区切ります。例: `--lang=ja --window-size=1600,1200` |
| `PANTHER_CHROME_BINARY` | 使用する Chrome のバイナリ |
| `PANTHER_FIREFOX_ARGUMENTS`、`PANTHER_FIREFOX_BINARY` | Firefox 用の同じ設定 |

このパッケージの `PANTHER_REPORT_*` 変数は、テストの報告書を PDF で書き出します。[テスト報告書](report.md#settings) を参照してください。

<a id="createpantherclient-options"></a>

## createPantherClient() のオプション

`createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = [])` は
Panther のものです。

- `$options`: `webServerDir`、`hostname` (デフォルトは `127.0.0.1`)、`port`、`router`、`readinessPath`、
  `external_base_uri`、`env` (Web サーバーに渡す追加の環境変数)、`browser` (`static::CHROME`、
  `static::FIREFOX`、`static::SELENIUM`)、`browser_arguments`
- `$kernelOptions`: 使われません。Symfony のカーネルはありません
- `$managerOptions`: ブラウザーマネージャーのオプション。たとえば chromedriver の `port` (デフォルトは
  `9515`)、`capabilities`、`chromedriver_arguments`

オプションは、実行中の最初の呼び出しでブラウザーが起動するときに適用されます。それ以降の呼び出しでは
同じブラウザーが返されます。

<a id="the-web-server"></a>

## Web サーバー

`createPantherClient()` は、Panther の `WebServerManager` で PHP のビルトイン Web サーバーを起動します。
上記のオプションと変数に加えて、CakePHP 向けの次のデフォルトが使われます。

<a id="the-cakephp-router-script"></a>

### CakePHP のルータースクリプト

デフォルトのルータースクリプト `vendor/pieceofcake2/panther/resources/router.php` は、次のように動作します。

- ドキュメントルートのファイル (CSS、JavaScript、画像) をそのまま配信します
- それ以外のリクエストはすべて、CakePHP がベースパスの検出に使うスクリプト名とともに、
  ドキュメントルートの `index.php` に渡します。`/articles.json` のようにファイルに見える URL も同様です
- Web サーバーのキャッシュ (`CACHE`) を `tmp/cache/panther/` に置きます。Web サーバーは
  テスト用データベースを `default` として使うので、開発用サーバーのモデルキャッシュを使ってはいけないためです
- `PantherTestCase` が `PANTHER_WORKING_DIR` として Web サーバーに渡す、テストの作業ディレクトリに移動します。
  ビルトイン Web サーバーはドキュメントルートで PHP を実行するので、そのままでは相対パスがテストとは別の
  ファイルを指すためです ([相対パス](test-database.md#relative-paths))

さらにカスタマイズするには、このスクリプトをアプリケーションにコピーし、`PANTHER_WEB_SERVER_ROUTER` に
そのコピーのパスをドキュメントルートからの相対パスで設定します。例: `../tests/router.php`
`PANTHER_WORKING_DIR` は自分のルータースクリプトにも渡されるので、同じようにそこへ移動できます。

```php
$workingDir = getenv('PANTHER_WORKING_DIR');
if (is_string($workingDir) && is_dir($workingDir)) {
    chdir($workingDir);
}
```

Web サーバーを別のディレクトリで実行するには、そのディレクトリを `env` オプションで渡してください。
テストの作業ディレクトリより優先されます: `static::createPantherClient(['env' => ['PANTHER_WORKING_DIR' => $dir]])`

<a id="modern-and-legacy-layouts"></a>

### モダン構成とレガシー構成

ドキュメントルートは、プロジェクトの `webroot/` (pieceofcake2/app) です。`app/webroot/` しかない場合は
そちらを使います (従来の CakePHP 2 のスケルトン)。プロジェクトとは Composer のルートパッケージです。
別のディレクトリを使うには `PANTHER_WEB_SERVER_DIR` を設定してください。
