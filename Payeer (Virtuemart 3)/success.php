<?php
$_REQUEST['option']='com_virtuemart';
$_REQUEST['view']='pluginresponse';
$_REQUEST['task']='pluginresponsereceived';
$_REQUEST['oi'] = $_REQUEST['m_orderid'];
?>
<form action="../../../index.php" method="post" name="fname">
	<input type="hidden" name="option" value="com_virtuemart">
	<input type="hidden" name="view" value="pluginresponse">
	<input type="hidden" name="task" value="pluginresponsereceived">
	<input type="hidden" name="oi" value="<?php echo $_GET['m_orderid']; ?>">
</form>
<script>
document.forms.fname.submit();
</script>
