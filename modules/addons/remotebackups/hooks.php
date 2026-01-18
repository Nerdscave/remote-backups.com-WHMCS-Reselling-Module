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
 * Hook: Daily Cron Job
 * 
 * You can also add size tracking to the daily cron if you prefer
 * over running a separate hourly cron script.
 * 
 * Note: For accurate hourly billing, the dedicated cron.php 
 * should be run hourly via system cron.
 */
add_hook('DailyCronJob', 1, function ($vars) {
    // Optional: Run size tracker on daily cron as backup
    // Uncomment if you want daily tracking in addition to hourly cron
    /*
    require_once __DIR__ . '/cron.php';
    remotebackups_runSizeTracker();
    */
});

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
