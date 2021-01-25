<?php 
require __DIR__ . '/functions.php';

$base_url = 'https://bddtrans.fr';
$credentialsPath = __DIR__ . '/bddtrans_credentials.json';
$commentsDbFile = __DIR__ . '/bddtrans_comments.json';
$time = time();

$defaultOptions = [
	'categorie' => 'autres',
	'bddtrans_id' => '',
	'format' => 'json'
];
$options = [];
if (php_sapi_name() == 'cli') {
	$options = getopt('', [
		'categorie:',
		'bddtrans_id:',
		'format::'
	]);
}

$options = array_merge($defaultOptions, $_GET, $options);

$categorie = $options['categorie'];
$bddtrans_id = $options['bddtrans_id'];
$format = $options['format'];

$bddtrans_credentials = [
	'pseudo'       => '',
	'password'     => ''
];
if (file_exists($credentialsPath)) {
	$bddtrans_credentials = json_decode(file_get_contents($credentialsPath), true);
}
if (empty($bddtrans_credentials['pseudo']) || empty($bddtrans_credentials['password'])) {
	file_put_contents($credentialsPath, json_encode($bddtrans_credentials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	echo 'Remplire le fichier '.basename($credentialsPath).'.';
	exit(1);
}
if (empty($bddtrans_credentials['token']) || $bddtrans_credentials['token_expires'] > $time) {
	$login_url = $base_url.'/userpanel/connexion.php';
	$bddtrans_credentials['token'] = getLoginToken($login_url, $bddtrans_credentials['pseudo'], $bddtrans_credentials['password']);
	$bddtrans_credentials['token_expires'] = ($time + (60 * 60 * 48)); // Token expires in 48 hours
	file_put_contents($credentialsPath, json_encode($bddtrans_credentials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

if (!file_exists($commentsDbFile)) {
	file_put_contents($commentsDbFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

$commentsDB = json_decode(file_get_contents($commentsDbFile), true);

if (!isset($commentsDB[$bddtrans_id]) || $commentsDB[$bddtrans_id]['updated'] < ($time - (60 * 60 * 48)) || empty($commentsDB[$bddtrans_id]['comments'])) {
	$token = $bddtrans_credentials['token'];
	$url = $base_url . '/' . $categorie . '/' . $bddtrans_id . '.html';

	$commentsDB[$bddtrans_id]['comments'] = getComments($url, $token);
	$commentsDB[$bddtrans_id]['updated'] = $time;
	file_put_contents($commentsDbFile, json_encode($commentsDB, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

$comments = $commentsDB[$bddtrans_id]['comments'];

if ($format == "json") {
	header('Content-Type: application/json');
	echo json_encode(empty($comments) ? [] : $comments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
else {
	if (sizeof($comments) >= 1) {
		foreach ($comments as $com) {
			echo '<div class="panel panel-default">';
			echo '<div class="panel-heading">'.$com['tag'].'</div>';
			echo '<div class="panel-body">';
			echo nl2br($com['body']);
			echo '</div>';
			echo '</div>';
		}
	}
	else {
		echo '<p>Aucun commentaires</p>';
	}
}
