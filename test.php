<?php
	require_once('vCard.php');

	$vCard = new vCard('Example3.0.vcf');

	if (count($vCard) == 0)
	{
		throw new Exception('vCard test: empty vCard!');
	}
	// if the file contains a single vCard, it is accessible directly.
	elseif (count($vCard) == 1)
	{
		echo '<pre>';
		print_r($vCard -> n);
		print_r($vCard -> tel);
		print_r($vCard);
		echo '</pre>';
	}
	// if the file contains multiple vCards, they are accessible as elements of an array
	else
	{
		foreach ($vCard as $Index => $vCardPart)
		{
			echo '<pre>';
			print_r($vCardPart -> n);
			print_r($vCardPart -> tel);
			print_r($vCardPart -> email);
			print_r($vCardPart -> phone);
			print_r($vCardPart -> adr);
			echo '</pre>';
			echo '<hr />';
		}
	}
?>