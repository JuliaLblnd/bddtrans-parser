<?php
require __DIR__ . '/functions.php';

if (php_sapi_name() != 'cli') {
	throw new Exception('This application must be run on the command line.');
}

$opts = getopt('o:s:');
$output_dir = (isset($opts['o'])) ? $opts['o'] : __DIR__;
$spreadsheetId = (isset($opts['s'])) ? $opts['s'] : null;
$jsonfile = $output_dir . '/bddtrans.json';
$csvfile  = $output_dir . '/bddtrans.csv';
$base_url = 'https://bddtrans.fr';

if (!is_dir($output_dir)) {
	exit("Directory '$output_dir' does not exit.");
}

$categories = array(
	"generalistes",
	"endocrinologues",
	"psy",
	"voix",
	"chirugiens",
	"gynecologues",
	"dermato",
	"avocats",
	"autres",
);

$praticiens = array();

foreach ($categories as $category) {
	for ($i=1; $i < 5; $i++) {
		$url = $base_url . '/' . $category . '-' . $i . '/';
		$html = urlopen($url);
		$timeout = 0;
		while (empty($html) && $timeout++ < 5) {
			usleep(100 * 1000); // Sleep fo 100 milliseconds
			$html = urlopen($url);
		}
		$xpath = getHtmlDomXPath($html);
		$summ_prat = $xpath->query("//div[@class='summ_prat']");

		if ($summ_prat->length == 0) {
			break;
		}

		foreach ($summ_prat as $prat) {
			$infos = $xpath->query("p[@class='view_prat']/strong", $prat);

			$description = $xpath->query("p[@class='view_prat']/span[@class='description']", $prat);
			$description = $description->item(0)->nodeValue;
			$description = trim($description);
			// $description = trim(preg_replace('/\s+/', ' ', $description));

			$link = $xpath->query("p[@class='view_prat_link']/a", $prat)->item(0);
			$link = $link->getAttribute("href");

			$comments = $xpath->query("p[@class='view_prat_link']/a", $prat)->item(1)->textContent;
			preg_match('/\((\d+)\)$/', $comments, $comments);
			$comments = intval($comments[1]);

			preg_match('/^.*\/(.*)\.html/', $link, $slug);
			$slug = $slug[1];

			$link = preg_replace('/^\.+/', $base_url, $link);

			$tags_dom = $xpath->query("p[@class='view_prat_tag']/span", $prat);
			$tags = array();
			foreach ($tags_dom as $tag) {
				array_push($tags, $tag->nodeValue);
			}
			$tags = implode(', ', $tags);

			$new_prat = array(
				"specialite" => $tags,
				"nom" => $infos->item(0)->nodeValue,
				"prenom" => $infos->item(1)->nodeValue,
				"adresse" => $infos->item(2)->nodeValue,
				"code postal" => $infos->item(3)->nodeValue,
				"ville" => $infos->item(4)->nodeValue,
				"pays" => $infos->item(5)->nodeValue,
				"description" => $description,
				"lien" => $link,
				"commentaires" => $comments,
				"categorie" => $category,
				"slug" => $slug
			);

			array_push($praticiens, $new_prat);
		}
	}
}
// $praticiens = array_unique($praticiens);

if (sizeof($praticiens) <= 1) {
	echo "No data\n";
	exit(1);
}

file_put_contents($jsonfile, json_encode($praticiens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$praticiens_values = array();
$header = false;
$fp = fopen($csvfile, 'w');
foreach ($praticiens as $row) {
	if (empty($header)) {
		$header = array_keys($row);
		array_push($praticiens_values, $header);
		fputcsv($fp, $header);
		$header = array_flip($header);
	}
	array_push($praticiens_values, array_values($row));
	fputcsv($fp, array_merge($header, $row));
}
fclose($fp);

if (isset($spreadsheetId)) {
	$autoloader = __DIR__ . '/vendor/autoload.php';
	if (file_exists($autoloader)) {
		require $autoloader;
		updateGoogleSheet($spreadsheetId, $praticiens_values);
	}
	else {
		echo "Dependencies not installed.\n";
		echo "Try to run \"composer install\".\n";
		exit(1);
	}
}
