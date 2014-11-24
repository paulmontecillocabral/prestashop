<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/payeer.php');

$payeer = new Payeer();

if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
{
	$m_key = Configuration::get('secret_key');
	$arHash = array($_POST['m_operation_id'],
		$_POST['m_operation_ps'],
		$_POST['m_operation_date'],
		$_POST['m_operation_pay_date'],
		$_POST['m_shop'],
		$_POST['m_orderid'],
		$_POST['m_amount'],
		$_POST['m_curr'],
		$_POST['m_desc'],
		$_POST['m_status'],
		$m_key
	);
	$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
	
	// проверка принадлежности ip списку доверенных ip
	$list_ip_str = str_replace(' ', '', Configuration::get('ip_filter'));
	
	if (!empty($list_ip_str)) 
	{
		$list_ip = explode(',', $list_ip_str);
		$this_ip = $_SERVER['REMOTE_ADDR'];
		$this_ip_field = explode('.', $this_ip);
		$list_ip_field = array();
		$i = 0;
		$valid_ip = FALSE;
		foreach ($list_ip as $ip)
		{
			$ip_field[$i] = explode('.', $ip);
			if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
				(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
				(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
				(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
				{
					$valid_ip = TRUE;
					break;
				}
			$i++;
		}
	}
	else
	{
		$valid_ip = TRUE;
	}		
	
	$log_text = 
		"--------------------------------------------------------\n".
		"operation id		" . $_POST["m_operation_id"] . "\n".
		"operation ps		" . $_POST["m_operation_ps"] . "\n".
		"operation date		" . $_POST["m_operation_date"] . "\n".
		"operation pay date	" . $_POST["m_operation_pay_date"] . "\n".
		"shop				" . $_POST["m_shop"] . "\n".
		"order id			" . $_POST["m_orderid"] . "\n".
		"amount				" . $_POST["m_amount"] . "\n".
		"currency			" . $_POST["m_curr"] . "\n".
		"description		" . base64_decode($_POST["m_desc"]) . "\n".
		"status				" . $_POST["m_status"] . "\n".
		"sign				" . $_POST["m_sign"] . "\n\n";
	
	if (Configuration::get('payeer_log') != '')
	{		
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . Configuration::get('payeer_log'), $log_text, FILE_APPEND);
	}
	
	if ($_POST['m_sign'] == $sign_hash && $_POST['m_status'] == 'success' && $valid_ip)
	{
		$payeer->validateOrder((int)($_POST['m_orderid']), 2, (float)($_POST['m_amount']), $payeer->displayName, NULL, array(), NULL, false, false);
		
		exit ($_POST['m_orderid'] . '|success');
	}
	else
	{
		$payeer->validateOrder((int)($_POST['m_orderid']), 8, (float)($_POST['m_amount']), $payeer->displayName, NULL, array(), NULL, false, false);
		
		$to = Configuration::get('email_error');
		$subject = "Ошибка оплаты";
		$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n";
		
		if ($_POST["m_sign"] != $sign_hash)
		{
			$message .= " - Не совпадают цифровые подписи\n";
		}
		
		if ($_POST['m_status'] != "success")
		{
			$message .= " - Cтатус платежа не является success\n";
		}
		
		if (!$valid_ip)
		{
			$message .= " - ip-адрес сервера не является доверенным\n";
			$message .= "   доверенные ip: " . Configuration::get('ip_filter') . "\n";
			$message .= "   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
		}
		
		$message .= "\n".$log_text;
		$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
		mail($to, $subject, $message, $headers);
				
		exit ($_POST['m_orderid'] . '|error');
	}
}