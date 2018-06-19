<?php

	require_once("circpro.php");
	require_once("global.resources.php");
	require_once("wp.resources.php");
	require_once("Email_Service.php");

	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );
	date_default_timezone_set("Pacific/Honolulu");
	$date_append = date("Ymd");

//When the list gets longer - manage partition number
//***Cron how to determine what batch you are processing and how many batches
//WP Data  --- circpro call only if no pubid from wp//
//function below //
//Refactor 1 task per function // 


	$section_number = $_GET['section_number'];

	
	$time_start = microtime(true);

	$url = "---"; //Daily Newspaper Alert 4,513
//\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\
//	1st STEP : CURL ITERABLE API 
//	RESULT : CSV File from ITERABLE LIST
//---------------------------------------------------------------------
	$iterable_csv_filename = './temp/Iterable_List_' . $date_append . '.csv';
	create_csv_from_url($url, $iterable_csv_filename); 
	echo "Created Iterable CSV";
//\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\
//	2nd STEP : READ THE ITERABLE CSV , PARTITION AND FILTER ACTIVE USERS
//	RESULT : ARRAY OF PENDING EMAILS
//---------------------------------------------------------------------
	filter_partitioned_emails($iterable_csv_filename, 3, $section_number);
//\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\
//	3rd STEP : Create CSV FOR PENDING EMAILS
//---------------------------------------------------------------------
	$pending_email_filename = './temp/'.$section_number.'_Pending_emails_' . $date_append . '.csv';
	create_csv_from_array($pending_email_filename,$pending_emails);
//\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\
//	4th STEP : RECHECK PENDING EMAILS - Wordpress Data & Circpro Data (getCustomerAndBalance)
//	RESULT : PROCESSED LIST - ACTIVE EMAILS & FAILED EMAILS
//---------------------------------------------------------------------
	$processed_email_filename = './unmerged_csv/'.$section_number.'_Processed_list_' . $date_append . '.csv';
	$failed_email_filename = './temp/'.$section_number.'_Failed_list_' . $date_append . '.csv'; 

	$row = 0;
	if (($handle = fopen($pending_email_filename, "r")) !== FALSE) {
		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
		    $num = count($data);
		    $row++;
		    for ($c=0; $c < $num; $c++) {	    	
		    	$active = create_processed_list ($data[$c], $processed_email_filename, $failed_email_filename);
	    	}
		}
	  fclose($handle);
	}

	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo '<br>Execution time : '.$time.' minutes';



//==================== F U N C T I O N S ==================== //

	function print_pre($object) {
		?><pre><?php print_r($object); ?></pre><?php
	}

	function create_csv_from_url ($url, $filename) {
		$date_append = date("Ymd");
		$outputcsv=file_get_contents($url);
		// $outputcsv=file_get_contents($url, false, null, 0, 1000); // write few emails only characters for testing = 13 emails
		file_put_contents($filename, $outputcsv);
	}
	
	$processed_emails = array();
	$pending_emails = array();		
	function filter_active_users ($email){
		if ( !empty($email) ) {
			global $processed_emails;
			global $pending_emails;
			$subscriber = get_subscriber_wp_data($email);
			$name_id = ($subscriber['name_id']);
			$pub_id = ($subscriber['publication_id']);
			if( $subscriber ){
				$status = isCustomerAllowed($name_id, $pub_id);
				if($status === true) {
					$processed_emails[] = $email;
				}else{
					$pending_emails[] = $email;
				}
			}else{
				$pending_emails[] = $email;
			}
		}
	}

	function partition( $list, $p ) {
	    $listlen = count( $list );
	    $partlen = floor( $listlen / $p );
	    $partrem = $listlen % $p;
	    $partition = array();
	    $mark = 0;
	    for ($px = 0; $px < $p; $px++) {
	        $incr = ($px < $partrem) ? $partlen + 1 : $partlen;
	        $partition[$px] = array_slice( $list, $mark, $incr );
	        $mark += $incr;
	    }
	    return $partition;
	}

	$all_emails = array();
	$partitioned_emails;
	function filter_partitioned_emails ($iterable_csv_filename, $partition_number, $section_number){
		if (($handle = fopen($iterable_csv_filename, "r")) !== FALSE) {
			global $partitioned_emails;
			global $all_emails;
			echo "fopen";
			$row = 0;
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			    $num = count($data);
			    $row++;
			    for ($c=0; $c < $num; $c++) {	    	
			    	$all_emails[] = $data[$c];
			    	$section = partition($all_emails, $partition_number);
			    	$partitioned_emails =  $section[$section_number];
			    	$emails_count = count($partitioned_emails);
		    	}
			}
		  fclose($handle);
		}
		$totalSec = count($partitioned_emails);
		for ($i=0; $i < $totalSec; $i++){
			$active = filter_active_users($partitioned_emails[$i]);
		}
	}

	function create_csv_from_array ($csv_name, $array) {
		$file = fopen($csv_name,"w");

		foreach ($array as $email)
		{
			fputcsv($file,explode(',',$email));
		}
		fclose($file); 
	}




	$processed_emails_2 = array();
	$failed_emails = array();
	$final_email_list;
	function create_processed_list($email, $processed_email_filename, $failed_email_filename){
		if ( !empty($email) ) {
			global $processed_emails_2;
			global $failed_emails;
			global $processed_emails;
			$subscriber = get_subscriber_wp_data($email);
			$name_id = ($subscriber['name_id']);
			$pub_id_wp = ($subscriber['publication_id']); 
			$account_no_wp = ($subscriber['account_number']);
			if( $name_id && strlen($pub_id_wp) === 7 )  {
				$status = isCustomerAllowed($name_id, $pub_id_wp);
				if($status === true){
					$processed_emails_2[] = $email;
				}else{
					$failed_emails[] = $email;
				}
			}else if ( strlen($pub_id_wp) <= 7 ){
				$get_pubID_with_getCustomerAndBalance = getCustomerAndBalance($name_id,$account_no_wp);
				$pub_id_wp_2 = $get_pubID_with_getCustomerAndBalance['subscription']['publication_id'];
				$status = isCustomerAllowed($name_id, $pub_id_wp_2);
				if($status === true){
					$processed_emails_2[] = $email;
				}else{
					$failed_emails[] = $email;
				}				
			}else{
				$failed_emails[] = $email;
			}
		}else{
			$failed_emails[] = $email;
		}
		global $date_append;


		if(!empty($processed_emails_2)){
			global $final_email_list;
			$final_email_list = array_merge($processed_emails, $processed_emails_2);
			create_csv_from_array($processed_email_filename, $final_email_list);
			create_csv_from_array($failed_email_filename, $failed_emails);
		}else{
			$final_email_list = $processed_emails;
			create_csv_from_array($processed_email_filename, $final_email_list);
			// echo "<br>processed email csv created. <br>";
			create_csv_from_array($failed_email_filename, $failed_emails);
			// echo "<br>failed emails csv created. <br>";	
		}
		
	}
	print_pre($final_email_list);
?>