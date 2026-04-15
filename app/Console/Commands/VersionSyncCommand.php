<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Version\ApplicationVersion;
use App\Version\GitVersionResolver;
use Myxa\Console\Command;

final class VersionSyncCommand extends Command
{
    public function __construct(
        private readonly ApplicationVersion $version,
        private readonly GitVersionResolver $git,
    ) {
    }

    public function name(): string
    {
        return 'version:sync';
    }

    public function description(): string
    {
        return 'Generate the application version manifest from Git metadata.';
    }

    protected function handle(): int
    {
        $payload = $this->version->writeGitManifest($this->git->resolve(base_path()));

        $this->table(
            ['Version', 'Source', 'Manifest', 'Commit'],
            [[
                $payload['version'],
                $payload['source'],
                $this->version->manifestPath(),
                (string) $payload['short_commit'],
            ]],
        );

        $this->success('Version manifest synced from Git.')->icon();

        return 0;
    }
}
