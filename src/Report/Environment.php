<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use Closure;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Where the tests ran: the repository, branch and commit, and the run of GitHub Actions or the local machine.
 *
 * On GitHub Actions, they come from its environment variables (`GITHUB_REPOSITORY`, `GITHUB_SHA`, ...) and the
 * run links to the run of the workflow. Elsewhere, they come from Git (`origin` remote, current branch and
 * commit, uncommitted changes); a value that Git cannot give is left out of the report.
 */
final class Environment
{
    public ?string $repository = null;
    public ?string $branch = null;
    public ?string $commit = null;
    public ?string $commitSubject = null;
    public bool $dirty = false;

    /**
     * The run of the CI, e.g. `GitHub Actions CI #12`, or null for a local run
     */
    public ?string $run = null;
    public ?string $runUrl = null;

    /**
     * The operating system of a local run
     */
    public ?string $os = null;

    /**
     * Detects the environment of this process
     *
     * @param string|null $projectDir Directory of the project (Git commands run there), default: the current directory
     * @return self
     */
    public static function detect(?string $projectDir = null): self
    {
        $env = getenv();
        $run = static function (array $command) use ($projectDir): ?string {
            $process = new Process($command, $projectDir);
            try {
                $process->run();
            } catch (Throwable) {
                return null;
            }

            return $process->isSuccessful() ? rtrim($process->getOutput()) : null;
        };

        return self::fromEnv($env, Closure::fromCallable($run));
    }

    /**
     * The environment of the variables, with a runner of the commands (Git)
     *
     * @param array<string, string> $env Environment variables
     * @param \Closure(array<string>): ?string $run Runs a command and returns its output, or null when it fails
     * @return self
     */
    public static function fromEnv(array $env, Closure $run): self
    {
        $environment = new self();
        if (($env['GITHUB_ACTIONS'] ?? '') === 'true') {
            $environment->github($env, $run);
        } else {
            $environment->local($run);
        }

        return $environment;
    }

    /**
     * The run of GitHub Actions
     *
     * @param array<string, string> $env Environment variables
     * @param \Closure(array<string>): ?string $run Command runner
     * @return void
     */
    private function github(array $env, Closure $run): void
    {
        $this->repository = self::nonEmpty($env['GITHUB_REPOSITORY'] ?? null);
        $this->branch = self::nonEmpty($env['GITHUB_HEAD_REF'] ?? null) ?? self::nonEmpty($env['GITHUB_REF_NAME'] ?? null);
        $sha = self::nonEmpty($env['GITHUB_SHA'] ?? null);

        // A pull request runs on a merge commit: report the head of the pull request instead
        $pullRequest = null;
        $eventPath = $env['GITHUB_EVENT_PATH'] ?? '';
        if (str_starts_with($env['GITHUB_EVENT_NAME'] ?? '', 'pull_request') && $eventPath !== '' && is_file($eventPath)) {
            $event = json_decode((string)file_get_contents($eventPath), true);
            $pullRequest = is_array($event) && is_array($event['pull_request'] ?? null) ? $event['pull_request'] : null;
            $sha = self::nonEmpty($pullRequest['head']['sha'] ?? null) ?? $sha;
        }

        if ($sha !== null) {
            $this->commit = substr($sha, 0, 7);
            $this->commitSubject = $run(['git', 'log', '-1', '--format=%s', $sha]);
            if ($this->commitSubject === null && $pullRequest !== null && isset($pullRequest['number'], $pullRequest['title'])) {
                $this->commitSubject = sprintf('#%d %s', (int)$pullRequest['number'], (string)$pullRequest['title']);
            }
        }

        $workflow = self::nonEmpty($env['GITHUB_WORKFLOW'] ?? null);
        $number = self::nonEmpty($env['GITHUB_RUN_NUMBER'] ?? null);
        $this->run = trim('GitHub Actions ' . ($workflow ?? '') . ($number !== null ? ' #' . $number : ''));
        $attempt = (int)($env['GITHUB_RUN_ATTEMPT'] ?? 1);
        if ($attempt > 1) {
            $this->run .= sprintf(' (attempt %d)', $attempt);
        }

        $server = self::nonEmpty($env['GITHUB_SERVER_URL'] ?? null) ?? 'https://github.com';
        $runId = self::nonEmpty($env['GITHUB_RUN_ID'] ?? null);
        if ($this->repository !== null && $runId !== null) {
            $this->runUrl = sprintf('%s/%s/actions/runs/%s', $server, $this->repository, $runId);
            if ($attempt > 1) {
                $this->runUrl .= '/attempts/' . $attempt;
            }
        }
    }

    /**
     * A local run: the values of Git and the operating system
     *
     * @param \Closure(array<string>): ?string $run Command runner
     * @return void
     */
    private function local(Closure $run): void
    {
        $remote = $run(['git', 'remote', 'get-url', 'origin']);
        if ($remote !== null && preg_match('#[/:]([^/:]+/[^/]+?)(?:\.git)?/?\z#', $remote, $matches)) {
            $this->repository = $matches[1];
        }
        $branch = $run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $this->branch = $branch === 'HEAD' ? null : self::nonEmpty($branch);
        $this->commit = self::nonEmpty($run(['git', 'rev-parse', '--short=7', 'HEAD']));
        if ($this->commit !== null) {
            $this->commitSubject = self::nonEmpty($run(['git', 'log', '-1', '--format=%s']));
            $this->dirty = self::nonEmpty($run(['git', 'status', '--porcelain'])) !== null;
        }
        $this->os = self::os($run);
    }

    /**
     * The name, version and architecture of the operating system, e.g. `macOS 15.1 arm64`
     *
     * @param \Closure(array<string>): ?string $run Command runner
     * @return string
     */
    private static function os(Closure $run): string
    {
        $name = PHP_OS_FAMILY . ' ' . php_uname('r');
        if (PHP_OS_FAMILY === 'Darwin') {
            $version = self::nonEmpty($run(['sw_vers', '-productVersion']));
            $name = $version !== null ? 'macOS ' . $version : $name;
        } elseif (PHP_OS_FAMILY === 'Linux' && is_readable('/etc/os-release')) {
            $release = parse_ini_file('/etc/os-release', false, INI_SCANNER_RAW) ?: [];
            $name = self::nonEmpty(isset($release['PRETTY_NAME']) ? trim((string)$release['PRETTY_NAME'], '"') : null) ?? $name;
        }

        return $name . ' ' . php_uname('m');
    }

    /**
     * The value, or null when it is empty
     *
     * @param mixed $value Value
     * @return string|null
     */
    private static function nonEmpty(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}
