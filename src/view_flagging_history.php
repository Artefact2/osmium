<?php
/* Osmium
 * Copyright (C) 2012 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Page\ViewFlaggingHistory;

require __DIR__.'/../inc/root.php';

if(!isset($_GET['accountid'])) {
	\Osmium\fatal(400);
}

\Osmium\State\assume_logged_in('..');
$a = \Osmium\State\get_state('a');
$accountid = intval($_GET['accountid']);

if($a['accountid'] != $accountid && $a['ismoderator'] !== 't') {
	\Osmium\fatal(403);
}

\Osmium\Chrome\print_header('Flagging history', '..');
echo "<div id='vflaghistory'>\n";
echo "<h2>Flagging history</h2>\n";

echo "<ul id='summary'>\n";
$summaryq = \Osmium\Db\query_params('SELECT status, COUNT(flagid) AS count FROM osmium.flags WHERE flaggedbyaccountid = $1 GROUP BY status ORDER BY status ASC', array($accountid));
$total = 0;
$statuses = \Osmium\Flag\get_flag_statuses();
while($row = \Osmium\Db\fetch_row($summaryq)) {
	echo '<li>'.$row[1].' '.$statuses[$row[0]]."</li>\n";
	$total += $row[1];
}
echo "<li>$total total</li>\n";
echo "</ul>\n";

$offset = \Osmium\Chrome\paginate('p', 50, $total, $result, $meta);
$rows = "<tr>\n<th>Flag ID</th>\n<th>Creation date</th>\n<th>Type</th>\n<th>Subtype</th>\n<th>Status</th>\n<th>Target</th>\n</tr>\n";

echo $meta;
echo $result;
echo "<table class='d'>\n";
echo "<thead>\n$rows</thead>\n<tfoot>\n$rows</tfoot>\n<tbody>\n";

$types = \Osmium\Flag\get_flag_types();
$subtypes = \Osmium\Flag\get_flag_subtypes();
$flagsq = \Osmium\Db\query_params('SELECT
flagid, createdat, type, subtype, status, other, target1, target2, target3, l.visibility, l.privatetoken
FROM osmium.flags
LEFT JOIN osmium.loadouts l ON (type = $3 AND target1 = l.loadoutid) OR (type = $4 AND target2 = l.loadoutid) OR (type = $5 AND target3 = l.loadoutid)
WHERE flaggedbyaccountid = $1
ORDER BY createdat DESC
LIMIT 50 OFFSET $2', array(
	$accountid,
	$offset,
	\Osmium\Flag\FLAG_TYPE_LOADOUT,
	\Osmium\Flag\FLAG_TYPE_COMMENT,
	\Osmium\Flag\FLAG_TYPE_COMMENTREPLY,
));

while($flag = \Osmium\Db\fetch_assoc($flagsq)) {
	echo "<tr class='status".$flag['status']."'>\n";
	echo "<td>".$flag['flagid']."</td>\n";
	echo "<td>".\Osmium\Chrome\format_relative_date($flag['createdat'])."</td>\n";
	echo "<td title='".\Osmium\Chrome\escape($flag['other'])."'>".$types[$flag['type']]."</td>\n<td>".$subtypes[$flag['subtype']]."</td>\n";
	echo "<td>".$statuses[$flag['status']]."</td>\n";
	echo "<td>";
	if($flag['type'] == \Osmium\Flag\FLAG_TYPE_LOADOUT) {
		$uri = \Osmium\Fit\get_fit_uri($flag['target1'], $flag['visibility'], $flag['privatetoken']);
		echo "<a href='../".$uri."'>#".$flag['target1']."</a>";
	} else if($flag['type'] == \Osmium\Flag\FLAG_TYPE_COMMENT) {
		$uri = \Osmium\Fit\get_fit_uri($flag['target2'], $flag['visibility'], $flag['privatetoken']);
		echo "<a href='../".$uri."?jtc=".$flag['target1']."#c".$flag['target1']."'>#".$flag['target1']."</a>";
	} else if($flag['type'] == \Osmium\Flag\FLAG_TYPE_COMMENTREPLY) {
		$uri = \Osmium\Fit\get_fit_uri($flag['target3'], $flag['visibility'], $flag['privatetoken']);
		echo "<a href='../".$uri."?jtc=".$flag['target2']."#r".$flag['target1']."'>#".$flag['target1']."</a>";
	} else {
		echo "<small>N/A</small>";
	}
	echo "</td>\n";
	echo "</tr>\n";
}

if($total == 0) {
	echo "<tr>\n<td colspan='6'>No flags to show.</td>\n</tr>\n";
}

echo "</tbody>\n</table>\n";
echo $result;

echo "</div>\n";
\Osmium\Chrome\print_footer();