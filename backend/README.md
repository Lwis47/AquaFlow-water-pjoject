# AquaFlow PHP API

This API uses PHP, PDO, and SQLite. The database is created on the first request and
stored in `backend/storage/aquaflow.sqlite`.

## Run locally

From the project root, run the launcher (enables the SQLite PDO driver for the
session without admin rights):

```powershell
.\serve.cmd
```

Or, if `pdo_sqlite` is already enabled in your php.ini:

```powershell
php -S localhost:8000
```

Then open `http://localhost:8000/index.html` and check the API at:

```text
http://localhost:8000/backend/public/api.php?route=health
```

## Available endpoints

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `health` | Check that the service is running. |
| GET, POST | `clients` | List or create clients. |
| GET | `services` | List the seeded service catalogue. |
| GET, POST | `applications` | List or create applications. |
| PUT | `applications/{id}` | Update application workflow status / notes. |
| POST | `quotes/calculate` | Calculate a non-persistent quote. |
| GET, POST | `quotes` | List saved quotations or calculate and save a new one. |
| PUT | `quotes/{id}` | Update quotation status (Draft / Sent / Accepted / Expired), notes or validity. |
| DELETE | `quotes/{id}` | Remove a quotation. |
| GET | `reports/overview` | Fetch high-level business metrics, quote pipeline and six-month revenue. |

Every route is sent through `api.php?route=<route>`. Requests with a body must use
JSON and `Content-Type: application/json`.

## Note on security

This is an application backend foundation, not a production deployment. Before
exposing it publicly, add authentication, role checks, CSRF protection for browser
sessions, a constrained CORS origin, HTTPS, rate limiting, and database backups.
