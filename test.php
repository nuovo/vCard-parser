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

		if ($vCard -> photo)
		{
			// If there is a photo or a logo, or a sound embedded in the file,
			//	it can be accessed directly, for example:
			foreach ($vCard -> photo as $Photo)
			{
				// You have to take into account that the file can also be a URI (in that case the Encoding parameter will be "uri" instead of "b"
				if ($Photo['Encoding'] == 'b')
				{
					echo '<img src="data:image/'.$Photo['Type'][0].';base64,'.$Photo['Value'].'" /><br />';
				}
				elseif ($Photo['Encoding'] == 'uri')
				{
					echo '<img src="'.$Photo['Value'].'" /><br />';
				}
			}

			// It can also be saved to a file
			$vCard -> SaveFile('photo', 0, 'test_image.jpg');
			// The parameters are:
			//	- name of the file we want to save (photo, logo or sound)
			//	- index of the file in case of multiple files (defaults to 0)
			//	- target path to save to, including the filenam
		}
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