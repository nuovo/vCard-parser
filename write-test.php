<?php
	require_once('vCard.php');

	$vCard = new vCard;
	$vCard -> n('John', 'FirstName');
	$vCard -> n('Doe', 'LastName');
	$vCard -> tel('555-1111');
	$vCard -> tel('555-1234', 'Work');

	$vCard = new vCard('Example3.0.vcf');

	echo '<pre>'.$vCard.'</pre>';
?>