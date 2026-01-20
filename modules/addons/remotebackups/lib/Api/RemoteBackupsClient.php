<?php
/**
 * Remote Backups API Client
 * 
 * PHP client for the remote-backups.com Reseller API
 *
 * @package    Remote Backups WHMCS Module
 * @author     Moritz Mantel / Nerdscave Hosting
 * @copyright  2026 Nerdscave Hosting (https://www.nerdscave-hosting.com/)
 * @license    GPL-3.0-or-later
 */

namespace WHMCS\Module\Addon\RemoteBackups\Api;

class RemoteBackupsClient
{
    private string $apiToken;
    private string $baseUrl = 'https://api.remote-backups.com';
    private int $timeout = 30;

    public function __construct(string $apiToken)
    {
        $this->apiToken = $apiToken;
    }

    /**
     * Test API connection
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $datastores = $this->listDatastores();
            return [
                'success' => true,
                'message' => 'Connection successful. Found ' . count($datastores) . ' datastore(s).'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * List all datastores for this reseller
     * @return array
     */
    public function listDatastores(): array
    {
        return $this->request('GET', '/reseller/datastore');
    }

    /**
     * Get single datastore details
     * @param string $datastoreId
     * @return array
     */
    public function getDatastore(string $datastoreId): array
    {
        return $this->request('GET', '/reseller/datastore/' . $datastoreId);
    }

    /**
     * Create a new datastore
     * 
     * The API accepts size in GB directly, not bytes.
     * 
     * @param string $friendlyName Human-readable name
     * @param int $sizeGB Size in GB
     * @return array Created datastore data
     */
    public function createDatastore(string $friendlyName, int $sizeGB): array
    {
        return $this->request('POST', '/reseller/datastore', [
            'friendly' => $friendlyName,
            'size' => $sizeGB
        ]);
    }

    /**
     * Resize an existing datastore
     * 
     * The PATCH endpoint requires all fields. This method fetches the current
     * datastore values first and only changes the size.
     * 
     * @param string $datastoreId
     * @param int $newSizeGB New size in GB (must be in 100GB increments, min 500)
     * @return array Updated datastore data
     */
    public function resizeDatastore(string $datastoreId, int $newSizeGB): array
    {
        // First get current datastore to preserve other values
        $current = $this->getDatastore($datastoreId);

        return $this->request('PATCH', '/reseller/datastore/' . $datastoreId, [
            'friendly' => $current['friendly'],
            'size' => $newSizeGB,
            'autoscalingEnabled' => $current['autoscalingEnabled'] ?? false,
            'autoscalingScaleUpOnly' => $current['autoscalingScaleUpOnly'] ?? false,
            'autoscalingLowerThreshold' => $current['autoscalingLowerThreshold'] ?? 70,
            'autoscalingUpperThreshold' => $current['autoscalingUpperThreshold'] ?? 80,
            'speed' => $current['speed'] ?? 500,
        ]);
    }

    /**
     * Update datastore with all settings (size, autoscaling, speed)
     * 
     * @param string $datastoreId
     * @param array $settings Associative array with settings to update
     * @return array Updated datastore data
     */
    public function updateDatastore(string $datastoreId, array $settings): array
    {
        // First get current datastore to use as defaults
        $current = $this->getDatastore($datastoreId);

        return $this->request('PATCH', '/reseller/datastore/' . $datastoreId, [
            'friendly' => $settings['friendly'] ?? $current['friendly'],
            'size' => $settings['size'] ?? ($current['size'] / 1e9),
            'autoscalingEnabled' => $settings['autoscalingEnabled'] ?? $current['autoscalingEnabled'] ?? false,
            'autoscalingScaleUpOnly' => $settings['autoscalingScaleUpOnly'] ?? $current['autoscalingScaleUpOnly'] ?? false,
            'autoscalingLowerThreshold' => $settings['autoscalingLowerThreshold'] ?? $current['autoscalingLowerThreshold'] ?? 70,
            'autoscalingUpperThreshold' => $settings['autoscalingUpperThreshold'] ?? $current['autoscalingUpperThreshold'] ?? 80,
            'speed' => $settings['speed'] ?? $current['speed'] ?? 500,
        ]);
    }

    /**
     * Delete a datastore
     * @param string $datastoreId
     * @return array
     */
    public function deleteDatastore(string $datastoreId): array
    {
        return $this->request('DELETE', '/reseller/datastore/' . $datastoreId);
    }

    /**
     * Get datastore size in GB from API response
     * @param array $datastore Datastore data from API
     * @return int Size in GB
     */
    public static function getSizeInGB(array $datastore): int
    {
        $sizeBytes = $datastore['size'] ?? 0;
        return (int) round($sizeBytes / 1000 / 1000 / 1000);
    }

    /**
     * Get used space in GB from API response
     * Uses status.used for current data (not metrics which may be empty)
     * @param array $datastore Datastore data from API
     * @return float Used space in GB
     */
    public static function getUsedInGB(array $datastore): float
    {
        // Try status.used first (current live data), fallback to 0
        $usedBytes = $datastore['status']['used'] ?? 0;
        return round($usedBytes / 1000 / 1000 / 1000, 2);
    }

    /**
     * Make HTTP request to API
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request body for POST/PATCH
     * @return array Response data
     * @throws \Exception on API errors
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);

        if ($data !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['message'] ?? 'Unknown API error';
            if (is_array($errorMessage)) {
                $errorMessage = implode(', ', $errorMessage);
            }
            throw new \Exception('API error (' . $httpCode . '): ' . $errorMessage);
        }

        return $decoded ?? [];
    }
}
