# AquaFlow Water Solutions

A responsive operations system for borehole drilling and water-services work. It stores clients, applications, quotations and service configuration in SQLite, calculates published service charges plus 16% tax, and produces live operational reports.

## Open locally

Install PHP 8.2+ with the PDO SQLite extension, then run the bundled launcher
(it enables the SQLite driver for the session without needing admin rights):

```powershell
.\serve.cmd
```

On macOS / Linux use `sh serve.sh`. If your PHP already has `pdo_sqlite` enabled
globally you can instead run `php -S localhost:8000`.

Open `http://localhost:8000`. The database is created automatically at
`backend/storage/aquaflow.sqlite` and seeded with a realistic demo client book,
applications and quotations on first run.

## Included screens

- Client directory with create, edit and safe removal
- Service catalogue management
- Application register and workflow-status updates
- Saved quotations with drilling, pump, depth/height and optional plumbing calculation, plus Draft / Sent / Accepted / Expired status management
- Live fees, tax, revenue and activity reports with CSV export

## Deploy on Render

This repository includes a production Dockerfile and `render.yaml` Blueprint. Create a new Blueprint in Render from this repository. It uses a persistent disk at `/var/www/html/backend/storage`; retain the disk so client and quotation records survive deployments. The Blueprint uses the Starter plan because persistent disks are not available on Render's free web-service tier.

The health check is available at `/backend/public/api.php?route=health`.

The interface uses the service categories, published rates and 16% tax rule from the supplied project brief. Plumbing rates are deliberately entered for each quote because the brief defines the calculation factors but does not publish pipe or outlet prices.
