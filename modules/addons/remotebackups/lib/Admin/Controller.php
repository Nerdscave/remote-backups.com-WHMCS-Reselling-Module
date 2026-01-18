<?php

namespace WHMCS\Module\Addon\RemoteBackups\Admin;

use WHMCS\Module\Addon\RemoteBackups\Api\RemoteBackupsClient;
use WHMCS\Database\Capsule;

/**
 * Admin Area Controller
 */
class Controller
{
    /**
     * Dashboard / Index page
     */
    public function index(array $vars): string
    {
        $modulelink = $vars['modulelink'];
        $apiToken = $vars['api_token'] ?? '';

        $datastoreCount = 0;
        $totalSizeGB = 0;
        $connectionStatus = 'Not configured';
        $statusClass = 'warning';

        if (!empty($apiToken)) {
            try {
                $client = new RemoteBackupsClient($apiToken);
                $datastores = $client->listDatastores();
                $datastoreCount = count($datastores);

                foreach ($datastores as $ds) {
                    $totalSizeGB += RemoteBackupsClient::getSizeInGB($ds);
                }

                $connectionStatus = 'Connected';
                $statusClass = 'success';
            } catch (\Exception $e) {
                $connectionStatus = 'Error: ' . $e->getMessage();
                $statusClass = 'danger';
            }
        }

        $pricePerTB = $vars['price_per_1000gb'] ?? '10.00';
        $minSize = $vars['min_size_gb'] ?? '100';
        $maxSize = $vars['max_size_gb'] ?? '10000';

        return <<<HTML
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-plug"></i> Connection Status</h3>
            </div>
            <div class="panel-body">
                <span class="label label-{$statusClass}">{$connectionStatus}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-database"></i> Datastores</h3>
            </div>
            <div class="panel-body">
                <h2>{$datastoreCount}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-hdd"></i> Total Storage</h3>
            </div>
            <div class="panel-body">
                <h2>{$totalSizeGB} GB</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-dollar-sign"></i> Price/1000GB</h3>
            </div>
            <div class="panel-body">
                <h2>{$pricePerTB}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-cog"></i> Current Configuration</h3>
            </div>
            <div class="panel-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="200">Setting</th>
                        <th>Value</th>
                    </tr>
                    <tr>
                        <td>Price per 1000GB</td>
                        <td>{$pricePerTB}</td>
                    </tr>
                    <tr>
                        <td>Minimum Size</td>
                        <td>{$minSize} GB</td>
                    </tr>
                    <tr>
                        <td>Maximum Size</td>
                        <td>{$maxSize} GB</td>
                    </tr>
                </table>
                <p class="text-muted">
                    <i class="fas fa-info-circle"></i> 
                    To change these settings, go to Setup → Addon Modules → Remote Backups → Configure
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <a href="{$modulelink}&action=datastores" class="btn btn-primary">
            <i class="fas fa-list"></i> View All Datastores
        </a>
        <a href="{$modulelink}&action=testconnection" class="btn btn-info">
            <i class="fas fa-plug"></i> Test Connection
        </a>
    </div>
</div>
HTML;
    }

    /**
     * List all datastores
     */
    public function datastores(array $vars): string
    {
        $modulelink = $vars['modulelink'];
        $apiToken = $vars['api_token'] ?? '';

        if (empty($apiToken)) {
            return '<div class="alert alert-warning">Please configure your API token first.</div>';
        }

        try {
            $client = new RemoteBackupsClient($apiToken);
            $datastores = $client->listDatastores();
        } catch (\Exception $e) {
            return '<div class="alert alert-danger">API Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        // Get WHMCS mappings
        $mappings = [];
        try {
            $rows = Capsule::table('mod_remotebackups_datastores')->get();
            foreach ($rows as $row) {
                $mappings[$row->datastore_id] = $row;
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        $html = <<<HTML
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fas fa-database"></i> Datastores from remote-backups.com</h3>
    </div>
    <div class="panel-body">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Used</th>
                    <th>Usage %</th>
                    <th>WHMCS Service</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($datastores as $ds) {
            $id = htmlspecialchars($ds['_id']);
            $name = htmlspecialchars($ds['friendly'] ?? 'N/A');
            $sizeGB = RemoteBackupsClient::getSizeInGB($ds);
            $usedGB = RemoteBackupsClient::getUsedInGB($ds);
            $usagePercent = $sizeGB > 0 ? round(($usedGB / $sizeGB) * 100, 1) : 0;
            $created = isset($ds['createdAt']) ? date('Y-m-d', strtotime($ds['createdAt'])) : 'N/A';

            $serviceLink = 'Not linked';
            if (isset($mappings[$id]) && $mappings[$id]->whmcs_service_id) {
                $serviceId = $mappings[$id]->whmcs_service_id;
                $serviceLink = "<a href='clientshosting.php?id={$serviceId}'>Service #{$serviceId}</a>";
            }

            // Color code usage
            $usageClass = 'success';
            if ($usagePercent > 80) {
                $usageClass = 'danger';
            } elseif ($usagePercent > 60) {
                $usageClass = 'warning';
            }

            $html .= <<<HTML
                <tr>
                    <td><code>{$id}</code></td>
                    <td>{$name}</td>
                    <td>{$sizeGB} GB</td>
                    <td>{$usedGB} GB</td>
                    <td><span class="label label-{$usageClass}">{$usagePercent}%</span></td>
                    <td>{$serviceLink}</td>
                    <td>{$created}</td>
                </tr>
HTML;
        }

        $html .= <<<HTML
            </tbody>
        </table>
    </div>
</div>
<a href="{$modulelink}" class="btn btn-default">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>
HTML;

        return $html;
    }

    /**
     * View usage history
     */
    public function usage(array $vars): string
    {
        $modulelink = $vars['modulelink'];

        try {
            $history = Capsule::table('mod_remotebackups_size_history')
                ->orderBy('recorded_at', 'desc')
                ->limit(100)
                ->get();
        } catch (\Exception $e) {
            return '<div class="alert alert-warning">No usage history available yet.</div>';
        }

        $html = <<<HTML
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fas fa-chart-line"></i> Size Change History (Last 100)</h3>
    </div>
    <div class="panel-body">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Datastore ID</th>
                    <th>Size (GB)</th>
                    <th>Recorded At</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($history as $row) {
            $datastoreId = htmlspecialchars($row->datastore_id);
            $sizeGB = $row->size_gb;
            $recordedAt = $row->recorded_at;

            $html .= <<<HTML
                <tr>
                    <td><code>{$datastoreId}</code></td>
                    <td>{$sizeGB} GB</td>
                    <td>{$recordedAt}</td>
                </tr>
HTML;
        }

        $html .= <<<HTML
            </tbody>
        </table>
    </div>
</div>
<a href="{$modulelink}" class="btn btn-default">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>
HTML;

        return $html;
    }

    /**
     * Test API connection
     */
    public function testconnection(array $vars): string
    {
        $modulelink = $vars['modulelink'];
        $apiToken = $vars['api_token'] ?? '';

        if (empty($apiToken)) {
            return <<<HTML
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 
    No API token configured. Please go to Setup → Addon Modules → Remote Backups → Configure
</div>
<a href="{$modulelink}" class="btn btn-default">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>
HTML;
        }

        $client = new RemoteBackupsClient($apiToken);
        $result = $client->testConnection();

        if ($result['success']) {
            $alertClass = 'success';
            $icon = 'check-circle';
        } else {
            $alertClass = 'danger';
            $icon = 'times-circle';
        }

        $message = htmlspecialchars($result['message']);

        return <<<HTML
<div class="alert alert-{$alertClass}">
    <i class="fas fa-{$icon}"></i> {$message}
</div>
<a href="{$modulelink}" class="btn btn-default">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>
HTML;
    }
}
