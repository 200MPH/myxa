<?php

declare(strict_types=1);

namespace Test\Feature;

use App\Foundation\ApplicationFactory;
use MongoDB\Client;
use Myxa\Mongo\MongoManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use Test\TestCase;
use Throwable;

#[CoversNothing]
final class MongoIntegrationTest extends TestCase
{
    private string $uri = 'mongodb://127.0.0.1:27017';

    private string $database = 'myxa_project_integration_test';

    private bool $mongoAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('mongodb')) {
            self::markTestSkipped('mongodb extension is not available.');
        }

        if (!class_exists(Client::class)) {
            self::markTestSkipped('mongodb/mongodb package is not installed.');
        }

        $this->uri = (string) (getenv('MONGO_URI') ?: $this->uri);

        try {
            $this->dropDatabase();
            $this->mongoAvailable = true;
        } catch (Throwable $exception) {
            self::markTestSkipped(sprintf(
                'MongoDB runtime server is not available at %s: %s',
                $this->uri,
                $exception->getMessage(),
            ));
        }

        $this->setEnvironmentValue('MONGO_CONNECTION', 'default');
        $this->setEnvironmentValue('MONGO_URI', $this->uri);
        $this->setEnvironmentValue('MONGO_DATABASE', $this->database);
    }

    protected function tearDown(): void
    {
        if ($this->mongoAvailable) {
            try {
                $this->dropDatabase();
            } catch (Throwable) {
            }
        }

        parent::tearDown();
    }

    public function testApplicationMongoProviderRunsCrudOperationsAgainstConfiguredMongo(): void
    {
        $app = ApplicationFactory::create(base_path());
        $mongo = $app->make(MongoManager::class);
        $collection = $mongo->collection('integration_documents');

        $id = $collection->insertOne([
            'type' => 'application-wiring',
            'name' => 'Mongo Integration',
        ]);

        self::assertIsString($id);

        $document = $collection->findOne(['_id' => $id]);

        self::assertSame('default', $mongo->getDefaultConnection());
        self::assertSame($id, $document['_id'] ?? null);
        self::assertSame('Mongo Integration', $document['name'] ?? null);

        self::assertTrue($collection->updateOne([
            '_id' => $id,
        ], [
            '_id' => $id,
            'type' => 'application-wiring',
            'name' => 'Mongo Integration Updated',
        ]));

        self::assertSame('Mongo Integration Updated', $collection->findOne(['_id' => $id])['name'] ?? null);
        self::assertTrue($collection->deleteOne(['_id' => $id]));
        self::assertNull($collection->findOne(['_id' => $id]));
    }

    private function dropDatabase(): void
    {
        $client = new Client($this->uri, [
            'serverSelectionTimeoutMS' => 1000,
        ]);

        $client->selectDatabase($this->database)->drop();
    }
}
