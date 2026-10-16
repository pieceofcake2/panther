# テスト報告書

`PANTHER_REPORT_PDF` を設定すると、拡張が最後のテストの後に End-to-End テストの報告書を PDF で書き出します。
成功・失敗を問わずすべてのテストの結果を、ページのスクリーンショットとあわせて載せます。リリースのときの
テスト報告書のように、残したり渡したりするための文書です。

```bash
PANTHER_REPORT_PDF=tmp/end-to-end-report.pdf vendor/bin/phpunit --testsuite EndToEnd
```

報告書は A4 で、すべてのページにヘッダーとページ番号が付きます。

1. **表紙**: 判定 (失敗したテストが無ければ合格)、テスト・成功・失敗・エラー・スキップの件数、リポジトリ・
   ブランチ・コミット、実行日時・所要時間・実行環境・ブラウザ・ソフトウェア
2. **テスト一覧**: クラスごとのテストと、その結果・時間。各テストのページにリンクします
3. **各テストのページ**: テスト名、結果、PHPUnit でのテストの名前、時間、doc コメントの説明、失敗の内容、
   スクリーンショット

報告書に載るのは `PantherTestCase` のテストで、PHPUnit が数えるものと同じです。テストの中で実行した
テストは載りません。

<a id="requirements"></a>

## 必要なもの

- 拡張 `PieceOfCake2\Panther\ServerExtension` ([設定](configuration.md#phpunitxmldist))
- PDF を作るための Chrome と chromedriver (テストと同じもの)。報告書は、テストのブラウザが何であっても、
  別に起動したヘッドレスの Chrome で作ります
- ヘッダーとページ番号には Chrome 131 以降 (CSS のページのマージンボックス)
- ページと報告書の言語のフォント。Linux で日本語を使うなら `fonts-noto-cjk` ([CI](ci.md))

<a id="titles"></a>

## テスト名

報告書は、PHPUnit の `--testdox` と同じく、doc コメントからテストの名前を決めます。

| | 名前 |
| --- | --- |
| クラス | クラスの `@testdox`、クラスの doc コメントの要約、またはクラス名を文にしたもの |
| テスト | メソッドの `@testdox`、doc コメントの要約、またはメソッド名を文にしたもの |
| 説明 | メソッドの doc コメントの残り (タグより前) |

```php
/**
 * お問い合わせフォーム
 */
class ContactsTest extends PantherTestCase
{
    /**
     * 入力の誤りを表示し、直して送信できる
     *
     * 必須の項目は確認画面の前に確かめる。
     */
    public function testInputErrors(): void
    {
    }

    /**
     * @dataProvider pageProvider
     * @testdox $path を開ける
     */
    public function testPage(string $path): void
    {
    }
}
```

要約は doc コメントの最初の段落です。データプロバイダーを使うテストは、テスト名の後にデータセットの名前を
付けます。ただし `@testdox` にプレースホルダー (`$path`) があれば、PHPUnit がそれをデータに置き換えるので
付けません。

<a id="screenshots"></a>

## スクリーンショット

すべてのテストで、終了時のブラウザの画面を `tearDown()` の前に撮ります。

- 失敗したテスト: 失敗した時点の画面
- 成功したテスト: 終了時の画面 (テストが自分でスクリーンショットを撮った場合は撮らない)

テストの途中の画面は `takeReportScreenshot()` で撮ります。

```php
public function testApply(): void
{
    $client = static::createPantherClient();
    $client->request('GET', '/contacts/add');
    $this->takeReportScreenshot('入力画面');

    $client->submitForm('確認', $data);
    $this->takeReportScreenshot('確認画面');

    $client->submitForm('送信');
    $this->takeReportScreenshot('完了画面', fullPage: true);
}
```

| 呼び方 | 報告書での撮り方 | 画像 |
| --- | --- | --- |
| `takeReportScreenshot('名前')` | `Viewport` | ブラウザに見えている範囲 (スクロールしている位置のまま) |
| `takeReportScreenshot('名前', element: '.jobs')` | `Element: .jobs` | 要素をいちばん上までスクロールした後の見えている範囲。画面の下にある部分を撮るとき |
| `takeReportScreenshot('名前', fitToPage: true)` | `Fit to page` | ページの上から (`element` を指定したときはその要素の上から)、テストのページの見出しの下に収まる分 (190mm) |
| `takeReportScreenshot('名前', fullPage: true)` | `Full page` | ページ全体。報告書のページを埋めるように分ける (見出しの下に収まる分、その後は 1 ページずつ。ページの行・区画の境目で切る)。分けた画像は 1 枚の画像として続けて載せる (最大 20) |

`element` は CSS セレクター、または `/`・`./`・`(` で始まる XPath です。`PANTHER_REPORT_PDF` が無いときは
`takeReportScreenshot()` は何もしないので、テストの速さは変わりません。

スクリーンショットは、CSS の 1 ピクセルを 1 ピクセルとする JPEG です。`deviceScaleFactor` を 3 にした
スマホのエミュレーションでも、画像が 9 倍の大きさになりません。画質は `PANTHER_REPORT_JPEG_QUALITY`
(既定は `60`) で変えられます。各画像には撮り方、ページのパスとタイトルを添えます。報告書では、どのスクリーンショットも同じ縮尺
(CSS の 1 ピクセルが 0.1mm。幅 768 ピクセル未満のスマホは 0.14mm) で載せます。1200×957 の画面は 120×96mm で、
1 ページに 2 枚載ります。Firefox では、WebDriver の
見えている範囲の PNG になります。

<a id="failures"></a>

## 失敗の内容

失敗したテストには、PHPUnit のメッセージと、テストの失敗した行を載せます。

```text
Failed asserting that the Panther text content of "body" contains "送信しました".
tests/TestCase/EndToEnd/ContactsTest.php:42
```

PHPUnit と Panther のメッセージは英語です。失敗をプロジェクトの言葉で説明したいときは、アサーションに
メッセージを渡します。PHPUnit はそれを最初に出します。

```php
$this->assertSelectorTextContains('body', '送信しました', 'お問い合わせが送信されなかった');
```

<a id="where-the-tests-ran"></a>

## 実行した場所

| | GitHub Actions | それ以外 |
| --- | --- | --- |
| リポジトリ | `GITHUB_REPOSITORY` | Git の `origin` リモート |
| ブランチ | push のブランチ、またはプルリクエストの元のブランチ | 今のブランチ |
| コミット | push したコミット、またはプルリクエストの先頭のコミット (マージコミットではない) | 今のコミットと、コミットしていない変更の有無 |
| 実行環境 | ワークフローと実行の番号、実行へのリンク | `ローカル` と OS |

Git から取れない値は載せません。

<a id="settings"></a>

## 設定

ほかの `PANTHER_*` 変数と同じく、シェルか `phpunit.xml.dist` の `<server>` で設定します。

| 変数 | 既定 | |
| --- | --- | --- |
| `PANTHER_REPORT_PDF` | | PDF のパス。設定したときだけ報告書を書き出す |
| `PANTHER_REPORT_LANGUAGE` | `en` | 見出しと項目名の言語。`en`、`ja`、または `PANTHER_REPORT_LOCALE_DIR` の言語 |
| `PANTHER_REPORT_LOCALE_DIR` | | 言語を足したり文言を置き換えたりする `<言語>.json` のディレクトリ |
| `PANTHER_REPORT_TITLE` | 言語の題名 | 報告書の題名 |
| `PANTHER_REPORT_PROJECT` | Composer のルートパッケージの名前 | 題名の下のプロジェクト名 |
| `PANTHER_REPORT_NOTE` | | 表紙の注記。たとえばスクリーンショットがテスト用のデータであること |
| `PANTHER_REPORT_JPEG_QUALITY` | `60` | スクリーンショットの JPEG の画質 (1〜100) |

```xml
<php>
    <server name="PANTHER_REPORT_LANGUAGE" value="ja"/>
    <server name="PANTHER_REPORT_NOTE" value="スクリーンショットはテスト用の架空のデータで撮影したものです。"/>
</php>
```

`PANTHER_REPORT_PDF` は CI か、報告書がほしいときにシェルで設定すると、ローカルでの実行の速さが変わりません。

報告書を書き出せなかったときは、その旨を出力し、テストの結果は変えません。CI では、
`actions/upload-artifact` の `if-no-files-found: error` などでファイルがあることを確かめてください ([CI](ci.md))。

<a id="languages"></a>

## 言語

英語 (`en`) と日本語 (`ja`) を `resources/report/locales/` に同梱しています。ほかの言語や別の言い回しにするには、
[`en.json`](../../resources/report/locales/en.json) と同じキーの `<言語>.json` を `PANTHER_REPORT_LOCALE_DIR` に
置きます。無いキーは英語になります。

```json
{
    "title": "受入テスト報告書",
    "verdict.passed": "合格",
    "duration.minutes": "{minutes}分{seconds}秒"
}
```

プレースホルダー (`{minutes}`) で、言語ごとに値の順番を変えられます。スクリーンショットの撮り方
(`Viewport`、`Element`、`Full page`) は翻訳しません。
