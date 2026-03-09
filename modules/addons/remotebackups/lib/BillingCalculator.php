<?php
/**
 * Remote Backups Billing Calculator
 *
 * Calculates prorated billing from the API's rescale-log.
 * Uses the actual hours in the billing period for accurate calculations.
 *
 * @package    Remote Backups WHMCS Module
 * @author     Moritz Mantel / Nerdscave Hosting
 * @copyright  2026 Nerdscave Hosting (https://www.nerdscave-hosting.com/)
 * @license    GPL-3.0-or-later
 */

namespace WHMCS\Module\Addon\RemoteBackups\Lib;

use WHMCS\Database\Capsule;

class BillingCalculator
{
    /**
     * Calculate the billable amount for a datastore in a given period.
     *
     * Uses the API's rescale-log entries instead of a local database table.
     * Each rescale-log entry has: from (GB), to (GB), createdAt (ISO 8601).
     *
     * IMPORTANT: Billing is based on PROVISIONED size, not used space.
     * Even an empty datastore is billed at its full provisioned size.
     *
     * @param string $datastoreId The datastore ID
     * @param array $rescaleLog Array of rescale-log entries from the API (newest first)
     * @param int $currentSizeGB Current datastore size from getDatastore()
     * @param string $datastoreCreatedAt Datastore creation timestamp (ISO 8601)
     * @param \DateTime $periodStart Start of billing period
     * @param \DateTime $periodEnd End of billing period
     * @param float $pricePerThousandGB Monthly price per 1000 GB
     * @return array
     */
    public static function calculate(
        string $datastoreId,
        array $rescaleLog,
        int $currentSizeGB,
        string $datastoreCreatedAt,
        \DateTime $periodStart,
        \DateTime $periodEnd,
        float $pricePerThousandGB
    ): array {
        // Total hours in the billing period
        $totalHours = ($periodEnd->getTimestamp() - $periodStart->getTimestamp()) / 3600;

        if ($totalHours <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid billing period',
                'amount' => 0,
            ];
        }

        // The rescale-log comes newest-first from the API, reverse it for chronological processing
        $log = array_reverse($rescaleLog);

        // Build a timeline of size changes from the rescale-log.
        // Each entry records when the size changed and what it changed to.
        // The initial size is the datastore's creation size.
        $timeline = [];

        // Determine initial size at creation:
        // If there are rescale-log entries, the first entry's "from" field is the size before the first resize.
        // If the log is empty, the datastore was never resized, so it has been at currentSizeGB since creation.
        $createdAt = new \DateTime($datastoreCreatedAt);

        if (!empty($log)) {
            $initialSize = (int) $log[0]['from'];
        } else {
            $initialSize = $currentSizeGB;
        }

        // Start timeline at creation with initial size
        $timeline[] = [
            'time' => $createdAt,
            'size_gb' => $initialSize,
        ];

        // Add each rescale event
        foreach ($log as $entry) {
            $timeline[] = [
                'time' => new \DateTime($entry['createdAt']),
                'size_gb' => (int) $entry['to'],
            ];
        }

        // Build segments within the billing period
        $segments = [];
        $currentSize = null;
        $currentStart = $periodStart;

        // Find the size at the start of the period
        foreach ($timeline as $event) {
            if ($event['time'] <= $periodStart) {
                $currentSize = $event['size_gb'];
            }
        }

        // If no event before period start, use initial size
        if ($currentSize === null) {
            $currentSize = $initialSize;
        }

        // Walk through timeline events within the period
        foreach ($timeline as $event) {
            // Skip events before or at the period start
            if ($event['time'] <= $periodStart) {
                $currentSize = $event['size_gb'];
                continue;
            }

            // Stop at period end
            if ($event['time'] >= $periodEnd) {
                break;
            }

            // Record segment from currentStart to this event
            $segmentHours = ($event['time']->getTimestamp() - $currentStart->getTimestamp()) / 3600;
            if ($segmentHours > 0) {
                $segments[] = [
                    'size_gb' => $currentSize,
                    'hours' => $segmentHours,
                    'from' => $currentStart->format('Y-m-d H:i:s'),
                    'to' => $event['time']->format('Y-m-d H:i:s'),
                ];
            }

            $currentSize = $event['size_gb'];
            $currentStart = $event['time'];

            // Stop after deletion — no further segments make sense
            if ($event['size_gb'] === 0) {
                break;
            }
        }

        // Final segment until period end
        $segmentHours = ($periodEnd->getTimestamp() - $currentStart->getTimestamp()) / 3600;
        if ($segmentHours > 0) {
            $segments[] = [
                'size_gb' => $currentSize,
                'hours' => $segmentHours,
                'from' => $currentStart->format('Y-m-d H:i:s'),
                'to' => $periodEnd->format('Y-m-d H:i:s'),
            ];
        }

        // Calculate weighted GB-hours
        $totalGBHours = 0;
        foreach ($segments as $segment) {
            $totalGBHours += $segment['size_gb'] * $segment['hours'];
        }

        // Calculate billable amount
        // Formula: (GB-hours / total hours in period) * (price per 1000 GB / 1000)
        $averageGB = $totalHours > 0 ? $totalGBHours / $totalHours : 0;
        $pricePerGB = $pricePerThousandGB / 1000;
        $amount = $averageGB * $pricePerGB;

        return [
            'success' => true,
            'datastore_id' => $datastoreId,
            'period_start' => $periodStart->format('Y-m-d H:i:s'),
            'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            'total_hours' => round($totalHours, 2),
            'segments' => $segments,
            'total_gb_hours' => round($totalGBHours, 2),
            'average_gb' => round($averageGB, 2),
            'price_per_1000gb' => $pricePerThousandGB,
            'amount' => round($amount, 2),
        ];
    }

    /**
     * Get the configured price per 1000 GB from addon settings
     *
     * @return float
     */
    public static function getPricePerThousandGB(): float
    {
        try {
            $settings = Capsule::table('tbladdonmodules')
                ->where('module', 'remotebackups')
                ->pluck('value', 'setting');

            return (float) ($settings['price_per_1000gb'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
