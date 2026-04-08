<?php 
require __DIR__ . '/functions.php';

$base_url = 'https://bddtrans.fr';
$credentialsPath = __DIR__ . '/bddtrans_credentials.json';
$commentsDbFile = __DIR__ . '/bddtrans_comments.json';
$json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$token_max_age = 60 * 12; // Max age of token in minutes
$comments_max_age = 60 * 48; // Max age of comments in minutes
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

if (empty($slug) || empty($categorie)) {
	echo 'Missing parameter `slug` or `categorie`';
	exit;
}

$bddtrans_credentials = [
	'pseudo'       => '',
	'password'     => ''
];
if (!file_exists($credentialsPath)) {
	file_put_contents($credentialsPath, json_encode($bddtrans_credentials, $json_flags));
}
$bddtrans_credentials = json_decode(file_get_contents($credentialsPath), true);
if (empty($bddtrans_credentials['pseudo']) || empty($bddtrans_credentials['password'])) {
	error_log('No credentials provided! Check file ' . $credentialsPath);
}

$token = $bddtrans_credentials['token'] ?? false;
$token_created = $bddtrans_credentials['token_created'] ?? 0;
$token_age = ($time - $token_created) / 60;
if ($token_age > $token_max_age || !checkLoginStatus($base_url, $token)) {
	$login_url = $base_url.'/userpanel/connexion.php';
	$token = getLoginToken($login_url, $bddtrans_credentials['pseudo'], $bddtrans_credentials['password']);
	if (!empty($token)) {
		$bddtrans_credentials['token'] = $token;
		$bddtrans_credentials['token_created'] = $time;
		file_put_contents($credentialsPath, json_encode($bddtrans_credentials, $json_flags));
	}
	error_log("Token expired after $token_age minutes. New token: " . $token);
}

if (!file_exists($commentsDbFile)) {
	file_put_contents($commentsDbFile, json_encode([], $json_flags));
}
$commentsDB = json_decode(file_get_contents($commentsDbFile), true);

$commentsExpired = empty($commentsDB[$slug]) || $commentsDB[$slug]['updated'] < $time - 60 * $comments_max_age;
if ($commentsExpired && !empty($token)) {
	$url = $base_url . '/' . $categorie . '/' . $slug . '.html';

	$commentsDB[$slug]['comments'] = getComments($url, $token);
	$commentsDB[$slug]['updated'] = $time;
	file_put_contents($commentsDbFile, json_encode($commentsDB, $json_flags));
	error_log('Retrieving comments for ' . $slug);
}

if (empty($commentsDB[$slug])) {
	echo '<div class="alert alert-danger">Commentaires non recuperer</div>';
	exit;
}

if ($format == "json") {
	header('Content-Type: application/json');
	echo json_encode([$slug => $commentsDB[$slug]], $json_flags);
	exit;
}

$comments = $commentsDB[$slug]['comments'];
echo '<div class="well well-sm">Derniere extraction des commentaires le '.date("d/m/Y à H:i:s", $commentsDB[$slug]['updated']).'</div>';
if (sizeof($comments) < 1) {
	echo '<div class="well well-sm">Aucun commentaires</div>';
}
foreach ($comments as $com) {
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">'.$com['tag'].'</div>';
	echo '<div class="panel-body">';
	echo nl2br($com['body']);
	echo '</div>';
	echo '</div>';
}
