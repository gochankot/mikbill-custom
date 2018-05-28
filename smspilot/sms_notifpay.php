<?php

$COMPANY="ISP";
$TEXT_base1="Ваш баланс ";
$TEXT_base2=", 1 числа будет списано ";
$TEXT_base3=". Пополните счет!";
$UE="руб";

include('smspilot.php');
define ("BILL_AUTH_TABLE","users");
define ("BILL_TARIF_TABLE", "packets" );
define ("BILL_SYSPOTS_TABLE", "sysopts" );

$config_file='../../app/etc/config.xml';

if (file_exists($config_file)) {
	$xml = simplexml_load_file($config_file);

	$CONF_IP   = (string) $xml->parameters->kernel->ip;
	$CONF_PORT = (string) $xml->parameters->kernel->port;
	$CONF_PID  = (string) $xml->parameters->kernel->pid;
	$CONF_LOG  = (string) $xml->parameters->kernel->log;
	$CONF_MYSQL_HOST     = (string) $xml->parameters->mysql->host;
	$CONF_MYSQL_USERNAME = (string) $xml->parameters->mysql->username;
	$CONF_MYSQL_PASSWORD = (string) $xml->parameters->mysql->password;
	$CONF_MYSQL_DBNAME   = (string) $xml->parameters->mysql->dbname;

} else {
	die("config not found");
}

function open_logs($CONF_LOG)
{
	return 	fopen($CONF_LOG, "a");
}

$stdlog = open_logs($CONF_LOG);


function do_log($stdlog,$text_log)
{
	fputs($stdlog, get_date()." ".$text_log."\n");
}

function do_log_sql($stdlog,$text_log,&$LINK)
{
	if (!mysql_ping($GLOBALS["LINK"]))
	{
		$do_mysql_reconect=1;
		fputs($stdlog, get_date()." MySQL Connect failed"."\n");
	}else{
		$do_mysql_reconect=0;
		fputs($stdlog, get_date()." ".$text_log."\n");
	}

	while ($do_mysql_reconect==1)
	{
		$config_file='../../app/etc/config.xml';

		if (file_exists($config_file)) {
			$xml = simplexml_load_file($config_file);
			$CONF_MYSQL_HOST     = (string) $xml->parameters->mysql->host;
			$CONF_MYSQL_USERNAME = (string) $xml->parameters->mysql->username;
			$CONF_MYSQL_PASSWORD = (string) $xml->parameters->mysql->password;
			$CONF_MYSQL_DBNAME   = (string) $xml->parameters->mysql->dbname;
		}
		$GLOBALS["LINK"] = mysql_pconnect ( $CONF_MYSQL_HOST ,  $CONF_MYSQL_USERNAME, $CONF_MYSQL_PASSWORD );
		mysql_select_db ( $CONF_MYSQL_DBNAME , $GLOBALS["LINK"] );

		if (mysql_ping($GLOBALS["LINK"])){
			$do_mysql_reconect=0;
			fputs($stdlog, get_date()." MySQL Connect restored"."\n");
		}


	}
	return "1";
}


function get_date()
{
	return date ( 'd.m.Y H:i:s' );
}

function get_users_dolgniki($LINK,$stdlog )
{
	$SQL_Querry_1="SELECT a.uid, a.user, a.deposit, a.sms_tel, (b.fixed_cost - ( b.fixed_cost /100 * a.fixed_cost ) ) AS price FROM users AS a LEFT JOIN packets AS b ON b.gid = a.gid WHERE a.real_ip != 1 AND a.deposit + a.credit - (b.fixed_cost - ( b.fixed_cost /100 * a.fixed_cost ) ) <0";
	$SQL_Querry_2="SELECT a.uid, a.user, a.deposit, a.sms_tel, (b.fixed_cost - ( b.fixed_cost /100 * a.fixed_cost ) + b.real_price ) AS price FROM users AS a LEFT JOIN packets AS b ON b.gid = a.gid WHERE a.real_ip = 1 AND a.deposit + a.credit - ( b.fixed_cost - ( b.fixed_cost /100 * a.fixed_cost ) + b.real_price ) <0";

	$result_1 = mysql_query ( $SQL_Querry_1, $LINK ) or do_log_sql($stdlog,"#deposit error ".mysql_error ( $LINK ) ,$LINK);
	$result_2 = mysql_query ( $SQL_Querry_2, $LINK ) or do_log_sql($stdlog,"#deposit error ".mysql_error ( $LINK ) ,$LINK);

	for ($i = 0; $i <= mysql_num_rows ($result_1); $i++)
	{
		$res = mysql_fetch_array ( $result_1 );
		$users_list_1[$i]=$res;
	}
	mysql_free_result ( $result_1 );

	for ($i = 0; $i <= mysql_num_rows ($result_2); $i++)
	{
		$res = mysql_fetch_array ( $result_2 );
		$users_list_2[$i]=$res;
	}
	mysql_free_result ( $result_2 );

	$users_list  = array_merge($users_list_1, $users_list_2);

	foreach($users_list as $key => $val)
	{
		$am_tmp = mysql_query ( 'SELECT b.amount FROM services_users_pairs as a LEFT JOIN services as b ON b.serviceid = a.serviceid  WHERE a.uid = '.$users_list[$key]['uid'].';', $LINK);
		if ($am_tmp)
		{
			for ($i = 0; $i <= mysql_num_rows ($am_tmp); $i++)
			{
				$am_res[$i]  = mysql_fetch_array ( $am_tmp );
			}
			mysql_free_result($am_tmp);

			foreach($am_res as $key_1 => $val_1)
			{
				$users_list[$key]['price'] = $users_list[$key]['price'] + $val_1['amount'];
			}
		}
		unset($am_res);
		unset($am_tmp);
	}
	return $users_list;

}

global $LINK;

$LINK = mysql_pconnect ( $CONF_MYSQL_HOST ,  $CONF_MYSQL_USERNAME, $CONF_MYSQL_PASSWORD );
if (!$LINK) {
	do_log($stdlog,"Cant connect to DB ".$CONF_MYSQL_HOST);
	echo("Cant connect to DB ".$CONF_MYSQL_HOST);
	exit();
}

mysql_select_db ( $CONF_MYSQL_DBNAME , $LINK ) or die('Could not select database.');

$users_dolgnki=get_users_dolgniki($LINK,$stdlog);


$ts=time();

foreach ($users_dolgnki as $key=>$value)
{
	$deposit=round($value['deposit'],2);
	$price=round($value['price'],2);
	$TEXT=$TEXT_base1.$deposit." ".$UE.$TEXT_base2.$price." ".$UE.$TEXT_base3;
#	$TEXT=iconv("CP1251","KOI8-U",$TEXT);

	$pattern = "|[^\d\(\)-+]|";
	$replacement = "";

	$SMS_TEL=preg_replace($pattern, $replacement, $value['sms_tel']);


	if (strlen($SMS_TEL)==10){
		#$TEXT=iconv("KOI8-U","UTF-8",$TEXT);
		sms("7".$SMS_TEL,$TEXT,$COMPANY);
		echo ("SMS Send to 7".$SMS_TEL." Text: ".$TEXT."(".$COMPANY.")\r\n");
		#var_dump("7".$SMS_TEL);
	}
	if ((strlen($SMS_TEL)<9)or(strlen($SMS_TEL)>12)){
	}
	if (strlen($SMS_TEL)==11){
		#$TEXT=iconv("KOI8-U","UTF-8",$TEXT);
		sms($SMS_TEL,$TEXT,$COMPANY);
		echo ("SMS Send to ".$SMS_TEL." Text: ".$TEXT."(".$COMPANY.")\r\n");
	}
}



