{* Settings Tab Template for Remote Backups *}
<div class="panel panel-default" id="remote-backup-settings">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-cog"></i> Datastore Settings</h3>
    </div>
    <div class="panel-body">
        {if $error}
            <div class="alert alert-danger">{$error}</div>
        {elseif $success}
            <div class="alert alert-success">{$success}</div>
        {/if}

        <form method="post" id="settings-form">
            <input type="hidden" name="action" value="saveSettings">
            
            {* Resize Section *}
            <div class="form-group">
                <h4><i class="fa fa-arrows-h"></i> Resize Datastore</h4>
                <label for="size_slider">Size (min {$min_size_gb} GB):</label>
                <div class="row">
                    <div class="col-sm-9">
                        <input type="range" 
                               id="size_slider" 
                               name="size_gb" 
                               min="{$min_size_gb}" 
                               max="{$max_size_gb}" 
                               step="100" 
                               value="{$current_size_gb}" 
                               class="form-control"
                               style="width: 100%;">
                    </div>
                    <div class="col-sm-3">
                        <span id="size_display" class="label label-primary" style="font-size: 18px;">{$current_size_gb} GB</span>
                    </div>
                </div>
                <p class="text-muted" style="margin-top: 10px;">
                    <small>
                        <i class="fa fa-info-circle"></i> 
                        Current size: {$current_size_gb} GB | Range: {$min_size_gb} GB - {$max_size_gb} GB
                    </small>
                </p>
            </div>
            
            <hr>
            
            {* Autoscaling Section *}
            <div class="form-group">
                <h4><i class="fa fa-arrows-v"></i> Autoscaling</h4>
                
                <div class="row">
                    <div class="col-sm-6">
                        <label for="autoscaling_enabled">Enable Autoscaling:</label>
                        <select id="autoscaling_enabled" name="autoscaling_enabled" class="form-control">
                            <option value="0" {if !$autoscaling_enabled}selected{/if}>Disabled</option>
                            <option value="1" {if $autoscaling_enabled}selected{/if}>Enabled</option>
                        </select>
                    </div>
                </div>
                
                <div id="autoscaling_options" style="{if !$autoscaling_enabled}display: none;{/if} margin-top: 15px;">
                    <div class="row">
                        <div class="col-sm-6">
                            <label for="scale_up_only">Scale Behavior:</label>
                            <select id="scale_up_only" name="scale_up_only" class="form-control">
                                <option value="0" {if !$autoscaling_scale_up_only}selected{/if}>Allow Scale Down</option>
                                <option value="1" {if $autoscaling_scale_up_only}selected{/if}>Scale Up Only</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row" style="margin-top: 10px;">
                        <div class="col-sm-6">
                            <label for="lower_threshold">Lower Threshold (%):</label>
                            <input type="number" 
                                   id="lower_threshold" 
                                   name="lower_threshold" 
                                   class="form-control" 
                                   min="0" max="100" 
                                   value="{$autoscaling_lower_threshold}">
                            <small class="text-muted">Scale down when usage falls below this</small>
                        </div>
                        <div class="col-sm-6">
                            <label for="upper_threshold">Upper Threshold (%):</label>
                            <input type="number" 
                                   id="upper_threshold" 
                                   name="upper_threshold" 
                                   class="form-control" 
                                   min="0" max="100" 
                                   value="{$autoscaling_upper_threshold}">
                            <small class="text-muted">Scale up when usage exceeds this</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" style="margin-top: 15px;">
                        <i class="fa fa-info-circle"></i>
                        With autoscaling enabled, your datastore will automatically resize based on usage.
                        <br><br>
                        <strong>Note:</strong> Scaling down only works for datastores over 500 GB and up to 20 TB.
                    </div>
                </div>
            </div>
            
            <hr>
            
            {* Bandwidth Limit Section *}
            <div class="form-group">
                <h4><i class="fa fa-tachometer"></i> Bandwidth Limit</h4>
                <div class="row">
                    <div class="col-sm-6">
                        <label for="bandwidth_limit">Maximum Transfer Speed:</label>
                        <select id="bandwidth_limit" name="bandwidth_limit" class="form-control">
                            <option value="100" {if $bandwidth_limit == 100}selected{/if}>100 Mbit/s</option>
                            <option value="250" {if $bandwidth_limit == 250}selected{/if}>250 Mbit/s</option>
                            <option value="500" {if $bandwidth_limit == 500}selected{/if}>500 Mbit/s</option>
                        </select>
                        <small class="text-muted">Limits the backup transfer speed to this value</small>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Save Settings
                </button>
                <a href="clientarea.php?action=productdetails&id={$serviceid}" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Overview
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Update size display when slider moves
document.getElementById('size_slider').addEventListener('input', function() {
    document.getElementById('size_display').textContent = this.value + ' GB';
});

// Toggle autoscaling options visibility
document.getElementById('autoscaling_enabled').addEventListener('change', function() {
    var options = document.getElementById('autoscaling_options');
    if (this.value === '1') {
        options.style.display = 'block';
    } else {
        options.style.display = 'none';
    }
});
</script>
