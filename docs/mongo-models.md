# Mongo Models

The framework includes `MongoModel` for document-backed models.

Use SQL `Model` when you are working with relational tables.

Use `MongoModel` when you want declared-property models backed by Mongo-style collections instead of SQL tables.

## On This Page

- [Basic Model](#basic-model)
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

## Typical Usage

```php
UserDocument::setManager($mongoManager);

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

Connection support today:

- `MongoManager` resolves named Mongo connections
- each `MongoConnection` resolves named collections
- the built-in collection implementation currently shipped by the framework is `InMemoryMongoCollection`
- custom collection backends can be added by implementing `MongoCollectionInterface`

So at the moment, Mongo support is best described as:

- a document-model layer with a connection/collection abstraction
- built-in in-memory support for tests and local experiments
- room for custom adapters when you want a real external Mongo backend

This project does not scaffold Mongo connections yet, so treat this as a framework capability you can wire in when your app needs document storage.

## Further Reading

- [Database](database.md)
- [Database Models and Queries](database-models.md)
- `vendor/200mph/myxa-framework/src/Mongo/README.md`
