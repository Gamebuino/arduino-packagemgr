<?php
// call at package_gamebuino_index.json via nginx
header('Content-Type: application/json');

$CACHEFILE = realpath(dirname(__FILE__)).'/package_cache.json';
if (file_exists($CACHEFILE)) {
	echo file_get_contents($CACHEFILE);
	exit;
}


include_once(realpath(dirname(__FILE__)).'/sql.php');


$json = [
	'packages' => []
];

$platforms_cache = [];
$boards_cache = [];
$tooldep_cache = [];
foreach ($sql->query("SELECT `id`, `name`, `maintainer`, `url`, `online_help` FROM `packages`", []) as $package) {
	$pid = (int)$package['id'];

	$package_json = [
		'name' => $package['name'],
		'maintainer' => $package['maintainer'],
		'websiteURL' => $package['url'],
		'help' => [
			'online' => $package['online_help'],
		],
		'platforms' => [],
		'tools' => [],
	];

	foreach ($sql->query("SELECT `platform`, `version` FROM `platform_versions` WHERE `package`=%d", [$pid]) as $p) {
		if (!isset($platforms_cache[$p['platform']])) {
			$plat = $sql->query("SELECT `name`, `architecture`, `category`, `filename` FROM `platforms` WHERE `id`=%d", [$p['platform']], 0);
			$filepath = realpath(dirname(__FILE__)).'/files/'.$plat['filename'].'.zip';
			$platforms_cache[$p['platform']] = [
				'name' => $plat['name'],
				'architecture' => $plat['architecture'],
				'category' => $plat['category'],
				'url' => $ROOTURL.'/files/'.$plat['filename'].'.zip',
				'archiveFileName' => $plat['filename'].'.zip',
				'checksum' => 'SHA-256:'.hash_file('sha256', $filepath),
				'size' => filesize($filepath),
			];
		}
		$obj = $platforms_cache[$p['platform']];
		$obj['version'] = $p['version'];
		
		$obj['boards'] = [];
		foreach ($sql->query("SELECT `board` FROM `platform_boards` WHERE `platform_version` = %d", [$p['platform']]) as $b) {
			if (!isset($boards_cache[$b['board']])) {
				$boards_cache[$b['board']] = $sql->query("SELECT `name` FROM `boards` WHERE `id` = %d", [$b['board']], 0)['name'];
			}
			$obj['boards'][] = [ 'name' => $boards_cache[$b['board']] ];
		}
		
		$obj['toolsDependencies'] = [];
		foreach ($sql->query("SELECT `tool` FROM `platform_tools` WHERE `platform_version` = %d", [$p['platform']]) as $pt) {
			if (!isset($tooldep_cache[$pt['tool']])) {
				$td = $sql->query("SELECT `packager`, `name`, `version` FROM `tools` WHERE `id` = %d", [$pt['tool']], 0);
				$tooldep_cache[$pt['tool']] = [
					'packager' => $td['packager'],
					'name' => $td['name'],
					'version' => $td['version'],
				];
			}
			$obj['toolsDependencies'][] = $tooldep_cache[$pt['tool']];
		}
		
		$package_json['platforms'][] = $obj;
	}
	$json['packages'][] = $package_json;
}
$s = json_encode($json);
file_put_contents($CACHEFILE, $s);
echo $s;
