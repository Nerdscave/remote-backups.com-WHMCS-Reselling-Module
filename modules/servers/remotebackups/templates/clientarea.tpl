<div class="panel panel-default" id="remote-backup-panel">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-cloud"></i> Remote Backup Datastore</h3>
    </div>
    <div class="panel-body">
        {if $error}
            <div class="alert alert-danger">{$error}</div>
        {else}
            {* Storage Overview *}
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
                    <span class="label label-info">{$size_gb} GB</span>
                </div>
                <div class="col-sm-4">
                    <strong>Used:</strong><br>
                    <span class="label label-{if $usage_percent > 80}danger{elseif $usage_percent > 60}warning{else}success{/if}">{$used_gb} GB ({$usage_percent}%)</span>
                </div>
                <div class="col-sm-4">
                    <strong>Available:</strong><br>
                    <span class="label label-default">{$size_gb - $used_gb} GB</span>
                </div>
            </div>
            
            <div class="progress" style="margin-top: 15px;">
                <div class="progress-bar {if $usage_percent > 80}progress-bar-danger{elseif $usage_percent > 60}progress-bar-warning{else}progress-bar-success{/if}" 
                     role="progressbar" 
                     style="width: {$usage_percent}%;">
                    {$usage_percent}%
                </div>
            </div>
            
            {* Connection Credentials - Main Section *}
            {if $server_hostname && $datastore_user}
            <hr>
            <h4><i class="fa fa-plug"></i> Connection Details</h4>
            
            <div class="connection-fields" style="margin-top: 15px;">
                {* Host *}
                <div class="form-group">
                    <label>Host:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="field-host" value="{$server_hostname}:8007" readonly>
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button" onclick="copyToClipboard('field-host')" title="Copy">
                                <i class="fa fa-copy"></i>
                            </button>
                        </span>
                    </div>
                </div>
                
                {* User *}
                <div class="form-group">
                    <label>User:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="field-user" value="{$datastore_user}" readonly>
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button" onclick="copyToClipboard('field-user')" title="Copy">
                                <i class="fa fa-copy"></i>
                            </button>
                        </span>
                    </div>
                </div>
                
                {* Password (hidden by default) *}
                <div class="form-group">
                    <label>Password:</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="field-password" value="{$datastore_password}" readonly>
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button" onclick="togglePassword()" title="Show/Hide" id="btn-toggle-pw">
                                <i class="fa fa-eye" id="icon-toggle-pw"></i>
                            </button>
                            <button class="btn btn-default" type="button" onclick="copyToClipboard('field-password')" title="Copy">
                                <i class="fa fa-copy"></i>
                            </button>
                        </span>
                    </div>
                </div>
                
                {* Datastore *}
                <div class="form-group">
                    <label>Datastore:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="field-datastore" value="{$datastore_id}" readonly>
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button" onclick="copyToClipboard('field-datastore')" title="Copy">
                                <i class="fa fa-copy"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
            
            <p class="text-muted" style="margin-top: 10px;">
                <i class="fa fa-info-circle"></i> 
                Use these credentials in your Proxmox Backup Server or Borg configuration.
            </p>
            
            {* Advanced Info Toggle *}
            <div style="margin-top: 20px;">
                <a href="#" onclick="toggleAdvanced(); return false;" id="advanced-toggle">
                    <i class="fa fa-chevron-right" id="advanced-icon"></i> Show Advanced Info
                </a>
            </div>
            
            {* Advanced Info Section (collapsed by default) *}
            <div id="advanced-section" style="display: none; margin-top: 15px;">
                <div class="alert alert-warning" style="margin-bottom: 15px;">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Subject to Change:</strong> The following information may change during maintenance. Always use the hostname for configuration.
                </div>
                
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
                            <strong>Server Fingerprint:</strong><br>
                            <code style="font-size: 11px; word-break: break-all;">{$server_fingerprint}</code>
                            <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">
                                <small>It is recommended to not pin this fingerprint as it may change.</small>
                            </p>
                        </div>
                    </div>
                    {/if}
                </div>
            </div>
            {/if}
            
            {* Usage Graphs - Two columns like Remote-Backup Dashboard *}
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h4><i class="fa fa-database"></i> Storage Usage</h4>
                    <div style="height: 200px; margin-bottom: 15px;">
                        <canvas id="usageChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <h4><i class="fa fa-exchange"></i> Transfer Rate</h4>
                    <div style="height: 200px; margin-bottom: 15px;">
                        <canvas id="transferChart"></canvas>
                    </div>
                </div>
            </div>
            
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

{* JavaScript Functions *}
<script>
function copyToClipboard(fieldId) {
    var field = document.getElementById(fieldId);
    var originalType = field.type;
    field.type = 'text';
    field.select();
    document.execCommand('copy');
    field.type = originalType;
    
    // Visual feedback
    var btn = field.parentElement.querySelector('button[onclick*="copyToClipboard"]');
    var icon = btn.querySelector('i');
    icon.className = 'fa fa-check';
    setTimeout(function() {
        icon.className = 'fa fa-copy';
    }, 1500);
}

function togglePassword() {
    var field = document.getElementById('field-password');
    var icon = document.getElementById('icon-toggle-pw');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

function toggleAdvanced() {
    var section = document.getElementById('advanced-section');
    var icon = document.getElementById('advanced-icon');
    var toggle = document.getElementById('advanced-toggle');
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
        icon.className = 'fa fa-chevron-down';
        toggle.innerHTML = '<i class="fa fa-chevron-down"></i> Hide Advanced Info';
    } else {
        section.style.display = 'none';
        icon.className = 'fa fa-chevron-right';
        toggle.innerHTML = '<i class="fa fa-chevron-right"></i> Show Advanced Info';
    }
}
</script>

{* Chart.js for Usage Graph (MIT License - see assets/CHART_LICENSE.txt) *}
<script src="../modules/servers/remotebackups/assets/chart.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('usageChart');
    if (!ctx) return;
    ctx = ctx.getContext('2d');
    
    var metrics = {$metrics_json};
    var totalSizeGB = {$size_gb};
    
    var labels = [];
    var usedData = [];
    var totalData = [];
    
    if (metrics && metrics.length > 0) {
        // Real data available
        metrics.forEach(function(m) {
            var date = new Date(m.time * 1000);
            labels.push(date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {literal}{hour: '2-digit', minute:'2-digit'}{/literal}));
            usedData.push((m.used / 1000000000).toFixed(1));
            totalData.push((m.total / 1000000000).toFixed(1));
        });
    } else {
        // No historical metrics - show current status as flatline
        var currentUsedGB = {$used_gb};
        var now = new Date();
        for (var i = 6; i >= 0; i--) {
            var d = new Date(now.getTime() - i * 3600000); // hourly intervals
            labels.push(d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {literal}{hour: '2-digit', minute:'2-digit'}{/literal}));
            usedData.push(currentUsedGB);
            totalData.push(totalSizeGB);
        }
    }
    
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
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, max: totalSizeGB + 50, title: { display: true, text: 'GB' } },
                x: { display: true, ticks: { maxTicksLimit: 6 } }
            }
        }
    });
})();

// Transfer Rate Chart
(function() {
    var ctx = document.getElementById('transferChart');
    if (!ctx) return;
    ctx = ctx.getContext('2d');
    
    var metrics = {$metrics_json};
    
    var labels = [];
    var readData = [];
    var writeData = [];
    
    if (metrics && metrics.length > 0) {
        // Real data available
        metrics.forEach(function(m) {
            var date = new Date(m.time * 1000);
            labels.push(date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {literal}{hour: '2-digit', minute:'2-digit'}{/literal}));
            // Convert bytes/s to MB/s
            readData.push((m.read_bytes || 0).toFixed(2));
            writeData.push((m.write_bytes || 0).toFixed(2));
        });
    } else {
        // No data yet - show flatline at 0
        var now = new Date();
        for (var i = 6; i >= 0; i--) {
            var d = new Date(now.getTime() - i * 3600000);
            labels.push(d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {literal}{hour: '2-digit', minute:'2-digit'}{/literal}));
            readData.push(0);
            writeData.push(0);
        }
    }
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Read (MB/s)',
                data: readData,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                fill: true,
                tension: 0.3
            }, {
                label: 'Write (MB/s)',
                data: writeData,
                borderColor: 'rgb(255, 159, 64)',
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'MB/s' } },
                x: { display: true, ticks: { maxTicksLimit: 6 } }
            }
        }
    });
})();
</script>

{* Auto-refresh script *}
<script>
(function() {
    var refreshInterval = 60000;
    var serviceId = {$serviceid};
    
    function refreshData() {
        var icon = document.getElementById('refresh-icon');
        if (icon) icon.className = 'fa fa-refresh fa-spin';
        
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'clientarea.php?action=productdetails&id=' + serviceId + '&ajax=1&modop=custom&a=getUsage', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (icon) icon.className = 'fa fa-refresh';
                var updated = document.getElementById('last-updated');
                if (updated) updated.textContent = new Date().toLocaleTimeString();
            }
        };
        xhr.send();
    }
    
    setInterval(refreshData, refreshInterval);
})();
</script>
