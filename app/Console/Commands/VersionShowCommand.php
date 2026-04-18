<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Version\ApplicationVersion;
use Myxa\Console\Command;

final class VersionShowCommand extends Command
{
    public function __construct(private readonly ApplicationVersion $version)
    {
    }

    public function name(): string
    {
        return 'version:show';
    }

    public function description(): string
    {
        return 'Show the current application version and manifest metadata.';
    }

    protected function handle(): int
    {
        $details = $this->version->details();

        $this->table(
            ['Version', 'Source', 'Manifest', 'Generated At'],
            [[
                $details['version'],
                $details['source'],
                $this->version->manifestPath(),
                $details['generated_at'] ?? '-',
            ]],
        );

        if ($details['describe'] !== null || $details['commit'] !== null) {
            $this->output('');
            $this->table(
                ['Tag', 'Describe', 'Commit', 'Dirty'],
                [[
                    $details['tag'] ?? '-',
                    $details['describe'] ?? '-',
                    $details['short_commit'] ?? $details['commit'] ?? '-',
                    $details['dirty'] === true ? 'yes' : 'no',
                ]],
            );
        }

        return 0;
    }
}
