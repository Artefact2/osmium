<?php
/* Osmium
 * Copyright (C) 2012, 2013, 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium;

/* This file will be included before inc/root.php and is the only file
 * included before dispatching the URI. */

$__start = microtime(true);

define(__NAMESPACE__.'\ROOT', realpath(__DIR__.'/../'));
define(__NAMESPACE__.'\INI_CONFIGURATION_FILE', ROOT.'/config.ini');

define(
	__NAMESPACE__.'\HTTPS',
	isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
);

/* Also used in try_get_fit_from_remote_format() */
const PUBLIC_LOADOUT_RULE = '%^/loadout/(?<loadoutid>[1-9][0-9]*)(R(?<revision>[1-9][0-9]*))?(P(?<preset>[0-9]+))?(C(?<chargepreset>[0-9]+))?(D(?<dronepreset>[0-9]+))?(/booster/(?<fleet>(fleet|wing|squad)))?(/remote/(?<remote>.+))?$%D';

/* Also used in try_get_fit_from_remote_format() */
const PRIVATE_LOADOUT_RULE = '%^/loadout/private/(?<loadoutid>[1-9][0-9]*)(R(?<revision>[1-9][0-9]*))?(P(?<preset>[0-9]+))?(C(?<chargepreset>[0-9]+))?(D(?<dronepreset>[0-9]+))?/(?<privatetoken>0|[1-9][0-9]*)(/booster/(?<fleet>(fleet|wing|squad)))?(/remote/(?<remote>.+))?$%D';

/* Also used in try_get_fit_from_remote_format() */
const NEW_LOADOUT_RULE = '%^/new(/(?<token>0|[1-9][0-9]*))?$%D';

function printr($stuff) {
	echo "<pre>\n";
	print_r($stuff);
	echo "</pre>\n";
}

function debug() {
	$f = fopen('/tmp/osmium', 'ab');
	ob_start();

	echo "\n\n===== ".date('c')."  =====\n";
	debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	echo "\n";

	foreach(func_get_args() as $thing) var_dump($thing);

	fwrite($f, ob_get_clean());
	fflush($f);
	fclose($f);
}

function get_ini_setting($key) {
	static $cnf = null;
	if($cnf === null) {
		if(!file_exists(INI_CONFIGURATION_FILE) || !is_readable(INI_CONFIGURATION_FILE)) {
			fatal(500, "Configuration file <code>'".INI_CONFIGURATION_FILE."'</code> not found or not readable.");
		}
		/* Maybe cache this in memory? Parsing ini files is pretty
		 * much as fast as deserialization and the file will be in
		 * filesystem cache anyway */
		$cnf = parse_ini_file(INI_CONFIGURATION_FILE);
	}

	return isset($cnf[$key]) ? $cnf[$key] : null;
}

function get_osmium_version() {
	$version = \Osmium\State\get_cache_memory('git_version', null);
	if($version !== null) return $version;

	$sem = \Osmium\State\semaphore_acquire('git_version');
	if($sem === false) return 'unknown';

	$version = \Osmium\State\get_cache_memory('git_version', null);
	if($version !== null) {
		\Osmium\State\semaphore_release($sem);
		return $version;
	}

	$version = trim(shell_exec(
		'cd '.escapeshellarg(ROOT)
		.'; (git describe --always --dirty 2>/dev/null || echo "unknown")'
	));
	\Osmium\State\put_cache_memory('git_version', $version, 600);
	\Osmium\State\semaphore_release($sem);
	return $version;
}

function curl_init_branded() {
	$c = curl_init();
	$cver = curl_version();
	$over = ltrim(get_osmium_version(), 'v');
	$pver = phpversion();
	curl_setopt($c, CURLOPT_USERAGENT, "Osmium/{$over} (PHP/{$pver}; libcurl/{$cver['version']}; {$cver['ssl_version']}; +http://artefact2.com/osmium/)");
	return $c;
}

function fatal($code, $message = '', $title = null, $showbt = null, $die = true) {
	/* Don't halt script execution if used with the shutup (@)
	 * operator */
	if(error_reporting() === 0) return;

	if(!headers_sent()) {
		http_response_code($code);
	}

	require_once __DIR__.'/root.php';

	$escape = function_exists('Osmium\Chrome\escape') ? 'Osmium\Chrome\escape' : 'htmlspecialchars';

	static $internaltitles = [
		"He's dead, Jim!",
		"Internal user error",
		"Oh noes!",
		"Spline reticulation error",
		"What could go wrong?",
		"Segmentation fault",
		"Nice job, user. You broke it.",
		"I don't know what I expected.",
		"Acute hamster failure",
		"You just lost the game.",
		"Sorry.",
	];

	if($showbt === null) $showbt = ($code >= 500);
	if($title === null && $code >= 500) $title = $internaltitles[time() % count($internaltitles)];
	$hue = ($code >= 400 && $code < 500) ? 10 : 210;

	$relprefix = rtrim(get_ini_setting('relative_path'), '/');

	echo "<!DOCTYPE html>\n<html xmlns='http://www.w3.org/1999/xhtml'>\n<head>\n";
	echo "<meta name='robots' content='noindex' />\n";
	echo "<meta charset='utf-8' />\n";
	echo "<link href='//fonts.googleapis.com/css?family=Droid+Serif:400,400italic,700,700italic|Droid+Sans:400,700|Droid+Sans+Mono' rel='stylesheet' type='text/css' />\n";
	echo "<link rel='stylesheet' href='".$relprefix."/static-".\Osmium\CSS_STATICVER."/fatal.css' type='text/css' />\n";
	echo "<title>{$code} / Osmium</title>\n";
	echo "</head>\n<body style='background: hsl({$hue}, 80%, 10%);'><div class='bg'></div>\n<div class='w'>\n";

	if($title) {
		echo "<h1>{$title}</h1>\n";
	} else {
		echo "<h1 class='code'>{$code}</h1>\n";
	}

	if($message !== '') {
		echo "<p>{$message}</p>\n";
	}

	echo "<p class='home'><a href='{$relprefix}/'><strong>Return to the main page</strong></a></p>\n";

	if($showbt) {
		echo "<p>This issue has been logged.<br />Please report it to the developers and describe the steps to reproduce this issue. Thanks!</p>\n";
		$bt = '';
		foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10) as $c) {
			$bt .= '\\'.$c['function'].'() called from '.$c['file'].':'.$c['line']."\n";
		}
		echo "<pre class='bt'>".$escape($bt)."</pre>\n";
	}

	list($msec, $sec) = explode(' ', microtime(), 2);
	$footer = gethostname().' – '.date('Y-m-d H:i:s', $sec).rtrim(substr($msec, 1), "0");

	echo "<footer><pre>".$escape($footer)."</pre></footer>\n";

	echo "</div></body>\n</html>\n";

	if($die) {
		die((int)$code);
	}
}
