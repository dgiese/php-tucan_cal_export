<?php
/*
Tucan Semester Calender Export V 0.5
(c) 2012 Dennis Giese (dgiese@prp.physik.tu-darmstadt.de)

Tucan Semester Calender Export is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License 2
as published by the Free Software Foundation.
*/

// Credentials für Login in Tucan abfragen
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="TUCaN Calendar - TUID required"');
    header('HTTP/1.0 401 Unauthorized');
    echo "Abbruch";
    exit;
} else {

	date_default_timezone_set("Europe/Berlin");

	// Felder befüllen für Login
	$fields = array(
			'usrname'=>$_SERVER['PHP_AUTH_USER'],
			'pass'=>$_SERVER['PHP_AUTH_PW'],
			'APPNAME'=>"CampusNet",
			'PRGNAME'=>"LOGINCHECK",
			'ARGUMENTS'=>"clino%2Cusrname%2Cpass%2Cmenuno%2Cpersno%2Cbrowser%2Cplatform",
			'clino'=>"000000000000001",
			'menuno'=>"000344",
			'persno'=>"00000000",
			'browser'=>"",
			'platform'=>""
	);



	// einloggen (ohne cookie-absicherung)
	$result= open_https_url("https://www.tucan.tu-darmstadt.de/scripts/mgrqcgi","",1,$fields);
	 //echo $result;
	if (preg_match("#<h1>Sie konnten nicht angemeldet werden</h1>#", $result))
	{
		// Username oder Passwort war falsch
		header('WWW-Authenticate: Basic realm="TUCaN Calendar - TUID-Login fehlgeschlagen (Passwort falsch?)"');
		header('HTTP/1.0 401 Unauthorized');
		echo "Abbruch";
		exit;
	}

	// Header ausgeben
echo utf8_encode("BEGIN:VCALENDAR
VERSION:2.0
PRODID:1337 Tucan Cal Export
METHOD:PUBLISH

BEGIN:VTIMEZONE
TZID:CampusNetZeit
BEGIN:STANDARD
DTSTART:16011028T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010325T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
END:DAYLIGHT
END:VTIMEZONE");


	preg_match('#URL=(.*)\r\n#', $result, $r);
	
	$url= "https://www.tucan.tu-darmstadt.de".$r[1];
	$url = str_ireplace ("MLSSTART", "SCHEDULER_EXPORT", $url);

	//MAGIC
	// Idee: ab dem aktuellen Monat die nächsten 6 Monate exportieren
	for ($i=0; $i <=6;$i++)
	{
		$jahr=date('Y');
		$monat=date('n')+$i; // Ab dem aktuellen Monat exportieren
		if ($monat == 13)
		{
			$jahr++;
			$monat=1;
		}
		$tempmonat=$monat;
		if ($tempmonat < 10)
		{
			$tempmonat= "0".$tempmonat;
		}
		$datumstring="Y".$jahr."M".$tempmonat;
		$result= open_https_url($url,"","",""); //auf export-seite gehen
		preg_match('#<input name=\"sessionno\" type=\"hidden\" value=\"(.*)\"#', $result, $r);
		if ($r[1] == "")
		{
			break;
		}
		$referer=$url;
		$fields = array(
			'month'=>$datumstring,
			'week'=>"0",
			'APPNAME'=>"CampusNet",
			'PRGNAME'=>"SCHEDULER_EXPORT_START",
			'ARGUMENTS'=>"sessionno%2Cmenuid%2Cdate",
			'sessionno'=>$r[1],
			'menuid'=>"000019",
			'date'=>$datumstring,
		);
		// ics erzeugen
		$result= open_https_url("https://www.tucan.tu-darmstadt.de/scripts/mgrqcgi",$referer,1,$fields); 
		preg_match('#href=\"/scripts/filetransfer?(.*)\"#', $result, $r);
		
		// Prüfen ob ein komplett leerer Monat zurückkommt (außer März und September, die leer sein dürfen)
		if ($r[1] == "" && date('n') != 9 && date('n') != 3) // o_O...da stimmt was nicht...keine Termine vorhanden?
		{
			break;
		}
		
		$urlex= "https://www.tucan.tu-darmstadt.de/scripts/filetransfer".$r[1]; // ics anziehen
		$result= open_https_url($urlex,"","","",0);
		
		//Header Wegschneiden
		//preg_match('#END:VTIMEZONE(.*)END:VCALENDAR#s', $result, $tocal);

		$tocal = explode("END:VTIMEZONE",$result);
		$tocal[1] = str_ireplace ("END:VCALENDAR", "", $tocal[1]);
		$tocal[1] = str_replace("\r","",$tocal[1]);

		echo utf8_encode($tocal[1]);
	}
	echo "END:VCALENDAR";
}

//todo: caching, da Anfrage sehr lange Antwortzeit (durch Tucan) hat
//echo utf8_encode($output);
//$fp = fopen('cal.ical', 'w');
//fwrite($fp, $output);
//fclose($fp);

function open_https_url($url,$refer = "",$post= 0,$fields="",$showheaders=1) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // SSL-Zertifkat nicht prüfen...
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HEADER, $showheaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)");

	if ($refer != "") {

        curl_setopt($ch, CURLOPT_REFERER, $refer );

    }
	$fields_string = "";
	if ($post == 1) {
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string,'&');
		curl_setopt($ch,CURLOPT_POST,count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		}
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	$result=curl_exec($ch);

    if(preg_match('#<a href=\"(.*)\">Weiter</a><br>#', $result, $r)){
		curl_close ($ch);
		$ch = curl_init();
		$url= "https://www.tucan.tu-darmstadt.de/scripts/mgrqcgi".$r[1];
		$url = str_ireplace ("&amp;", "&", $url);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // SSL-Zertifkat nicht prüfen...
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_HEADER, $showheaders);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)");
		$result=curl_exec($ch);
		curl_close ($ch);
	}
    return $result;
}


