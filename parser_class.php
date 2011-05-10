
<?php

//$path = 'contacts-sales-lv-people.vcf';     
$path='test.vcf';
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
 public $marker0;
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
 public function __construct($path){
  $lines = file($path);
  $card = array();
  $remember='';
  foreach($lines as $line){

		$pos = strpos($line,':');
		$end=strlen(trim($line));
		if(substr(trim($line),$end-1, $end)=='='){
			$remember=$line;
			continue;
		}else{
			$remember='';
		}
		if($remember=''){
		
		//if it is beginning of card - skip  	
		$pos=strpos($line, 'BEGIN:VCARD');
		if($pos !== false){
		$vCard = array();
		continue;
		}
   
	   }else{
	   
	   $line=$line.$remember;
	   $remember='';
	   
	   //if it is end of card - skip
	   if($pos !== false){
		    $pos2 = strpos($line,'END:VCARD');
			   if($pos2 !== false){
				    $this->vCards[] = $vCard;
				    $vCard = array();
				    continue;
			}
			
			//clear marker and line 
	
		    $marker=$this->escape(substr($line,0,$pos));
		    $this->marker0 = $marker;  
		    $line_end=$this->escape(substr($line,$pos+1,strlen($line)));
		    $pos3=strpos($marker,';');
	
		    
			//if there are arguments like N;Type=Work: John Smith
		    if(strpos($marker,';')!==false){   
			    $newvar=$this->split($marker,$line_end);	
				$this->insert($newvar,$marker,$pos3,$vCard);	
	  
			}
			else// if there are no arguments like FN:John Smith
			{
				$newvar=$this->split($marker,$line_end);	
				$this->insert($newvar,$marker,strlen($marker),$vCard);			
		
			}
		}
	}
  } 
 }
 
 
 /**
    * 
    * Functions which inserts data into array
    * 
    *
    * @access public
    * 
    * @return nothing
    * 
    */

public function insert($newvar,$marker,$pos3,&$vCard){

  if(is_array($newvar)){
			    while (list($key, $value) = each($newvar)) { 
				
				   if(!isset($vCard[substr($marker,0,$pos3)])){
				   		$vCard[substr($marker,0,$pos3)][$key]=$value;

				   }
				   
				   else{

				   		if(is_array($vCard[substr($marker,0,$pos3)]))//if already tehre are such kid info
				   		{				   		
							if(isset($vCard[substr($marker,0,$pos3)][$key])){
								if(count($vCard[substr($marker,0,$pos3)][$key])==1)
								{
									$mas=$vCard[substr($marker,0,$pos3)][$key];
									$vCard[substr($marker,0,$pos3)][$key]=array('1'=>$value);
									$vCard[substr($marker,0,$pos3)][$key][]=$mas;
								}
								else{
									$vCard[substr($marker,0,$pos3)][$key][]=$value;
								}
							}
							else
							{
								$vCard[substr($marker,0,$pos3)][$key]=$value;
							}

							
				   		}	
				   		else{
				   			$perms= $vCard[substr($marker,0,$pos3)];
				   			$vCard[substr($marker,0,$pos3)][$key]=$value;		   			
							$vCard[substr($marker,0,$pos3)][]=$perms;
				   		
						}
				   		
				   }
  	
			    }
		    }
			else
			{
				if($newvar!=''){
					$marker=substr($marker,0, $pos3);
					$vCard[$marker]=$newvar;
				}

			}
			
			return;
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
  $text = str_replace(';;', ';', $text);
  $text = str_replace('\,', ',', $text);
  $text = str_replace("\n", "", $text);
  return $text;
 }
 
  /**
    * 
    * Functions which escape text from last symbols
    *
    * @access public
    * 
    * @return string with text
    * 
    */
 public function finalescape(&$text){
 	 $text = str_replace(";", "", $text);
 	 $text = str_replace(";;", "", $text);
	 return $text;
 }
 
 /**
    * 
    * Functions which splits name and address into 
    * multiple fields
    *
    * @access public
    * 
    * @return array with values
    * 
    */
 
 
 public function split($marker,$line_end)
 {	
	$pos=strpos($marker,';');
	if($pos!==false){
		$marker2=substr($marker,0, $pos);
	}
	else{
		$marker2=$marker;
	}
	
	switch ($marker2) {
    case 'N'://N;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Dombrovskis;J=C4=81nis;;;
		$exploded = explode (';',$marker);
		$exploded2 = explode (';',$line_end);
		$exploded2= array_filter($exploded2);
		if (in_array('ENCODING=QUOTED-PRINTABLE',$exploded)){
			$returnam = array('LastName'=>trim(quoted_printable_decode($exploded2[0])),'Name'=>trim(quoted_printable_decode($exploded2[1])));
		}
		else{
			$returnam = array('LastName'=>trim($exploded2[0]),'Name'=>trim($exploded2[1]));
		}
		return $returnam;
        break;
    case 'ADR'://ADR;TYPE=HOME:;;Eksporta 12-256;Riga;;;Latvia


		$line_end =substr($line_end,1,strlen($line_end));
		$line_end = str_replace(';', ' ', $line_end);
		$exploded2= explode(';',$marker);
		if (in_array('ENCODING=QUOTED-PRINTABLE',$exploded2)){
			$returnam = trim(quoted_printable_decode($line_end));
		}
		else{
			$returnam = trim($line_end);
		}

		return $returnam;  
		break;
	case 'URL':
		$types=substr($this->marker0,9,trim(strlen($this->marker0)));
		$types=explode(',',$types);
		foreach($types as $ty)
		{
			$returnam[$ty]=trim($line_end);
		}
		return $returnam;
	case 'TEL'://TEL;TYPE=WORK:+371 29594419
		$types=substr($this->marker0,9,trim(strlen($this->marker0)));
		$types=explode(',',$types);
		foreach($types as $ty)
		{
			$returnam[$ty]=trim($line_end);
		}
		
		return $returnam;
		break;
	case 'EMAIL'://EMAIL;TYPE=WORK,INTERNET:martins@sales.lv
	    $types=substr($this->marker0,11,trim(strlen($this->marker0)));
		$types=explode(',',$types);
		foreach($types as $ty)
		{
			$returnam[$ty]=trim($line_end);
		}

		return $returnam;
	    break;
    case 'X-TWITTER':
	    $types=substr($this->marker0,15,trim(strlen($this->marker0)));
		$types=explode(',',$types);
		foreach($types as $ty)
		{
			$returnam[$ty]=trim($line_end);
		}
		return $returnam;
    	break;
    case 'X-SKYPE':
	    $types=substr($this->marker0,13,trim(strlen($this->marker0)));
		$types=explode(',',$types);
		foreach($types as $ty)
		{
			$returnam[$ty]=trim($line_end);
		}
		return $returnam;
    	break;
    case 'FN':
	    $exploded = explode (';',$marker);
		$exploded2 = explode (';',$line_end);
		$exploded2= array_filter($exploded2);
		if (in_array('ENCODING=QUOTED-PRINTABLE',$exploded)){
			$returnam = trim(quoted_printable_decode($exploded2[0]));
		}
		else{
		
			$returnam = trim($exploded2[0]);
		}

		return $returnam;
    	break;
	case 'PHOTO':
		return '';
    	break;
    case 'NOTE':
    	return '';
    	break;
    default:
		break;
	}

	return ;
 }
 
}

?>