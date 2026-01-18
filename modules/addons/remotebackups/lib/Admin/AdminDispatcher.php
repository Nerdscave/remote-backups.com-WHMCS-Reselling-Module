<?php

namespace WHMCS\Module\Addon\RemoteBackups\Admin;

/**
 * Admin Area Dispatcher
 */
class AdminDispatcher
{
    public function dispatch(string $action, array $vars): string
    {
        if (empty($action)) {
            $action = 'index';
        }

        $controller = new Controller();

        if (is_callable([$controller, $action])) {
            return $controller->$action($vars);
        }

        return '<div class="alert alert-danger">Invalid action requested.</div>';
    }
}
