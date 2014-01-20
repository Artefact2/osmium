<?php
/* Osmium
 * Copyright (C) 2012, 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Chrome;

/** Relative path to Osmium root */
$__osmium_chrome_relative = '.';

/** Javascript scripts to add just before </body> */
$__osmium_js_scripts = array();

/** Javascript snippets to add just before </body> */
$__osmium_js_snippets = array();

/** Javascript data to add just before </body> */
$__osmium_js_data = array();

/**
 * Print the page header. Nothing should be printed before this call
 * (except header() calls).
 *
 * @param $title the title of the page (will only be put in <title>),
 * no escaping done.
 *
 * @param $relative the relative path to the main page.
 *
 * @param $add_head optional HTML code to add just before </head>,
 * unescaped.
 */
function print_header($title = '', $relative = '.', $index = true, $add_head = '') {
	global $__osmium_chrome_relative;
	$__osmium_chrome_relative = $relative;

	/* If the user is using TLS, only allow resources from
	 * https://. If not, allow from both. */
	if(\Osmium\HTTPS) {
		header(
			"Content-Security-Policy: default-src 'none'"
			." ; style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com http://cdnjs.cloudflare.com 'unsafe-inline'"
			." ; font-src https://themes.googleusercontent.com"
			." ; img-src 'self' https://image.eveonline.com"
			." ; script-src 'self' https://cdnjs.cloudflare.com"
			." ; connect-src 'self'"
		);

		if(\Osmium\get_ini_setting('https_available') && \Osmium\get_ini_setting('use_hsts')) {
			$maxage = (int)\Osmium\get_ini_setting('https_cert_expiration') - time() - 86400;
			if($maxage > 0) {
				header('Strict-Transport-Policy: max-age='.$maxage);
			}
		}
	} else {
		header(
			"Content-Security-Policy: default-src 'none'"
			." ; style-src 'self' https://fonts.googleapis.com http://fonts.googleapis.com https://cdnjs.cloudflare.com http://cdnjs.cloudflare.com 'unsafe-inline'"
			." ; font-src https://themes.googleusercontent.com http://themes.googleusercontent.com"
			." ; img-src 'self' https://image.eveonline.com http://image.eveonline.com"
			." ; script-src 'self' https://cdnjs.cloudflare.com http://cdnjs.cloudflare.com"
			." ; connect-src 'self'"
		);
	}

	$osmium = \Osmium\get_ini_setting('name');
	if($title == '') {
		$title = $osmium.' / '.\Osmium\get_ini_setting('description');
	} else {
		$title .= ' / '.$osmium;
	}

	$notifications = \Osmium\Notification\get_new_notification_count();
	if($notifications > 0) {
		$title = "({$notifications}) ".$title;
	}

	if(XHTML) {
		header('Content-Type: application/xhtml+xml; charset=utf-8');
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<html xmlns='http://www.w3.org/1999/xhtml' xmlns:v='urn:schemas-microsoft-com:vml'>\n";
	} else {
		header('Content-Type: text/html; charset=utf-8');
		echo "<!DOCTYPE html>\n<html>\n";
	}

	echo "<head>\n";
	if(!XHTML) echo "<meta charset='UTF-8' />\n";
	if(!$index) echo "<meta name='robots' content='noindex' />\n";
	echo "<link href='//fonts.googleapis.com/css?family=Droid+Serif:400,400italic,700,700italic|Droid+Sans:400,700|Droid+Sans+Mono' rel='stylesheet' type='text/css' />\n";

	echo "<link rel='help' href='{$relative}/help' />\n";

	/* Guess the current theme and put it first (to avoid blinking). */
	static $themes = array('Dark' => 'dark.css', 'Light' => 'light.css');
	$curtheme = isset($_COOKIE['t']) && isset($themes[$_COOKIE['t']]) ? $_COOKIE['t'] : 'Dark';

	echo "<link rel='stylesheet' href='$relative/static-".\Osmium\CSS_STATICVER."/".$themes[$curtheme]."' title='".$curtheme."' type='text/css' />\n";
	foreach($themes as $t => $f) {
		if($curtheme === $t) continue;

		echo "<link rel='alternate stylesheet' href='$relative/static-".\Osmium\CSS_STATICVER."/$f' title='$t' type='text/css' />\n";
	}

	$favicon = \Osmium\get_ini_setting('favicon');
	if(substr($favicon, 0, 2) === '//') {
		$favicon = urlencode($favicon);
	} else {
		$favicon = $relative.'/static-'.\Osmium\STATICVER.'/'.urlencode($favicon);
	}
	echo "<link rel='icon' type='image/png' href='{$favicon}' />\n";
	echo "<title>$title</title>\n";
	echo "$add_head</head>\n<body>\n<div id='wrapper'>\n";

	echo "<nav>\n";
	\Osmium\State\print_login_or_logout_box($relative, $notifications);

	echo "<ul>\n";
	echo get_navigation_link(
		$relative.'/', $osmium, $osmium,
		"Go back to the home page"
	);
	echo get_navigation_link(
		$relative.'/search', "Search loadouts", "Search",
		"Search fittings by ship, by fitted modules, by tags, etc."
	);
	echo get_navigation_link(
		$relative.'/new', "Create loadout", "Create",
		"Create a new fitting"
	);
	echo get_navigation_link(
		$relative.'/import', "Import", "Import",
		"Import one of more fittings from various formats"
	);
	echo get_navigation_link(
		$relative.'/convert', "Convert", "Convert",
		"Perform a quick conversion of fittings from one format to another"
	);

	if(\Osmium\State\is_logged_in()) {
		$a = \Osmium\State\get_state('a');

		echo get_navigation_link($relative.'/settings', "Settings");

		if($a['ismoderator'] === 't') {
			echo get_navigation_link(
				$relative.'/moderation/',
				\Osmium\Flag\MODERATOR_SYMBOL.'Moderation',
				\Osmium\Flag\MODERATOR_SYMBOL
			);
		}
	}
	echo "</ul>\n";

	echo "</nav>\n";

	\Osmium\Chrome\print_js_snippet('persistent_theme');
	\Osmium\Chrome\print_js_snippet('notifications');
	\Osmium\Chrome\print_js_snippet('feedback');
	\Osmium\Chrome\add_js_data('relative', $__osmium_chrome_relative);
}

/**
 * Print the page footer. As this closes the <html> tag, nothing
 * should be printed after calling this.
 */
function print_footer() {
	global $__osmium_chrome_relative, $__osmium_js_scripts, $__osmium_js_snippets, $__osmium_js_data, $__start;

	echo "<div id='push'></div>\n</div>\n<footer>\n<p>\n";
	echo "<a href='".$__osmium_chrome_relative."/changelog'><code>".\Osmium\get_osmium_version()."</code></a> –\n";
	echo "<a href='".$__osmium_chrome_relative."/about' rel='jslicense'>About</a> –\n";
	echo "<a href='".$__osmium_chrome_relative."/help' rel='help'>Help</a>\n";
	echo "</p>\n</footer>\n";

	/* If these scripts are changed, also change the license
	 * information in about.php */
	//echo "<script type='application/javascript' src='//cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>\n";
	//echo "<script type='application/javascript' src='//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js'></script>\n";
	# XXX changed for offline development
	echo "<script type='application/javascript' src='/static-".\Osmium\STATICVER."/3rdparty/jquery.min.js'></script>\n";
	echo "<script type='application/javascript' src='/static-".\Osmium\STATICVER."/3rdparty/jquery-ui.min.js'></script>\n";

	foreach($__osmium_js_scripts as $script) {
		echo "<script type='application/javascript' src='{$script}'></script>\n";
	}

	if(count($__osmium_js_snippets) > 0) {
		$name = 'JS_'.substr(sha1(implode("\n", $__osmium_js_snippets)), 0, 7);
		$cache = '/static/cache/'.$name;
		$cachefile = \Osmium\ROOT.$cache;
		$cacheuri = $__osmium_chrome_relative.'/static-'.\Osmium\JS_STATICVER.'/cache/'.$name.'.min.js';

		if(!file_exists($cachefile.'.min.js')) {
			$sem = \Osmium\State\semaphore_acquire('JS_'.$cachefile.'.js');
			if(!file_exists($cachefile.'.min.js')) {
				shell_exec('cat '.implode(' ', array_map('escapeshellarg', $__osmium_js_snippets))
				           .' >> '.escapeshellarg($cachefile.'.js'));

				if($min = \Osmium\get_ini_setting('minify_js')) {
					$command = \Osmium\get_ini_setting('minify_command');

					/* Concatenate & minify */
					shell_exec('cat '.implode(' ', array_map('escapeshellarg', $__osmium_js_snippets))
					           .' | '.$command.' >> '.escapeshellarg($cachefile.'.min.js'));
				}

				if(!$min || !file_exists($cachefile)) {
					/* Not minifying, or minifier failed for some reason */
					shell_exec('ln -s '.escapeshellarg($cachefile.'.js')
					           .' '.escapeshellarg($cachefile.'.min.js'));
				}
			}
			\Osmium\State\semaphore_release($sem);
		}

		echo "<script type='application/javascript' src='".escape($cacheuri)."'></script>\n";
	}

	if($__osmium_js_data !== []) {
		echo "<div id='osmium-data'";

		foreach($__osmium_js_data as $k => $strv) {
			echo " data-".$k."='".escape($strv)."'";
		}

		echo "></div>\n";
	}

	echo "</body>\n</html>\n";
	echo "<!-- ".(microtime(true) - $__start)." -->\n";

	\Osmium\State\put_activity();
}

function get_navigation_link($dest, $label, $shortlabel = null, $title = null) {
	if($shortlabel === null) {
		$shortlabel = $label;
	}

	if($title === null) {
		$core = "<span class='full'>{$label}</span>"
			."<span class='mini' title='{$label}'>{$shortlabel}</span>";
	} else {
		$core = "<span class='full' title='{$title}'>{$label}</span>"
			."<span class='mini' title='{$label} — {$title}'>{$shortlabel}</span>";
	}

	if(is_current($dest)) {
		return "<li><strong><a href='$dest'>{$core}</a></strong></li>\n";
	}

	return "<li><a href='$dest'>{$core}</a></li>\n";
}

/** @internal */
function is_current($relativeuri) {
	static $absoluteparts = null;
	static $currenturi;

	if($absoluteparts === null) {
		$absoluteparts = explode(
			'/',
			$currenturi = explode('?', $_SERVER['REQUEST_URI'], 2)[0]
		);

		$currenturi = ltrim($currenturi, '/');
	}

	$relativeparts = explode('/', explode('?', $relativeuri, 2)[0]);

	foreach($relativeparts as $p) {
		if($p === '.') {
			array_pop($absoluteparts);
			$absoluteparts[] = '';
		} else if($p === '..') {
			if(array_pop($absoluteparts) === '') {
				array_pop($absoluteparts);
				$absoluteparts[] = '';
			}
		} else {
			$absoluteparts[] = $p;
		}
	}

	$absolute = ltrim(implode('/', $absoluteparts), '/');

	if($absolute === "") return $currenturi === "";

	if(strpos($currenturi, $absolute) !== 0) {
		return false;
	}

	$l = strlen($absolute);
	return !isset($currenturi[$l]) || $currenturi[$l] === '/';
}

/**
 * Include a Javascript script in the current document.
 */
function include_js($uri) {
	global $__osmium_js_scripts;
	$__osmium_js_scripts[] = $uri;
}

/**
 * Print a Javascript snippet in the current document.
 *
 * @param $js_file name of the snippet, witout the ".js" extension
 * (assumed to be in /src/snippets/).
 */
function print_js_snippet($js_file) {
	global $__osmium_js_snippets;

	$__osmium_js_snippets[] = \Osmium\ROOT.'/src/snippets/'.$js_file.'.js';
}

function add_js_data($name, $strvalue) {
	global $__osmium_js_data;
	$__osmium_js_data[$name] = $strvalue;
}

/**
 * Ends the script and outputs JSON-encoded data.
 *
 * @param $data the PHP object/array/value to encode.
 *
 * @param $flags flags to pass to json_encode().
 */
function return_json($data, $flags = 0) {
	header('Content-Type: application/json');
	die(json_encode($data, $flags));
}
