<?php

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
	$client = new Google_Client();
	$client->setApplicationName('BDDTrans Parser');
	$client->setScopes(Google_Service_Sheets::SPREADSHEETS);
	$client->setAuthConfig(__DIR__ . '/credentials.json');
	$client->setAccessType('offline');
	$client->setPrompt('select_account consent');

	// Load previously authorized token from a file, if it exists.
	// The file token.json stores the user's access and refresh tokens, and is
	// created automatically when the authorization flow completes for the first
	// time.
	$tokenPath = __DIR__ . '/token.json';
	if (file_exists($tokenPath)) {
		$accessToken = json_decode(file_get_contents($tokenPath), true);
		$client->setAccessToken($accessToken);
	}

	// If there is no previous token or it's expired.
	if ($client->isAccessTokenExpired()) {
		// Refresh the token if possible, else fetch a new one.
		if ($client->getRefreshToken()) {
			$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
		} else {
			// Request authorization from the user.
			$authUrl = $client->createAuthUrl();
			printf("Open the following link in your browser:\n%s\n", $authUrl);
			print 'Enter verification code: ';
			$authCode = trim(fgets(STDIN));

			// Exchange authorization code for an access token.
			$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
			$client->setAccessToken($accessToken);

			// Check to see if there was an error.
			if (array_key_exists('error', $accessToken)) {
				throw new Exception(join(', ', $accessToken));
			}
		}
		// Save the token to a file.
		if (!file_exists(dirname($tokenPath))) {
			mkdir(dirname($tokenPath), 0700, true);
		}
		file_put_contents($tokenPath, json_encode($client->getAccessToken()));
	}
	return $client;
}


/**
 * Return page content of a URL
 * cURL: http://php.net/manual/en/book.curl.php
 * cURL Options: http://www.php.net/manual/en/function.curl-setopt.php
 * @param $url URL of the request
 * @param $token Login token
 * @return String The page content
 */
function urlopen($url, $token = null)
{        
	$curl_handler = curl_init();
	$options = array(CURLOPT_URL => $url,
					 CURLOPT_FAILONERROR => true,
					 CURLOPT_RETURNTRANSFER => true,
					 CURLOPT_TIMEOUT => 3,
					 CURLOPT_POST => true
					 );
	curl_setopt_array($curl_handler, $options);
	if ($token) {
		curl_setopt($curl_handler, CURLOPT_COOKIE, "token=$token");
	}
	$result = curl_exec($curl_handler);
	curl_close($curl_handler);
	
	return $result;
}

/**
 * Return token from Login Cookies
 * cURL: http://php.net/manual/en/book.curl.php
 * cURL Options: http://www.php.net/manual/en/function.curl-setopt.php
 * @param $url URL of the request
 * @param $login Login for connexion to site
 * @param $password Password for connexion to site
 * @return String The token
 */
function getLoginToken($url, $login, $password)
{        
	$curl_handler = curl_init();
	$options = array(CURLOPT_URL => $url,
					 CURLOPT_FAILONERROR => true,
					 CURLOPT_RETURNTRANSFER => true,
					 CURLOPT_TIMEOUT => 3,
					 CURLOPT_POST => true,
					 CURLOPT_HEADER => true,
					 CURLOPT_POSTFIELDS => "pseudoco=$login&pwdco=$password",
					 CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded')
					 );
	curl_setopt_array($curl_handler, $options);
	$result = curl_exec($curl_handler);
	curl_close($curl_handler);
	
	preg_match('/^Set-Cookie:.*token=([a-zA-Z0-9]{64});/mi', $result, $matches);
	return $matches[1];
}

/**
 * Return the comments for the specified page
 * @param $url URL of the page
 * @param $token Login token
 * @return Array All comments
 */
function getComments($url, $token)
{        
	$html = urlopen($url, $token);
	$xpath = getHtmlDomXPath($html);
	$view_com = $xpath->query("//div[@class='view_com']");

	$comments = array();

	foreach ($view_com as $com) {
		$tag = $xpath->query("p[@class='view_com_tag']/span", $com)->item(0)->textContent;
		$body = $xpath->query("p[@class='view_com_body']", $com)->item(0)->textContent;
		array_push($comments, array(
			'tag' => $tag,
			'body' => ($body)
		));
	}
	
	return $comments;
}

/**
 * @param $html
 * @return DOMXpath
 */
function getHtmlDomXPath($html)
{
	$doc = new DOMDocument();
	libxml_use_internal_errors(true);
	$doc->loadHTML($html);
	libxml_clear_errors();

	return new DOMXpath($doc);
}

function writeCSV($data, $csvfile)
{
	$fp = fopen($csvfile, 'w');
	$header = false;
	foreach ($data as $row)
	{
		if (empty($header))
		{
			$header = array_keys($row);
			fputcsv($fp, $header);
			$header = array_flip($header);
		}
		fputcsv($fp, array_merge($header, $row));
	}
	fclose($fp);
	return;
}

function updateGoogleSheet($spreadsheetId, $values)
{
	$client = getClient();
	$service = new Google_Service_Sheets($client);

	$range = "A:Z";

	$body = new Google_Service_Sheets_ValueRange([
		'values' => $values
	]);
	$params = [
		'valueInputOption' => "RAW"
	];
	$result = $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
	printf("%d rows updated.\n", $result->getUpdatedRows());

	// return array(
	// 	'spreadsheetId'	 => $result->spreadsheetId,
	// 	'updatedCells'	 => $result->updatedCells,
	// 	'updatedColumns' => $result->updatedColumns,
	// 	'updatedRange'	 => $result->updatedRange,
	// 	'updatedRows'	 => $result->updatedRows
	// );
	return;
}
