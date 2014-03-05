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

define(
	__NAMESPACE__.'\XHTML',
	isset($_SERVER['HTTP_ACCEPT']) && (
		strpos($_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml') !== false
	)
);

const F_USED_SHOW_ABSOLUTE = 1;
const F_USED_SHOW_DIFFERENCE = 2;
const F_USED_SHOW_PERCENTAGE = 4;
const F_USED_SHOW_PROGRESS_BAR = 8;

require __DIR__.'/chrome-layout.php';
require __DIR__.'/chrome-fit.php';


/**
 * Escape a string.
 */
function escape($s) {
	if(\Osmium\Chrome\XHTML) {
		return htmlspecialchars($s, ENT_QUOTES | ENT_XML1);
	}

	/* ENT_HTML5 should be used, but some old old browsers won't
	 * understand &apos; */
	return htmlspecialchars($s, ENT_QUOTES);
}

/* Format a number with a fixed number of significant digits.
 *
 * @param $sd number of significant digits. If negative, keep all
 * significant digits.
 *
 * @param $min show this suffix at least (must be one of k, m or b)
 */
function format($number, $sd = 3, $min = '') {
	static $suffixes = [
		'b' => 1e9,
		'm' => 1e6,
		'k' => 1e3,
		'' => 1
	];

	if($number < 0) return '-'.format(-$number, $sd, $min);

	if($sd < 0) return number_format($number / $suffixes[$min]).$min;

	foreach($suffixes as $s => $limit) {
		if($number >= $limit || $s === $min) {
			return round_sd($number / $limit, $sd - 1).$s;
		}
	}
}

/** Much better than the junk trim() function from the PHP library */
function trim($s) {
	return preg_replace('%^\p{Z}*(.*?)\p{Z}*$%u', '$1', $s);
}

function format_small_progress_bar($percent, $fill = true, $ltr = true, $maxoverflow = 10) {
	$over = min($maxoverflow, max(0, $percent - 100));

	$bclass = $ltr ? 'lbar' : 'bar';
	$oclass = $ltr ? 'bar' : 'lbar';
	if($percent > 100) {
		$c = 'p100';
	} else if($percent >= 95) {
		$c = 'p95';
	} else if($percent >= 80) {
		$c = 'p80';
	} else {
		$c = 'p00';
	}
	$progress = max(0, min(100, $percent));

	$ret = "<span class='{$bclass} {$c}' style='width: ".round($progress, 2)."%;'></span>";
	if($over > 0) {
		$over = round($over, 2).'%';
		$ret .= "<span class='{$oclass} {$c} over' style='width: {$over}; "
			.($ltr ? 'right' : 'left').": -{$over};'></span>";
	}
	if($fill) {
		$ret .= "<span class='bar fill'></span>";
	}

	return $ret;
}

function format_used($rawused, $rawtotal, $digits, $opts = F_USED_SHOW_ABSOLUTE) {
	$lines = array();

	$used = format_number($rawused).' / '.format_number($rawtotal);
	$diff = ($rawtotal >= $rawused) ?
		format_number($rawtotal - $rawused).' left' :
		format_number($rawused - $rawtotal).' over';

	if($opts & F_USED_SHOW_ABSOLUTE) {
		$lines[] = "<span title='".escape($diff)."'>"
			.escape($used)
			."</span>";
	}
	if($opts & F_USED_SHOW_DIFFERENCE) {
		$lines[] = "<span title='".escape($used)."'>"
			.escape($diff)
			."</span>";
	}

	$percent = $rawtotal > 0 ? (100 * $rawused / $rawtotal) : ($rawused > 0 ? 100 : 0);
	if($opts & F_USED_SHOW_PERCENTAGE) {
		$lines[] = ($rawtotal == 0 ? ($rawused == 0 ? 0 : '∞') : round($percent, $digits)).' %';
	}

	$ret = implode("<br />", $lines);

	if($opts & F_USED_SHOW_PROGRESS_BAR) {
		$ret .= format_small_progress_bar($percent);
	}

	return $ret;
}

function format_number($num, $precisionoffset = 0) {
	$num = floatval($num);
	if($num < 0) {
		$sign = '-';
		$num = -$num;
	} else {
		$sign = '';
	}

	if($num < 10000) return $sign.round($num, max(0, 1 + $precisionoffset));
	else if($num < 1000000) {
		return $sign.round($num / 1000, max(0, 2 + $precisionoffset)).'k';
	} else {
		return $sign.round($num / 1000000, max(0, 3 + $precisionoffset)).'m';
	}
}

function format_duration($seconds) {
	$s = fmod($seconds, 60);
	$m = round($seconds - $s) / 60;

	$s = floor($s);

	if($s == 0 && $m == 0) return '0s';
	else {
		$k = '';
		if($m != 0) { $k .= $m.'m'; }
		if($s != 0) { $k .= $s.'s'; }
		return $k;
	}
}

function format_long_duration($seconds, $precision = 6) {
	$tz = date_default_timezone_get();
	date_default_timezone_set('UTC');
	list($y, $m, $d, $h, $i, $s) = explode('-', date('Y-m-d-H-i-s', 0));
	list($Y, $M, $D, $H, $I, $S) = explode('-', date('Y-m-d-H-i-s', $seconds));
	date_default_timezone_set($tz);

	$years = $Y - $y;
	$months = $M - $m;
	$days = $D - $d;
	$hours = $H - $h;
	$minutes = $I - $i;
	$seconds = $S - $s;

	$out = array(
		'year' => $years,
		'month' => $months,
		'day' => $days,
		'hour' => $hours,
		'minute' => $minutes,
		'second' => $seconds,
	);
	foreach($out as $k => $v) {
		if($v == 0) unset($out[$k]);
		else if($v === 1) $out[$k] = $v.' '.$k;
		else $out[$k] = $v.' '.$k.'s';
	}
	$out = array_slice($out, 0, $precision);

	$c = count($out);
	if($c === 0) {
		return 'less than 1 '.$k;
	}
	if($c >= 2) {
		$s = array_pop($out);
		$m = array_pop($out);
		$out[] = $m.' and '.$s;
	}

	return implode(', ', $out);
}

function format_relative_date($date, $now = null) {
	if($now === null) $now = time();
	$before = "<time datetime='".date('c', $date)."'>";
	$after = '</time>';

	if($date > $now || $date < ($now - 2 * 86400)) {
		return $before.date('Y-m-d', $date).$after;
	}

	$duration = $now - $date;

	if($duration < 2) return $before."less than a second ago".$after;

	$s = $duration % 60;
	$m = (($duration - $s) / 60) % 60;
	$h = (($duration - $s - 60 * $m) / 3600) % 24;
	$d = ($duration - $s - 60 * $m - 3600 * $h) / 86400;

	$a = array_filter(array(
		                  'd' => $d,
		                  'h' => $h,
		                  'm' => $m,
		                  's' => $s
		                  ));

	$ret = array();
	foreach($a as $k => $v) {
		$ret[] = $v.$k;
	}

	return $before.implode(' ', array_slice($ret, 0, 2)).' ago'.$after;
}

/**
 * Format the capacitor stability percentage or the time it lasts.
 *
 * @param $array array that has the same structure than the return
 * value of get_capacitor_stability().
 */
function format_capacitor($arr) {
	$rate = round(-$arr['delta'] * 1000, 1).' GJ/s';
	if($rate > 0) $rate = '+'.((string)$rate);

	if($arr['stable']) {
		return array(round(100 * $arr['stable_fraction'], 1)."%", $rate);
	} else {
		return array(format_duration($arr['depletion_time'] / 1000), $rate);
	}
}

/**
 * Format a resonance by displaying it as a resistance percentage.
 */
function format_resonance($resonance) {
	if($resonance < 0) return '100%';
	if($resonance > 1) return '0%';

	$percent = (1 - $resonance) * 100;

	return "<div>"
		.number_format($percent, 1)."%"
		.format_small_progress_bar($percent, false, false, 0)
		."</div>";
}

function format_integer($i, $exact = true) {
	if($exact) {
		return number_format($i);
	}

	if($i >= 1e9) {
		return number_format($i / 1e9, 3).'b';
	}

	if($i >= 1e6) {
		return number_format($i / 1e6, 3).'m';
	}

	if($i >= 1e4) {
		return number_format($i / 1e3, 2).'k';
	}

	return number_format($i);
}

function format_reputation($rep) {
	if($rep <= 0) $rep = 0;
	$rep = format_integer($rep, false);
	return "<span class='reputation' title='reputation'>{$rep}</span>";
}

/**
 * Get nickname or character name of current user.
 */
function get_name($a, &$rawname) {
	$name = $rawname = 'Anonymous';

	if(isset($a['accountid']) && $a['accountid'] > 0) {
		if(isset($a['apiverified']) && $a['apiverified'] === 't' &&
		   isset($a['characterid']) && $a['characterid'] > 0) {
			$name = '<span class="apiverified">'.escape($rawname = $a['charactername']).'</span>';
		} else {
			$name = '<span class="normalaccount">'.escape($rawname = $a['nickname']).'</span>';
		}
	}

	return $name;
}

/**
 * Format a character name (with a link to the profile).
 */
function format_character_name($a, $relative = '.', &$rawname = null) {
	$name = get_name($a, $rawname);
	$name = \Osmium\Flag\maybe_add_moderator_symbol($a, $name);
	return maybe_add_profile_link($a, $relative, $name);
}

function maybe_add_profile_link($a, $relative, $name) {
	if(isset($a['accountid'])) {
		return "<a class='profile' href='$relative/profile/".$a['accountid']."'>$name</a>";
	} else {
		return $name;
	}
}

/**
 * Format a optimal/falloff to give a short result (like 5+3.2k).
 *
 * @param An array containing ranges, using the format generated by
 * get_module_interesting_attributes().
 */
function format_short_range($ranges) {
	if(isset($ranges['maxrange'])) {
		$max = round($ranges['maxrange'] / 1000, $ranges['maxrange'] >= 10000 ? 0 : 1);
		return '≤'.$max.'k';
	} else if(isset($ranges['range'])) {
		$optimal = round($ranges['range'] / 1000, $ranges['range'] >= 10000 ? 0 : 1);

		if(isset($ranges['falloff'])) {
			$falloff = '+'.round($ranges['falloff'] / 1000, $ranges['falloff'] >= 10000 ? 0 : 1);
		} else {
			$falloff = '';
		}

		return $optimal.$falloff.'k';
	}

	return '';
}

/**
 * Format a optimal/falloff/tracking speed.
 *
 * @param An array containing ranges, using the format generated by
 * get_module_interesting_attributes().
 */
function format_long_range($ranges) {
	$r = array();

	if(isset($ranges['range'])) {
		$r[] = (isset($ranges['falloff']) ? "Optimal" : "Range").": ".format_range($ranges['range']);
	}

	if(isset($ranges['falloff'])) {
		$r[] = "Falloff: ".format_range($ranges['falloff']);
	}

	if(isset($ranges['trackingspeed'])) {
		$r[] = "Tracking: ".round_sd($ranges['trackingspeed'], 2)." rad/s";
	}

	if(isset($ranges['maxrange'])) {
		$r[] = "Maximum range: ".format_range($ranges['maxrange']);
	}

	return implode(";\n", $r);
}

/** Format a range in meters. */
function format_range($meters, $shortfmt = false) {
	if($shortfmt) {
		return round($meters / 1000, 1).'k';
	} else {
		if($meters >= 10000) {
			return round($meters / 1000, 1).' km';
		} else {
			return round($meters).' m';
		}
	}
}

/**
 * Round a number with a fixed number of significant digits.
 */
function round_sd($number, $digits = 0) {
	if($number == 0) return 0;
	else {
		$normalized = $number / ($m = pow(10, floor(log10($number))));
	}

	$normalized = number_format($normalized, $digits);

	return $normalized * $m;
}

/**
 * Generate pagination links and get the offset of the current page.
 *
 * @param $name name of the $_GET parameter
 * @param $perpage number of elements per page
 * @param $total total number of elements
 * @param $result where the pagination links are stored
 * @param $metaresult where the pagination info is stored
 * @param $pageoverride force a certain page number instead of $_GET default
 * @param $format format of $metaresult; %1, %2 and %3 will be replaced
 * @param $anchor optional anchor to append to the generated link URIs
 *
 * @return offset of the current page
 */
function paginate($name, $perpage, $total, &$result, &$metaresult,
                  $pageoverride = null, $format = 'Showing rows %1-%2 of %3.',
                  $anchor = '') {
	if($pageoverride !== null) {
		$page = $pageoverride;
	} else if(isset($_GET[$name])) {
		$page = intval($_GET[$name]);
	} else {
		$page = 1;
	}

	$maxpage = max(1, ceil($total / $perpage));

	if($page < 1) $page = 1;
	if($page > $maxpage) $page = $maxpage;

	$offset = ($page - 1) * $perpage;
	$max = min($total, $offset + $perpage);

	$replacement = ($total > 0) ? array($offset + 1, $max, $total) : array(0, 0, 0);
	$metaresult = "<p class='pagination'>\n";
	$metaresult .= str_replace(array('%1', '%2', '%3'), $replacement, $format);
	$metaresult .= "\n</p>\n";

	if($maxpage == 1) {
		$result = '';
		return $offset;
	}

	$r ="<ol class='pagination'>\n";

	$inf = max(1, $page - 5);
	$sup = min($maxpage, $page + 4);
	$p = $_GET;

	if($page > 1) {
		$p[$name] = $page - 1;
		$q = http_build_query($p, '', '&amp;');
		$r .= "<li value='".($page - 1)."'><a title='go to previous page' href='?$q$anchor'>Previous</a></li>\n";
	} else {
		$r .= "<li class='dummy' value='".($page - 1)."'><span>Previous</span></li>\n";
	}

	for($i = $inf; $i <= $sup; ++$i) {
		if($i != $page) {
			$p[$name] = $i;
			$q = http_build_query($p, '', '&amp;');
			$r .= "<li value='$i'><a title='go to page $i' href='?$q$anchor'>$i</a></li>\n";
		} else {
			$r .= "<li class='current' value='$i'><span>$i</span></li>\n";
		}
	}

	if($page < $maxpage) {
		$p[$name] = $page + 1;
		$q = http_build_query($p, '', '&amp;');
		$r .= "<li value='".($page + 1)."'><a title='go to next page' href='?$q$anchor'>Next</a></li>\n";
	} else {
		$r .= "<li class='dummy' value='".($page + 1)."'><span>Next</span></li>\n";
	}

	$r .= "</ol>\n";

	$result = $r;
	return $offset;
}

function sanitize_html_trust($html) {
	static $purifier = null;
	require_once 'HTMLPurifier.includes.php';
	require_once 'HTMLPurifier.auto.php';

	if($purifier === null) {
		$config = \HTMLPurifier_Config::createDefault();
		$config->set('Cache.SerializerPath', \Osmium\CACHE_DIRECTORY);
		$purifier = new \HTMLPurifier($config);
	}

	return $purifier->purify($html);
}

function sanitize_html($html) {
	static $purifier = null;
	require_once 'HTMLPurifier.includes.php';
	require_once 'HTMLPurifier.auto.php';

	if($purifier === null) {
		$config = \HTMLPurifier_Config::createDefault();

		$config->set('Cache.SerializerPath', \Osmium\CACHE_DIRECTORY);
		$config->set('HTML.DefinitionID', 'Osmium-full');
		$config->set('HTML.DefinitionRev', 1);
		$config->set('HTML.Doctype', 'XHTML 1.1');

		$config->set('Attr.AllowedClasses', array());
		$config->set('HTML.Nofollow', true);
		$config->set('CSS.AllowedProperties', array());
		$config->set('AutoFormat.Linkify', true);

		$purifier = new \HTMLPurifier($config);
	}

	return $purifier->purify($html);
}

function sanitize_html_phrasing($html) {
	static $purifier = null;
	require_once 'HTMLPurifier.includes.php';
	require_once 'HTMLPurifier.auto.php';

	if($purifier === null) {
		$config = \HTMLPurifier_Config::createDefault();

		$config->set('Cache.SerializerPath', \Osmium\CACHE_DIRECTORY);
		$config->set('HTML.DefinitionID', 'Osmium-phrasing');
		$config->set('HTML.DefinitionRev', 1);
		$config->set('HTML.Doctype', 'XHTML 1.1');

		$config->set('Attr.AllowedClasses', array());
		$config->set('HTML.Nofollow', true);
		$config->set('CSS.AllowedProperties', array());
		$config->set('HTML.AllowedElements', 'a, abbr, b, cite, code, del, em, i, ins, kbd, q, s, samp, small, span, strong, sub, sup');
		$config->set('AutoFormat.Linkify', true);

		$purifier = new \HTMLPurifier($config);
	}

	return $purifier->purify($html);
}

function format_md($markdowntext) {
	require_once \Osmium\ROOT.'/lib/markdown.php';
    return \Markdown($markdowntext);
}

function format_sanitize_md($markdowntext) {
	return sanitize_html(format_md($markdowntext));
}

function format_sanitize_md_phrasing($markdowntext) {
	return sanitize_html_phrasing(format_md($markdowntext));
}

function format_isk($isk, $withunit = true) {
	if($isk >= 10000000000) {
		$isk = round($isk / 1000000000, 2).'b';
	} else if($isk >= 1000000000) {
		$isk = round($isk / 1000000000, 3).'b';
	} else if($isk > 100000000) {
		$isk = round($isk / 1000000, 1).'m';
	} else if($isk > 10000000) {
		$isk = round($isk / 1000000, 2).'m';
	} else if($isk > 1000000) {
		$isk = round($isk / 1000000, 3).'m';
	} else {
		$isk = round($isk / 1000, 1).'k';
	}

	return $isk.($withunit ? ' ISK' : '');
}

function truncate_string($s, $length, $fill = '…') {
	if(mb_strlen($s) > $length) {
		$s = mb_substr($s, 0, $length - mb_strlen($fill)).$fill;
	}

	return $s;
}

function format_number_with_unit($number, $unitid, $unitdisplayname) {
	switch((int)$unitid) {

	case 1: /* Meters */
		if($number >= 10000) {
			$number /= 1000;
			$unitdisplayname = 'km';
		} else {
			$unitdisplayname = 'm';
		}
		break;

	case 101: /* Milliseconds */
		$number /= 1000;
		$unitdisplayname = 's';
		break;

	case 104: /* Multiplier */
		$unitdisplayname = 'x';
		break;

	case 108: /* Inverse absolute percent */
		$number = (1 - $number) * 100;

	case 105: /* Percentage */
	case 121: /* Real percentage */
		$unitdisplayname = '%';
		break;

	case 111: /* Inversed modifier percent */
		$number = 2 - $number;

	case 109: /* Modifier percent */
		$number -= 1;
		$number *= 100;

	case 124: /* Modifier relative percent */
		$unitdisplayname = '%';
		$rounded = ($number >= 0 ? '+' : '').$number;
		break;

	case 127: /* Absolute percent */
		$unitdisplayname = '%';
		$number *= 100;
		break;

	case 112: /* Rotation speed */
		$rounded = round_sd($number, 4);
		break;

	case 115: /* Group ID */
		$row = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
			'SELECT groupname, typeid FROM eve.invgroups
			LEFT JOIN eve.invtypes
			ON invtypes.groupid = invgroups.groupid AND invtypes.published = true
			WHERE invgroups.groupid = $1 ORDER BY typeid ASC
			LIMIT 1',
			array($number)
		));
		if($row !== false) {
			$image = '';
			if($row[1] !== null) {
				$image = "<img src='//image.eveonline.com/Type/".$row[1]."_64.png' alt='' /> ";
			}
			return $image.escape($row[0]);
		}
		$unitdisplayname = 'Group ID';
		break;

	case 116: /* Type ID */
		$typename = \Osmium\Fit\get_typename($number);
		if($typename !== false) {
			return "<img src='//image.eveonline.com/Type/".$number."_64.png' alt='' /> "
				.escape($typename);
		}
		$unitdisplayname = 'Type ID';
		break;

	case 117: /* Size class */
		if($number == 1) return 'Small';
		if($number == 2) return 'Medium';
		if($number == 3) return 'Large';
		if($number == 4) return 'XLarge';
		break;

	case 137: /* Boolean */
		if($number == 0) return 'False';
		if($number == 1) return 'True';
		break;

	case 139: /* Bonus */
		$rounded = (($number >= 0) ? '+' : '').$number;
		break;

	case 140: /* Level */
		return 'Level '.$number;

	case 142: /* Sex */
		if($number == 1) return 'Male';
		if($number == 2) return 'Unisex';
		if($number == 3) return 'Female';
		break;

	}

	if(!isset($rounded)) {
		$n = number_format($number, 3);
		list($rounded, $dec) = explode('.', $n);
		$dec = rtrim($dec, '0');
		if($dec) $rounded .= '.'.$dec;
	}

	return '<span title="'.sprintf("%.14f", $number).'">'
		.$rounded.' '.escape($unitdisplayname)
		.'</span>';
}

function sprite($relative, $alt, $grid_x, $grid_y, $grid_width, $grid_height = null, $width = null, $height = null) {
	if($grid_height === null) $grid_height = $grid_width;
	if($width === null) $width = $grid_width;
	if($height === null) $height = $width;

	$posx = $grid_x * $width;
	$posy = $grid_y * $height;
	$imgwidth = $width / $grid_width * 1024;
	$imgheight = $height / $grid_height * 1024;

	$alt = escape($alt);

	return "<span class='mainsprite' style='width: {$width}px; height: {$height}px;'><img src='{$relative}/static-".\Osmium\STATICVER."/icons/sprite.png' alt='{$alt}' title='{$alt}' style='width: {$imgwidth}px; height: {$imgheight}px; top: -{$posx}px; left: -{$posy}px;' /></span>";
}

/** Format a skill level in roman numerals. */
function format_skill_level($level) {
	static $levels = array(
		null => 'Untrained',
		0 => '0',
		1 => 'I',
		2 => 'II',
		3 => 'III',
		4 => 'IV',
		5 => 'V',
	);

	return isset($levels[$level]) ? $levels[$level] : 'Unknown';
}
