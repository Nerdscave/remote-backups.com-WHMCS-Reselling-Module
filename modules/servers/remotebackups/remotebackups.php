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
    $minSize = (int) ($addonSettings['min_size_gb'] ?? 500);
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
    $minSize = (int) ($addonSettings['min_size_gb'] ?? 500);
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
 * 
 * Supports multiple tabs:
 * - Overview (default): Shows connection details and usage
 * - Settings: Allows resize and autoscaling configuration
 */
function remotebackups_ClientArea(array $params): array
{
    $serviceId = $params['serviceid'];
    $datastoreId = remotebackups_getDatastoreId($serviceId);
    $requestedAction = $_REQUEST['customAction'] ?? '';
    $graphRange = $_REQUEST['graphRange'] ?? 'hour';

    // Get addon settings for min/max size
    $addonSettings = [];
    try {
        $addonSettings = Capsule::table('tbladdonmodules')
            ->where('module', 'remotebackups')
            ->pluck('value', 'setting');
    } catch (\Exception $e) {
        // Ignore
    }
    $minSizeGB = (int) ($addonSettings['min_size_gb'] ?? 500);
    $maxSizeGB = (int) ($addonSettings['max_size_gb'] ?? 10000);

    // Handle Settings tab
    if ($requestedAction === 'settings') {
        return remotebackups_ClientAreaSettings($params, $datastoreId, $minSizeGB, $maxSizeGB);
    }

    // Handle save settings action
    if ($requestedAction === 'saveSettings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        return remotebackups_ClientAreaSaveSettings($params, $datastoreId, $minSizeGB, $maxSizeGB);
    }

    // Handle save prune settings action
    if ($requestedAction === 'savePruneSettings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        return remotebackups_ClientAreaSavePruneSettings($params, $datastoreId);
    }

    // Default: Overview tab
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
        'graph_range' => $graphRange ?? 'hour',
    ];

    if (isset($_GET['success'])) {
        if ($_GET['success'] === 'settings') {
            $templateVars['settings_saved'] = true;
        } elseif ($_GET['success'] === 'prune') {
            $templateVars['prune_settings_saved'] = true;
        }
    }

    if (isset($_GET['error'])) {
        if ($_GET['error'] === 'settings_rate_limit') {
            $templateVars['settings_error'] = true;
            $templateVars['error'] = 'Please wait at least 5 seconds before saving settings again.';
        } elseif ($_GET['error'] === 'prune_rate_limit') {
            $templateVars['prune_settings_error'] = true;
            $templateVars['error'] = 'Please wait at least 5 seconds before saving prune settings again.';
        }
    }

    if ($datastoreId) {
        $client = remotebackups_getClient();
        if ($client) {
            try {
                $ds = $client->getDatastore($datastoreId);
                $sizeGB = RemoteBackupsClient::getSizeInGB($ds);
                $usedGB = RemoteBackupsClient::getUsedInGB($ds);

                $templateVars['size_gb'] = $sizeGB;
                $templateVars['used_gb'] = $usedGB;
                $templateVars['available_gb'] = $sizeGB - $usedGB;
                $templateVars['usage_percent'] = $sizeGB > 0 ? round(($usedGB / $sizeGB) * 100, 1) : 0;
                $templateVars['friendly_name'] = $ds['friendly'] ?? 'N/A';
                $templateVars['datastore_user'] = $ds['datastoreUser'] ?? '';
                // Use datastorePassword (not datastoreUserPassword) - matches Remote-Backup panel
                $templateVars['datastore_password'] = $ds['datastorePassword'] ?? '';

                // Server connection info
                if (isset($ds['server'])) {
                    $templateVars['server_hostname'] = $ds['server']['hostname'] ?? '';
                    $templateVars['server_ip'] = $ds['server']['ip'] ?? '';
                    $templateVars['server_ip6'] = $ds['server']['ip6'] ?? '';
                    $templateVars['server_fingerprint'] = $ds['server']['fingerprint'] ?? '';
                }

                // Graph data from dedicated graph endpoint
                $validRanges = ['hour', 'day', 'week', 'month', 'decade'];
                $safeRange = in_array($graphRange, $validRanges) ? $graphRange : 'hour';
                try {
                    $graphData = $client->getDatastoreGraph($datastoreId, $safeRange);
                    $templateVars['metrics'] = $graphData;
                    $templateVars['metrics_json'] = json_encode($graphData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                } catch (\Exception $e) {
                    // Graph data is non-critical, don't fail the whole page
                    $templateVars['metrics'] = [];
                    $templateVars['metrics_json'] = '[]';
                }
                $templateVars['graph_range'] = $safeRange;

                // Settings data for inline settings tab
                $templateVars['autoscaling_enabled'] = $ds['autoscalingEnabled'] ?? false;
                $templateVars['autoscaling_scale_up_only'] = $ds['autoscalingScaleUpOnly'] ?? false;
                $templateVars['autoscaling_lower_threshold'] = $ds['autoscalingLowerThreshold'] ?? 70;
                $templateVars['autoscaling_upper_threshold'] = $ds['autoscalingUpperThreshold'] ?? 80;
                $templateVars['bandwidth_limit'] = $ds['speed'] ?? 500;
                $templateVars['min_size_gb'] = $minSizeGB;
                $templateVars['max_size_gb'] = $maxSizeGB;

                // Load Prune Settings
                $gc = $ds['gc'] ?? [];
                $templateVars['prune_keep_last'] = $gc['keep-last'] ?? '';
                $templateVars['prune_keep_hourly'] = $gc['keep-hourly'] ?? '';
                $templateVars['prune_keep_daily'] = $gc['keep-daily'] ?? '';
                $templateVars['prune_keep_weekly'] = $gc['keep-weekly'] ?? '';
                $templateVars['prune_keep_monthly'] = $gc['keep-monthly'] ?? '';
                $templateVars['prune_keep_yearly'] = $gc['keep-yearly'] ?? '';

                // Parse Schedule
                $schedule = trim($gc['schedule'] ?? '');
                $parsedDays = [];
                $parsedHours = [];

                if ($schedule !== '') {
                    if ($schedule === 'daily') {
                        $parsedDays = explode(',', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
                        $parsedHours = ['00:00'];
                    } elseif ($schedule === 'hourly') {
                        $parsedDays = explode(',', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
                        $parsedHours = explode(',', '00:00,01:00,02:00,03:00,04:00,05:00,06:00,07:00,08:00,09:00,10:00,11:00,12:00,13:00,14:00,15:00,16:00,17:00,18:00,19:00,20:00,21:00,22:00,23:00');
                    } else {
                        $parts = explode(' ', $schedule);
                        $daysPart = '*';
                        $hoursPart = '00:00';
                        if (count($parts) === 2) {
                            $daysPart = $parts[0];
                            $hoursPart = $parts[1];
                        } elseif (count($parts) === 1) {
                            if (strpos($parts[0], ':') !== false || (strpos($parts[0], '*') !== false && !preg_match('/[a-zA-Z]/', $parts[0]))) {
                                $hoursPart = $parts[0];
                                $daysPart = '*';
                            } else {
                                $daysPart = $parts[0];
                            }
                        }

                        if ($daysPart === '*') {
                            $parsedDays = explode(',', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
                        } else {
                            $daysStr = str_ireplace('mon..fri', 'Mon,Tue,Wed,Thu,Fri', $daysPart);
                            $parsedDays = array_map(function ($v) {
                                return ucfirst(strtolower(trim($v)));
                            }, explode(',', $daysStr));
                        }

                        if ($hoursPart === '*') {
                            $parsedHours = explode(',', '00:00,01:00,02:00,03:00,04:00,05:00,06:00,07:00,08:00,09:00,10:00,11:00,12:00,13:00,14:00,15:00,16:00,17:00,18:00,19:00,20:00,21:00,22:00,23:00');
                        } else {
                            $rawHours = array_map('trim', explode(',', $hoursPart));
                            $parsedHours = [];
                            foreach ($rawHours as $hr) {
                                if (preg_match('/^(\d{1,2})(:\d{1,2})?$/', $hr, $m)) {
                                    $parsedHours[] = sprintf('%02d:00', $m[1]);
                                } else {
                                    $parsedHours[] = $hr;
                                }
                            }
                        }
                    }
                }

                $templateVars['prune_schedule_days'] = $parsedDays;
                $templateVars['prune_schedule_hours'] = $parsedHours;
            } catch (\Exception $e) {
                $templateVars['error'] = $e->getMessage();
            }
        }
    }

    // Add WHMCS billing/contract variables from params
    // Use model if available (WHMCS 8+) or fall back to params
    $model = $params['model'] ?? null;

    $templateVars['regdate'] = $model ? $model->regdate : ($params['regdate'] ?? '');
    $templateVars['nextduedate'] = $model ? $model->nextduedate : ($params['nextduedate'] ?? '');
    $templateVars['billingcycle'] = $model ? $model->billingcycle : ($params['billingcycle'] ?? '');
    $templateVars['firstpaymentamount'] = $model ? '€' . number_format($model->firstpaymentamount, 2) : ($params['firstpaymentamount'] ?? '');
    $templateVars['status'] = $model ? $model->domainstatus : ($params['status'] ?? '');
    $templateVars['paymentmethod'] = $params['paymentmethod'] ?? '';
    $templateVars['product'] = $params['product']['name'] ?? ($params['productname'] ?? 'Remote Backup Datastore');
    $templateVars['orderid'] = $model ? $model->orderid : ($params['orderid'] ?? '');

    // Add pricing info for resize preview
    // Load from addon settings directly (BillingCalculator is in addon module, not available here)
    try {
        $addonSettings = Capsule::table('tbladdonmodules')
            ->where('module', 'remotebackups')
            ->where('setting', 'price_per_1000gb')
            ->first();
        $templateVars['price_per_1000gb'] = (float) ($addonSettings->value ?? 10);
    } catch (\Exception $e) {
        $templateVars['price_per_1000gb'] = 10; // Default fallback
    }

    // Pass CSRF token to template for form protection
    $templateVars['token'] = $_SESSION['token'] ?? '';

    return [
        'tabOverviewReplacementTemplate' => 'templates/clientarea.tpl',
        'templateVariables' => $templateVars,
        // No sidebar tabs needed - all tabs are inline in the template
    ];
}

/**
 * Settings tab handler
 */
function remotebackups_ClientAreaSettings(array $params, ?string $datastoreId, int $minSizeGB, int $maxSizeGB): array
{
    $serviceId = $params['serviceid'];
    $templateVars = [
        'serviceid' => $serviceId,
        'datastore_id' => $datastoreId,
        'current_size_gb' => 500,
        'min_size_gb' => $minSizeGB,
        'max_size_gb' => $maxSizeGB,
        'autoscaling_enabled' => false,
        'autoscaling_scale_up_only' => false,
        'autoscaling_lower_threshold' => 70,
        'autoscaling_upper_threshold' => 80,
        'bandwidth_limit' => 500,
    ];

    if ($datastoreId) {
        $client = remotebackups_getClient();
        if ($client) {
            try {
                $ds = $client->getDatastore($datastoreId);
                $templateVars['current_size_gb'] = RemoteBackupsClient::getSizeInGB($ds);
                $templateVars['autoscaling_enabled'] = $ds['autoscalingEnabled'] ?? false;
                $templateVars['autoscaling_scale_up_only'] = $ds['autoscalingScaleUpOnly'] ?? false;
                $templateVars['autoscaling_lower_threshold'] = $ds['autoscalingLowerThreshold'] ?? 70;
                $templateVars['autoscaling_upper_threshold'] = $ds['autoscalingUpperThreshold'] ?? 80;
                $templateVars['bandwidth_limit'] = $ds['speed'] ?? 500;

                $gc = $ds['gc'] ?? [];
                $templateVars['prune_keep_last'] = $gc['keep-last'] ?? '';
                $templateVars['prune_keep_hourly'] = $gc['keep-hourly'] ?? '';
                $templateVars['prune_keep_daily'] = $gc['keep-daily'] ?? '';
                $templateVars['prune_keep_weekly'] = $gc['keep-weekly'] ?? '';
                $templateVars['prune_keep_monthly'] = $gc['keep-monthly'] ?? '';
                $templateVars['prune_keep_yearly'] = $gc['keep-yearly'] ?? '';

                // Parse Schedule
                $schedule = trim($gc['schedule'] ?? '');
                $parsedDays = [];
                $parsedHours = [];

                if ($schedule !== '') {
                    if ($schedule === 'daily') {
                        $parsedDays = explode(',', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
                        $parsedHours = ['00:00'];
                    } elseif ($schedule === 'hourly') {
                        $parsedDays = explode(',', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
                        $parsedHours = explode(',', '00:00,01:00,02:00,03:00,04:00,05:00,06:00,07:00,08:00,09:00,10:00,11:00,12:00,13:00,14:00,15:00,16:00,17:00,18:00,19:00,20:00,21:00,22:00,23:00');
                    } else {
                        $parts = explode(' ', $schedule);
                        $daysPart = '*';
                        $hoursPart = '00:00';
                        if (count($parts) === 2) {
                            $daysPart = $parts[0];
                            $hoursPart = $parts[1];
                        } elseif (count($parts) === 1) {
                            if (strpos($parts[0], ':') !== false || (strpos($parts[0], '*') !== false && !preg_match('/[a-zA-Z]/', $parts[0]))) {
                                $hoursPart = $parts[0];
                                $daysPart = '*';
                            } else {
                                $daysPart = $parts[0];
                            }
                        }

                        if ($daysPart === '*') {
                            $parsedDays = explode(',', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
                        } else {
                            $daysStr = str_ireplace('mon..fri', 'Mon,Tue,Wed,Thu,Fri', $daysPart);
                            $parsedDays = array_map(function ($v) {
                                return ucfirst(strtolower(trim($v)));
                            }, explode(',', $daysStr));
                        }

                        if ($hoursPart === '*') {
                            $parsedHours = explode(',', '00:00,01:00,02:00,03:00,04:00,05:00,06:00,07:00,08:00,09:00,10:00,11:00,12:00,13:00,14:00,15:00,16:00,17:00,18:00,19:00,20:00,21:00,22:00,23:00');
                        } else {
                            $rawHours = array_map('trim', explode(',', $hoursPart));
                            $parsedHours = [];
                            foreach ($rawHours as $hr) {
                                if (preg_match('/^(\d{1,2})(:\d{1,2})?$/', $hr, $m)) {
                                    $parsedHours[] = sprintf('%02d:00', $m[1]);
                                } else {
                                    $parsedHours[] = $hr;
                                }
                            }
                        }
                    }
                }

                $templateVars['prune_schedule_days'] = $parsedDays;
                $templateVars['prune_schedule_hours'] = $parsedHours;
            } catch (\Exception $e) {
                $templateVars['error'] = $e->getMessage();
            }
        }
    }

    // Settings tab is now inline in clientarea.tpl - redirect to main view
    // This function is only called for legacy ?customAction=settings URLs
    unset($_REQUEST['customAction']);
    return remotebackups_ClientArea($params);
}

/**
 * Save settings action handler
 */
function remotebackups_ClientAreaSaveSettings(array $params, ?string $datastoreId, int $minSizeGB, int $maxSizeGB): array
{
    $serviceId = $params['serviceid'];

    // Prevent infinite recursion when calling remotebackups_ClientArea
    unset($_REQUEST['customAction']);

    // CSRF protection
    if (!isset($_POST['token']) || $_POST['token'] !== ($_SESSION['token'] ?? '')) {
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['settings_error'] = true;
        $result['templateVariables']['error'] = 'Invalid request token. Please try again.';
        return $result;
    }

    // Basic rate limit to prevent API spamming (Settings)
    if (isset($_SESSION['rb_last_settings_update']) && time() - $_SESSION['rb_last_settings_update'] < 5) {
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['settings_error'] = true;
        $result['templateVariables']['error'] = 'Please wait at least 5 seconds before saving settings again.';
        return $result;
    }
    $_SESSION['rb_last_settings_update'] = time();

    if (!$datastoreId) {
        return remotebackups_ClientAreaSettings($params, $datastoreId, $minSizeGB, $maxSizeGB);
    }

    $client = remotebackups_getClient();
    if (!$client) {
        $templateVars = remotebackups_ClientAreaSettings($params, $datastoreId, $minSizeGB, $maxSizeGB)['templateVariables'];
        $templateVars['error'] = 'API client not available';
        return ['templatefile' => 'templates/settings', 'templateVariables' => $templateVars];
    }

    try {
        // Get current datastore data
        $ds = $client->getDatastore($datastoreId);

        // Prepare update data
        $newSizeGB = isset($_POST['size_gb'])
            ? (int) $_POST['size_gb']
            : RemoteBackupsClient::getSizeInGB($ds);
        $newSizeGB = (int) round($newSizeGB / 100) * 100;
        $newSizeGB = max($minSizeGB, min($maxSizeGB, $newSizeGB));

        $updateData = [
            'friendly' => $ds['friendly'],
            'size' => $newSizeGB,
            'autoscalingEnabled' => ($_POST['autoscaling_enabled'] ?? '0') === '1',
            'autoscalingScaleUpOnly' => ($_POST['scale_up_only'] ?? '0') === '1',
            'autoscalingLowerThreshold' => (int) ($_POST['lower_threshold'] ?? 70),
            'autoscalingUpperThreshold' => (int) ($_POST['upper_threshold'] ?? 80),
            'speed' => (int) ($_POST['bandwidth_limit'] ?? 500),
        ];

        // Call API to update all settings including autoscaling
        $client->updateDatastore($datastoreId, $updateData);

        // Log the action
        logModuleCall('remotebackups', 'saveSettings', json_encode($updateData), 'Success');

        // Return to main client area with success message and a JS redirect to clean POST state
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['settings_saved'] = true;
        $result['templateVariables']['js_redirect'] = "clientarea.php?action=productdetails&id=" . $serviceId . "&success=settings";
        return $result;

    } catch (\Exception $e) {
        logModuleCall('remotebackups', 'saveSettings', json_encode($_POST), 'Error: ' . $e->getMessage());

        // Return to main client area with error message
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['settings_error'] = true;
        return $result;
    }
}

/**
 * Save prune settings action handler
 */
function remotebackups_ClientAreaSavePruneSettings(array $params, ?string $datastoreId): array
{
    $serviceId = $params['serviceid'];

    // Prevent infinite recursion when calling remotebackups_ClientArea
    unset($_REQUEST['customAction']);

    // CSRF protection
    if (!isset($_POST['token']) || $_POST['token'] !== ($_SESSION['token'] ?? '')) {
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['prune_settings_error'] = true;
        $result['templateVariables']['error'] = 'Invalid request token. Please try again.';
        return $result;
    }

    // Basic rate limit to prevent API spamming (Prune Settings)
    if (isset($_SESSION['rb_last_prune_update']) && time() - $_SESSION['rb_last_prune_update'] < 5) {
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['prune_settings_error'] = true;
        $result['templateVariables']['error'] = 'Please wait at least 5 seconds before saving prune settings again. (Rate Limited)';
        return $result;
    }
    $_SESSION['rb_last_prune_update'] = time();

    if (!$datastoreId) {
        return remotebackups_ClientArea($params);
    }

    $client = remotebackups_getClient();
    if (!$client) {
        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['error'] = 'API client not available';
        return $result;
    }

    try {
        // Retention limit handling:
        // - Positive integer → set that limit
        // - Empty or 0 → send 0, which tells the API to clear the limit (it won't forward 0 to PBS)
        // We always send every field so the API can distinguish "keep this limit" from "remove it".
        $keepLast = [];
        $keepFields = [
            'keep_last' => 'keep-last',
            'keep_hourly' => 'keep-hourly',
            'keep_daily' => 'keep-daily',
            'keep_weekly' => 'keep-weekly',
            'keep_monthly' => 'keep-monthly',
            'keep_yearly' => 'keep-yearly',
        ];

        foreach ($keepFields as $postKey => $apiKey) {
            $val = $_POST[$postKey] ?? '';
            if (is_numeric($val) && (int) $val > 0) {
                $keepLast[$apiKey] = (int) $val;
            } else {
                // Send 0 to clear the limit. Omitting the key would leave the old value in place.
                $keepLast[$apiKey] = 0;
            }
        }

        // API Requirement: If no limits are set, the Reseller API expects an empty object `{}`. 
        // If we pass an empty PHP array `[]`, json_encode outputs `[]` which the API rejects as invalid type.
        // Forcing a stdClass ensures `json_encode` creates `{}`.
        if (empty($keepLast)) {
            $keepLast = new \stdClass();
        }

        $days = $_POST['prune_schedule_days'] ?? [];
        $hours = $_POST['prune_schedule_hours'] ?? [];

        if (!is_array($days)) {
            $days = [$days];
        }
        if (!is_array($hours)) {
            $hours = [$hours];
        }

        $days = array_filter(array_map('trim', $days), function ($v) {
            return $v !== '';
        });
        $hours = array_filter(array_map('trim', $hours), function ($v) {
            return $v !== '';
        });

        if (empty($days)) {
            $daysStr = 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
        } else {
            $allowedDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $validDays = array_filter(array_map(function ($v) use ($allowedDays) {
                $n = ucfirst(strtolower(trim($v)));
                return in_array($n, $allowedDays, true) ? $n : null;
            }, $days));
            $daysStr = implode(',', $validDays) ?: 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
        }

        if (empty($hours)) {
            $hoursStr = '00:00';
        } else {
            // Dashboard format: "Wed,Thu 0,1,2,16:00" 
            // We strip ":00" from the WHMCS form values, remove leading zeros, and append a single ":00" at the end.
            $hourInts = array_map(function ($h) {
                return (int) str_replace(':00', '', $h);
            }, $hours);
            $hoursStr = implode(',', $hourInts) . ':00';
        }

        $targetSchedule = $daysStr . ' ' . $hoursStr;

        // The Remote Backups API has a quirk where it expects the schedule string
        // BOTH inside the keepLast object (as "schedule") AND on the root level (as "targetSchedule").
        if ($keepLast instanceof \stdClass) {
            $keepLast->schedule = $targetSchedule;
        } else {
            $keepLast['schedule'] = $targetSchedule;
        }

        $client->updatePruneSettings($datastoreId, $keepLast, trim($targetSchedule));

        logModuleCall('remotebackups', 'savePruneSettings', json_encode(['keep' => $keepLast, 'schedule' => $targetSchedule]), 'Success');

        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['prune_settings_saved'] = true;
        $result['templateVariables']['js_redirect'] = "clientarea.php?action=productdetails&id=" . $serviceId . "&success=prune";
        return $result;

    } catch (\Exception $e) {
        logModuleCall('remotebackups', 'savePruneSettings', json_encode($_POST), 'Error: ' . $e->getMessage());

        $result = remotebackups_ClientArea($params);
        $result['templateVariables']['prune_settings_error'] = true;
        $result['templateVariables']['error'] = $e->getMessage();
        return $result;
    }
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
