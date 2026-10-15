# CI with GitHub Actions

The runner image of `ubuntu-latest` has Chrome and a matching chromedriver; `vendor/bin/bdi detect drivers`
makes sure. Run the End-to-End tests in a job of their own, upload the screenshots when it fails, and add a
[job summary](https://github.com/pieceofcake2/ci-summary#readme) that links to them
([docs/examples/end-to-end.yml](examples/end-to-end.yml)):

```yaml
