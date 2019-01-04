<html>
<body>

<?php
$ip = $_SERVER['REMOTE_ADDR'];
$USER = '';
$PASS = '';
$ATTEMPT_CODE = '';
$ORIGINAL_SP_REF_ID = '';
$PRD_CUSTOMER_ID = '';
$PRD_PRODUCT = '';
$PRD_TRANSACTION_AMOUNT = '';
$PRD_CC_LAST = '';
$PRD_NEXT_CHARGE_DATE = '';
$TEST_MODE = '';
?>

<form method='post' action='https://harbor.com/wp-content/plugins/harboruoda-prd/prd-listener-csn.php'>

<table>
<tr><td>USER: </td><td><input type='text' name='USER' value='<?php echo $USER; ?>'></td></tr>
<tr><td>PASS: </td><td><input type='text' name='PASS' value='<?php echo $PASS; ?>'></td></tr>
<tr><td>ATTEMPT_CODE: </td><td><input type='text' name='ATTEMPT_CODE' value='<?php echo $ATTEMPT_CODE; ?>'></td></tr>
<tr><td>ORIGINAL_SP_REF_ID: </td><td><input type='text' name='ORIGINAL_SP_REF_ID' value='<?php echo $ORIGINAL_SP_REF_ID; ?>'></td></tr>
<tr><td>PRD_SP_REF_ID: </td><td><input type='text' name='PRD_SP_REF_ID' value='<?php echo $PRD_SP_REF_ID; ?>'></td></tr>
<tr><td>PRD_CUSTOMER_ID: </td><td><input type='text' name='PRD_CUSTOMER_ID' value='<?php echo $PRD_CUSTOMER_ID; ?>'></td></tr>
<tr><td>PRD_PRODUCT: </td><td><input type='text' name='PRD_PRODUCT' value='<?php echo $PRD_PRODUCT; ?>'></td></tr>
<tr><td>PRD_TRANSACTION_AMOUNT: </td><td><input type='text' name='PRD_TRANSACTION_AMOUNT' value='<?php echo $PRD_TRANSACTION_AMOUNT; ?>'></td></tr>
<tr><td>PRD_CC_LAST: </td><td><input type='text' name='PRD_CC_LAST' value='<?php echo $PRD_CC_LAST; ?>'></td></tr>
<tr><td>PRD_NEXT_CHARGE_DATE: </td><td><input type='text' name='PRD_NEXT_CHARGE_DATE' value='<?php echo $PRD_NEXT_CHARGE_DATE; ?>'></td></tr>
<tr><td>TEST_MODE: </td><td><input type='checkbox' name='TEST_MODE' value='true' <?php echo $TEST_MODE; ?>></td></tr>
<tr><td></td><td align=right><input type='submit'></td></tr>
</table>

</form>

</body>
</html>
