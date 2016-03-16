<?php
	$order_id = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_GET['m_orderid'], 0, 32));
	$_REQUEST['option']='com_virtuemart';
	$_REQUEST['view']='pluginresponse';
	$_REQUEST['task']='pluginresponsereceived';
	$_REQUEST['oi'] = $order_id;
?>
<form action="../../../index.php" method="post" name="fname">
	<input type="hidden" name="option" value="com_virtuemart">
	<input type="hidden" name="view" value="pluginresponse">
	<input type="hidden" name="task" value="pluginresponsereceived">
	<input type="hidden" name="oi" value="<?php echo $order_id; ?>">
</form>
<script>document.forms.fname.submit();</script>