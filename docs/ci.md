# CI with GitHub Actions

The runner image of `ubuntu-latest` has Chrome and a matching chromedriver; `vendor/bin/bdi detect drivers`
makes sure. Run the End-to-End tests in a job of their own, upload their [test report](report.md), and add a
[job summary](https://github.com/pieceofcake2/ci-summary#readme) that links to it
([docs/examples/end-to-end.yml](examples/end-to-end.yml)):

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
          DATABASE_URL: mysql://root:root@127.0.0.1:3306/test_app
          PANTHER_NO_SANDBOX: 1
          PANTHER_REPORT_PDF: tmp/end-to-end-report.pdf
        run: vendor/bin/phpunit --testsuite EndToEnd --log-junit end-to-end-junit.xml

      # The test report of every run, passed or not; one file is uploaded as it is, without a zip
      - name: Upload the test report
        id: report
        if: ${{ !cancelled() }}
        uses: actions/upload-artifact@v7
        with:
          path: tmp/end-to-end-report.pdf
          archive: false
          retention-days: 30
          if-no-files-found: error

      - name: End-to-End summary
        if: ${{ !cancelled() }}
        uses: pieceofcake2/ci-summary@v1
        with:
          type: junit
          title: End-to-End
          file: end-to-end-junit.xml
          artifact-name: end-to-end-report.pdf
          artifact-url: ${{ steps.report.outputs.artifact-url }}
          artifact-note: The test report is {artifact}.
```

`PANTHER_REPORT_PDF` writes the [test report](report.md) of the run, and the next step uploads it: with
`archive: false` (actions/upload-artifact v7), the artifact is the PDF itself, not a zip, named after the file.
`if-no-files-found: error` makes the job fail when the report was not written.

`bdi` is [dbrekelmans/bdi](https://github.com/dbrekelmans/bdi); add it with
`composer require --dev dbrekelmans/bdi`. `PANTHER_NO_SANDBOX=1` is needed on the GitHub runners. The runners have no Japanese (CJK) fonts, so the example installs `fonts-noto-cjk`; without it, Japanese text is drawn as boxes in the screenshots. Leave the step out if your pages have no CJK text.
`DATABASE_URL` is a real environment variable, so it wins over pieceofcake2/app's
`config/.env.test` in the tests and in the web server, which inherits it (the `'dsn'` key needs
pieceofcake2/cakephp 2.12.7 or later); see [Test Database](test-database.md).
