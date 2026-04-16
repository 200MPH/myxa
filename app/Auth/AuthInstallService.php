<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Migrations\MigrationConfig;
use App\Database\Migrations\MigrationScaffolder;

final class AuthInstallService
{
    /**
     * Create the auth storage migrations used by users, sessions, and API tokens.
     */
    public function __construct(
        private readonly MigrationConfig $config,
        private readonly MigrationScaffolder $scaffolder,
    ) {
    }

    /**
     * Generate any missing auth migration files.
     *
     * @return array{created: list<string>, skipped: list<string>}
     */
    public function install(bool $includeSessions = true): array
    {
        $created = [];
        $skipped = [];

        foreach ($this->migrationSources($includeSessions) as $name => $source) {
            if ($this->hasMigration($name)) {
                $skipped[] = $name;
                continue;
            }

            $created[] = $this->scaffolder->write($name, $source);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function migrationSources(bool $includeSessions): array
    {
        $suffix = gmdate('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 6);

        $sources = [
            'create_users_table' => sprintf(<<<'PHP'
<?php

declare(strict_types=1);

use Myxa\Database\Migrations\Migration;
use Myxa\Database\Schema\Blueprint;
use Myxa\Database\Schema\Schema;

final class %s extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email', 191)->unique();
            $table->string('password_hash', 255);
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('users');
    }
}
PHP, 'CreateUsersTable' . $suffix),
            'create_personal_access_tokens_table' => sprintf(<<<'PHP'
<?php

declare(strict_types=1);

use Myxa\Database\Migrations\Migration;
use Myxa\Database\Schema\Blueprint;
use Myxa\Database\Schema\Schema;

final class %s extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->string('name', 191);
            $table->string('token_hash', 255)->unique();
            $table->json('scopes');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('personal_access_tokens');
    }
}
PHP, 'CreatePersonalAccessTokensTable' . $suffix),
        ];

        if ($includeSessions) {
            $sources['create_user_sessions_table'] = sprintf(<<<'PHP'
<?php

declare(strict_types=1);

use Myxa\Database\Migrations\Migration;
use Myxa\Database\Schema\Blueprint;
use Myxa\Database\Schema\Schema;

final class %s extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('user_sessions', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->string('session_hash', 255)->unique();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('user_sessions');
    }
}
PHP, 'CreateUserSessionsTable' . $suffix);
        }

        return $sources;
    }

    private function hasMigration(string $name): bool
    {
        $pattern = rtrim($this->config->migrationsPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . '*_' . $name . '.php';

        return (glob($pattern) ?: []) !== [];
    }
}
