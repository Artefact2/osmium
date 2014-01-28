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

namespace Osmium\Page\ViewProfile;

require __DIR__.'/../inc/root.php';

if(!isset($_GET['accountid'])) {
	\Osmium\fatal(404);
}

$row = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT accountid, creationdate, lastlogindate, apiverified, nickname, characterid, charactername, corporationid, corporationname, allianceid, alliancename, ismoderator, flagweight, reputation FROM osmium.accounts WHERE accountid = $1', array($_GET['accountid'])));

if($row === false) {
	\Osmium\fatal(404);
}

$a = \Osmium\State\get_state('a', array());
$myprofile = \Osmium\State\is_logged_in() && $a['accountid'] == $_GET['accountid'];
$ismoderator = isset($a['ismoderator']) && $a['ismoderator'] === 't';

$name = \Osmium\Chrome\get_name($row, $rname);
\Osmium\Chrome\print_header(\Osmium\Chrome\escape($rname)."'s profile", '..');

echo "<div id='vprofile'>\n";
echo "<header>\n";
$sep = "<tr class='sep'><td colspan='3'> </td></tr>\n";

$moderator = ($row['ismoderator'] === 't') ? 'Moderator '.\Osmium\Flag\MODERATOR_SYMBOL : '';
$isthisme = $myprofile ? '(this is you!)' : '';
echo "<h2>".$name." <small>$isthisme $moderator</small></h2>\n";

if($row['apiverified'] === 't') {
	$allianceid = (($row['allianceid'] == null) ? 1 : $row['allianceid']);
	$alliancename = ($allianceid === 1) ? '(no alliance)' : $row['alliancename'];

	echo "<p>\n"
		."<a href='../search?q=".urlencode("@restrictedtoaccountid > 0")."'>"
		."<img src='//image.eveonline.com/Character/".$row['characterid']."_512.jpg' alt='portrait' />"
		."</a><br />\n"
		."<a href='../search?q=".urlencode("@restrictedtocorporationid > 0")."'>"
		."<img src='//image.eveonline.com/Corporation/".$row['corporationid']."_256.png'"
		." alt='corporation logo' title='".\Osmium\Chrome\escape($row['corporationname'])."' /></a>"
		."<a href='../search?q=".urlencode("@restrictedtoallianceid > 0")."'>"
		."<img src='//image.eveonline.com/Alliance/".$allianceid."_128.png'"
		." alt='alliance logo' title='".\Osmium\Chrome\escape($alliancename)."' /></a></p>\n";
}

echo "<table>\n<tbody>\n";

if($row['apiverified'] === 't') {
	echo "<tr>\n<th rowspan='2'>character</th>\n<td>corporation</td>\n<td>".\Osmium\Chrome\escape($row['corporationname'])."</td>\n</tr>\n<tr>\n<td>alliance</td>\n<td>".\Osmium\Chrome\escape($alliancename)."</td>\n</tr>\n";
	echo $sep;
}

echo "<tr>\n<th rowspan='2'>visits</th>\n<td>member for</td>\n<td>".\Osmium\Chrome\format_long_duration(time() - $row['creationdate'], 2)."</td>\n</tr>\n<tr>\n<td>last seen</td>\n<td>".\Osmium\Chrome\format_long_duration(time() - $row['lastlogindate'], 2)." ago</td>\n</tr>\n";

echo $sep;

echo "<tr>\n<th rowspan='2'>meta</th>\n<td>api key verified</td>\n<td>".(($row['apiverified'] === 't') ? 'yes' : 'no')."</td>\n</tr>\n";
echo "<tr>\n<td>reputation score</td>\n<td>".number_format($row['reputation']);
if($myprofile) {
	echo " <a href='../privileges'>(check my privileges)</a>";
}
echo "</td>\n</tr>\n";

if($myprofile || $ismoderator) {
	echo $sep;
	echo "<tr>\n<th>private</th>\n<td>flag weight</td>\n<td>".$row['flagweight']." <a href='../flagginghistory/".$row['accountid']."'>(see flagging history)</a></td>\n</tr>\n";
}

echo "</tbody>\n</table>\n</header>\n";

echo "<ul class='tabs'>\n";
if($myprofile) {
	echo "<li><a href='#pfavorites'>Favorites</a></li>\n";
	echo "<li><a href='#phidden'>Hidden</a></li>\n";
}
echo "<li><a href='#ploadouts'>Recent</a></li>\n";
echo "<li><a href='#reputation'>Reputation</a></li>\n";
echo "<li><a href='#votes'>Votes</a></li>\n";
//echo "<li><a href='#comment'>Comments</a></li>\n";
echo "</ul>\n";





echo "<section id='ploadouts' class='psection'>\n";
echo "<h2>Loadouts recently submitted <small><a href=\"../search?q=".urlencode('@author "'.\Osmium\Chrome\escape($rname).'"')."\">(browse all)</a></small></h2>\n";
\Osmium\Search\print_pretty_results("..", '@author "'.$rname.'"', 'ORDER BY creationdate DESC', false, 20, 'p', \Osmium\Chrome\escape($rname).' does not have submitted any loadouts.');
echo "</section>\n";





if($myprofile) {
	$a = \Osmium\State\get_state('a');
	/* TODO: paginate this */

	echo "<section id='pfavorites' class='psection'>\n<h2>My favorite loadouts</h2>\n";
	$favorites = array();
	$stale = array();
	$favq = \Osmium\Db\query_params(
		'SELECT af.loadoutid, al.loadoutid FROM osmium.accountfavorites af
		LEFT JOIN osmium.allowedloadoutsbyaccount al ON al.loadoutid = af.loadoutid AND al.accountid = $1
		WHERE af.accountid = $1
		ORDER BY af.favoritedate DESC',
		array($a['accountid'])
	);
	while($r = \Osmium\Db\fetch_row($favq)) {
		if($r[0] === $r[1]) {
			$favorites[] = $r[0];
		} else {
			$stale[] = $r[0];
		}
	}

	if(count($stale) > 0) {
		echo "<p>These following loadouts you added as favorites are no longer accessible to you:</p>\n<ol>\n";

		foreach($stale as $id) {
			echo "<li>Loadout <a href='../loadout/{$id}'>#{$id}</a>"
				." — <a href='../favorite/{$id}?tok=".\Osmium\State\get_token()
				."&amp;redirect=profile'>unfavorite</a></li>\n";
		}

		echo "</ol>\n";
	}

	\Osmium\Search\print_loadout_list($favorites, '..', 0, 'You have no favorited loadouts.');
	echo "</section>\n";

	echo "<section id='phidden' class='psection'>\n<h2>My hidden loadouts</h2>\n";
	$hidden = array();
	$hiddenq = \Osmium\Db\query_params('SELECT loadoutid FROM osmium.loadouts WHERE accountid = $1 AND visibility = $2 ORDER BY loadoutid DESC', array($a['accountid'], \Osmium\Fit\VISIBILITY_PRIVATE));
	while($r = \Osmium\Db\fetch_row($hiddenq)) {
		$hidden[] = $r[0];
	}
	\Osmium\Search\print_loadout_list($hidden, '..', 0, 'You have no hidden loadouts.');	
	echo "</section>\n";
}





echo "<section id='reputation' class='psection'>\n";
echo "<h2>Reputation changes this month <small>".\Osmium\Chrome\format_reputation($row['reputation'])." reputation</small></h2>\n";

$votetypes = \Osmium\Reputation\get_vote_types();
$repchangesq = \Osmium\Db\query_params(
	'SELECT v.creationdate, reputationgiventodest, type, targettype, targetid1, targetid2, targetid3,
		sl.loadoutid, f.name
	FROM osmium.votes AS v
	LEFT JOIN osmium.searchableloadouts AS sl ON ((v.targettype = $3 AND v.targetid1 = sl.loadoutid
		AND v.targetid2 IS NULL AND v.targetid3 IS NULL)
		OR (v.targettype = $4 AND v.targetid2 = sl.loadoutid AND v.targetid3 IS NULL))
	LEFT JOIN osmium.loadoutslatestrevision AS llr ON llr.loadoutid = sl.loadoutid
	LEFT JOIN osmium.loadouthistory AS lh ON lh.loadoutid = sl.loadoutid AND lh.revision = llr.latestrevision
	LEFT JOIN osmium.fittings AS f ON f.fittinghash = lh.fittinghash
	WHERE v.accountid = $1 AND v.creationdate >= $2 AND reputationgiventodest <> 0
	ORDER BY creationdate DESC',
	array($_GET['accountid'],
	      time() - 86400 * 365.25 / 12,
	      \Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT,
	      \Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
		)
	);
echo "<ul>\n";
$lastday = null;
$first = true;
$data = array();

function print_target($d) {
	if($d['targettype'] == \Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT) {
		if($d['name'] !== null) {
			echo "<a href='../loadout/".$d['loadoutid']."'>".\Osmium\Chrome\escape($d['name'])."</a>";
		} else {
			echo "<small>Private/hidden loadout</small>";
		}
	} else if($d['targettype'] == \Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT) {
		if($d['name'] !== null) {
			echo "Comment <a href='../loadout/".$d['loadoutid']."?jtc=".$d['targetid1']."#c".$d['targetid1']."'>#".$d['targetid1']."</a> on <a href='../loadout/".$d['loadoutid']."'>".\Osmium\Chrome\escape($d['name'])."</a>";
		} else {
			echo "<small>Comment on a private/hidden loadout</small>";
		}
	}
}

function print_reputation_day($day, $data) {
	global $votetypes;

	$net = 0;
	foreach($data as $d) $net += $d['reputationgiventodest'];
	$class = ($net >= 0) ? 'positive' : 'negative';

	echo "<li>\n<h4>$day <span class='$class'>$net</span></h4>\n";
	echo "<table class='d'>\n<tbody>\n";

	foreach($data as $d) {
		echo "<tr>\n";
		$rep = $d['reputationgiventodest'];
		$class = ($rep >= 0) ? 'positive' : 'negative';
		if($rep > 0) $rep = '+'.$rep;
		echo "<td class='rep $class'>$rep</td>\n";

		$time = date('H:i', $d['creationdate']);
		echo "<td class='time'>$time</td>\n";

		$type = $votetypes[$d['type']];
		echo "<td class='type'>$type</td>\n";

		echo "<td class='l'>";
		print_target($d);
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "</tbody>\n</table>\n</li>\n";
}

while($r = \Osmium\Db\fetch_assoc($repchangesq)) {
	$day = date('Y-m-d', $r['creationdate']);
	if($lastday !== $day) {
		if($first) $first = false;
		else {
			print_reputation_day($lastday, $data);
		}

		$lastday = $day;
		$data = array();
	}

	$data[] = $r;
}
if(!$first) print_reputation_day($day, $data);
echo "</ul>\n";
if($first) echo "<p class='placeholder'>No reputation changes this month.</p>\n";
echo "</section>\n";



$__time = microtime(true);

echo "<section id='votes' class='psection'>\n";

list($total) = \Osmium\Db\fetch_row(
	\Osmium\Db\query_params(
		'SELECT COUNT(voteid) FROM osmium.votes WHERE fromaccountid = $1',
		array($_GET['accountid'])
		));
$offset = \Osmium\Chrome\paginate('vp', 25, $total, $result, $metaresult, null, '', '#votes');

echo "<h2>Votes cast <small>".number_format($total)." votes cast</small></h2>\n";
echo $result;

$votesq = \Osmium\Db\query_params(
	'SELECT v.creationdate, type, targettype, targetid1, targetid2, targetid3, sl.loadoutid, f.name
	FROM osmium.votes AS v
	LEFT JOIN osmium.searchableloadouts AS sl ON sl.accountid IN (0, $5) AND (
		((v.targettype = $2 AND v.targetid1 = sl.loadoutid
		AND v.targetid2 IS NULL AND v.targetid3 IS NULL)
		OR (v.targettype = $3 AND v.targetid2 = sl.loadoutid AND v.targetid3 IS NULL))
	)
	LEFT JOIN osmium.loadoutslatestrevision AS llr ON llr.loadoutid = sl.loadoutid
	LEFT JOIN osmium.loadouthistory AS lh ON lh.loadoutid = sl.loadoutid AND lh.revision = llr.latestrevision
	LEFT JOIN osmium.fittings AS f ON f.fittinghash = lh.fittinghash
	WHERE fromaccountid = $1 ORDER BY v.creationdate DESC LIMIT 25 OFFSET $4',
	array(
		$_GET['accountid'],
		\Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT,
		\Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
		$offset,
		isset($a['accountid']) ? $a['accountid'] : 0,
	)
);
echo "<table class='d'>\n<tbody>\n";
$first = true;
while($v = \Osmium\Db\fetch_assoc($votesq)) {
	$first = false;

	echo "<tr>\n";

	echo "<td class='date'>".\Osmium\Chrome\format_relative_date($v['creationdate'])."</td>\n";
	echo "<td class='type'>".$votetypes[$v['type']]."</td>\n";

	echo "<td class='l'>";
	print_target($v);
	echo "</td>\n";

	echo "</tr>\n";
}
echo "</tbody>\n</table>\n";
if($first) echo "<p class='placeholder'>No votes cast.</p>\n";
echo "</section>\n";





echo "</div>\n";
\Osmium\Chrome\add_js_data('defaulttab', ($myprofile ? 2 : 0));
\Osmium\Chrome\print_js_snippet('tabs');
\Osmium\Chrome\print_js_snippet('view_profile');
\Osmium\Chrome\print_footer();
