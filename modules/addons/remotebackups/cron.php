<?php
/**
 * Remote Backups Size Tracker Cron
 *
 * Run this script hourly via cron to track datastore size changes
 * for usage-based billing.
 *
 * Usage: php -q /path/to/whmcs/modules/addons/remotebackups/cron.php
 *
 * @package    Remote Backups WHMCS Module
 * @author     Moritz Mantel / Nerdscave Hosting
 * @copyright  2026 Nerdscave Hosting (https://www.nerdscave-hosting.com/)
 * @license    GPL-3.0-or-later
 */

// Change to WHMCS root directory
$whmcsRoot = dirname(__DIR__, 3); // Up 3 levels from modules/addons/remotebackups
chdir($whmcsRoot);

// Include WHMCS init
require_once $whmcsRoot . '/init.php';

use WHMCS\Database\Capsule;

// Include our API client
require_once __DIR__ . '/lib/Api/RemoteBackupsClient.php';

use WHMCS\Module\Addon\RemoteBackups\Api\RemoteBackupsClient;

/**
 * Main cron function
 */
function remotebackups_runSizeTracker(): array
{
    $log = [];

    // Get API token from addon settings
    try {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'remotebackups')
            ->pluck('value', 'setting');

        $apiToken = $settings['api_token'] ?? '';
    } catch (\Exception $e) {
        return ['error' => 'Failed to load addon settings: ' . $e->getMessage()];
    }

    if (empty($apiToken)) {
        return ['error' => 'API token not configured'];
    }

    // Initialize API client
    $client = new RemoteBackupsClient($apiToken);

    // Fetch all datastores from API
    try {
        $datastores = $client->listDatastores();
    } catch (\Exception $e) {
        return ['error' => 'API error: ' . $e->getMessage()];
    }

    $log['datastores_found'] = count($datastores);
    $log['size_changes'] = 0;
    $log['details'] = [];

    foreach ($datastores as $ds) {
        $datastoreId = $ds['_id'];
        $currentSizeGB = RemoteBackupsClient::getSizeInGB($ds);

        // Get last recorded size for this datastore
        $lastRecord = null;
        try {
            $lastRecord = Capsule::table('mod_remotebackups_size_history')
                ->where('datastore_id', $datastoreId)
                ->orderBy('recorded_at', 'desc')
                ->first();
        } catch (\Exception $e) {
            // Table might not exist, continue
        }

        $lastSizeGB = $lastRecord ? $lastRecord->size_gb : null;

        // Only record if size changed or no previous record
        if ($lastSizeGB === null || $lastSizeGB !== $currentSizeGB) {
            try {
                Capsule::table('mod_remotebackups_size_history')->insert([
                    'datastore_id' => $datastoreId,
                    'size_gb' => $currentSizeGB,
                    'recorded_at' => date('Y-m-d H:i:s'),
                ]);

                $log['size_changes']++;
                $log['details'][] = [
                    'datastore_id' => $datastoreId,
                    'previous_size' => $lastSizeGB,
                    'new_size' => $currentSizeGB,
                ];

                // Also update the mapping table current_size
                Capsule::table('mod_remotebackups_datastores')
                    ->where('datastore_id', $datastoreId)
                    ->update([
                        'current_size_gb' => $currentSizeGB,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

            } catch (\Exception $e) {
                $log['details'][] = [
                    'datastore_id' => $datastoreId,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    return $log;
}

// Run if executed directly
if (php_sapi_name() === 'cli') {
    echo "Remote Backups Size Tracker\n";
    echo "===========================\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    $result = remotebackups_runSizeTracker();

    if (isset($result['error'])) {
        echo "ERROR: " . $result['error'] . "\n";
        exit(1);
    }

    echo "Datastores found: " . $result['datastores_found'] . "\n";
    echo "Size changes recorded: " . $result['size_changes'] . "\n";

    if (!empty($result['details'])) {
        echo "\nDetails:\n";
        foreach ($result['details'] as $detail) {
            if (isset($detail['error'])) {
                echo "  - {$detail['datastore_id']}: ERROR - {$detail['error']}\n";
            } else {
                $prev = $detail['previous_size'] ?? 'N/A';
                echo "  - {$detail['datastore_id']}: {$prev} GB -> {$detail['new_size']} GB\n";
            }
        }
    }

    echo "\nDone.\n";
}
