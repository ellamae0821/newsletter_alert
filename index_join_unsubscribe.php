<?php
	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );

	require_once("circpro.php");
	require_once("global.resources.php");
	require_once("wp.resources.php");
	require_once("Email_Service.php");


	date_default_timezone_set("Pacific/Honolulu");
	$date_append = date("Ymd");
	// $date_append = "20180705";

	function print_pre($object) {
		?><pre><?php print_r($object); ?></pre><?php
	}

	$time_start = microtime(true);
	$login = array(
	);
	$url = ""; //Pressreader Only
	$list_url = "";
	$iterable_csv_filename		= './guzzle/iterable/Iterable_List_' . $date_append . '.csv';
	$pressreader_csv_filename = './guzzle/pressreader/Iterable_PressReader_List_' . $date_append . '.csv';
	$pressreader_list = $pressreader_csv_filename;
	$merged_list = './guzzle/merged/unfiltered_emails_' . $date_append . '.csv';
	$cleansed_emails = array();
	$unfiltered_list = array();
	$final_list = './guzzle/for_upload/HSA_newspaperalerts_ACTIVE_' . $date_append . '.csv';
	$failed_list_filename = './guzzle/unsubscribers/unsubscribers_' . $date_append . '.csv';
	$divisor = 45;
	$list_ID = 117560;
	$all_emails;	
	$section_emails;
//	get all the sections files with 30 emails each 
	$sections = get_section_emails($iterable_csv_filename, $divisor);
	echo "Got sections: $sections";
//	put all processed files into array (all_failedFiles_to_array)
	$all_list = all_processedFiles_to_array($sections);
//	create pressReader csv
	create_csv_from_url($url, $pressreader_csv_filename);
//	Joins all the files creating unfiltered list
	$all_list[] = $pressreader_list;
	print_pre($all_list);
	joinFiles($all_list, $merged_list);
//	filter duplicate emails creating HSA_Final list array
	filter_duplicate_emails($merged_list);
//	Final list array to CSV
	create_csv_from_array($final_list, $cleansed_emails);
//	Import the file to Tecnavia
	ftpFile("ftp.ta.newsmemory.com", $final_list);


//------------------ JOIN ALL FAILED FILES AND UNSUBSCRIBE -------------------- //

	$all_failed_list = all_failedFiles_to_array($sections);
	// print_pre($all_failed_list);
	joinFiles($all_failed_list, $failed_list_filename);

	$failed_emails_array = csv_to_array($failed_list_filename);
	print_pre($failed_emails_array);
	if( empty($failed_emails_array)){
		echo "No emails to unsubscribe. <br>";
	}else{
		unsubscribe_from_list($list_ID, $failed_emails_array, $list_url);
	}





	function all_processedFiles_to_array($array_of_sections){
		foreach ($array_of_sections as $key => $index){
			global $date_append;
			$each_listName = './guzzle/processed/'. $key .'_Processed_list_' . $date_append . '.csv';
			$all_list[] = $each_listName;
		}
		return $all_list;
	} 

	function all_failedFiles_to_array($array_of_sections){
		foreach ($array_of_sections as $key => $index){
			global $date_append;
			$each_listName = './guzzle/failed/'. $key .'_Failed_list_' . $date_append . '.csv';
			$all_list[] = $each_listName;
		}
		return $all_list;
	} 


	function get_section_emails ($iterable_csv_filename, $divisor){
		if (($handle = fopen($iterable_csv_filename, "r")) !== FALSE) {
			global $section_emails;
			global $all_emails;
			global $date_append;
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
		// print_pre($sections);
		// $tot_sec = (count($sections))-1;
		return $sections;
	}

	function create_csv_from_url ($url, $filename) {
		$date_append = date("Ymd");
		$outputcsv=file_get_contents($url);
		$csv_created = file_put_contents($filename, $outputcsv);
		if($csv_created !== false){
			echo $filename . " has been created <br>";
		}
	}

	function joinFiles(array $files, $result) {
		if(!is_array($files)) {
		    throw new Exception('`$files` must be an array');
		}
		
		$wH = fopen($result, "w+");

		foreach($files as $file) {
		    $fh = fopen($file, "r");
		    while(!feof($fh)) {
		        fwrite($wH, fgets($fh));
		    }
		    fclose($fh);
		    unset($fh);
		    fwrite($wH, ""); //usually last line doesn't have a newline
		}
		fclose($wH);
		unset($wH);
	}

	function csv_to_array ($csv_filename) {
		$array_of_data= array();
		if (($handle = fopen($csv_filename, "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 1000 , ",")) !== FALSE) {
			    $num = count($data);
			    for ($c=0; $c < $num; $c++) {	    	
			    	$array_of_data[] = array('email' => $data[$c]);
		    	}
			}
		  fclose($handle);
		}
		return $array_of_data;
	}

	function unsubscribe_from_list ($list_ID, $subscriber_array, $url){
		$params = array();
		$params['listId'] = $list_ID; 
		$params['subscribers'] = $subscriber_array;
		$params['campaignId'] = 0;
		$params['channelUnsubscribe'] = true;
		$payload = json_encode($params);
		print_pre(json_encode($params));
		
		$ch = curl_init($url);
		curl_setopt( $ch, CURLOPT_URL, $url);
		curl_setopt( $ch, CURLOPT_POST, true);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		$result = curl_exec($ch);
		$result_details = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		print_pre($result);
		print_pre($result_details);
	}

	
	function filter_duplicate_emails ($csv_filename){
		if (($handle = fopen($csv_filename, "r")) !== FALSE) {
			global $unfiltered_list;
			global $cleansed_emails;
			$row = 0;
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			    $num = count($data);
			    $row++;
			    for ($c=0; $c < $num; $c++) {	    	
			    	$unfiltered_list[] = $data[$c];
		    	}
			}
		  fclose($handle);
		}
		$cleansed_emails = array_unique($unfiltered_list);
	}


	function create_csv_from_array ($csv_name, $array) {
		$file = fopen($csv_name,"w");

		foreach ($array as $email)
		{
			fputcsv($file,explode(',',$email));
		}
		fclose($file); 
	}
	


	//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	// 	   Function:	ftpFile
	//	Description:	Uploads a given file to the specified
	//					FTP server. 
	//		Created:	July 22, 2010
	//		 Author:	Brant M. Songsong
	//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	function ftpFile($ftp_server,$local_file_path,$remote_path = null)
	{
		global $DEBUG, $login;

		// Get username and password for this ftp server
		$address = $login[$ftp_server]["address"];
		$user = $login[$ftp_server]["user"];
		$password = $login[$ftp_server]["pwd"];
		$dir = isset($login[$ftp_server]["dir"]) ? $login[$ftp_server]["dir"] : null;

		// Establish Connection to FTP Server
		$conn = ftp_connect($address) or die("Couldn't connect to $address");

		// Authenticate
		if (ftp_login($conn,$user,$password))
		{
            if ($remote_path) {
                $dir = $remote_path;
            }

            // If directory doesn't exist, create it
            if (!@ftp_chdir($conn, $dir)) {
                @ftp_mkdir($conn, $dir);
                @ftp_chdir($conn, $dir);
            }

			// turn passive mode on
			ftp_pasv($conn, true);

            $local_file_path_array = array();
			
            // Convert to array if local_file_path is a string
            if (!is_array($local_file_path)) { 
                $local_file_path_array[] = $local_file_path;
            }
            else {
                $local_file_path_array = $local_file_path;
            }

            foreach ($local_file_path_array as $local_file_path) {

                // Open the file for reading (so pointer returns to start of file)
                $feed_file_handle = fopen($local_file_path,"r");

                // Define the Local File
                $local_file = $local_file_path;

                // Define the Remote File
                $file_path = explode("/",$local_file_path);
                $remote_file = $file_path[count($file_path)-1];

                if ($DEBUG) { 
                    echo "We're in!!!!<br /><br />"; 
                }

                // Copy the file to the FTP server
                $file_move_success = ftp_put($conn,$remote_file,$local_file,FTP_BINARY);

                if ($DEBUG)
                {
                    if ($file_move_success) {
                        echo "Successfully uploaded $local_file_path" . "<br /><br />";
                    } else {
                        echo "There was a problem uploading $local_file_path" . "<br /><br />";
                    } // end if
                } // end if

            } // end foreach


		} // end if
		
		// Close FTP connection
		echo "successfully moved $local_file_path to $ftp_server<br>";
		ftp_close($conn); 
		
	} // end function
	

	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo 'Execution time : '.$time.' minutes';

?>