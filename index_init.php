<?php
	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );
	require_once("circpro.php");
	require_once("global.resources.php");
	require_once("wp.resources.php");
	require_once("Email_Service.php");


	date_default_timezone_set("Pacific/Honolulu");
	$date_append 			= date("Ymd");
	$time_start 			= microtime(true);
	$json_filename 			= './guzzle/json/section_' . $date_append . '.json';
	$url 					= ""; 
	$iterable_csv_filename	= './guzzle/iterable/Iterable_List_' . $date_append . '.csv';


//----------------------- SFTP CREDS ---------------------------

//----------------------- SFTP CREDS ---------------------------

	$result = create_csv_from_url($url, $iterable_csv_filename); 
	print_pre($result);
	echo "<br>Created Iterable CSV Success";
//	set divisor
	$max_emails_per_section = 45;
//	find total sections to be written to Json
	$section_count = count_sections($iterable_csv_filename, $max_emails_per_section);
	$total_index = $section_count;  
	print_pre($total_index);
//	write the json file
	write_json($total_index, $json_filename);
//	upload the json to SA sftp
	// sftp_upload($dataFile, $sftpServer, $sftpUsername, $sftpPassword, $sftpPort, $sftpRemoteDir); // sftp upload disabled due to connectivity issues 07/18/2018



	function write_json($divisor, $json_filename){
		global $total_index;
		$json[$total_index] = array('total_index' => $total_index);
		print_pre($json[$total_index]);
		$write_json = file_put_contents($json_filename, json_encode($json[$total_index]));
		if($write_json !== false) {
			echo "Json write success at $json_filename";
		}
	}

	function sftp_upload($dataFile, $sftpServer, $sftpUsername, $sftpPassword, $sftpPort, $sftpRemoteDir){
		$ch = curl_init('sftp://' . $sftpServer . ':' . $sftpPort . $sftpRemoteDir . '/' . basename($dataFile));
	 
		$fh = fopen($dataFile, 'r');
		 
		if ($fh) {
		    curl_setopt($ch, CURLOPT_USERPWD, $sftpUsername . ':' . $sftpPassword);
		    curl_setopt($ch, CURLOPT_UPLOAD, true);
		    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
		    curl_setopt($ch, CURLOPT_INFILE, $fh);
		    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($dataFile));
		    curl_setopt($ch, CURLOPT_VERBOSE, true);
		 
		    $verbose = fopen('php://temp', 'w+');
		    curl_setopt($ch, CURLOPT_STDERR, $verbose);
		 
		    $response = curl_exec($ch);
		    $error = curl_error($ch);
		    curl_close($ch);
		 
		    if ($response) {
		        echo " Upload to $sftpServer : Success";
		    } else {
		        echo "Failure";
		        rewind($verbose);
		        $verboseLog = stream_get_contents($verbose);
		        echo "Verbose information:\n" . $verboseLog . "\n";
		    }
		}

	}
	

	function count_sections ($iterable_csv_filename, $divisor ){
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
		$total_count = count($sections);
		return $total_count;
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


	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo '<br>Execution time : '.$time.' minutes <br>';

?>