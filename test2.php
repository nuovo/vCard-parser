<?php

include('vCard.php');
$vCard = new vCard('Example3.0.vcf');

printf("nb contact:%s\n",count($vCard));
$keylist=$vCard->getKeyList();
foreach($keylist as $key => $val)
{
  printf("Properties:%s\n",$val);
  print_r($vCard->$val);
}
