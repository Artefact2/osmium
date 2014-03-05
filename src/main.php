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

namespace Osmium\Page\Main;

require __DIR__.'/../inc/root.php';

function print_estimated_count($table, $single, $plural) {
	$n = (int)\Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT reltuples FROM pg_class WHERE relname = $1',
		array($table)
	))[0];
	echo "<li><strong>"
		.\Osmium\Chrome\format_integer($n)
		."</strong> ".($n === 1 ? $single : $plural)."</li>\n";
}

function get_cache_memory_or_gen($key, $ttl, $genfunc, $prefix = 'Metrics_') {
	$val = \Osmium\State\get_cache_memory($key, null, $prefix);
	if($val === null) {
		$sem = \Osmium\State\semaphore_acquire($prefix.$key);
		if($sem === false) return false;

		$val = \Osmium\State\get_cache_memory($key, null, $prefix);
		if($val === null) {
			$val = $genfunc();
			\Osmium\State\put_cache_memory($key, $val, $ttl, $prefix);
		}
		\Osmium\State\semaphore_release($sem);
	}

	return $val;
}

function print_cached_count($getcount, $key, $ttl, $single, $plural) {
	$val = get_cache_memory_or_gen($key, $ttl, $getcount);
	if($val === false) return;

	echo "<li><strong>".\Osmium\Chrome\format_integer($val)
		."</strong> ".($val === 1 ? $single : $plural)."</li>\n";
}

function print_cached_string($getstring, $key, $ttl, $label) {
	$val = get_cache_memory_or_gen($key, $ttl, $getstring);
	if($val === false) return;

	echo "<li><strong>".$val."</strong> ".$label."</li>\n";
}

\Osmium\Chrome\print_header(
	'', '.', true,
	"<link href='./atom/newfits.xml' type='application/atom+xml' rel='alternate' title='New fits' />\n<link href='./atom/recentlyupdated.xml' type='application/atom+xml' rel='alternate' title='Recently updated' />\n");

echo "<h1 id='mainp'>".\Osmium\get_ini_setting('name')." — ".\Osmium\get_ini_setting('description')."</h1>\n";

echo "<div class='mainpcont quick' id='search_mini'>\n";
\Osmium\Search\print_search_form('./search');
echo "</div>\n";

$a = \Osmium\State\get_state('a', null);
$now = time();
$accountid = $a === null ? 0 : $a['accountid'];





echo "<div class='mainpcont'>\n<section class='metrics'>\n";
echo "<h2>Quick stats</h2>\n<ul>\n";

print_estimated_count('loadouts', 'loadout', 'total loadouts');
print_estimated_count('fittings', 'fitting', 'distinct fittings');
print_estimated_count('fittingmodules', 'fitted module', 'total fitted modules');

print_cached_count(function() {
	return \Osmium\State\count_cache_entries([ 'mmin' => 10 ], 'Loadout_New_')
		+ \Osmium\State\count_memory_cache_entries(array(), 'Loadout_View_');
}, "dogma_contexts", 30, "dogma context", "dogma contexts");

echo "</ul>\n<ul>\n";

print_estimated_count('accounts', 'account', 'accounts');

print_cached_count(function() {
	return (int)\Osmium\Db\fetch_row(\Osmium\Db\query(
		'SELECT count(accountid) FROM accounts WHERE apiverified = true'
	))[0];
}, "verified_accounts", 3601, "verified account", "verified accounts");

print_cached_count(function() {
	return (int)\Osmium\Db\fetch_row(\Osmium\Db\query(
		'SELECT sum(reputation) FROM accounts'
	))[0];
}, "reputation_total", 3602, "total reputation points", "total reputation points");

print_cached_count(function() {
	return max(1, \Osmium\State\count_memory_cache_entries(array(), 'Activity_'));
}, "active_users", 10, "active user", "active users");

echo "</ul>\n";
echo "</section>\n";




echo get_cache_memory_or_gen('popular_tags', 3603, function() {
	$r = "<section class='populartags'>\n";
	$r .= "<h2>Common tags</h2>\n<ul class='tags' id='populartags'>\n";

	$query = \Osmium\Db\query(
		"SELECT tagname, count
		FROM osmium.tagcount
		ORDER BY count DESC
		LIMIT 15"
	);

	$ptags = array();
	while($row = \Osmium\Db\fetch_row($query)) {
		$r .= "<li><a href='./browse/best?q=".urlencode('@tags "'.$row[0].'"')
			."'>{$row[0]}</a> ({$row[1]})</li>\n";
	}

	$r .= "</ul>\n</section>\n";
	return $r;
}, 'Main_');





$a = \Osmium\State\get_state('a');
if(isset($a['accountid']) && $a['accountid'] > 0) {
	$q = \Osmium\Db\query_params(
		'SELECT sl.loadoutid
		FROM osmium.searchableloadouts AS sl
		JOIN osmium.loadouthistory AS lh ON lh.loadoutid = sl.loadoutid AND lh.revision = 1
		WHERE sl.accountid IN (0, $1)
		ORDER BY lh.updatedate DESC
		LIMIT 20',
		array($a['accountid'])
	);
	$ids = array(0);
	while($row = \Osmium\Db\fetch_row($q)) {
		$ids[] = (int)$row[0];
	}

	echo "<section class='newfits'>\n"
		."<h2>New fits <small><a href='./atom/newfits.xml' type='application/atom+xml'>"
		."<img src='./static-".\Osmium\STATICVER."/icons/feed.svg' alt='Atom feed' /></a></small></h2>\n";

	\Osmium\Search\print_loadout_list(
		$ids, '.', 0, 'No loadouts yet! What are you waiting for?'
	);

	echo "<p class='b_more'><a href='./browse/new'>Browse more new loadouts…</a></p>\n</section>\n";



	$vercutoff = array_values(\Osmium\Fit\get_eve_db_versions())[2]['build'];
	echo "<section class='popularfits'>\n<h2>Popular fits</h2>\n";

	$ids = \Osmium\Search\get_search_ids(
		'', 'AND build >= '.$vercutoff.' ORDER BY score DESC', 0, 20
	);

	\Osmium\Search\print_loadout_list(
		$ids, '.', 0, 'No loadouts yet! What are you waiting for?'
	);

	echo "<p class='b_more'><a href='./browse/best'>Browse more popular loadouts…</a></p>\n</section>\n";
} else {
	echo get_cache_memory_or_gen('new_fits', 601, function() {
		$q = \Osmium\Db\query(
			'SELECT sl.loadoutid
			FROM osmium.searchableloadouts AS sl
			JOIN osmium.loadouthistory AS lh ON lh.loadoutid = sl.loadoutid AND lh.revision = 1
			WHERE sl.accountid = 0
			ORDER BY lh.updatedate DESC
			LIMIT 20'
		);
		$ids = array(0);
		while($row = \Osmium\Db\fetch_row($q)) {
			$ids[] = (int)$row[0];
		}

		$r = "<section class='newfits'>\n"
			."<h2>New fits <small><a href='./atom/newfits.xml' type='application/atom+xml'>"
			."<img src='./static-".\Osmium\STATICVER."/icons/feed.svg' alt='Atom feed' /></a></small></h2>\n";

		ob_start();
		\Osmium\Search\print_loadout_list(
			$ids, '.', 0, 'No loadouts yet! What are you waiting for?'
		);
		$r .= ob_get_clean();

		$r .= "<p class='b_more'><a href='./browse/new'>Browse more new loadouts…</a></p>\n</section>\n";
		return $r;
	}, 'Main_');



	echo get_cache_memory_or_gen('popular_fits', 602, function() {
		$vercutoff = array_values(\Osmium\Fit\get_eve_db_versions())[2]['build'];
		$r = "<section class='popularfits'>\n<h2>Popular fits</h2>\n";

		ob_start();
		\Osmium\Search\print_loadout_list(
			\Osmium\Search\get_search_ids(
				'', 'AND build >= '.$vercutoff.' ORDER BY score DESC', 0, 20
			),
			'.', 0, 'No loadouts yet! What are you waiting for?'
		);
		$r .= ob_get_clean();

		$r .= "<p class='b_more'><a href='./browse/best'>Browse more popular loadouts…</a></p>\n</section>\n";
		return $r;
	}, 'Main_');
}





echo get_cache_memory_or_gen('fotw', 603, function() {
	$r = "<section class='fotw'>\n";
	$r .= "<h2>Flavors of the week <small>data from <a href='https://zkillboard.com/'>zKillboard</a></small></h2>\n";

	$topkills = \Osmium\State\get_cache('top_kills', null);
	if($topkills === null) {
		$r .= "<p class='placeholder'>Flavors of the week unavailable.</p>\n";
		return $r."</section>\n";
	}

	$r .= "<ol>\n";

	foreach($topkills['fotw'] as $f) {
		list($shipid, ) = explode(':', $f['dna'], 2);
		$fname = \Osmium\Chrome\escape(
			\Osmium\Fit\get_typename($shipid)." fitting"
			.($f['tags'] ? ": ".implode(", ", $f['tags']) : '')
		);

		$r .= "<li><a rel='nofollow' href='./loadout/dna/".$f['dna']."'>"
			."<div class='abs dogmaattrs'>\n"
			."<strong>".\Osmium\Chrome\format($f['dps'], 2)."</strong> <small>DPS</small><br />"
			."<strong>".\Osmium\Chrome\format($f['ehp'], 2, 'k')."</strong> <small>EHP</small><br />"
			."<strong>".\Osmium\Chrome\format($f['price'], 2)."</strong> <small>ISK</small>"
			."</div>"
			.\Osmium\Chrome\ship_icon($shipid)
			."<div class='absnum losscount'><span><strong>".\Osmium\Chrome\format_integer($f['count'])
			."</strong><small>lost</small></span></div>\n"
			."</a></li>\n";
	}
	$r .= "</ol>\n";

	if($topkills['fotw'] === []) {
		$r .= "<p class='placeholder'>No flavors of the week.</p>\n";
	}

	return $r."</section>\n";
}, 'Main_');





echo get_cache_memory_or_gen('doctrines', 604, function() {
	$r = "<section class='doctrines'>\n";
	$r .= "<h2>Popular alliance doctrines <small>data from <a href='https://zkillboard.com/'>zKillboard</a></small></h2>\n";

	$topkills = \Osmium\State\get_cache('top_kills', null);
	if($topkills === null) {
		$r .= "<p class='placeholder'>Alliance doctrines unavailable.</p>\n";
		return $r."</section>\n";
	}

	$r .= "<ol>\n";

	foreach($topkills['doctrine'] as $f) {
		list($shipid, ) = explode(':', $f['dna'], 2);
		$fname = \Osmium\Chrome\escape(
			\Osmium\Fit\get_typename($shipid)." fitting"
			.($f['tags'] ? ": ".implode(", ", $f['tags']) : '')
		);

		$anames = array();
		$alogos = "";
		foreach($f['alliances'] as $a) {
			list($count, $id, $name) = $a;
			$name = \Osmium\Chrome\escape($name);
			$anames[] = $name.' ('.round(100 * $count / $f['count']).' %)';
			$alogos .= "<img class='abs' src='//image.eveonline.com/Alliance/{$id}_128.png' alt='{$name}' />\n";
		}

		$aname = "mainly flown by: &#10;".implode(", &#10;", $anames);

		$r .= "<li><a rel='nofollow' href='./loadout/dna/".$f['dna']."'>"
			."<div class='abs dogmaattrs'>\n"
			."<strong>".\Osmium\Chrome\format($f['dps'], 2)."</strong> <small>DPS</small><br />"
			."<strong>".\Osmium\Chrome\format($f['ehp'], 2, 'k')."</strong> <small>EHP</small><br />"
			."<strong>".\Osmium\Chrome\format($f['price'], 2)."</strong> <small>ISK</small>"
			."</div>"
			.\Osmium\Chrome\ship_icon($shipid)
			."<div title='{$aname}' class='abs alogos n".(count($f['alliances']))."'>\n{$alogos}</div>\n"
			."<div class='absnum losscount'><span><strong>".\Osmium\Chrome\format_integer($f['count'])
			."</strong><small>lost</small></span></div>\n"
			."</a></li>\n";
	}
	$r .= "</ol>\n";

	if($topkills['doctrine'] === []) {
		$r .= "<p class='placeholder'>No alliance doctrines. Maybe there is a problem with the zKillboard API, or the cache is still filling up.</p>\n";
	}

	return $r."</section>\n";
}, 'Main_');





echo "</div>\n";
\Osmium\Chrome\print_footer();
