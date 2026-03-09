# Remote Backups WHMCS Module

> [!CAUTION]
> Development version. Features may be incomplete, APIs may change, and bugs are expected. Do not use in production without thorough testing.

WHMCS module for resellers of [remote-backups.com](https://remote-backups.com/). Lets hosting providers sell managed backup storage to their customers through automatic provisioning, usage tracking, and billing.

Developed by [Nerdscave Hosting](https://www.nerdscave-hosting.com/)

## Features

### Provisioning and management
- Automatic datastore creation on order, deletion on termination
- Resize on upgrades/downgrades via the API

### Billing
- Prorated billing based on the API's rescale-log (tracks every resize event)
- Configurable pricing per 1000 GB with min/max size limits
- Weighted-average billing calculations using actual resize timestamps

### Admin dashboard
- All datastores listed with status, usage, and links to WHMCS services
- Rescale log view (from, to, auto/manual, timestamp)
- Connection test button

### Client area
- Connection credentials with copy-to-clipboard and password reveal
- Collapsible advanced info (IPs, fingerprint)
- Storage usage and transfer rate graphs (Chart.js)
- **Settings tab**: resize datastore, autoscaling config, bandwidth limit
- **Prune settings tab**: retention limits (keep-last, hourly, daily, weekly, monthly, yearly) and schedule (days + hours). Clearing a field sends `0` to the API, which removes the limit without forwarding to PBS

## How it works

Two components: an addon module for admin/config and a server module for provisioning and the client area.

### Addon module

Handles config and admin. On activation it creates a database table for datastore mapping. Admins set their API token, price per 1000 GB, and size limits. The dashboard lists all datastores with current size, usage, and links to WHMCS services.

### Server module

Handles provisioning. On order, WHMCS calls CreateAccount which creates a datastore via the API and stores the mapping. Termination deletes the datastore. Upgrades/downgrades resize it.

### Billing

> [!IMPORTANT]
> **Billing is based on PROVISIONED size, not actual used storage.**
> 
> Even an empty datastore is billed at its full provisioned size. This matches how remote-backups.com charges resellers.

On invoice creation, the module fetches the rescale-log from the API (`GET /reseller/datastore/{id}/rescale-log`) and calculates prorated cost:

1. **Determine billing period** - Based on the service's billing cycle (monthly, quarterly, etc.)
2. **Fetch rescale-log** - Get all resize events from the API covering the billing period
3. **Build timeline** - Reconstruct the size at each point using the `from`/`to` fields and `createdAt` timestamps
4. **Calculate weighted average** - Multiply each size by its duration in hours
5. **Apply pricing** - Use the configured price per 1000 GB

**Formula:**
```
Average GB = (Σ Size_GB × Hours_at_that_size) / Total_hours_in_period
Monthly Cost = Average_GB × (Price_per_1000GB / 1000)
```

#### Example

Given this rescale-log for a monthly billing cycle:

| Date/Time | Event | From | To |
|-----------|-------|------|-----|
| Jan 18, 13:31 | Datastore created | - | 500 GB |
| Jan 19, 01:00 | Autoscaling (automatic) | 500 GB | 600 GB |
| Feb 18, 13:31 | Billing period ends | - | - |

Calculation:
- 500 GB × 11.5 hours = 5,750 GB-hours
- 600 GB × 732.5 hours = 439,500 GB-hours (rest of month)
- Total = 445,250 GB-hours
- Period = 744 hours (31 days in January)
- **Average = 598.45 GB**

At €10/1000GB/month: 598.45 GB × €0.01 = **€5.98**

The invoice description will show: "Usage-based billing: 598.45 GB average over 744 hours"

## Requirements

- WHMCS 8.0 or higher
- PHP 8.0 or higher with cURL extension
- A reseller account at remote-backups.com with API access

## Installation

### Step 1: Upload Files

Copy the module directories to your WHMCS installation:

```
modules/addons/remotebackups/    -> /path/to/whmcs/modules/addons/remotebackups/
modules/servers/remotebackups/   -> /path/to/whmcs/modules/servers/remotebackups/
```

### Step 2: Activate the Addon

Go to Setup, then Addon Modules. Find Remote Backups in the list and click Activate. Click Configure and enter:

- Your API token from remote-backups.com
- The monthly price per 1000 GB in your default currency
- Minimum datastore size in GB (customers cannot order smaller)
- Maximum datastore size in GB (customers cannot order larger)

### Step 3: Create a Product

Go to Setup, then Products/Services, then Products/Services. Create a new product and under Module Settings, select Remote Backups. Configure:

- Datastore Size (GB): The size of the datastore for this product
- Name Prefix: A prefix for datastore names, for example "backup"

The module will create datastores named like "backup-client123-service456" to ensure uniqueness.

## API endpoints

Base URL: `https://api.remote-backups.com` (reseller API, no `/v1` prefix).

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | /reseller/datastore | List all datastores |
| POST | /reseller/datastore | Create a new datastore |
| GET | /reseller/datastore/:id | Get datastore details |
| PATCH | /reseller/datastore/:id | Update datastore settings |
| DELETE | /reseller/datastore/:id | Delete a datastore |
| PATCH | /reseller/datastore/:id/prune-settings | Update retention and schedule |
| GET | /reseller/datastore/:id/graph?range=hour | Fetch metrics data |
| GET | /reseller/datastore/:id/rescale-log?range=30d | Get resize event history |

Sizes go to the API in GB. The API returns bytes, which the module converts back.

Size constraints: 500-10000 GB, increments of 100 GB.

## Database tables

Created on activation:

**mod_remotebackups_datastores** — maps datastore IDs to WHMCS service IDs, stores current size.

## Troubleshooting

### Connection failed

Check your API token. Go to Addons > Remote Backups > Test Connection.

### Products page error

Make sure the module files are in the right place. The server module at `/modules/servers/remotebackups/` needs to be able to load the addon module at `/modules/addons/remotebackups/`.

### Provisioning fails

Check the module log under Utilities > Logs > Module Log. All API requests and responses are logged there.

## Changelog

### v1.1.0
- Billing now uses the API's rescale-log endpoint instead of local hourly polling
- Removed `cron.php` (no more hourly cron needed)
- Removed `mod_remotebackups_size_history` database table
- Admin "Usage History" replaced with "Rescale Log" showing data from the API

### v1.0.0
- Initial release

## License

GPL-3.0-or-later

Copyright 2026 Moritz Mantel / Nerdscave Hosting

## Links

- [Nerdscave Hosting](https://www.nerdscave-hosting.com/)
- [remote-backups.com](https://remote-backups.com/)
- [API Reference](https://api.remote-backups.com/reference)
- [API Swagger UI](https://api.remote-backups.com/docs)
- [remote-backups.com Documentation](https://docs-next.bennetg.de/products/remote-backups/remote_configuration)
