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

namespace Osmium\Page\ViewLoadoutHistory;

require __DIR__.'/../inc/root.php';

$loadoutid = intval($_GET['loadoutid']);

if(!\Osmium\State\can_view_fit($loadoutid)) {
	\Osmium\fatal(404);
}
$fit = \Osmium\Fit\get_fit($loadoutid);
$can_edit = \Osmium\State\can_edit_fit($loadoutid);
$loadouturi = \Osmium\Fit\get_fit_uri($loadoutid, $fit['metadata']['visibility'], $fit['metadata']['privatetoken']);

if(!\Osmium\State\can_access_fit($fit) ||
   ($fit['metadata']['visibility'] != \Osmium\Fit\VISIBILITY_PUBLIC && !\Osmium\State\is_fit_green($loadoutid))) {
	\Osmium\fatal(403, "Please access the loadout page first (and eventually input the password), then retry.");
}

if($can_edit && isset($_GET['tok']) && $_GET['tok'] == \Osmium\State\get_token() && isset($_GET['rollback'])) {
	$hash = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT fittinghash FROM osmium.loadouthistory WHERE loadoutid = $1 AND revision = $2', array($loadoutid, $_GET['rollback'])));
	$rev = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT MAX(revision) FROM osmium.loadouthistory WHERE loadoutid = $1', array($loadoutid)));
	if($hash !== false && $rev !== false) {
		$newrev = $rev[0] + 1;
		$hash = $hash[0];

		$accountid = \Osmium\State\get_state('a')['accountid'];
		\Osmium\Db\query_params('INSERT INTO osmium.loadouthistory (loadoutid, revision, fittinghash, updatedbyaccountid, updatedate) VALUES ($1, $2, $3, $4, $5)', array($loadoutid, $newrev, $hash, $accountid, time()));
		\Osmium\Log\add_log_entry(\Osmium\Log\LOG_TYPE_REVERT_LOADOUT, null, $loadoutid, $_GET['rollback']);

		\Osmium\State\invalidate_cache('loadout-'.$loadoutid);
		\Osmium\Fit\insert_fitting_delta_against_previous_revision(\Osmium\Fit\get_fit($loadoutid));
		header('Location: ./'.$loadoutid);
		die();
	}
}

\Osmium\Chrome\print_header('Revision history of loadout #'.$loadoutid, '..',
                            $fit['metadata']['visibility'] == \Osmium\Fit\VISIBILITY_PUBLIC);

echo "<h1>Revision history of loadout <a href='../$loadouturi'>#$loadoutid</a></h1>\n";

$histq = \Osmium\Db\query_params('SELECT loadouthistory.fittinghash, loadouthistory.revision, loadouthistory.updatedate, delta, accounts.accountid, nickname, apiverified, characterid, charactername, corporationid, corporationname, allianceid, alliancename, ismoderator
FROM osmium.loadouthistory
LEFT JOIN osmium.loadouthistory AS previousrev ON loadouthistory.loadoutid = previousrev.loadoutid
                                               AND previousrev.revision = (loadouthistory.revision - 1)
LEFT JOIN osmium.fittingdeltas ON fittingdeltas.fittinghash1 = previousrev.fittinghash
                               AND fittingdeltas.fittinghash2 = loadouthistory.fittinghash
JOIN osmium.accounts ON accounts.accountid = loadouthistory.updatedbyaccountid
WHERE loadouthistory.loadoutid = $1
ORDER BY revision DESC', array($loadoutid));

echo "<ol id='lhistory'>\n";
$first = true;
while($rev = \Osmium\Db\fetch_assoc($histq)) {
	$class = $first ? 'opened' : 'closed';
	$loadouturi = \Osmium\Fit\get_fit_uri(
		$loadoutid,
		$fit['metadata']['visibility'],
		$fit['metadata']['privatetoken'],
		$rev['revision']
	);

	echo "<li value='".$rev['revision']."' class='$class' id='revision".$rev['revision']."'>\n<p>";
	echo "<a href='../{$loadouturi}'><strong>Revision #".$rev['revision']."</strong></a>";
	echo ", by ".\Osmium\Chrome\format_character_name($rev, '..');
	echo " (".date('Y-m-d', $rev['updatedate']).")";
	echo " — <small class='anchor'><a href='#revision".$rev['revision']."'>#</a></small>";
	if(!$first && $can_edit) {
		echo " — <small><a class='dangerous' href='?rollback=".$rev['revision']."&amp;tok=".\Osmium\State\get_token()."'>rollback to this revision</a></small>";
	}
	echo "<br /><code>fittinghash ".$rev['fittinghash']."</code></p>\n";

	if($rev['revision'] > 1) {
		echo "<pre>";
		echo $rev['delta'] === null ? '(delta is not available, sorry)' : (
			empty($rev['delta']) ? '(blank delta, something else must have changed)' : $rev['delta']
		);
		echo "</pre>\n";
	}

	echo "</li>\n";
	if($first) $first = false;
}
echo "</ol>\n";

\Osmium\Chrome\print_js_snippet('loadout_history');
\Osmium\Chrome\print_footer();
