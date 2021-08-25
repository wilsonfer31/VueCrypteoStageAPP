<?php

include "config.php";
;
if(isset($_GET['utilisateurid'])){
	$number = $_GET['utilisateurid'];

	

	  $response = $db->qs()
			->from('communication_mobile')
			->select(array('*', $db->expr('SUM(`communication_mobile`.`real_duration`)  as allData ')))
			->join('customer_phonenumber', 'communication_mobile.calling_number', 'number')
			->join('customer','customer_phonenumber.customer_id', 'id' )
			->join('contract','customer_phonenumber.customer_id', 'id' )
			->where(array('communication_mobile.calling_number' => $number))		
			->execute()
			->fetchAll(PDO::FETCH_ASSOC);

}else if(isset($_GET['PageValue'])){
	$nextPageNumber = $_GET['PageValue'];
	$size =  $_GET['limit'];


$response = $db->qs()
	->from('communication_mobile')
	->select(array('*', $db->expr('SUM(`communication_mobile`.`real_duration`)  as allData ')))
	->join('customer_phonenumber', 'communication_mobile.calling_number', 'number')
	->join('customer','customer_phonenumber.customer_id', 'id' )
	->join('contract','customer_phonenumber.customer_id', 'id' )
	->groupBy('communication_mobile.calling_number')	
	->limit( $nextPageNumber,$size)
	->execute()
	->fetchAll(PDO::FETCH_ASSOC);

}else if(isset($_GET['alertGigas'])){
	$alertGigas = $_GET['alertGigas'];
	$number =  $_GET['number'];
	$response = $db->update('customer_phonenumber', ['alert' => $alertGigas], ['number' => $number]);


}else if(isset($_GET['isChecked'])){
	$isChecked = $_GET['isChecked'];
	$number =  $_GET['number'];
	$response = $db->update('customer_phonenumber', ['alert_active' => $isChecked], ['number' => $number]);



}else if(isset($_GET['sub'])){
	$subValue = $_GET['sub'];

	$number =  $_GET['number'];
	$response = $db->update('customer_phonenumber', ['contract' => $subValue], ['number' => $number]);
}else if(isset($_GET['getPage'])){
	$response = $db->qs()
	->from('communication_mobile')
	->select(array($db->expr(" COUNT(DISTINCT `calling_number`)  as nbOfUsers")))
	->execute()
	->fetchAll(PDO::FETCH_ASSOC);
}else{
	$size =  $_GET['limit'];
$response = $db->qs()
->from('communication_mobile')
->select(array('*', $db->expr('SUM(`communication_mobile`.`real_duration`)  as allData ')))
->join('customer_phonenumber', 'communication_mobile.calling_number', 'number')
->join('customer','customer_phonenumber.customer_id', 'id' )
->join('contract','customer_phonenumber.customer_id', 'id' )
->groupBy('calling_number')	
->limit(0,$size)
->execute()
->fetchAll(PDO::FETCH_ASSOC);
}


echo json_encode($response);
exit;


