# Mongo Models

The framework includes `MongoModel` for document-backed models.

Use SQL `Model` when you are working with relational tables.

Use `MongoModel` when you want declared-property models backed by Mongo-style collections instead of SQL tables.

## On This Page

- [Basic Model](#basic-model)
- [Connection Setup](#connection-setup)
- [Typical Usage](#typical-usage)
- [Differences From SQL Models](#differences-from-sql-models)
- [Further Reading](#further-reading)

## Basic Model

```php
use Myxa\Mongo\MongoModel;

final class UserDocument extends MongoModel
{
    protected string $collection = 'users';

    // Mongo uses _id by default.
    protected string|int|null $_id = null;

    protected string $email = '';
    protected string $status = '';
}
```

The same strict declared-property idea still applies:

- document fields should be declared as real properties
- unknown attributes are rejected during normal writes
- guarded, hidden, internal, and cast attributes still apply
- built-in casts currently include `DateTime`, `DateTimeImmutable`, and `Json`

## Connection Setup

This project wires Mongo through `config/services.php`:

```php
'mongo' => [
    'default' => (string) env('MONGO_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'uri' => (string) env('MONGO_URI', 'mongodb://127.0.0.1:27017'),
            'database' => (string) env('MONGO_DATABASE', 'myxa'),
            'uri_options' => [],
            'driver_options' => [],
        ],
    ],
],
```

`App\Providers\MongoServiceProvider` registers these connections lazily with the framework
`MongoServiceProvider`, so the app does not connect to Mongo until code asks for a collection.

The Docker development environment includes a `mongo` service, and the PHP image enables `ext-mongodb`.
The Composer package `mongodb/mongodb` is required when real Mongo connections are used.

## Typical Usage

```php
$user = UserDocument::create([
    'email' => 'john@example.com',
    'status' => 'active',
]);

$found = UserDocument::find($user->getKey());
$found->status = 'inactive';
$found->save();
```

## Differences From SQL Models

- `MongoModel` is document-backed
- it does not use the SQL query builder
- it does not provide SQL-style relations like `hasMany()` or `belongsTo()`

Connection support:

- `MongoManager` resolves named Mongo connections
- real Mongo connections resolve collections lazily through `MongoConnection::fromUri()`
- `MongoDbCollection` adapts the official `mongodb/mongodb` collection API
- `InMemoryMongoCollection` is still useful for isolated tests and local experiments

## Further Reading

- [Database](database.md)
- [Database Models and Queries](database-models.md)
- `vendor/200mph/myxa-framework/src/Mongo/README.md`
