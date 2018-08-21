<?php
	ini_set( 'display_errors', 1 );
	ini_set( 'display_startup_errors', 1 );
	error_reporting( E_ALL );
	date_default_timezone_set("Pacific/Honolulu");
	$date_append = date("Ymd");
	echo $date_append;
	$time_start = microtime(true);
	require_once("./vendor/autoload.php");
	use GuzzleHttp\Client;
	use Guzzle\Common\Exception\MultiTransferException;
	use GuzzleHttp\Promise;
	use GuzzleHttp\Pool;
	use GuzzleHttp\Psr7\Request;

	$client = new Client();
	$json_filename = '-_' . $date_append . '.json';

//	Read Json

	$section_total=read_json($json_filename);
	echo $section_total;

	$section_total_number = $section_total['total_index'];
	print_pre($section_total_number);
	
//----------------------GUZZLE PROCESS START-------------------
	$requests = function ($total) {
    	$uri = '-';
    	for ($i = 0; $i < $total; $i++) {
        	yield new Request('GET', $uri . '?section_number='. $i );
        	print_pre($i);
	    }
	};

	$pool = new Pool($client, $requests($section_total_number), [
	    'concurrency' => $section_total_number,
	    'fulfilled' => function ($response, $index) {
	        // this is delivered each successful response

	    },
	    'rejected' => function ($reason, $index) {
	        // this is delivered each failed request
	    },
	]);

	$promise = $pool->promise();
	$promise->wait();
//----------------------GUZZLE PROCESS END-------------------



//==================== F U N C T I O N S ==================== //


	function read_json($json_filename){
		$string = file_get_contents($json_filename);
		$section_total = json_decode($string, true);
		print_pre($section_total);
		return $section_total;
	}

	function print_pre($object) {
		?><pre><?php print_r($object); ?></pre><?php
	}


	$time_end = microtime(true);
	$time = ($time_end - $time_start)/60;
	echo '<br>Execution time : '.$time.' minutes <br>';

?>