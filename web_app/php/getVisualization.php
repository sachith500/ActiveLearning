<?php

	require 'hostspecs.php';	// $host & $port defined here
	require '../db/logging.php';
	session_start();

	
	$visCmd =  array( "command" => "visualize",
					  "strata" => intval($_POST['strata']),
					  "groups" => intval($_POST['groups']),
	  		 	      "uid" => $_SESSION['uid'] );

	$visCmd = json_encode($visCmd);
	
	$addr = gethostbyname($host);
	set_time_limit(0);	
	$socket = socket_create(AF_INET, SOCK_STREAM, 0);

	if( $socket === false ) {
		log_error("[GetVis] socket_create failed: ".socket_strerror(socket_last_error()));
		exit();		
	}
	
	$result = socket_connect($socket, $addr, $port);
	if( !$result ) {
		log_error("[GetVis] socket_connect failed: ".socket_strerror(socket_last_error()));
		exit();
	}
	
	socket_write($socket, $visCmd, strlen($visCmd));
	
	// FIXME! - Should loop until no data is left on the socket
	$response = socket_read($socket, 8192);
	$additional = socket_read($socket, 8192);
	socket_close($socket);
	$response = $response.$additional;
	
	// Now get the max X & Y from the database for the slide of the samples
	//
	$dbConn = mysqli_connect("localhost", "guest", "", "nuclei");
	if( !$dbConn ) {
		log_error("[GetVis] Unable to connect to the database server ".mysqli_connect_error() );
		exit();
	}

	$response = json_decode($response, true);
	
	for($i = 0, $len = count($response); $i < $len; ++$i) {
	
		$response[$i]['score'] = round($response[$i]['score'], 4);
		$response[$i]['centX'] = round($response[$i]['centX'], 1);
		$response[$i]['centY'] = round($response[$i]['centY'], 1);
		
		// get slide dimensions for the nuclei
		//
		$sql = 'SELECT x_size, y_size FROM slides WHERE name="'.$response[$i]['slide'].'"';
		if( $result = mysqli_query($dbConn, $sql) ) {
			$array = mysqli_fetch_row($result);
			
			$response[$i]['maxX'] = intval($array[0]);
			$response[$i]['maxY'] = intval($array[1]);
			mysqli_free_result($result);
		} 
		
		// Get database id for the nuclei
		//
		$sql = 'SELECT id FROM boundaries WHERE slide="'.$response[$i]['slide'].'"';
		$sql = $sql.' AND centroid_x='.$response[$i]['centX'].' and centroid_y='.$response[$i]['centY'];

		if( $result = mysqli_query($dbConn, $sql) ) {
			$array = mysqli_fetch_row($result);
			
			$response[$i]['id'] = intval($array[0]);
			mysqli_free_result($result);
		} 		
	}	
	mysqli_close($dbConn);
	$response = json_encode($response);
	
	echo $response;

?>