# GitHub Actions での CI

`ubuntu-latest` のランナーイメージには Chrome と、それに合った chromedriver が含まれています。
`vendor/bin/bdi detect drivers` で確実に合わせます。End-to-End テストは専用のジョブで実行し、失敗した
ときはスクリーンショットをアップロードして、それへのリンクを含む
[ジョブサマリー](https://github.com/pieceofcake2/ci-summary#readme) を追加します
([docs/examples/end-to-end.yml](../examples/end-to-end.yml))。

```yaml
# .github/workflows/end-to-end.yml
name: End-to-End

on:
  push:
  pull_request:

permissions:
  contents: read

jobs:
  end-to-end:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.4
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_app
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=10

    steps:
      - uses: actions/checkout@v7
        with:
          persist-credentials: false

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo_mysql
          coverage: none

      - name: Install Composer packages
        run: composer install --no-interaction --no-progress

      - name: Install chromedriver for the installed Chrome
        run: vendor/bin/bdi detect drivers

      # The runners have no Japanese fonts; without them Japanese text is drawn as boxes in the screenshots
      - name: Install Japanese fonts
        run: sudo apt-get update -qq && sudo apt-get install -y -qq fonts-noto-cjk

      - name: Run End-to-End tests
        env:
          DB_TEST_DATASOURCE: Database/Mysql
          DB_TEST_HOST: 127.0.0.1
          DB_TEST_USERNAME: root
          DB_TEST_PASSWORD: root
          DB_TEST_DATABASE: test_app
          PANTHER_NO_SANDBOX: 1
        run: vendor/bin/phpunit --testsuite EndToEnd --log-junit end-to-end-junit.xml

      - name: Upload the error screenshots
        id: screenshots
        if: ${{ failure() }}
        uses: actions/upload-artifact@v7
        with:
          name: error-screenshots
          path: tmp/error-screenshots/
          retention-days: 7
          if-no-files-found: ignore

      - name: End-to-End summary
        if: ${{ !cancelled() }}
        uses: pieceofcake2/ci-summary@v1
        with:
          type: junit
          title: End-to-End
          file: end-to-end-junit.xml
          artifact-name: error-screenshots
          artifact-url: ${{ steps.screenshots.outputs.artifact-url }}
          artifact-note: Screenshots of the failed tests are in the {artifact} artifact.
```

`bdi` は [dbrekelmans/bdi](https://github.com/dbrekelmans/bdi) です。
`composer require --dev dbrekelmans/bdi` で追加してください。GitHub のランナーでは
`PANTHER_NO_SANDBOX=1` が必要です。ランナーには日本語 (CJK) のフォントが無いので、例では `fonts-noto-cjk` を入れています。入れないと、スクリーンショットの日本語が四角 (豆腐) で表示されます。ページに CJK の文字が無ければ、このステップは不要です。
