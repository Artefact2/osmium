<?php
/* Osmium
 * Copyright (C) 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Page\ViewPrivileges;

require __DIR__.'/../inc/root.php';

\Osmium\Chrome\print_header('Privileges', '.');

$anonymous = !\Osmium\State\is_logged_in();
$myrep = \Osmium\Reputation\get_current_reputation();
$bs = \Osmium\get_ini_setting('bootstrap_mode');

echo "<div id='vprivileges'>\n";

echo "<section id='repguide'>\n<h2>What is reputation?</h2>\n";

echo "<p>
Reputation points are a way to roughly measure how much the community trusts you.
The more points you have, the more privileges you unlock.
</p>
<p>
Most privileges do not apply to private or hidden loadouts.
</p>\n";

echo "<h3>How do I get reputation?</h3>\n";

$changes = \Osmium\Reputation\get_updown_vote_reputation();
$up = $changes[\Osmium\Reputation\VOTE_TYPE_UP];
$down = $changes[\Osmium\Reputation\VOTE_TYPE_DOWN];

function formatquantities(array $deltas, $type) {
	$return = [];

	list($destdelta, $srcdelta) = $deltas[$type];

	if($destdelta !== 0) {
		$unit = ' point'.(abs($destdelta) != 1 ? 's' : '');
		if($destdelta > 0) $destdelta = '+'.$destdelta;
		$return[] = $destdelta.$unit;
	}
	if($srcdelta !== 0) {
		$unit = ' point'.(abs($srcdelta) != 1 ? 's' : '');
		if($srcdelta > 0) $srcdelta = '+'.$srcdelta;
		$return[] = $srcdelta.$unit.' for the voter';
	}

	return $return === [] ? '' : ' <small>('.implode('; ', $return).')</small>';
}

echo "<p>
You get reputation points when…
</p>
<ul>
<li>Someone upvotes one of your public lodaouts".formatquantities($up, \Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT).";</li>
<li>Someone upvotes one of your comments on a public loadout".formatquantities($up, \Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT).".</li>
</ul>
<p>
If you submit quality content, reputation points will come naturally. Similarly, upvote content when you believe it deserves it.
</p>
<h3>Can I lose points?</h3>
<p>
It is possible to lose reputation, when…
</p>
<ul>
<li>Someone downvotes one of your public lodaouts".formatquantities($down, \Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT).";</li>
<li>Someone downvotes one of your comments on a public loadout".formatquantities($down, \Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT).".</li>
</ul>
<h3>So votes are only useful for reputation?</h3>
<p>
Not only. The amount of votes is used to determine a <a href='http://www.evanmiller.org/how-not-to-sort-by-average-rating.html'>score</a> which is used to sort results. Entries with a very bad score may not appear in search results at all.
</p>\n";

if($anonymous) {
	echo "<p>\nYou need to be logged in to gain reputation points and earn privileges.</p>\n";
} else {
	echo "<p>\nYou currently have <strong class='reptotal'>".\Osmium\Chrome\format_integer($myrep)."</strong> reputation points.</p>\n";
}

echo "</section>\n";

echo "<section id='privlist'>\n<h2>Available privileges</h2>\n";

if($bs) {
	echo "<p class='notice_box'><strong>The site is currently in bootstrap mode.</strong><br />Some privilege requirements may be lowered in bootstrap mode. <br />When this happens, the real requirements will be shown in parentheses.</p>\n";
}

echo "<ol>";

foreach(\Osmium\Reputation\get_privileges() as $p => $d) {
	$name = $d['name'];
	$rep_needed = $d['req'][0];
	$rep_needed_bs = $d['req'][1];
	$desc = $d['desc'];

	$needed = $bs ? $rep_needed_bs : $rep_needed;
	$progress = round(min(1, $myrep / $needed) * 100, 2);

	echo "<li id='p{$p}' class='".($myrep >= $needed ? 'haveit' : ($anonymous ? '' : 'donthaveit'))."'>\n";
	echo "<h2>".\Osmium\Chrome\escape($name)." <span>";

	if($myrep >= $needed) {
		echo "got it!";
		if($myrep < $rep_needed) {	
			echo " <small>(".\Osmium\Chrome\format_integer($myrep)
				." / ".\Osmium\Chrome\format_integer($rep_needed).")</small>";
		}
	} else {
		echo \Osmium\Chrome\format_integer($myrep)
			." / ".\Osmium\Chrome\format_integer($bs ? $rep_needed_bs : $rep_needed);

		if($bs && $rep_needed > $rep_needed_bs) {
			echo " <small>(".\Osmium\Chrome\format_integer($myrep)
				." / ".\Osmium\Chrome\format_integer($rep_needed).")</small>";
		}
	}
	echo "</span></h2>\n";
	echo "<div class='progress'><div class='pinner' style='width: {$progress}%;'> </div></div>\n";
	echo "<div class='desc'>\n{$desc}</div>\n";
	echo "</li>\n";
}

echo "</ol>\n";

echo "</section>\n</div>\n";
\Osmium\Chrome\print_js_snippet('view_privileges');
\Osmium\Chrome\print_footer();
