<?php

	require_once("circpro.php");
	require_once("global.resources.php");
	require_once("wp.resources.php");
	require_once("Email_Service.php");

	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );
	date_default_timezone_set("Pacific/Honolulu");
	// $date_append = "20180621";
	$date_append = date("Ymd");


	$section_number = $_GET['section_number'];

	
	$time_start = microtime(true);

	$url = "-"; //Daily Newspaper Alert 4,513

	$iterable_csv_filename		= './temp/Iterable_List_' . $date_append . '.csv';
	$pending_email_filename 	= './temp/'.$section_number.'_Pending_emails_' . $date_append . '.csv';
	$processed_email_filename 	= './unmerged_csv/'.$section_number.'_Processed_list_' . $date_append . '.csv';
	$failed_email_filename 		= './temp/'.$section_number.'_Failed_list_' . $date_append . '.csv'; 
	$divisor = 450 ; // number of emails you want per section 
	$all_emails = array();
	$section_emails;
	$sections;
	$processed_emails = array();
	$pending_emails = array();	
	$processed_emails_2 = array();
	$failed_emails = array();
	$final_email_list;

//	If first run, then create the iterable CSV = $iterable_csv_filename
	if ( $section_number == 0 ){
		create_csv_from_url($url, $iterable_csv_filename); 
		echo "<br>Created Iterable CSV for SECTION NUMBER : $section_number <br>";
	}
//	Break Iterable CSV into sections = $section_emails 
	get_section_emails($iterable_csv_filename, $section_number, $divisor);
	// print_pre($section_emails);
//	Filter the section by WP = $processed_emails & $pending_emails
	filter_active_emails_by_wp($section_emails);
	// print_pre($processed_emails);
	// print_pre($pending_emails);
//	If there's $pending_emails , recheck by Circpro 
//	If none, $processed_emails is the final list 
	if(!empty($pending_emails)){
		filter_active_emails_by_circpro($pending_emails);
	}else{
		create_csv_from_array($processed_email_filename, $processed_emails);
	}
//	Create CSV for reprocessed emails
	if(!empty($processed_emails_2)){
		global $final_email_list;
		$final_email_list = array_merge($processed_emails, $processed_emails_2);
		create_csv_from_array($processed_email_filename, $final_email_list);
		create_csv_from_array($failed_email_filename, $failed_emails);
	}else{
		$final_email_list = $processed_emails;
		create_csv_from_array($processed_email_filename, $final_email_list);
		create_csv_from_array($failed_email_filename, $failed_emails);
	}	


	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo '<br>Execution time : '.$time.' minutes <br>';



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



	function get_section_emails ($iterable_csv_filename, $section_number, $divisor ){
		if (($handle = fopen($iterable_csv_filename, "r")) !== FALSE) {
			global $section_emails;
			global $all_emails;
			echo "fopen";
			$row = 0;
			while (($data = fgetcsv($handle, 1000 , ",")) !== FALSE) {
			    $num = count($data);
			    $row++;
			    for ($c=0; $c < $num; $c++) {	    	
			    	$all_emails[] = $data[$c];

		    	}
			}
		  fclose($handle);
		}
		// print_pre($all_emails);
		$sections = array_chunk($all_emails, $divisor);
		$section_emails =  $sections[$section_number];
	}

	function filter_active_emails_by_wp ($section_emails){
		$total_section_emails = count($section_emails);
		// print_pre($total_section_emails);		
		for ($i=0; $i < $total_section_emails; $i++){
			$email = $section_emails[$i];
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
	}

	
	function filter_active_users ($email){
		if ( !empty($email) ) {
			global $processed_emails;
			global $pending_emails;
			$subscriber = get_subscriber_wp_data($email);
			$name_id = ($subscriber['name_id']);
			$pub_id = ($subscriber['publication_id']);
			if( $name_id && strlen($pub_id_wp) === 7 ){
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


	function create_csv_from_array ($csv_name, $array) {
		$file = fopen($csv_name,"w");
		foreach ($array as $email)
		{
			fputcsv($file,explode(',',$email));
		}
		fclose($file); 
	}


	function filter_active_emails_by_circpro($pending_emails){
		$total_pending_emails = count($pending_emails);
		for ($i=0; $i < $total_pending_emails; $i++){
			$email = $pending_emails[i];
			if ( !empty($email) ) {
				global $processed_emails;
				global $pending_emails;
				$subscriber = get_subscriber_wp_data($email);
				$name_id = ($subscriber['name_id']);
				$account_no_wp = ($subscriber['account_number']);
				$get_pubID_with_getCustomerAndBalance = getCustomerAndBalance($name_id,$account_no_wp);
				$pub_id_wp_2 = $get_pubID_with_getCustomerAndBalance['subscription']['publication_id'];
				$status = isCustomerAllowed($name_id, $pub_id_wp_2);
				if($status === true){
					$processed_emails_2[] = $email;
				}else{
					$failed_emails[] = $email;
				}				
			}
		}
	}


?>