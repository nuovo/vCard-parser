<?php
	require_once('vCard.php');

	$vCard = new vCard;
	$vCard -> n('John', 'FirstName');
	$vCard -> n('Doe', 'LastName');
	$vCard -> tel('555-1111');
	$vCard -> tel('555-1234', 'Work');
	$vCard -> adr('', 'POBox');
	$vCard -> adr('', 'ExtendedAddress');
	$vCard -> adr('42 Plantation St.', 'StreetAddress');
	$vCard -> adr('Baytown', 'Locality');
	$vCard -> adr('LA', 'Region');
	$vCard -> adr('30314', 'PostalCode');
	$vCard -> adr('USA', 'Country');

  $vCard -> setAttr('X-GENERIC', 'Dummy');

	//$vCard = new vCard('Example3.0.vcf');

	echo '<pre>'.$vCard.'</pre>';
?>