# CI with GitHub Actions

The runner image of `ubuntu-latest` has Chrome and a matching chromedriver; `vendor/bin/bdi detect drivers`
makes sure. Run the End-to-End tests in a job of their own, upload the screenshots when it fails, and add a
[job summary](https://github.com/pieceofcake2/ci-summary#readme) that links to them
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

`bdi` is [dbrekelmans/bdi](https://github.com/dbrekelmans/bdi); add it with
`composer require --dev dbrekelmans/bdi`. `PANTHER_NO_SANDBOX=1` is needed on the GitHub runners. The runners have no Japanese (CJK) fonts, so the example installs `fonts-noto-cjk`; without it, Japanese text is drawn as boxes in the screenshots. Leave the step out if your pages have no CJK text.
