<?php 
require __DIR__ . '/functions.php';

$base_url = 'https://bddtrans.fr';
$credentialsPath = __DIR__ . '/bddtrans_credentials.json';
$commentsDbFile = __DIR__ . '/bddtrans_comments.json';
$json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$time = time();

$defaultOptions = [
	'categorie' => 'autres',
	'slug' => '',
	'format' => 'json'
];
$options = [];
if (php_sapi_name() == 'cli') {
	$options = getopt('', [
		'categorie:',
		'slug:',
		'format::'
	]);
}

$options = array_merge($defaultOptions, $_GET, $options);

$categorie = $options['categorie'];
$slug = $options['slug'];
$format = $options['format'];

$bddtrans_credentials = [
	'pseudo'       => '',
	'password'     => ''
];
if (file_exists($credentialsPath)) {
	$bddtrans_credentials = json_decode(file_get_contents($credentialsPath), true);
}
if (empty($bddtrans_credentials['pseudo']) || empty($bddtrans_credentials['password'])) {
	file_put_contents($credentialsPath, json_encode($bddtrans_credentials, $json_flags));
	echo 'Remplire le fichier '.basename($credentialsPath).'.';
	exit(1);
}
if (empty($bddtrans_credentials['token']) || $bddtrans_credentials['token_expires'] > $time) {
	$login_url = $base_url.'/userpanel/connexion.php';
	$bddtrans_credentials['token'] = getLoginToken($login_url, $bddtrans_credentials['pseudo'], $bddtrans_credentials['password']);
	$bddtrans_credentials['token_expires'] = ($time + (60 * 60 * 10)); // Token expires in 10 hours
	file_put_contents($credentialsPath, json_encode($bddtrans_credentials, $json_flags));
}

if (!file_exists($commentsDbFile)) {
	file_put_contents($commentsDbFile, json_encode([], $json_flags));
}

$commentsDB = json_decode(file_get_contents($commentsDbFile), true);

if (
	empty($commentsDB[$slug])
	OR $commentsDB[$slug]['updated'] < ($time - (60 * 60 * 48))
	OR empty($commentsDB[$slug]['comments'])
) {
	$token = $bddtrans_credentials['token'];
	$url = $base_url . '/' . $categorie . '/' . $slug . '.html';

	$commentsDB[$slug]['comments'] = getComments($url, $token);
	$commentsDB[$slug]['updated'] = $time;
	file_put_contents($commentsDbFile, json_encode($commentsDB, $json_flags));
}

$comments = $commentsDB[$slug]['comments'];

if ($format == "json") {
	$comments = empty($slug) ? $commentsDB : $comments;
	header('Content-Type: application/json');
	echo json_encode(empty($comments) ? [] : $comments, $json_flags);
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
