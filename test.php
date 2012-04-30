<!DOCTYPE html>   
<html>
<head>
	<meta charset="utf-8" />
	<style type="text/css">
	body
	{
		font-family: Arial, sans-serif;
		padding: 20px 50px;
	}
	div.Agent
	{
		padding: 20px;
		border: 1px solid #ddd;
		background-color: #fafafa;
	}
	img
	{
		float: right;
		margin: 10px;
		padding: 10px;
		border: 1px solid #ddd;
	}
	</style>
</head>

<?php
	require_once('vCard.php');

	/**
	 * Test function for vCard content output
	 * @param vCard vCard object
	 */
	function OutputvCard(vCard $vCard)
	{
		echo '<h2>'.$vCard -> FN[0].'</h2>';

		if ($vCard -> PHOTO)
		{
			foreach ($vCard -> PHOTO as $Photo)
			{
				if ($Photo['Encoding'] == 'b')
				{
					echo '<img src="data:image/'.$Photo['Type'][0].';base64,'.$Photo['Value'].'" /><br />';
				}
				elseif ($Photo['Encoding'] == 'uri')
				{
					echo '<img src="'.$Photo['Value'].'" /><br />';
				}

				/*
				// It can also be saved to a file
				try
				{
					$vCard -> SaveFile('photo', 0, 'test_image.jpg');
					// The parameters are:
					//	- name of the file we want to save (photo, logo or sound)
					//	- index of the file in case of multiple files (defaults to 0)
					//	- target path to save to, including the filenam
				}
				catch (Exception $E)
				{
					// Target path not writable
				}
				*/
			}
		}

		foreach ($vCard -> N as $Name)
		{
			echo '<h3>Name: '.$Name['FirstName'].' '.$Name['LastName'].'</h3>';
		}

		if ($vCard -> TEL)
		{
			echo '<p><h4>Phone</h4>';
			foreach ($vCard -> TEL as $Tel)
			{
				echo $Tel['Value'].' ('.implode(', ', $Tel['Type']).')<br />';
			}
			echo '</p>';
		}

		if ($vCard -> EMAIL)
		{
			echo '<p><h4>Email</h4>';
			foreach ($vCard -> EMAIL as $Email)
			{
				echo $Email['Value'].' ('.implode(', ', $Email['Type']).')<br />';
			}
			echo '</p>';
		}

		if ($vCard -> AGENT)
		{
			echo '<h4>Agents</h4>';
			foreach ($vCard -> AGENT as $Agent)
			{
				echo '<div class="Agent">';
				OutputvCard($Agent);
				echo '</div>';
			}
		}
	}

	$vCard = new vCard(
		'Example.vcf', // Path to vCard file
		false, // Raw vCard text, can be used instead of a file
		array( // Option array
			// This lets you get single values for elements that could contain multiple values but have only one value.
			//	This defaults to false so every value that could have multiple values is returned as array.
			'Collapse' => false
		)
	);

	if (count($vCard) == 0)
	{
		throw new Exception('vCard test: empty vCard!');
	}
	// if the file contains a single vCard, it is accessible directly.
	elseif (count($vCard) == 1)
	{
		OutputvCard($vCard);
	}
	// if the file contains multiple vCards, they are accessible as elements of an array
	else
	{
		foreach ($vCard as $Index => $vCardPart)
		{
			OutputvCard($vCard);
		}
	}
?>