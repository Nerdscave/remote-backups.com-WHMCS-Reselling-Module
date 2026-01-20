<?php
/**
 * Remote Backups Billing Calculator
 *
 * Calculates prorated billing based on size history.
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
     * IMPORTANT: Billing is based on PROVISIONED size, not used space.
     * Even an empty datastore is billed at its full provisioned size.
     *
     * @param string $datastoreId The datastore ID
     * @param \DateTime $periodStart Start of billing period
     * @param \DateTime $periodEnd End of billing period
     * @param float $pricePerThousandGB Monthly price per 1000 GB
     * @return array
     */
    public static function calculate(
        string $datastoreId,
        \DateTime $periodStart,
        \DateTime $periodEnd,
        float $pricePerThousandGB
    ): array {
        // Get all size records for this datastore, ordered by time
        $sizeHistory = Capsule::table('mod_remotebackups_size_history')
            ->where('datastore_id', $datastoreId)
            ->where('recorded_at', '<=', $periodEnd->format('Y-m-d H:i:s'))
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($sizeHistory->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No size history found for datastore',
                'amount' => 0,
            ];
        }

        // Calculate total hours in the billing period
        $totalHours = ($periodEnd->getTimestamp() - $periodStart->getTimestamp()) / 3600;

        // Find the size at the start of the period (last record before periodStart)
        $startingSize = null;
        $startingRecord = null;
        foreach ($sizeHistory as $record) {
            $recordTime = new \DateTime($record->recorded_at);
            if ($recordTime <= $periodStart) {
                $startingSize = $record->size_gb;
                $startingRecord = $recordTime;
            } else {
                break;
            }
        }

        // If no record before period start, use the first record
        if ($startingSize === null && $sizeHistory->count() > 0) {
            $firstRecord = $sizeHistory->first();
            $startingSize = $firstRecord->size_gb;
            $startingRecord = new \DateTime($firstRecord->recorded_at);
        }

        // Build list of size segments within the period
        $segments = [];
        $currentSize = $startingSize;
        $currentStart = $periodStart;

        foreach ($sizeHistory as $record) {
            $recordTime = new \DateTime($record->recorded_at);

            // Skip records before the period
            if ($recordTime <= $periodStart) {
                $currentSize = $record->size_gb;
                continue;
            }

            // Stop at period end
            if ($recordTime >= $periodEnd) {
                break;
            }

            // This is a size change within the period
            $segmentHours = ($recordTime->getTimestamp() - $currentStart->getTimestamp()) / 3600;
            if ($segmentHours > 0) {
                $segments[] = [
                    'size_gb' => $currentSize,
                    'hours' => $segmentHours,
                    'from' => $currentStart->format('Y-m-d H:i:s'),
                    'to' => $recordTime->format('Y-m-d H:i:s'),
                ];
            }

            $currentSize = $record->size_gb;
            $currentStart = $recordTime;
        }

        // Add final segment until period end
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
        // This gives the prorated monthly cost
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
