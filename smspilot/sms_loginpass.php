<?php

$COMPANY="ISP";
$TEXT_base1="Приветствуем Вас!\nВаш логин: ";
$TEXT_base2="\nПароль: ";
$TEXT_base3="\nАбонентская плата ";
$TEXT_base4=". Доступ в Личный Кабинет stat.local.isp";
$UE="руб/мес";

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

	#$SQL_Querry="SELECT a.user, a.password, a.sms_tel,(b.fixed_cost-(b.fixed_cost/100*a.fixed_cost)) as price FROM " . BILL_AUTH_TABLE . " as a LEFT JOIN " . BILL_TARIF_TABLE . " as b ON b.gid = a.gid WHERE a.uid=<ID_USER>";
	$SQL_Querry="SELECT a.user, a.password, a.sms_tel,(b.fixed_cost-(b.fixed_cost/100*a.fixed_cost)) as price FROM " . BILL_AUTH_TABLE . " as a LEFT JOIN " . BILL_TARIF_TABLE . " as b ON b.gid = a.gid";

	$result = mysql_query ( $SQL_Querry, $LINK ) or do_log_sql($stdlog,"#deposit error ".mysql_error ( $LINK ) ,$LINK);

	for ($i = 0; $i <= mysql_num_rows ($result); $i++) {
		$res = mysql_fetch_array ( $result );
		$users_list[$i]=$res;
	}
	mysql_free_result ( $result );

	return $users_list;
}

global $LINK;

$LINK = mysql_pconnect ( $CONF_MYSQL_HOST ,  $CONF_MYSQL_USERNAME, $CONF_MYSQL_PASSWORD );
if (!$LINK) {
	do_log($stdlog,"Cant connect to DB ".$CONF_MYSQL_HOST);
	exit();
}

mysql_select_db ( $CONF_MYSQL_DBNAME , $LINK ) or die('Could not select database.');

$users_dolgnki=get_users_dolgniki($LINK,$stdlog);


$ts=time();

foreach ($users_dolgnki as $key=>$value)
{
	$login=$value['user'];
	$pass=$value['password'];
	$price=round($value['price'],2);
	$TEXT=$TEXT_base1.$login.$TEXT_base2.$pass.$TEXT_base3.$price." ".$UE.$TEXT_base4;
	#$TEXT=$TEXT_base1.$login.$TEXT_base2.$pass.$TEXT_base4;

#	$TEXT=iconv("CP1251","KOI8-U",$TEXT);

	$pattern = "|[^\d\(\)-+]|";
	$replacement = "";

	$SMS_TEL=preg_replace($pattern, $replacement, $value['sms_tel']);


	if (strlen($SMS_TEL)==10){
		#$TEXT=iconv("KOI8-U","UTF-8",$TEXT);
		sms("7".$SMS_TEL,$TEXT,$COMPANY);
		echo ("SMS Send to 7".$SMS_TEL." Text: ".$TEXT."(".$COMPANY.")\r\n");
#		var_dump("7".$SMS_TEL);
	}
	if ((strlen($SMS_TEL)<9)or(strlen($SMS_TEL)>12)){
	}
	if (strlen($SMS_TEL)==11){
#
		#$TEXT=iconv("KOI8-U","UTF-8",$TEXT);
		sms($SMS_TEL,$TEXT,$COMPANY);
		echo ("SMS Send to ".$SMS_TEL." Text: ".$TEXT."(".$COMPANY.")\r\n");
	}
}



