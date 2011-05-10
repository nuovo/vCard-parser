
<?php

$path = 'contacts-sales-lv-people.vcf';     
//$path='test.vcf';
$parse = new vCard($path);
echo '<pre>';
print_r($parse->vCards);
echo '</pre>';

 /**
    * 
    * Class parses vCard file and returns array with information form vCard.
    * 
    * @access public
    * 
    * @return array with vCards
    * 
    */
class vCard{

 public $mode;  //single, multiple, error
 public $vCards = array();

 /**
    * 
    * Functions which reads text line by line from file
    * and puts it into formatted array
    *
    * @access public
    * 
    * @return array with one or multiple vCards()
    * 
    */
 public function vCard($path){
  $lines = file($path);
  $card = array();
  //$counter = 0;
  
  foreach($lines as $line){
  
   $pos=strpos($line, 'BEGIN:VCARD');
   if($pos !== false){
    $vCard = array();
    continue;
   }
   
   $pos = strpos($line,':');
   
   if($pos !== false){
    $pos2 = strpos($line,'END:VCARD');
	   if($pos2 !== false){
	    $this->vCards[] = $vCard;
	    continue;
	   }
    $marker=$this->escape(substr($line,0,$pos));
    $line_end=$this->escape(substr($line,$pos+1,strlen($line)));
    $pos3=strpos($marker,';');

    if(strpos($marker,';')!==false){ 
	    $a=$this->Qcode($marker, $line_end);
		$vCard[$a[0]]=$a[1];
    }
    else{
    	$vCard[$marker] = $line_end;
    }

   }else{
   	    $a=$this->Qcode($marker, $line_end);
    	$vCard[$a[0]] = $vCard[$a[0]] . $this->escape($line);
    
   }
  } 
 }
 /**
    * 
    * Function checks if in string is any formatting rule, and
    * if there is any then returns cleared marker and formatted string
    * @access public
    * 
    * @return cleared text string
    * @return formated marker
    *
    * @see vCard()
    * 
    */
 public function Qcode($marker,$line_end){
	$exploded = explode (';',$marker);
	if (in_array('QUOTED-PRINTABLE',$exploded)){
		$returnam = array(substr($marker,0,strpos($marker,';')),trim(quoted_printable_decode($line_end)));
		return $returnam;
	}
	else{
		$returnam = array(substr($marker,0,strpos($marker,';')),trim($line_end));
		return $returnam;
	}
 	
 }
 
 
 /**
    * 
    * Functions which clears text from unneccessary symbols
    * 
    * @access public
    * 
    * @return cleared text string
    *
    * @see vCard()
    * 
    */
 
 public function escape(&$text){
  $text = str_replace('\:', ':', $text);
  $text = str_replace('\;', ';', $text);
  $text = str_replace(';;', '', $text);
  $text = str_replace('\,', ',', $text);
  $text = str_replace("\n", "", $text);

    
  return $text;
 }
}

?>