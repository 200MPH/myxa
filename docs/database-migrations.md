# Database Migrations

Migrations keep SQL schema changes versioned with the app.

## Common Commands

Generate a migration:

```bash
./myxa make:migration create_posts_table --create=posts
```

Run pending migrations:

```bash
./myxa migrate
```

Show status:

```bash
./myxa migrate:status
```

Rollback:

```bash
./myxa migrate:rollback --step=1
```

The migration repository table is created automatically if it does not exist.

## Reverse Engineering and Schema Diffing

Snapshot the current schema:

```bash
./myxa migrate:snapshot
```

Compare a live table to the stored snapshot:

```bash
./myxa migrate:diff users
./myxa migrate:diff users --write
```

Generate a create migration from an existing table:

```bash
./myxa migrate:reverse users
```

This is useful when adopting an existing database into the migration workflow.

## Model Scaffolding From Existing Sources

Generate from a live table:

```bash
./myxa make:model App\\Models\\AuditLog --from-table=audit_logs
```

Generate from a migration file:

```bash
./myxa make:model App\\Models\\AuditLog --from-migration=2026_04_17_120000_create_audit_logs_table.php
```

Use generated models as a starting point, then review property types, casts, guarded fields, and hidden fields before treating them as final.

## Further Reading

- [Database](database.md)
- [Database Models and Queries](database-models.md)
- [Database Seeding](database-seeding.md)
- [Console and Scaffolding](console-and-scaffolding.md)
- `vendor/200mph/myxa-framework/src/Database/Migrations/README.md`
- `vendor/200mph/myxa-framework/src/Database/Schema/README.md`
