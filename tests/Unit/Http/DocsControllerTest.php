<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use App\Config\ConfigRepository;
use App\Docs\DocsCatalog;
use App\Docs\MarkdownRenderer;
use App\Http\Controllers\DocsController;
use App\Version\ApplicationVersion;
use Myxa\Http\Request;
use Myxa\Support\Html\Html;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(DocsController::class)]
final class DocsControllerTest extends TestCase
{
    private string $docsPath;
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docsPath = sys_get_temp_dir() . '/myxa-docs-controller-' . uniqid('', true);
        $this->manifestPath = sys_get_temp_dir() . '/myxa-docs-version-' . uniqid('', true) . '.json';
        mkdir($this->docsPath, 0777, true);
        $this->unsetEnvironmentValue('APP_VERSION');

        file_put_contents(
            $this->docsPath . '/configuration.md',
            <<<'MD'
# Configuration

Configure your Myxa application with clear environment values, predictable defaults,
and framework-friendly service settings.

## Details

More text here.
MD,
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->docsPath . '/configuration.md')) {
            unlink($this->docsPath . '/configuration.md');
        }

        if (is_dir($this->docsPath)) {
            rmdir($this->docsPath);
        }

        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    public function testShowRendersSocialMetadataAndLinksLogoHome(): void
    {
        $controller = new DocsController();
        $response = $controller->show(
            'configuration',
            new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/docs/configuration',
                'HTTP_HOST' => 'myxa.dev',
                'HTTPS' => 'on',
            ]),
            new ConfigRepository([
                'app' => [
                    'name' => 'Myxa',
                ],
                'version' => [
                    'fallback' => '1.0.3',
                ],
            ]),
            new ApplicationVersion(new ConfigRepository([
                'version' => [
                    'manifest' => $this->manifestPath,
                    'fallback' => '1.0.3',
                ],
            ])),
            new Html(resource_path('views')),
            new DocsCatalog($this->docsPath),
            new MarkdownRenderer(),
        );

        self::assertSame(200, $response->statusCode());
    }
}
