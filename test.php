<?php
   
require_once 'parser_class.php';    
$path = 'contacts-sales-lv-people.vcf';     
$parse = new vCard($path);
print_r($parse);

?>