# property-offers-api

https://github.com/JohnStormP/property-offers-api

Suppliers send offers via import (queued), then you can search the cheapest ones and reserve.

PHP 8.2, Laravel 12, MySQL. Queue driver in `.env.example` is `database`.


## How to run

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create mysql db, set `.env`, then:

```bash
php artisan migrate
php artisan db:seed
php artisan serve
```

Seeder puts `supplier-a` / `supplier-b` in the db. Imports expect those codes.

For imports, you also need a worker:

```bash
php artisan queue:work
```

Or set `QUEUE_CONNECTION=sync` locally if you don't want a second process.

```bash
php artisan test
```

Postman collection is in `postman/`.


## Endpoints

- `POST /api/imports` → 202
- `GET /api/imports/{id}`
- `GET /api/properties?city=&check_in=&check_out=&guests=&page=`
- `POST /api/offers/{id}/reservations` → 201

Bodies/examples — see postman.


## Imports

Unique on `(supplier, external_import_id)`.

Same request twice → same import row, job is not dispatched again, still 202.

Offers unique on `(supplier, external_id)` — existing ones get updated. Property is found/created by `code`.


## Reservations

Inside a transaction we decrement units with a condition, not select-then-update:

```sql
update offers
set available_units = available_units - 1
where id = ?
  and available_units > 0
  and expires_at > now()
```

0 rows updated → 409, reservation is not created.
