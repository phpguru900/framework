<?php echo $this->element('AccountSubMenu'); ?>
<?php if ($is_admin && !empty($external_providers)) { ?>
<div class="row">
	<div class="large-12 columns">
		<h5>Available Providers</h5>
	</div>
<?php foreach ($external_providers as $controller_name => $external_provider){ ?>
<?php if( empty($external_provider['connection_data']) ) { ?>
    <div class="large-6 columns end">
		<div class="panel">
    		<h6><?php echo $external_provider['name']; ?></h6>
    		<p class="subheader">Description of what this provider is used for</p>
<?php if( !empty($external_provider['methods']['login']) ) { ?>
			<?php echo $this->Html->link('I have an account', '/' . $controller_name . '/login/' . $account['Account']['id'], array(
                'class' => 'button small error-message' // 'error-message' is for adding a CSS class to the login links, when using the default CakePHP theme. To be changed
				)); ?>
<?php } ?>
<?php if( !empty($external_provider['methods']['register']) ) { ?>
            <?php echo $this->Html->link('I need a new account', '/' . $controller_name . '/register/' . $account['Account']['id'], array(
                    'class' => 'button small success'
                )); ?>
<?php } ?>
		</div>
    </div>
<?php } else { ?>
<?php // We don't have to display logged-in external providers. We just display their connected plugins / data, when necessary ?>
    <?php //echo nl2br(print_r(json_decode($external_provider['connection_data']), true)); ?>
<?php } ?>
<?php } ?>
<?php unset($external_provider); ?>
</div>
<?php } ?>
