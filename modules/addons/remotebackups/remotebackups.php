<?php
/**
 * Remote Backups WHMCS Addon Module
 *
 * Addon module for resellers of remote-backups.com
 * Provides configuration, datastore overview, and pricing management
 *
 * @package    Remote Backups WHMCS Module
 * @author     Moritz Mantel / Nerdscave Hosting
 * @copyright  2026 Nerdscave Hosting (https://www.nerdscave-hosting.com/)
 * @license    GPL-3.0-or-later
 * @link       https://www.nerdscave-hosting.com/
 * @see        https://developers.whmcs.com/addon-modules/
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\RemoteBackups\Admin\AdminDispatcher;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Autoload our classes
spl_autoload_register(function ($class) {
    $prefix = 'WHMCS\\Module\\Addon\\RemoteBackups\\';
    $baseDir = __DIR__ . '/lib/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Module configuration
 */
function remotebackups_config(): array
{
    return [
        'name' => 'Remote Backups',
        'description' => 'Integration with remote-backups.com for resellers. '
            . 'Manage datastores, configure pricing, and track usage.<br>'
            . '<br><strong>Developed by:</strong> <a href="https://www.nerdscave-hosting.com/" target="_blank">Nerdscave Hosting</a>',
        'author' => '<a href="https://www.nerdscave-hosting.com/" target="_blank">Moritz Mantel / Nerdscave Hosting</a>',
        'language' => 'english',
        'version' => '1.0.0',
        'fields' => [
            'api_token' => [
                'FriendlyName' => 'API Token',
                'Type' => 'password',
                'Size' => '60',
                'Default' => '',
                'Description' => 'Your remote-backups.com reseller API token',
            ],
            'price_per_1000gb' => [
                'FriendlyName' => 'Price per 1000GB',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '10.00',
                'Description' => 'Monthly price per 1000GB (in your default currency)',
            ],
            'min_size_gb' => [
                'FriendlyName' => 'Minimum Size (GB)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '100',
                'Description' => 'Minimum datastore size in GB',
            ],
            'max_size_gb' => [
                'FriendlyName' => 'Maximum Size (GB)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '10000',
                'Description' => 'Maximum datastore size in GB',
            ],
        ]
    ];
}

/**
 * Activate module - create database tables
 */
function remotebackups_activate(): array
{
    try {
        $schema = Capsule::schema();

        // Datastore mapping table
        if (!$schema->hasTable('mod_remotebackups_datastores')) {
            $schema->create('mod_remotebackups_datastores', function ($table) {
                $table->increments('id');
                $table->string('datastore_id', 255)->unique();
                $table->integer('whmcs_service_id')->unsigned()->nullable();
                $table->integer('current_size_gb')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->index('whmcs_service_id');
            });
        }

        // Size history for hourly billing
        if (!$schema->hasTable('mod_remotebackups_size_history')) {
            $schema->create('mod_remotebackups_size_history', function ($table) {
                $table->increments('id');
                $table->string('datastore_id', 255);
                $table->integer('size_gb');
                $table->timestamp('recorded_at')->useCurrent();

                $table->index(['datastore_id', 'recorded_at']);
            });
        }

        return [
            'status' => 'success',
            'description' => 'Remote Backups module activated successfully. '
                . 'Please configure your API token in the module settings.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Failed to create database tables: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivate module - remove database tables
 */
function remotebackups_deactivate(): array
{
    try {
        $schema = Capsule::schema();

        // Ask for confirmation before dropping tables
        // In production, you might want to keep the data
        $schema->dropIfExists('mod_remotebackups_size_history');
        $schema->dropIfExists('mod_remotebackups_datastores');

        return [
            'status' => 'success',
            'description' => 'Remote Backups module deactivated. Database tables removed.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Failed to remove database tables: ' . $e->getMessage(),
        ];
    }
}

/**
 * Upgrade handler for version updates
 */
function remotebackups_upgrade(array $vars): void
{
    $currentVersion = $vars['version'];

    // Add upgrade logic here for future versions
    // if (version_compare($currentVersion, '1.1.0', '<')) { ... }
}

/**
 * Admin area output
 */
function remotebackups_output(array $vars): void
{
    $action = $_REQUEST['action'] ?? 'index';

    $dispatcher = new AdminDispatcher();
    echo $dispatcher->dispatch($action, $vars);
}

/**
 * Admin sidebar
 */
function remotebackups_sidebar(array $vars): string
{
    $modulelink = $vars['modulelink'];

    return <<<HTML
<span class="header">
    <i class="fas fa-cloud"></i> Navigation
</span>
<ul class="menu">
    <li><a href="{$modulelink}">Dashboard</a></li>
    <li><a href="{$modulelink}&action=datastores">Datastores</a></li>
    <li><a href="{$modulelink}&action=usage">Usage History</a></li>
    <li><a href="{$modulelink}&action=testconnection">Test Connection</a></li>
</ul>
HTML;
}
