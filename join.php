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
	// $date_append = "20180626";

	function print_pre($object) {
		?><pre><?php print_r($object); ?></pre><?php
	}

	$time_start = microtime(true);
	$login = array(
	    
	    )
	);
	$url = "-"; //Pressreader Only
	$pressreader_csv_filename = './unmerged_csv/Iterable_PressReader_List_' . $date_append . '.csv';
	$list_1 = './unmerged_csv/0_Processed_list_' . $date_append . '.csv';
	$list_2 = './unmerged_csv/1_Processed_list_' . $date_append . '.csv';
	$list_3 = './unmerged_csv/2_Processed_list_' . $date_append . '.csv';
	$list_4 = './unmerged_csv/3_Processed_list_' . $date_append . '.csv';
	$list_5 = './unmerged_csv/4_Processed_list_' . $date_append . '.csv';
	$list_6 = './unmerged_csv/5_Processed_list_' . $date_append . '.csv';
	$list_7 = './unmerged_csv/6_Processed_list_' . $date_append . '.csv';
/*	$list_8 = './unmerged_csv/7_Processed_list_' . $date_append . '.csv';
	$list_9 = './unmerged_csv/8_Processed_list_' . $date_append . '.csv';
	$list_10 = './unmerged_csv/9_Processed_list_' . $date_append . '.csv';
	$list_11 = './unmerged_csv/10_Processed_list_' . $date_append . '.csv';
	$list_12 = './unmerged_csv/11_Processed_list_' . $date_append . '.csv';*/
	$pressreader_list = $pressreader_csv_filename;
	$merged_list = './merged_csv/unfiltered_HSA_newspaperalerts_ACTIVE_' . $date_append . '.csv';
	$cleansed_emails = array();
	$unfiltered_list = array();
	$final_list = './merged_csv/HSA_newspaperalerts_ACTIVE_' . $date_append . '.csv';
	$all_list = array($list_1, $list_2, $list_3, $list_4, $list_5, $list_6, $list_7, $pressreader_list);

//	create pressReader csv
	create_csv_from_url($url, $pressreader_csv_filename);
//	Joins all the files
	joinFiles($all_list, $merged_list);
//filter duplicate emails
	filter_duplicate_emails($merged_list);
//	Create final list 
	create_csv_from_array($final_list, $cleansed_emails);
//	Import the file to Tecnavia
	ftpFile("ftp.ta.newsmemory.com", $final_list);


	function create_csv_from_url ($url, $filename) {
		$date_append = date("Ymd");
		$outputcsv=file_get_contents($url);
		file_put_contents($filename, $outputcsv);
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
	echo "number of UNFILTERED EMAILS:<br>";
	print_pre(count($unfiltered_list));
	echo "number of CLEANSED EMAILS:<br>";
	print_pre(count($cleansed_emails));


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
		ftp_close($conn); 
		
	} // end function
	

	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo 'Execution time : '.$time.' minutes';

?>