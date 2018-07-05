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
	$time_start = microtime(true);


	$section_number = $_GET['section_number'];

	$url = "-"; //Daily Newspaper Alert 4,513
	$iterable_csv_filename		= './temp/Iterable_List_' . $date_append . '.csv';
	$pending_email_filename 	= './temp/'.$section_number.'_Pending_emails_' . $date_append . '.csv';
	$processed_email_filename 	= './unmerged_csv/'.$section_number.'_Processed_list_' . $date_append . '.csv';
	$failed_email_filename 		= './temp/'.$section_number.'_Failed_list_' . $date_append . '.csv'; 
	$divisor = 400 ; // number of emails you want per section 
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
	// print_pre($all_emails);
//	Filter the section by WP = $processed_emails & $pending_emails
	filter_active_emails_by_wp($section_emails);
	create_csv_from_array($pending_email_filename, $pending_emails);
//	If there's $pending_emails , recheck by Circpro 
	if(!empty($pending_emails)){
		global $final_email_list;
		global $processed_emails;
		global $processed_emails_2;
		filter_active_emails_by_circpro($pending_emails);
		$final_email_list = array_merge($processed_emails, $processed_emails_2);
		$total_final_email_list = count($final_email_list);
		echo "<br>PROCESSED EMAILS total count : $total_final_email_list<br>"; 
		// print_pre($final_email_list);
		$total_failed_email_list = count($failed_emails);
		echo "<br>FAILED EMAILS total count : $total_failed_email_list<br>";
		// print_pre($failed_emails);
		create_csv_from_array($processed_email_filename, $final_email_list);
		create_csv_from_array($failed_email_filename, $failed_emails);
	}else{
//	If no pending email, $processed_emails is the final list 
		$total_final_email_list = count($processed_emails);
		echo "<br>PROCESSED EMAILS total count : $total_final_email_list<br>"; 
		create_csv_from_array($processed_email_filename, $processed_emails);
		create_csv_from_array($failed_email_filename, $failed_emails);
	}


	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo '<br>Execution time : '.$time.' minutes <br>';

//==================== F U N C T I O N S ==================== //


function array_not_unique($raw_array) {
    $dupes = array();
    natcasesort($raw_array);
    reset($raw_array);

    $old_key   = NULL;
    $old_value = NULL;
    foreach ($raw_array as $key => $value) {
        if ($value === NULL) { continue; }
        if (strcasecmp($old_value, $value) === 0) {
            $dupes[$old_key] = $old_value;
            $dupes[$key]     = $value;
        }
        $old_value = $value;
        $old_key   = $key;
    }
    return $dupes;
}
	function print_pre($object) {
		?><pre><?php print_r($object); ?></pre><?php
	}

	function create_csv_from_url ($url, $filename) {
		$date_append = date("Ymd");
		$outputcsv=file_get_contents($url);
		// $outputcsv=file_get_contents($url, false, null, 0, 2000); // write few emails only characters for testing = 13 emails
		file_put_contents($filename, $outputcsv);
	}



	function get_section_emails ($iterable_csv_filename, $section_number, $divisor ){
		if (($handle = fopen($iterable_csv_filename, "r")) !== FALSE) {
			global $section_emails;
			global $all_emails;
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
		$sections = array_chunk($all_emails, $divisor);
		print_pre($sections);
		$tot_sec = (count($sections))-1;
		echo "<br>This is section $section_number out of $tot_sec<br>";
		print_pre($sections);
		$section_emails =  $sections[$section_number];
		$tot_section_emails = count($section_emails);
		echo "<br>This is total section emails : $tot_section_emails<br>";

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
			$email = $pending_emails[$i];
			if ( !empty($email) ) {
				global $processed_emails;
				global $pending_emails;
				global $failed_emails;
				global $processed_emails_2;
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