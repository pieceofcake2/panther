<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use Closure;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Environment;

class EnvironmentTest extends TestCase
{
    /**
     * A command runner that answers the Git commands of the map
     *
     * @param array<string, string|null> $outputs Output of each command line
     * @param array<string> $commands Receives the commands
     * @return \Closure
     */
    private static function runner(array $outputs, array &$commands = []): Closure
    {
        return static function (array $command) use ($outputs, &$commands): ?string {
            $line = implode(' ', $command);
            $commands[] = $line;

            return $outputs[$line] ?? null;
        };
    }

    public function testGitHubActionsPush(): void
    {
        $environment = Environment::fromEnv([
            'GITHUB_ACTIONS' => 'true',
            'GITHUB_REPOSITORY' => 'acme/app',
            'GITHUB_REF_NAME' => 'master',
            'GITHUB_SHA' => '1234567890abcdef',
            'GITHUB_WORKFLOW' => 'CI',
            'GITHUB_RUN_NUMBER' => '812',
            'GITHUB_RUN_ID' => '99',
            'GITHUB_RUN_ATTEMPT' => '1',
            'GITHUB_SERVER_URL' => 'https://github.com',
        ], self::runner(['git log -1 --format=%s 1234567890abcdef' => 'feat: add the report']));

        $this->assertSame('acme/app', $environment->repository);
        $this->assertSame('master', $environment->branch);
        $this->assertSame('1234567', $environment->commit);
        $this->assertSame('feat: add the report', $environment->commitSubject);
        $this->assertFalse($environment->dirty);
        $this->assertSame('GitHub Actions CI #812', $environment->run);
        $this->assertSame('https://github.com/acme/app/actions/runs/99', $environment->runUrl);
        $this->assertNull($environment->os);
    }

    public function testGitHubActionsPullRequestReportsItsHead(): void
    {
        $event = TMP . 'event-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($event, json_encode(['pull_request' => ['number' => 12, 'title' => 'Add the report', 'head' => ['sha' => 'abcdef1234567890']]]));
        try {
            $environment = Environment::fromEnv([
                'GITHUB_ACTIONS' => 'true',
                'GITHUB_EVENT_NAME' => 'pull_request',
                'GITHUB_EVENT_PATH' => $event,
                'GITHUB_REPOSITORY' => 'acme/app',
                'GITHUB_HEAD_REF' => 'feat/report',
                'GITHUB_REF_NAME' => '12/merge',
                'GITHUB_SHA' => '1111111111111111',
                'GITHUB_WORKFLOW' => 'CI',
                'GITHUB_RUN_NUMBER' => '813',
                'GITHUB_RUN_ID' => '100',
                'GITHUB_RUN_ATTEMPT' => '2',
            ], self::runner([]));
        } finally {
            unlink($event);
        }

        $this->assertSame('feat/report', $environment->branch);
        $this->assertSame('abcdef1', $environment->commit);
        // The head commit is not in a shallow checkout of the merge commit: the pull request instead
        $this->assertSame('#12 Add the report', $environment->commitSubject);
        $this->assertSame('GitHub Actions CI #813 (attempt 2)', $environment->run);
        $this->assertSame('https://github.com/acme/app/actions/runs/100/attempts/2', $environment->runUrl);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function remoteProvider(): array
    {
        return [
            'SSH' => ['git@github.com:acme/app.git', 'acme/app'],
            'HTTPS' => ['https://github.com/acme/app.git', 'acme/app'],
            'without .git' => ['https://gitlab.example.com/group/app', 'group/app'],
        ];
    }

    /**
     * @dataProvider remoteProvider
     */
    public function testLocalRepository(string $remote, string $repository): void
    {
        $environment = Environment::fromEnv([], self::runner([
            'git remote get-url origin' => $remote,
            'git rev-parse --abbrev-ref HEAD' => 'feat/report',
            'git rev-parse --short=7 HEAD' => 'a1b2c3d',
            'git log -1 --format=%s' => 'feat: add the report',
            'git status --porcelain' => ' M src/Report/Recorder.php',
            'sw_vers -productVersion' => '15.1',
        ]));

        $this->assertSame($repository, $environment->repository);
        $this->assertSame('feat/report', $environment->branch);
        $this->assertSame('a1b2c3d', $environment->commit);
        $this->assertSame('feat: add the report', $environment->commitSubject);
        $this->assertTrue($environment->dirty);
        $this->assertNull($environment->run);
        $this->assertNotNull($environment->os);
        $this->assertStringEndsWith(' ' . php_uname('m'), $environment->os);
    }

    public function testLocalWithoutChanges(): void
    {
        $environment = Environment::fromEnv([], self::runner([
            'git rev-parse --abbrev-ref HEAD' => 'HEAD',
            'git rev-parse --short=7 HEAD' => 'a1b2c3d',
            'git status --porcelain' => '',
        ]));

        // A detached HEAD has no branch
        $this->assertNull($environment->branch);
        $this->assertNull($environment->repository);
        $this->assertFalse($environment->dirty);
    }

    public function testLocalWithoutGit(): void
    {
        $commands = [];
        $environment = Environment::fromEnv([], self::runner([], $commands));

        $this->assertNull($environment->repository);
        $this->assertNull($environment->branch);
        $this->assertNull($environment->commit);
        $this->assertNull($environment->commitSubject);
        $this->assertFalse($environment->dirty);
        $this->assertNotContains('git status --porcelain', $commands);
    }

    public function testDetect(): void
    {
        $environment = Environment::detect(dirname(__DIR__, 3));

        $this->assertNotNull($environment->commit);
    }
}
