<div class="header-spacer"></div>
<div class="row">
	<div class="columns">
		<h3>Setup Your New Account</h3>
		<ul class="breadcrumbs">
			<li><a href="#">Step 1: <strong>Add Account Info</strong></a></li>
			<li><a href="#">Step 2: <strong>Add Billing Info</strong></a></li>
			<li class="current"><a href="#">Step 3: <strong>Activate Google Analytics</strong></a></li>
			<li class="unavailable"><a href="#">Step 4: <strong>Create Site Tags</strong></a></li>
		</ul>
	</div>
</div>
<div class="row">
	<div class="columns">
		<div class="panel">
			<div class="row">
				<div class="large-3 columns">
					<a class="button small success radius error-message expand" href="/GoogleAnalyticsAccounts/login/3?setup=true">Activate Google Analytics</a>
				</div>
				<div class="large-9 columns">
					<p class="subheader">Activate Google Analytics to include your website's analytics data in the Choozle Insights reporting.</p>
					<p class="subheader">When you click the Activate Google Analytics button, you will be temporarily redirected to Google where you can select which account you would like to connect to. After selecting your account, you will be redirected back to this step in the account creation process.</p>
					<?php echo $this->Html->link('<strong>Skip this step</strong> and do it later under the account\'s Settings tab.', array('controller' => 'sites','action' => 'index', 'true'), array('escape' => FALSE)); ?>
				</div>
			</div>
	    </div>
    </div>
</div>