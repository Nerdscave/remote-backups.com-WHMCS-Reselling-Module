<?php
/**
 * Remote Backups WHMCS Server Module (Provisioning)
 *
 * Handles automatic datastore provisioning for WHMCS product orders
 *
 * @package    Remote Backups WHMCS Module
 * @author     Moritz Mantel / Nerdscave Hosting
 * @copyright  2026 Nerdscave Hosting (https://www.nerdscave-hosting.com/)
 * @license    GPL-3.0-or-later
 * @see        https://developers.whmcs.com/provisioning-modules/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Include the addon's API client
// Path: /modules/servers/remotebackups/ -> /modules/addons/remotebackups/
require_once __DIR__ . '/../../addons/remotebackups/lib/Api/RemoteBackupsClient.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\RemoteBackups\Api\RemoteBackupsClient;

/**
 * Module metadata
 */
function remotebackups_MetaData(): array
{
    return [
        'DisplayName' => 'Remote Backups',
        'APIVersion' => '1.1',
        'RequiresServer' => false, // We use addon config, not server config
    ];
}

/**
 * Configuration options shown when creating a product
 */
function remotebackups_ConfigOptions(): array
{
    return [
        'datastore_size_gb' => [
            'FriendlyName' => 'Datastore Size (GB)',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '500',
            'Description' => 'Size of the datastore in GB',
        ],
        'datastore_name_prefix' => [
            'FriendlyName' => 'Name Prefix',
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'whmcs',
            'Description' => 'Prefix for datastore names (e.g., whmcs-client-123)',
        ],
    ];
}

/**
 * Get API client using addon module settings
 */
function remotebackups_getClient(): ?RemoteBackupsClient
{
    try {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'remotebackups')
            ->pluck('value', 'setting');

        $apiToken = $settings['api_token'] ?? '';

        if (empty($apiToken)) {
            return null;
        }

        return new RemoteBackupsClient($apiToken);
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Get addon module settings
 */
function remotebackups_getAddonSettings(): array
{
    try {
        return Capsule::table('tbladdonmodules')
            ->where('module', 'remotebackups')
            ->pluck('value', 'setting')
            ->toArray();
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Create a new datastore for a service
 */
function remotebackups_CreateAccount(array $params): string
{
    $client = remotebackups_getClient();
    if (!$client) {
        return 'API token not configured in Remote Backups addon module';
    }

    $serviceId = $params['serviceid'];
    $clientId = $params['userid'];
    $domain = $params['domain'];
    $sizeGB = (int) ($params['configoption1'] ?? 500);
    $prefix = $params['configoption2'] ?? 'whmcs';

    // Validate against addon limits
    $addonSettings = remotebackups_getAddonSettings();
    $minSize = (int) ($addonSettings['min_size_gb'] ?? 100);
    $maxSize = (int) ($addonSettings['max_size_gb'] ?? 10000);

    if ($sizeGB < $minSize) {
        return "Size {$sizeGB}GB is below minimum of {$minSize}GB";
    }
    if ($sizeGB > $maxSize) {
        return "Size {$sizeGB}GB exceeds maximum of {$maxSize}GB";
    }

    // Generate unique datastore name
    $friendlyName = sprintf('%s-client%d-service%d', $prefix, $clientId, $serviceId);

    try {
        // Create datastore via API
        $datastore = $client->createDatastore($friendlyName, $sizeGB);

        $datastoreId = $datastore['_id'];

        // Store mapping in our database
        Capsule::table('mod_remotebackups_datastores')->insert([
            'datastore_id' => $datastoreId,
            'whmcs_service_id' => $serviceId,
            'current_size_gb' => $sizeGB,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record initial size in history
        Capsule::table('mod_remotebackups_size_history')->insert([
            'datastore_id' => $datastoreId,
            'size_gb' => $sizeGB,
            'recorded_at' => date('Y-m-d H:i:s'),
        ]);

        // Store datastore ID in service custom field or dedicated field
        // This allows retrieval even if our mapping table fails
        Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->update(['dedicatedip' => $datastoreId]); // Using dedicatedip field for storage

        logModuleCall(
            'remotebackups',
            'CreateAccount',
            $params,
            $datastore,
            'Datastore created: ' . $datastoreId
        );

        return 'success';

    } catch (\Exception $e) {
        logModuleCall(
            'remotebackups',
            'CreateAccount',
            $params,
            $e->getMessage(),
            'Error creating datastore'
        );
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate/delete a datastore
 */
function remotebackups_TerminateAccount(array $params): string
{
    $client = remotebackups_getClient();
    if (!$client) {
        return 'API token not configured in Remote Backups addon module';
    }

    $serviceId = $params['serviceid'];

    // Get datastore ID from our mapping
    $datastoreId = remotebackups_getDatastoreId($serviceId);

    if (!$datastoreId) {
        return 'No datastore linked to this service';
    }

    try {
        // Delete via API
        $client->deleteDatastore($datastoreId);

        // Remove from our mapping table
        Capsule::table('mod_remotebackups_datastores')
            ->where('datastore_id', $datastoreId)
            ->delete();

        // Clear from service
        Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->update(['dedicatedip' => '']);

        logModuleCall(
            'remotebackups',
            'TerminateAccount',
            $params,
            'success',
            'Datastore deleted: ' . $datastoreId
        );

        return 'success';

    } catch (\Exception $e) {
        logModuleCall(
            'remotebackups',
            'TerminateAccount',
            $params,
            $e->getMessage(),
            'Error deleting datastore'
        );
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend account - not supported by API, just log
 */
function remotebackups_SuspendAccount(array $params): string
{
    $serviceId = $params['serviceid'];
    $datastoreId = remotebackups_getDatastoreId($serviceId);

    logModuleCall(
        'remotebackups',
        'SuspendAccount',
        $params,
        'Suspension logged (API does not support suspend)',
        'Datastore: ' . ($datastoreId ?? 'N/A')
    );

    // Remote Backups API doesn't have suspend functionality
    // We just mark it in WHMCS, the datastore remains accessible
    return 'success';
}

/**
 * Unsuspend account
 */
function remotebackups_UnsuspendAccount(array $params): string
{
    logModuleCall(
        'remotebackups',
        'UnsuspendAccount',
        $params,
        'Unsuspension logged',
        null
    );

    return 'success';
}

/**
 * Upgrade/Downgrade - resize datastore
 */
function remotebackups_ChangePackage(array $params): string
{
    $client = remotebackups_getClient();
    if (!$client) {
        return 'API token not configured in Remote Backups addon module';
    }

    $serviceId = $params['serviceid'];
    $newSizeGB = (int) ($params['configoption1'] ?? 500);

    // Validate against addon limits
    $addonSettings = remotebackups_getAddonSettings();
    $minSize = (int) ($addonSettings['min_size_gb'] ?? 100);
    $maxSize = (int) ($addonSettings['max_size_gb'] ?? 10000);

    if ($newSizeGB < $minSize) {
        return "Size {$newSizeGB}GB is below minimum of {$minSize}GB";
    }
    if ($newSizeGB > $maxSize) {
        return "Size {$newSizeGB}GB exceeds maximum of {$maxSize}GB";
    }

    $datastoreId = remotebackups_getDatastoreId($serviceId);

    if (!$datastoreId) {
        return 'No datastore linked to this service';
    }

    try {
        // Resize via API
        $result = $client->resizeDatastore($datastoreId, $newSizeGB);

        // Update our mapping
        Capsule::table('mod_remotebackups_datastores')
            ->where('datastore_id', $datastoreId)
            ->update([
                'current_size_gb' => $newSizeGB,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        // Record size change in history
        Capsule::table('mod_remotebackups_size_history')->insert([
            'datastore_id' => $datastoreId,
            'size_gb' => $newSizeGB,
            'recorded_at' => date('Y-m-d H:i:s'),
        ]);

        logModuleCall(
            'remotebackups',
            'ChangePackage',
            $params,
            $result,
            'Datastore resized to ' . $newSizeGB . 'GB'
        );

        return 'success';

    } catch (\Exception $e) {
        logModuleCall(
            'remotebackups',
            'ChangePackage',
            $params,
            $e->getMessage(),
            'Error resizing datastore'
        );
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Admin area service detail output
 */
function remotebackups_AdminServicesTabFields(array $params): array
{
    $serviceId = $params['serviceid'];
    $datastoreId = remotebackups_getDatastoreId($serviceId);

    $fields = [
        'Datastore ID' => $datastoreId ?? 'Not linked',
    ];

    if ($datastoreId) {
        $client = remotebackups_getClient();
        if ($client) {
            try {
                $ds = $client->getDatastore($datastoreId);
                $sizeGB = RemoteBackupsClient::getSizeInGB($ds);
                $usedGB = RemoteBackupsClient::getUsedInGB($ds);
                $usagePercent = $sizeGB > 0 ? round(($usedGB / $sizeGB) * 100, 1) : 0;

                $fields['Datastore Name'] = $ds['friendly'] ?? 'N/A';
                $fields['Size'] = $sizeGB . ' GB';
                $fields['Used'] = $usedGB . ' GB (' . $usagePercent . '%)';
                $fields['Status'] = 'Active';
            } catch (\Exception $e) {
                $fields['API Status'] = 'Error: ' . $e->getMessage();
            }
        }
    }

    return $fields;
}

/**
 * Client area output
 */
function remotebackups_ClientArea(array $params): array
{
    $serviceId = $params['serviceid'];
    $datastoreId = remotebackups_getDatastoreId($serviceId);

    $templateVars = [
        'serviceid' => $serviceId,
        'datastore_id' => $datastoreId ?? 'Not available',
        'size_gb' => 0,
        'used_gb' => 0,
        'usage_percent' => 0,
        'friendly_name' => 'N/A',
        'server_hostname' => '',
        'server_ip' => '',
        'server_ip6' => '',
        'server_fingerprint' => '',
        'metrics' => [],
        'metrics_json' => '[]',
    ];

    if ($datastoreId) {
        $client = remotebackups_getClient();
        if ($client) {
            try {
                $ds = $client->getDatastore($datastoreId);
                $sizeGB = RemoteBackupsClient::getSizeInGB($ds);
                $usedGB = RemoteBackupsClient::getUsedInGB($ds);

                $templateVars['size_gb'] = $sizeGB;
                $templateVars['used_gb'] = $usedGB;
                $templateVars['usage_percent'] = $sizeGB > 0 ? round(($usedGB / $sizeGB) * 100, 1) : 0;
                $templateVars['friendly_name'] = $ds['friendly'] ?? 'N/A';
                $templateVars['datastore_user'] = $ds['datastoreUser'] ?? '';
                $templateVars['datastore_password'] = $ds['datastoreUserPassword'] ?? '';

                // Server connection info
                if (isset($ds['server'])) {
                    $templateVars['server_hostname'] = $ds['server']['hostname'] ?? '';
                    $templateVars['server_ip'] = $ds['server']['ip'] ?? '';
                    $templateVars['server_ip6'] = $ds['server']['ip6'] ?? '';
                    $templateVars['server_fingerprint'] = $ds['server']['fingerprint'] ?? '';
                }

                // Metrics for usage graph (last 48 data points = ~4 days)
                if (isset($ds['metrics']) && is_array($ds['metrics'])) {
                    $metrics = array_slice($ds['metrics'], -48);
                    $templateVars['metrics'] = $metrics;
                    $templateVars['metrics_json'] = json_encode($metrics);
                }
            } catch (\Exception $e) {
                $templateVars['error'] = $e->getMessage();
            }
        }
    }

    return [
        'tabOverviewReplacementTemplate' => 'templates/clientarea.tpl',
        'templateVariables' => $templateVars,
    ];
}

/**
 * Helper: Get datastore ID for a service
 */
function remotebackups_getDatastoreId(int $serviceId): ?string
{
    // Try our mapping table first
    try {
        $mapping = Capsule::table('mod_remotebackups_datastores')
            ->where('whmcs_service_id', $serviceId)
            ->first();

        if ($mapping) {
            return $mapping->datastore_id;
        }
    } catch (\Exception $e) {
        // Mapping table might not exist
    }

    // Fallback to dedicatedip field
    try {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if ($service && !empty($service->dedicatedip)) {
            return $service->dedicatedip;
        }
    } catch (\Exception $e) {
        // Ignore
    }

    return null;
}
