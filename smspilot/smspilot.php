<?php
// v1.8.1
if (!defined('SMSPILOT_API')) define('SMSPILOT_API', 'http://smspilot.ru/api.php');
if (!defined('SMSPILOT_APIKEY')) define('SMSPILOT_APIKEY', '2G8X066241XFBM4XC7111HDWF1PI9I0Y3A3VX2R61YA4BGY1W9JRW3A37YQ6CU1K');
if (!defined('SMSPILOT_CHARSET')) define('SMSPILOT_CHARSET', 'UTF-8');
if (!defined('SMSPILOT_FROM')) define('SMSPILOT_FROM', 'smspilot.ru'); // new in 1.8.1

/* SMS Pilot API/PHP v1.8
 * SEE: http://www.smspilot.ru/apikey.php

 Example (send):
    include('smspilot.php');
	sms('79087964781','Hello');

 Example 2 (send & replace sender):
	include('smspilot.php');
 	if (sms('79087964781','Привет', 'yandex.ru'))
		echo 'Сообщение успешно отправлено';
	else
		echo 'Ошибка! '.sms_error();
		
 Example 3 (send list):
    include('smspilot.php');
	$phones = array(
		'79087964781',
		'79167965432',
		'79139876543'
	);
	sms( $phones,'Hello','TEST_LIST');

 Example 4 (send & get cost, balance, status)
	include('smspilot.php');
	if ( ($s = sms('79087964781','Сохраняем ID этой sms','TEST_ALL')) !== false ) {
		echo 'Цена='.sms_cost().'<br />'
			.'Баланс='.sms_balance().'<br />'
			.'Статус=<pre>'.print_r( $s[0], true ).'</pre>'; // Array ( [id] => 94 [phone] => 79087964781 [zone] => 2 [status] => 0 )
	} else
		echo sms_error();

 Example 5 (check status)
 	include('smspilot.php');
	if ( ($s = sms_check( 94 )) !== false )
		print_r( $s[0] ); // Array ( [id] => 94 [phone] => 79087964781 [zone] => 2 [status] => 2 )
	else
		echo sms_error();

 Example 6 (balance);
   include('smspilot.php');
   if ( ($b = sms_balance()) !== false )
	  echo 'Баланс='.$b.' sms-кредитов';
	else
		echo sms_error();
 	
*/

function sms( $to, $text, $from = '' ) {
	//
	if (SMSPILOT_CHARSET != 'UTF-8')
		$text = mb_convert_encoding($text, 'utf-8', SMSPILOT_CHARSET);
	
	$apiurl = SMSPILOT_API
		.'?send='.urlencode($text)
		.'&to='.(is_array($to) ? implode(',', $to) : $to)
		.'&from='.(($from) ? urlencode($from) : SMSPILOT_FROM)
		.'&apikey='.SMSPILOT_APIKEY;

	$result = file_get_contents( $apiurl );

	if ($result) {
		if (substr($result,0,6) == 'ERROR=') {
			sms_error( substr($result,6) );
			return false;
		} elseif (substr($result,0,8) == 'SUCCESS=') {
			
			$success = substr($result,8,($p = strpos($result,"\n"))-8);
						
			sms_success( $success );
			
			if (preg_match('~(\d+)/(\d+)~', $success, $matches )) {
				sms_cost( $matches[1] ); // new in 1.8
				sms_balance( $matches[2] ); // new in 1.8
			}			
			//status
			$status_csv = substr( $result, $p+1 );
			$status_csv = explode( "\n", $status_csv );
			$status = array();
			foreach( $status_csv as $line ) {
				$s = explode(',', $line);
				$status[] = array(
					'id' => $s[0],
					'phone' => $s[1],
					'zone' => $s[2],
					'status' => $s[3]
				);
			}
			sms_status( $status );

			return $status;
		} else {
			sms_error( 'UNKNOWN RESPONSE' );
			return false;
		}
	} else {
		sms_error( 'CONNECTION ERROR' );
		return false;		
	}	
}
function sms_check( $id ) {
	
	$apiurl = SMSPILOT_API
		.'?check='.(is_array($id) ? implode(',', $id) : $id)
		.'&apikey='.SMSPILOT_APIKEY;

	$result = file_get_contents( $apiurl );

	if ($result) {
		if (substr($result,0,6) == 'ERROR=') {
			sms_error( substr($result,6) );
			return false;
		} else {
				
			$status_csv = $result;
			//status
			$status_csv = explode( "\n", $status_csv );
			$status = array();
			foreach( $status_csv as $line ) {
				$s = explode(',', $line);
				$status[] = array(
					'id' => $s[0],
					'phone' => $s[1],
					'zone' => $s[2],
					'status' => $s[3]
				);
			}
			return sms_status( $status );

		}
	} else {
		sms_error( 'CONNECTION ERROR' );
		return false;		
	}
}
// new in 1.8
function sms_balance( $set = NULL ) {
	static $b;
	if ( $set !== NULL )
		return $b = $set;
	elseif (isset($b))
		return $b;
	
	$apiurl = SMSPILOT_API
		.'?balance=sms'
		.'&apikey='.SMSPILOT_APIKEY;

	$result = file_get_contents( $apiurl );

	if ($result) {
		if (substr($result,0,6) == 'ERROR=') {
			sms_error( substr($result,6) );
			return false;
		} else
			return $b = $result;
	} else {
		sms_error( 'CONNECTION ERROR' );
		return false;		
	}
}
function sms_info( $apikey = NULL ) {
	
	if ($apikey === NULL)
		$apikey = SMSPILOT_APIKEY;
		
	$apiurl = SMSPILOT_API
		.'?apikey='.$apikey;

	$result = file_get_contents( $apiurl );
	if ($result) {
		if (substr($result,0,6) == 'ERROR=') {
			sms_error( substr($result, 6) );
		} elseif (substr($result,0,8) == 'SUCCESS=') {
			
			$s = substr($result,8, $p = strpos($result,"\n"));
			
			sms_success( $s );
			
			$lines = explode("\n",substr($result,$p));
			
			$info = array();
			foreach( $lines as $line )
				if ($p = strpos($line,'='))
					$info[ substr($line,0,$p) ] = substr($line,$p+1);

			if (SMSPILOT_CHARSET != 'UTF-8')
				foreach( $info as $k => $v)
					$info[ $k ] = mb_convert_encoding($v,SMSPILOT_CHARSET,'UTF-8');

			return $info;
		}
	} else {
		sms_error( 'CONNECTION ERROR' );
		return false;				
	}
}
//
function sms_error( $set = NULL ) {
	static $e;
	if ($set !== NULL)
		return $e = $set;
	else
		return (isset($e)) ? $e : false;
}
function sms_success( $set = NULL ) {
	static $s;
	
	if ($set !== NULL)
		return $s = $set;
	else
		return (isset($s)) ? $s : false;	
}
// new in 1.7
function sms_status( $set = NULL ) {
	static $s;
	if ($set !== NULL )
		return $s = $set;
	else
		return (isset($s)) ? $s : false;
}
// new in 1.8
function sms_cost( $set = NULL ) {
	static $c;
	if ($set !== NULL)
		return $c = $set;
	else
		return (isset($c)) ?  $c : 1;
}


?>