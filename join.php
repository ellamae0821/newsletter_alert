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

	function print_pre($object) {
		?><pre><?php print_r($object); ?></pre><?php
	}

	$time_start = microtime(true);


	$url = "---"; //Pressreader Only

	$iterable_csv_filename = './unmerged_csv/Iterable_PressReader_List_' . $date_append . '.csv';
	create_csv_from_url($url, $iterable_csv_filename);

	function create_csv_from_url ($url, $filename) {
		$date_append = date("Ymd");
		$outputcsv=file_get_contents($url);
		file_put_contents($filename, $outputcsv);
	}
//\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\_/\
//	1st STEP : PATCH ALL PROCESSED FILES
//	RESULT : FINAL CSV FILE for export to TENCNAVIA
//---------------------------------------------------------------------
	$list_1 = './unmerged_csv/0_Processed_list_' . $date_append . '.csv';
	$list_2 = './unmerged_csv/1_Processed_list_' . $date_append . '.csv';
	$list_3 = './unmerged_csv/2_Processed_list_' . $date_append . '.csv';
	$list_4 = $iterable_csv_filename;
	$final_list = './merged_csv/HSA_newspaperalerts_ACTIVE_' . $date_append . '.csv';


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

	$all_list = array($list_1, $list_2, $list_3, $list_4);
	joinFiles($all_list, $final_list);
	print_pre($final_list);


	$cleansed_emails = array();
	function filter_unique_emails ($csv_filename){
		if (($handle = fopen($csv_filename, "r")) !== FALSE) {
			global $cleansed_emails;
			echo "fopen";
			$row = 0;
			$unfiltered_list = array();
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			    $num = count($data);
			    $row++;
			    for ($c=0; $c < $num; $c++) {	    	
			    	$unfiltered_list[] = $data[$c];
			    	$cleansed_emails = array_unique($unfiltered_list);
		    	}
			}
		  fclose($handle);
		}
	}
	filter_unique_emails($final_list);
	print_pre($cleansed_emails);

	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo 'Execution time : '.$time.' minutes';

?>