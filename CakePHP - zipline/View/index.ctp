<div class="header-spacer"></div>
<div class="row">
    <div class="large-12 columns main-content-column">
        <h3>My Accounts</h3>
        <?php echo $this->Session->flash(); ?>
	    <table class="report-table" width="100%">
	        <thead>
		        <tr>
		            <th>Company</th>
		            <th>Subscription</th>
		            <th>Industry</th>
		            <th>Region</th>
		            <th>Created</th>
		            <th>Action</th>
		        </tr>
	        </thead>
	        <!-- Here is where we loop through our $accounts array, printing out account info -->
	        <tbody>
		        <?php foreach ($accounts as $account): ?>
		        <tr>
		            <td><?php echo $this->Html->link($account[0]['company_name'], array('controller' => 'accounts', 'action' => 'view', $account[0]['id'])); ?></td>
		            <td><?php echo $account[0]['subscription']; ?></td>
		            <td><?php echo $account[0]['industry']; ?></td>
		            <td><?php echo $account[0]['region']; ?></td>
		            <td><?php echo date('d F Y, H:i', strtotime($account[0]['created'])); ?></td>
		            <td><?php //echo $this->Form->postLink('Delete', array('action' => 'delete', $account[0]['id']), array('confirm' => 'Are you sure?')); ?>
                        <?php if (empty($account[0]['permission']) || $account[0]['permission']=='A') { ?>
		                <?php echo $this->Html->link('Edit', array('action' => 'edit', $account[0]['id']), array('class' => 'button small radius')); ?>
                        <?php } ?></td>
		        </tr>
		        <?php endforeach; ?>
		        <?php unset($account); ?>
	        </tbody>
	    </table>
	    <p><?php echo $this->Html->link('Add Account', array('controller' => 'accounts', 'action' => 'add'), array('class' => 'button small success radius')); ?></p>
    </div>
</div>

