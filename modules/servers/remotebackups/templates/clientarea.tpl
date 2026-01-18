<div class="panel panel-default" id="remote-backup-panel">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-cloud"></i> Remote Backup Datastore</h3>
    </div>
    <div class="panel-body">
        {if $error}
            <div class="alert alert-danger">{$error}</div>
        {else}
            <div class="row">
                <div class="col-sm-6">
                    <strong>Datastore Name:</strong><br>
                    {$friendly_name}
                </div>
                <div class="col-sm-6">
                    <strong>Datastore ID:</strong><br>
                    <code>{$datastore_id}</code>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-sm-4">
                    <strong>Total Size:</strong><br>
                    <span class="label label-info" id="total-size">{$size_gb} GB</span>
                </div>
                <div class="col-sm-4">
                    <strong>Used:</strong><br>
                    <span class="label label-{if $usage_percent > 80}danger{elseif $usage_percent > 60}warning{else}success{/if}" id="used-size">{$used_gb} GB ({$usage_percent}%)</span>
                </div>
                <div class="col-sm-4">
                    <strong>Available:</strong><br>
                    <span class="label label-default" id="available-size">{$size_gb - $used_gb} GB</span>
                </div>
            </div>
            
            <hr>
            
            <div class="progress">
                <div class="progress-bar {if $usage_percent > 80}progress-bar-danger{elseif $usage_percent > 60}progress-bar-warning{else}progress-bar-success{/if}" 
                     role="progressbar" 
                     style="width: {$usage_percent}%;"
                     id="usage-progress">
                    {$usage_percent}%
                </div>
            </div>
            
            {* Usage History Graph *}
            {if $metrics && count($metrics) > 0}
            <hr>
            <h4><i class="fa fa-line-chart"></i> Usage History</h4>
            <div style="height: 200px; margin-bottom: 15px;">
                <canvas id="usageChart"></canvas>
            </div>
            {/if}
            
            {* Server Connection Details *}
            {if $server_hostname}
            <hr>
            <h4><i class="fa fa-server"></i> Server Connection</h4>
            <div class="well">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Hostname:</strong><br>
                        <code>{$server_hostname}</code>
                    </div>
                    <div class="col-sm-6">
                        <strong>Port:</strong><br>
                        <code>8007</code>
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-6">
                        <strong>IPv4:</strong><br>
                        <code>{$server_ip}</code>
                    </div>
                    <div class="col-sm-6">
                        <strong>IPv6:</strong><br>
                        <code>{$server_ip6}</code>
                    </div>
                </div>
                {if $server_fingerprint}
                <div class="row" style="margin-top: 10px;">
                    <div class="col-sm-12">
                        <strong>Server Fingerprint:</strong>
                        <span class="text-warning" style="margin-left: 5px;">
                            <i class="fa fa-exclamation-triangle"></i> May change during maintenance
                        </span><br>
                        <code style="font-size: 11px; word-break: break-all;">{$server_fingerprint}</code>
                        <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">
                            <small>It is recommended to not pin this fingerprint in your configuration as it may change.</small>
                        </p>
                    </div>
                </div>
                {/if}
            </div>
            <p class="text-muted">
                <i class="fa fa-warning"></i> 
                IP addresses may change. Always use the hostname for configuration.
            </p>
            {/if}
            
            {* User Credentials *}
            {if $datastore_user}
            <hr>
            <h4><i class="fa fa-key"></i> Authentication</h4>
            <div class="well">
                <div class="row">
                    <div class="col-sm-6">
                        <strong>Username:</strong><br>
                        <code>{$datastore_user}</code>
                    </div>
                    <div class="col-sm-6">
                        <strong>Password:</strong><br>
                        <code>{$datastore_password}</code>
                    </div>
                </div>
            </div>
            <p class="text-muted">
                <i class="fa fa-info-circle"></i> 
                Use these credentials in your Proxmox Backup Server or Borg configuration.
            </p>
            {/if}
            
            {* Last Updated *}
            <div class="text-right text-muted" style="margin-top: 15px;">
                <small>
                    <i class="fa fa-refresh" id="refresh-icon"></i>
                    Last updated: <span id="last-updated">{$smarty.now|date_format:"%H:%M:%S"}</span>
                    <span id="auto-refresh-status">(auto-refresh active)</span>
                </small>
            </div>
        {/if}
    </div>
</div>

{* Chart.js for Usage Graph *}
{if $metrics && count($metrics) > 0}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('usageChart').getContext('2d');
    var metrics = {$metrics_json};
    
    // Prepare data
    var labels = [];
    var usedData = [];
    var totalData = [];
    
    metrics.forEach(function(m) {
        var date = new Date(m.time * 1000);
        labels.push(date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {literal}{hour: '2-digit', minute:'2-digit'}{/literal}));
        usedData.push((m.used / 1000000000).toFixed(1));
        totalData.push((m.total / 1000000000).toFixed(1));
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Used (GB)',
                data: usedData,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                fill: true,
                tension: 0.3
            }, {
                label: 'Total (GB)',
                data: totalData,
                borderColor: 'rgb(54, 162, 235)',
                borderDash: [5, 5],
                fill: false,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'GB'
                    }
                },
                x: {
                    display: true,
                    ticks: {
                        maxTicksLimit: 6
                    }
                }
            }
        }
    });
})();
</script>
{/if}

{* Auto-refresh script *}
<script>
(function() {
    var refreshInterval = 60000; // 60 seconds
    var serviceId = {$serviceid};
    
    function refreshData() {
        var icon = document.getElementById('refresh-icon');
        if (icon) icon.className = 'fa fa-refresh fa-spin';
        
        // AJAX call to refresh data
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'clientarea.php?action=productdetails&id=' + serviceId + '&ajax=1&modop=custom&a=getUsage', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (icon) icon.className = 'fa fa-refresh';
                var updated = document.getElementById('last-updated');
                if (updated) {
                    var now = new Date();
                    updated.textContent = now.toLocaleTimeString();
                }
            }
        };
        xhr.send();
    }
    
    // Start auto-refresh
    setInterval(refreshData, refreshInterval);
})();
</script>
