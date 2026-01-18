# Remote Backups WHMCS Module

WHMCS integration module for resellers of [remote-backups.com](https://remote-backups.com/).

This module allows hosting providers and MSPs to offer managed backup storage to their customers. It connects to the remote-backups.com API and handles automatic provisioning, usage tracking, and billing integration.

Developed by [Nerdscave Hosting](https://www.nerdscave-hosting.com/)

## Features

- Automatic datastore provisioning when customers order backup storage
- Hourly usage tracking for accurate billing based on actual storage consumption
- Configurable pricing per 1000 GB with minimum and maximum size limits
- Admin dashboard showing all datastores, connection status, and usage statistics
- Client area integration displaying storage usage and connection credentials

## How It Works

The module consists of two components: an addon module for configuration and administration, and a server module for automatic provisioning.

### Addon Module

The addon module provides the admin interface and handles all configuration. When activated, it creates two database tables: one for mapping datastores to WHMCS services, and one for recording size changes over time.

Administrators configure their API token, set the price per 1000 GB, and define minimum and maximum datastore sizes. The dashboard displays all datastores from the remote-backups.com account, including current size, usage percentage, and links to associated WHMCS services.

### Server Module

The server module handles automatic provisioning. When a customer orders a product using this module, WHMCS calls the CreateAccount function which creates a new datastore via the API. The datastore ID is stored in the database and linked to the WHMCS service.

When a service is terminated, the module deletes the associated datastore. Upgrades and downgrades trigger a resize operation through the API.

### Hourly Usage Tracking

Remote-backups.com supports autoscaling, meaning datastore sizes can change automatically based on usage. To support accurate billing, the module includes a cron script that runs hourly. It queries all datastores from the API and compares their current size to the last recorded size. When a size change is detected, a new entry is written to the history table with a timestamp.

This allows billing calculations based on actual usage. For example, if a datastore was 500 GB for 200 hours and 1000 GB for 520 hours in a billing period, the invoice can reflect the prorated cost for each size tier.

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

### Step 3: Configure the Cron

Add the following line to your crontab to enable hourly usage tracking:

```
0 * * * * php -q /path/to/whmcs/modules/addons/remotebackups/cron.php
```

### Step 4: Create a Product

Go to Setup, then Products/Services, then Products/Services. Create a new product and under Module Settings, select Remote Backups. Configure:

- Datastore Size (GB): The size of the datastore for this product
- Name Prefix: A prefix for datastore names, for example "backup"

The module will create datastores named like "backup-client123-service456" to ensure uniqueness.

## API Endpoints

The module uses the following endpoints from the remote-backups.com API:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | /reseller/datastore | List all datastores |
| POST | /reseller/datastore | Create a new datastore |
| GET | /reseller/datastore/:id | Get datastore details |
| PATCH | /reseller/datastore/:id | Resize a datastore |
| DELETE | /reseller/datastore/:id | Delete a datastore |

Sizes are transmitted in GB directly. The API returns sizes in bytes, which the module converts back to GB for display.

Size constraints: minimum 500 GB, increments of 100 GB.

## Database Tables

The module creates two tables on activation:

**mod_remotebackups_datastores**: Maps datastore IDs from remote-backups.com to WHMCS service IDs. Also stores the current size for quick reference.

**mod_remotebackups_size_history**: Records size changes with timestamps. Only stores entries when a size change is detected, not every hour.

## Troubleshooting

### Connection Failed

Check that your API token is correct. Go to Addons, then Remote Backups, then click Test Connection to verify.

### Products Page Error

If you see an error about a missing file when opening the products page, ensure the module files are in the correct location. The server module at `/modules/servers/remotebackups/` must be able to access the addon module at `/modules/addons/remotebackups/`.

### Provisioning Fails

Check the module log in Utilities, then Logs, then Module Log. The module logs all API requests and responses for debugging.

## License

GPL-3.0-or-later

Copyright 2026 Moritz Mantel / Nerdscave Hosting

## Links

- [Nerdscave Hosting](https://www.nerdscave-hosting.com/)
- [remote-backups.com](https://remote-backups.com/)
- [remote-backups.com Documentation](https://docs-next.bennetg.de/products/remote-backups/remote_configuration)
