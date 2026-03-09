{* Remote Backups Client Area - Tabbed Interface *}
{if $js_redirect}
    <div class="alert alert-info">
        <i class="fa fa-spinner fa-spin"></i> Saving settings...
    </div>
    <script>
        window.location.href = '{$js_redirect|escape:"javascript"}';
    </script>
{else}
<div class="panel panel-default" id="remote-backup-panel">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-cloud"></i> {$friendly_name}
        </h3>
    </div>
    
    <div class="panel-body">
        {if $error}
            <div class="alert alert-danger">{$error|escape:'html'}</div>
        {elseif $settings_error}
            <div class="alert alert-danger">Failed to save settings. Please try again.</div>
        {elseif $settings_saved}
            <div class="alert alert-success">Settings saved successfully.</div>
        {/if}

        {if !$error}
            {* Tab Navigation - Pills style for better separation *}
            <ul class="nav nav-pills" role="tablist" style="margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 15px;">
                <li role="presentation" class="active" style="margin-right: 10px;">
                    <a href="#overview-tab" aria-controls="overview-tab" role="tab" data-toggle="tab" style="font-weight: 600; padding: 10px 20px;">
                        <i class="fa fa-tachometer"></i> Overview
                    </a>
                </li>
                <li role="presentation" style="margin-right: 10px;">
                    <a href="#connection-tab" aria-controls="connection-tab" role="tab" data-toggle="tab" style="font-weight: 600; padding: 10px 20px;">
                        <i class="fa fa-plug"></i> Connection
                    </a>
                </li>
                <li role="presentation" style="margin-right: 10px;">
                    <a href="#billing-tab" aria-controls="billing-tab" role="tab" data-toggle="tab" style="font-weight: 600; padding: 10px 20px;">
                        <i class="fa fa-credit-card"></i> Billing
                    </a>
                </li>
                <li role="presentation">
                    <a href="#settings-tab" aria-controls="settings-tab" role="tab" data-toggle="tab" style="font-weight: 600; padding: 10px 20px;">
                        <i class="fa fa-cog"></i> Settings
                    </a>
                </li>
            </ul>
            
            {* Tab Content *}
            <div class="tab-content">
                {* ===== OVERVIEW TAB ===== *}
                <div role="tabpanel" class="tab-pane active" id="overview-tab">
                    {* Storage Stats *}
                    <div class="row" style="margin-bottom: 25px;">
                        {* Total Size Card *}
                        <div class="col-sm-4">
                            <div style="background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s; height: 100%;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                                <h5 style="color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0; margin-bottom: 15px; font-size: 13px;">Total Size</h5>
                                <div style="font-size: 32px; font-weight: 700; color: #337ab7; line-height: 1;">
                                    {$size_gb|number_format:0} <span style="font-size: 16px; font-weight: 600; color: #6c757d;">GB</span>
                                </div>
                                <div style="margin-top: 15px; font-size: 12px; color: #8a9096;">
                                    <i class="fa fa-hdd-o"></i> Gesamt verfügbarer Speicherplatz
                                </div>
                            </div>
                        </div>
                        
                        {* Used Storage Card *}
                        <div class="col-sm-4">
                            <div style="background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s; height: 100%;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                                <h5 style="color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0; margin-bottom: 15px; font-size: 13px;">Used</h5>
                                <div style="font-size: 32px; font-weight: 700; color: {if $usage_percent > 80}#d9534f{elseif $usage_percent > 60}#f0ad4e{else}#5cb85c{/if}; line-height: 1;">
                                    {$used_gb|number_format:1} <span style="font-size: 16px; font-weight: 600; color: #6c757d;">GB</span>
                                </div>
                                <div style="margin-top: 15px; font-size: 12px; color: {if $usage_percent > 80}#d9534f{elseif $usage_percent > 60}#f0ad4e{else}#8a9096{/if}; font-weight: 600;">
                                    {$usage_percent}% in Verwendung
                                </div>
                            </div>
                        </div>
                        
                        {* Available Space Card *}
                        <div class="col-sm-4">
                            <div style="background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 25px 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s; height: 100%;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                                <h5 style="color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0; margin-bottom: 15px; font-size: 13px;">Available</h5>
                                <div style="font-size: 32px; font-weight: 700; color: #5cb85c; line-height: 1;">
                                    {$available_gb|number_format:1} <span style="font-size: 16px; font-weight: 600; color: #6c757d;">GB</span>
                                </div>
                                <div style="margin-top: 15px; font-size: 12px; color: #8a9096;">
                                    <i class="fa fa-check-circle" style="color: #5cb85c;"></i> Freier Speicherplatz
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {* Progress Bar *}
                    <div class="progress" style="height: 25px; margin-bottom: 20px;">
                        <div class="progress-bar {if $usage_percent > 80}progress-bar-danger{elseif $usage_percent > 60}progress-bar-warning{else}progress-bar-success{/if}" 
                             role="progressbar" 
                             style="width: {$usage_percent}%; line-height: 25px; font-size: 14px;">
                            {$usage_percent}% used
                        </div>
                    </div>
                    
                    {* Time Range Selector *}
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-sm-12">
                            <div class="btn-group" role="group" aria-label="Graph time range">
                                {foreach ['hour' => 'Hour', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'decade' => 'Decade'] as $rangeKey => $rangeLabel}
                                    <a href="clientarea.php?action=productdetails&id={$serviceid}&graphRange={$rangeKey}" 
                                       class="btn btn-default{if $graph_range == $rangeKey} active{/if}">
                                        {$rangeLabel}
                                    </a>
                                {/foreach}
                            </div>
                        </div>
                    </div>
                    
                    {* Usage Graphs *}
                    <div class="row">
                        <div class="col-sm-6">
                            <h5><i class="fa fa-bar-chart"></i> Storage Usage</h5>
                            <div style="height: 200px;">
                                <canvas id="usageChart"></canvas>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <h5><i class="fa fa-exchange"></i> Transfer Rate</h5>
                            <div style="height: 200px;">
                                <canvas id="transferChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-right text-muted" style="margin-top: 15px;">
                        <small>
                            <span id="last-updated">{$smarty.now|date_format:"%H:%M:%S"}</span> | 
                            <a href="javascript:location.reload();" style="color: inherit;"><i class="fa fa-refresh"></i> Refresh</a>
                        </small>
                    </div>
                </div>
                
                {* ===== CONNECTION TAB ===== *}
                <div role="tabpanel" class="tab-pane" id="connection-tab">
                    <h4><i class="fa fa-plug"></i> Connection Details</h4>
                    <p class="text-muted">Use these credentials in your Proxmox Backup Server or Borg configuration.</p>
                    
                    <div class="form-group">
                        <label>Host:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{$server_hostname}:8007" readonly id="conn-host">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="copyToClipboard('conn-host')">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>User:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{$datastore_user}" readonly id="conn-user">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="copyToClipboard('conn-user')">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password:</label>
                        <div class="input-group">
                            <input type="password" class="form-control" value="{$datastore_password}" readonly id="conn-password">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="togglePassword('conn-password', this)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <button class="btn btn-default" type="button" onclick="copyToClipboard('conn-password')">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Datastore:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="{$datastore_id}" readonly id="conn-datastore">
                            <span class="input-group-btn">
                                <button class="btn btn-default" type="button" onclick="copyToClipboard('conn-datastore')">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    
                    {* Advanced Info (Collapsible) *}
                    <div style="margin-top: 20px;">
                        <a data-toggle="collapse" href="#advancedInfo" class="text-muted">
                            <i class="fa fa-chevron-down"></i> Show Advanced Info
                        </a>
                        <div class="collapse" id="advancedInfo" style="margin-top: 10px;">
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle"></i>
                                <strong>For advanced users only.</strong> Most backup software only needs the settings above.
                            </div>
                            <table class="table table-condensed">
                                <tr><td style="width: 100px;">ID:</td><td><code style="font-size: 12px; word-break: break-all;">{$datastore_id}</code></td></tr>
                                <tr><td>IPv4:</td><td><code style="font-size: 12px;">{$server_ip}</code></td></tr>
                                <tr><td>IPv6:</td><td><code style="font-size: 12px; word-break: break-all;">{$server_ip6}</code></td></tr>
                                <tr><td>Fingerprint:</td><td><code style="font-size: 11px; word-break: break-all; display: block; max-width: 100%;">{$server_fingerprint}</code></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                {* ===== BILLING TAB ===== *}
                <div role="tabpanel" class="tab-pane" id="billing-tab">
                    <h4><i class="fa fa-credit-card"></i> Billing & Contract</h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-striped">
                                <tr>
                                    <td><strong><i class="fa fa-calendar"></i> Registration Date:</strong></td>
                                    <td>{$regdate}</td>
                                </tr>
                                <tr>
                                    <td><strong><i class="fa fa-refresh"></i> Billing Cycle:</strong></td>
                                    <td>{$billingcycle}</td>
                                </tr>
                                <tr>
                                    <td><strong><i class="fa fa-money"></i> Recurring Amount:</strong></td>
                                    <td>{$firstpaymentamount}</td>
                                </tr>
                                <tr>
                                    <td><strong><i class="fa fa-calendar-check-o"></i> Next Due Date:</strong></td>
                                    <td>{$nextduedate}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-striped">
                                <tr>
                                    <td><strong><i class="fa fa-check-circle"></i> Status:</strong></td>
                                    <td>
                                        <span class="label label-{if $status == 'Active'}success{elseif $status == 'Suspended'}warning{else}default{/if}">
                                            {$status}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><i class="fa fa-credit-card"></i> Payment Method:</strong></td>
                                    <td>{$paymentmethod}</td>
                                </tr>
                                <tr>
                                    <td><strong><i class="fa fa-server"></i> Product:</strong></td>
                                    <td>{$product}</td>
                                </tr>
                                <tr>
                                    <td><strong><i class="fa fa-tag"></i> Order ID:</strong></td>
                                    <td>#{$orderid}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" style="margin-top: 15px;">
                        <i class="fa fa-info-circle"></i>
                        Billing is calculated based on your provisioned datastore size. 
                        Changing the size will affect your next invoice.
                    </div>
                </div>
                
                {* ===== SETTINGS TAB ===== *}
                <div role="tabpanel" class="tab-pane" id="settings-tab">
                    <form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&customAction=saveSettings">
                        <input type="hidden" name="token" value="{$token}">

                        {* Resize Section with BIG visible value *}
                        <div class="well" style="text-align: center;">
                            <h4><i class="fa fa-arrows-h"></i> Datastore Size</h4>
                            <h1 id="size-display" style="font-size: 72px; margin: 20px 0; color: #337ab7;">{$size_gb} GB</h1>
                            <input type="range" 
                                   id="size_slider" 
                                   name="size_gb" 
                                   min="{$min_size_gb|default:500}" 
                                   max="{$max_size_gb|default:10000}" 
                                   step="100" 
                                   value="{$size_gb}" 
                                   style="width: 100%; height: 30px;">
                            <p class="text-muted" style="margin-top: 15px;">
                                Drag slider to resize. Range: {$min_size_gb|default:500} GB - {$max_size_gb|default:10000} GB
                            </p>
                            
                            {* Price Preview *}
                            {math equation="size * price / 1000" size=$size_gb price=$price_per_1000gb assign="initial_price"}
                            <p id="price-preview" style="font-size: 18px; font-weight: bold; color: #5cb85c; margin-top: 10px;">
                                Estimated: ~€<span id="price-display">{$initial_price|number_format:2}</span> / month
                            </p>
                        </div>
                        
                        <hr>
                        
                        {* Bandwidth Limit - Own Section *}
                        <h4><i class="fa fa-tachometer"></i> Bandwidth Limit</h4>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Maximum Transfer Speed:</label>
                                    <select name="bandwidth_limit" class="form-control">
                                        <option value="100" {if $bandwidth_limit == 100}selected{/if}>100 Mbit/s</option>
                                        <option value="250" {if $bandwidth_limit == 250}selected{/if}>250 Mbit/s</option>
                                        <option value="500" {if $bandwidth_limit == 500}selected{/if}>500 Mbit/s</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="alert alert-info" style="margin-top: 25px;">
                                    <i class="fa fa-info-circle"></i> Limits the maximum transfer speed for your backups.
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        {* Autoscaling - Own Section *}
                        <h4><i class="fa fa-magic"></i> Autoscaling</h4>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Enable Autoscaling:</label>
                                    <select id="autoscaling_enabled" name="autoscaling_enabled" class="form-control">
                                        <option value="0" {if !$autoscaling_enabled}selected{/if}>Disabled</option>
                                        <option value="1" {if $autoscaling_enabled}selected{/if}>Enabled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div id="autoscaling_options" style="{if !$autoscaling_enabled}display: none;{/if}">
                            <div class="row">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Scale Behavior:</label>
                                        <select name="scale_up_only" class="form-control">
                                            <option value="0" {if !$autoscaling_scale_up_only}selected{/if}>Allow Scale Down</option>
                                            <option value="1" {if $autoscaling_scale_up_only}selected{/if}>Scale Up Only</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Lower Threshold (%):</label>
                                        <input type="number" name="lower_threshold" class="form-control" min="0" max="100" value="{$autoscaling_lower_threshold|default:70}">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Upper Threshold (%):</label>
                                        <input type="number" name="upper_threshold" class="form-control" min="0" max="100" value="{$autoscaling_upper_threshold|default:80}">
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i>
                                With autoscaling, your datastore resizes automatically. Scale down only works for 500 GB - 20 TB.
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa fa-save"></i> Save Settings
                        </button>
                    </form>

                    <hr style="margin-top: 40px; margin-bottom: 40px;">

                    {* Prune Settings - Own Form *}
                    <h4><i class="fa fa-history"></i> Backup & Retention</h4>
                    
                    {if $prune_settings_saved}
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i> Prune settings saved successfully.
                        </div>
                    {/if}
                    {if $prune_settings_error}
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle"></i> Error saving prune settings{if $error}: {$error|escape:'html'}{/if}
                        </div>
                    {/if}
                    
                    <form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&customAction=savePruneSettings">
                        <input type="hidden" name="token" value="{$token}">
                        <div class="well">
                            <p class="text-muted" style="margin-bottom: 20px;">
                                Configure prune schedules for automatic backup cleanup. This feature is exclusive to Proxmox Backup Server / Proxmox Virtual Environment connections.
                            </p>
                            
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Keep Last:</label>
                                        <input type="number" name="keep_last" class="form-control" min="1" value="{$prune_keep_last}">
                                    </div>
                                    <div class="form-group">
                                        <label>Keep Daily:</label>
                                        <input type="number" name="keep_daily" class="form-control" min="1" value="{$prune_keep_daily}">
                                    </div>
                                    <div class="form-group">
                                        <label>Keep Monthly:</label>
                                        <input type="number" name="keep_monthly" class="form-control" min="1" value="{$prune_keep_monthly}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Keep Hourly:</label>
                                        <input type="number" name="keep_hourly" class="form-control" min="1" value="{$prune_keep_hourly}">
                                    </div>
                                    <div class="form-group">
                                        <label>Keep Weekly:</label>
                                        <input type="number" name="keep_weekly" class="form-control" min="1" value="{$prune_keep_weekly}">
                                    </div>
                                    <div class="form-group">
                                        <label>Keep Yearly:</label>
                                        <input type="number" name="keep_yearly" class="form-control" min="1" value="{$prune_keep_yearly}">
                                    </div>
                                </div>
                            </div>
                            
                            <hr style="border-top-color: #d8d8d8;">
                            
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Schedule Day(s):</label>
                                        <select name="prune_schedule_days[]" class="form-control" multiple="multiple" style="height: 160px;">
                                            <option value="Mon" {if in_array('Mon', $prune_schedule_days)}selected{/if}>Monday</option>
                                            <option value="Tue" {if in_array('Tue', $prune_schedule_days)}selected{/if}>Tuesday</option>
                                            <option value="Wed" {if in_array('Wed', $prune_schedule_days)}selected{/if}>Wednesday</option>
                                            <option value="Thu" {if in_array('Thu', $prune_schedule_days)}selected{/if}>Thursday</option>
                                            <option value="Fri" {if in_array('Fri', $prune_schedule_days)}selected{/if}>Friday</option>
                                            <option value="Sat" {if in_array('Sat', $prune_schedule_days)}selected{/if}>Saturday</option>
                                            <option value="Sun" {if in_array('Sun', $prune_schedule_days)}selected{/if}>Sunday</option>
                                        </select>
                                        <p class="help-block"><small>Hold Ctrl (Windows) or Cmd (Mac) to select multiple days.</small></p>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Schedule Hour(s):</label>
                                        <select name="prune_schedule_hours[]" class="form-control" multiple="multiple" style="height: 160px;">
                                            {foreach from=[0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23] item=h}
                                                {assign var="hourStr" value="`$h|string_format:'%02d'`:00"}
                                                <option value="{$hourStr}" {if in_array($hourStr, $prune_schedule_hours)}selected{/if}>{$hourStr} Uhr</option>
                                            {/foreach}
                                        </select>
                                        <p class="help-block"><small>Hold Ctrl/Cmd to select multiple hours.</small></p>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-default">
                                <i class="fa fa-save"></i> Save Prune Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        {/if}
    </div>
</div>

{* Clipboard and Password Toggle Functions *}
<script>
function copyToClipboard(inputId) {
    var input = document.getElementById(inputId);
    var textToCopy = input.value;
    
    // Use modern Clipboard API with fallback
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(textToCopy);
    } else {
        // Fallback for older browsers
        var originalType = input.type;
        input.type = 'text';
        input.select();
        document.execCommand('copy');
        input.type = originalType;
    }
    
    // Show feedback
    var btn = input.parentNode.querySelector('.btn');
    var icon = btn.querySelector('i');
    icon.className = 'fa fa-check';
    setTimeout(function() { icon.className = 'fa fa-copy'; }, 1500);
}

function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

// Slider value update - BIG and visible!
var sizeSlider = document.getElementById('size_slider');
if (sizeSlider) sizeSlider.addEventListener('input', function() {
    var pricePerThousandGB = {$price_per_1000gb|default:10};
    var sizeDisplay = document.getElementById('size-display');
    var priceDisplay = document.getElementById('price-display');
    
    sizeDisplay.textContent = this.value + ' GB';
    
    // Calculate and display estimated monthly price
    var estimatedPrice = (this.value * pricePerThousandGB / 1000).toFixed(2);
    if (priceDisplay) {
        priceDisplay.textContent = estimatedPrice;
    }
});

// Toggle autoscaling options
var aeNode = document.getElementById('autoscaling_enabled');
if (aeNode) {
    aeNode.addEventListener('change', function() {
        document.getElementById('autoscaling_options').style.display = this.value === '1' ? 'block' : 'none';
    });
}
</script>

{* Chart.js *}
<script src="{$WEB_ROOT}/modules/servers/remotebackups/assets/chart.min.js"></script>
<script>
(function() {
    var metricsData = {$metrics_json};
    var usedGB = {$used_gb};
    var totalGB = {$size_gb};
    var graphRange = '{$graph_range|default:"hour"|escape:"javascript"}';
    
    // Format timestamp based on selected range
    function formatTime(unixTimestamp) {
        var d = new Date(unixTimestamp * 1000);
        switch (graphRange) {
            case 'hour':
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            case 'day':
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            case 'week':
            case 'month':
                return d.toLocaleDateString([], { day: '2-digit', month: '2-digit' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            case 'decade':
                return d.toLocaleDateString([], { day: '2-digit', month: '2-digit', year: '2-digit' });
            default:
                return d.toLocaleTimeString();
        }
    }
    
    // Reduce data points for large datasets to keep charts readable
    function downsample(data, maxPoints) {
        if (data.length <= maxPoints) return data;
        var step = Math.ceil(data.length / maxPoints);
        var result = [];
        for (var i = 0; i < data.length; i += step) {
            result.push(data[i]);
        }
        // Always include last point
        if (result[result.length - 1] !== data[data.length - 1]) {
            result.push(data[data.length - 1]);
        }
        return result;
    }
    
    // Downsample to max ~120 points for readability
    var chartData = downsample(metricsData, 120);
    
    // Prepare shared data arrays (outside chart if-blocks so both charts can use them)
    var labels = [], usedData = [], totalData = [], readData = [], writeData = [];

    if (chartData.length > 0) {
        chartData.forEach(function(m) {
            labels.push(formatTime(m.time));
            usedData.push(+((m.used || 0) / 1e9).toFixed(2));
            totalData.push(+((m.total || 0) / 1e9).toFixed(2));
            readData.push(+((m.read_bytes || 0) / 1e6).toFixed(2));
            writeData.push(+((m.write_bytes || 0) / 1e6).toFixed(2));
        });
    } else {
        labels = [formatTime(Date.now() / 1000)];
        usedData = [usedGB];
        totalData = [totalGB];
        readData = [0];
        writeData = [0];
    }

    // Storage Usage Chart
    var usageCanvas = document.getElementById('usageChart');
    if (usageCanvas) {
        new Chart(usageCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Used (GB)',
                    data: usedData,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    pointRadius: chartData.length > 60 ? 0 : 2,
                    borderWidth: 2
                }, {
                    label: 'Total (GB)',
                    data: totalData,
                    borderColor: 'rgb(201, 203, 207)',
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 8,
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'GB' }
                    }
                }
            }
        });
    }

    // Transfer Rate Chart
    var transferCanvas = document.getElementById('transferChart');
    if (transferCanvas) {
        new Chart(transferCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Read (MB)',
                    data: readData,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    fill: true,
                    pointRadius: chartData.length > 60 ? 0 : 2,
                    borderWidth: 2
                }, {
                    label: 'Write (MB)',
                    data: writeData,
                    borderColor: 'rgb(255, 159, 64)',
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    fill: true,
                    pointRadius: chartData.length > 60 ? 0 : 2,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 8,
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'MB' }
                    }
                }
            }
        });
    }
})();
</script>

{* Timestamp update removed - was misleading as it didn't refresh actual data *}
{/if}
