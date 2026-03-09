<?php
/**
 * Remote Backups WHMCS Hooks
 *
 * @package    Remote Backups WHMCS Module
 * @author     Moritz Mantel / Nerdscave Hosting
 * @copyright  2026 Nerdscave Hosting (https://www.nerdscave-hosting.com/)
 * @license    GPL-3.0-or-later
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

/**
 * Hook: After module configuration is saved
 * 
 * Test the API connection when settings are saved
 */
add_hook('AddonConfigSave', 1, function ($vars) {
    if ($vars['modulename'] !== 'remotebackups') {
        return;
    }

    // You could add validation here
    // The admin will see the test result on the dashboard
});

// Settings sidebar link removed — Settings and Prune Settings are now tabs
// on the main product details page, rendered inline by clientarea.tpl.

/**
 * Hook: Admin Area Footer Output
 * 
 * Add custom JS/CSS to admin area if viewing our module
 */
add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    // Only on our module page
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'addonmodules.php') === false) {
        return '';
    }

    if (($_GET['module'] ?? '') !== 'remotebackups') {
        return '';
    }

    // Add any custom scripts here
    return <<<HTML
<script>
// Remote Backups module custom scripts
console.log('Remote Backups WHMCS Module loaded');
</script>
HTML;
});

/**
 * Hook: Invoice Creation Pre Email
 * 
 * Calculate prorated billing from the API's rescale-log before invoice is sent.
 * Adjusts the line item amount based on actual datastore sizes during
 * the billing period.
 * 
 * IMPORTANT: Billing is based on PROVISIONED size, not actual used storage.
 * Even an empty datastore is billed at its full provisioned size.
 */
add_hook('InvoiceCreationPreEmail', 1, function ($vars) {
    $invoiceId = $vars['invoiceid'];

    require_once __DIR__ . '/lib/BillingCalculator.php';
    require_once __DIR__ . '/lib/Api/RemoteBackupsClient.php';

    try {
        // Get API token
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'remotebackups')
            ->pluck('value', 'setting');

        $apiToken = $settings['api_token'] ?? '';
        if (empty($apiToken)) {
            return; // No API token, skip billing
        }

        $client = new \WHMCS\Module\Addon\RemoteBackups\Api\RemoteBackupsClient($apiToken);

        // Get invoice items
        $invoiceItems = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->where('type', 'Hosting')
            ->get();

        foreach ($invoiceItems as $item) {
            // Check if this is a Remote Backups service
            $service = Capsule::table('tblhosting')
                ->where('id', $item->relid)
                ->first();

            if (!$service) {
                continue;
            }

            // Check if this service uses our server module
            $product = Capsule::table('tblproducts')
                ->where('id', $service->packageid)
                ->first();

            if (!$product || $product->servertype !== 'remotebackups') {
                continue;
            }

            // Get the datastore mapping
            $mapping = Capsule::table('mod_remotebackups_datastores')
                ->where('whmcs_service_id', $item->relid)
                ->first();

            if (!$mapping) {
                continue;
            }

            // Determine billing period
            $periodEnd = new \DateTime($service->nextduedate);

            $periodStart = clone $periodEnd;
            switch (strtolower($service->billingcycle)) {
                case 'monthly':
                    $periodStart->modify('-1 month');
                    break;
                case 'quarterly':
                    $periodStart->modify('-3 months');
                    break;
                case 'semi-annually':
                case 'semiannually':
                    $periodStart->modify('-6 months');
                    break;
                case 'annually':
                    $periodStart->modify('-1 year');
                    break;
                case 'biennially':
                    $periodStart->modify('-2 years');
                    break;
                case 'triennially':
                    $periodStart->modify('-3 years');
                    break;
                default:
                    $periodStart->modify('-1 month');
            }

            // Get price per 1000 GB from addon settings
            $pricePerThousandGB = \WHMCS\Module\Addon\RemoteBackups\Lib\BillingCalculator::getPricePerThousandGB();

            if ($pricePerThousandGB <= 0) {
                continue; // No pricing configured
            }

            // Fetch rescale-log and current datastore info from the API
            $datastoreId = $mapping->datastore_id;

            try {
                $datastore = $client->getDatastore($datastoreId);
                $currentSizeGB = \WHMCS\Module\Addon\RemoteBackups\Api\RemoteBackupsClient::getSizeInGB($datastore);
                $datastoreCreatedAt = $datastore['createdAt'] ?? $periodStart->format('c');

                // Fetch rescale-log covering the full billing period.
                // The API's range parameter is relative to now, so we need
                // (now - periodStart) + buffer, not (periodEnd - periodStart).
                $now = new \DateTime();
                $daysDiff = max(30, (int) ceil(($now->getTimestamp() - $periodStart->getTimestamp()) / 86400) + 7);
                $rescaleLog = $client->getRescaleLog($datastoreId, $daysDiff . 'd');
            } catch (\Exception $e) {
                logActivity("Remote Backups Billing: Could not fetch data for datastore {$datastoreId}: " . $e->getMessage());
                continue;
            }

            // Calculate prorated amount
            $result = \WHMCS\Module\Addon\RemoteBackups\Lib\BillingCalculator::calculate(
                $datastoreId,
                $rescaleLog,
                $currentSizeGB,
                $datastoreCreatedAt,
                $periodStart,
                $periodEnd,
                $pricePerThousandGB
            );

            if ($result['success'] && $result['amount'] > 0) {
                // Build detailed usage breakdown for invoice
                $usageDetails = "Usage-based billing:\n";
                $usageDetails .= "Period: " . $result['period_start'] . " to " . $result['period_end'] . "\n";
                $usageDetails .= "Size breakdown:\n";

                foreach ($result['segments'] as $segment) {
                    $fromDate = (new \DateTime($segment['from']))->format('d.m.Y H:i');
                    $toDate = (new \DateTime($segment['to']))->format('d.m.Y H:i');
                    $hours = round($segment['hours'], 1);
                    $usageDetails .= "  • {$segment['size_gb']} GB: {$fromDate} - {$toDate} ({$hours}h)\n";
                }

                $usageDetails .= "Average: {$result['average_gb']} GB over {$result['total_hours']} hours";

                // Update the invoice item with calculated amount
                Capsule::table('tblinvoiceitems')
                    ->where('id', $item->id)
                    ->update([
                        'amount' => $result['amount'],
                        'description' => $item->description . "\n" . $usageDetails,
                    ]);

                // Update invoice total
                $invoice = Capsule::table('tblinvoices')
                    ->where('id', $invoiceId)
                    ->first();

                if ($invoice) {
                    $oldAmount = $item->amount;
                    $difference = $result['amount'] - $oldAmount;

                    Capsule::table('tblinvoices')
                        ->where('id', $invoiceId)
                        ->update([
                            'subtotal' => $invoice->subtotal + $difference,
                            'total' => $invoice->total + $difference,
                        ]);
                }

                // Log the calculation for debugging
                logActivity(
                    "Remote Backups Billing: Service #{$item->relid} " .
                    "Datastore {$datastoreId}: " .
                    "{$result['average_gb']} GB avg × €{$pricePerThousandGB}/1000GB = €{$result['amount']} " .
                    "(was €{$item->amount})"
                );
            }
        }
    } catch (\Exception $e) {
        logActivity("Remote Backups Billing Error: " . $e->getMessage());
    }
});
