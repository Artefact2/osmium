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

namespace Osmium\Page\CastFlag;

require __DIR__.'/../inc/root.php';

if(!\Osmium\State\is_logged_in()) {
	\Osmium\fatal(403);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$a = \Osmium\State\get_state('a');

$options = array();
$flagtype = null;
$title = 'Flag';
$ftitle = 'Flag';
$otherid1 = null;
$otherid2 = null;

if($type == 'loadout') {
	$flagtype = \Osmium\Flag\FLAG_TYPE_LOADOUT;
	$loadoutid = $id;
	$uri = "../".\Osmium\Fit\fetch_fit_uri($id);
	$title = 'Flag loadout #'.$id;
	$ftitle = "Flag loadout <a href='$uri'>#$id</a>";

	$options[\Osmium\Flag\FLAG_SUBTYPE_NOT_A_REAL_LOADOUT] = array('Not a real loadout', 'This loadout cannot be fitted on either Tranquility or Singularity, or fills no purpose.');
} else if($type == 'comment' || $type == 'commentreply') {
	if($type == 'comment') {
		$flagtype = \Osmium\Flag\FLAG_TYPE_COMMENT;
		$entity = 'comment';
		$commentid = $id;
		$anchor = 'c'.$id;

		$row = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT loadoutid FROM osmium.loadoutcomments WHERE commentid = $1', array($id)));
		if($row === false) {
			\Osmium\fatal(404);
		}

		$loadoutid = $otherid1 = $row['loadoutid'];
	} else if($type == 'commentreply') {
		$flagtype = \Osmium\Flag\FLAG_TYPE_COMMENTREPLY;
		$entity = 'comment reply';
		$anchor = 'r'.$id;

		$row = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT lcr.commentid, loadoutid FROM osmium.loadoutcommentreplies AS lcr JOIN osmium.loadoutcomments AS lc ON lc.commentid = lcr.commentid WHERE commentreplyid = $1', array($id)));
		if($row === false) {
			\Osmium\fatal(404);
		}

		$commentid = $otherid1 = $row['commentid'];
		$loadoutid = $otherid2 = $row['loadoutid'];
	}

	$uri = "../".\Osmium\Fit\fetch_fit_uri($loadoutid)."?jtc={$commentid}#{$anchor}";
	$title = 'Flag '.$entity.' #'.$id;
	$ftitle = 'Flag '.$entity." <a href='$uri'>#$id</a>";

	$options[\Osmium\Flag\FLAG_SUBTYPE_NOT_CONSTRUCTIVE] = array('Not constructive', 'This comment is useless, or brings nothing new or interesting to the loadout.');
} else {
	\Osmium\fatal(400);
}

$fit = \Osmium\Fit\get_fit($loadoutid);

if($fit === false) {
	\Osmium\fatal(500, "get_fit() returned false, please report!");
}
if(!\Osmium\Flag\is_fit_flaggable($fit)) {
	\Osmium\fatal(400);
}

$options[\Osmium\Flag\FLAG_SUBTYPE_OFFENSIVE] = array('Offensive');
$options[\Osmium\Flag\FLAG_SUBTYPE_SPAM] = array('Spam');
$options[\Osmium\Flag\FLAG_SUBTYPE_OTHER] = array('Requires moderator attention', "<textarea placeholder='You want to report something that does not fall in any other category above? Use this field to provide more details.' name='other'></textarea>");

if(isset($_POST['flagtype']) && isset($options[$_POST['flagtype']])) {
	$flagsubtype = (int)$_POST['flagtype'];
	$other = trim($_POST['other']);

	if($flagsubtype == \Osmium\Flag\FLAG_SUBTYPE_OTHER && !$other) {
		\Osmium\Forms\add_field_error('flagtype'.\Osmium\Flag\FLAG_SUBTYPE_OTHER, 'Please provide a reason.');
	} else {
		$row = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT flagid FROM osmium.flags WHERE flaggedbyaccountid = $1 AND createdat >= $2 AND type = $3 AND subtype = $4 AND target1 = $5 AND status = $6', 
		                                                    array($a['accountid'],
		                                                          time() - 7200,
		                                                          $flagtype,
		                                                          $flagsubtype,
		                                                          $id,
		                                                          \Osmium\Flag\FLAG_STATUS_NEW)));
		if($row !== false) {
			\Osmium\Forms\add_field_error('flagtype'.$flagsubtype, 'You already flagged this fit recently.');
		} else {
			\Osmium\Db\query_params('INSERT INTO osmium.flags (flaggedbyaccountid, createdat, type, subtype, status, other, target1, target2, target3) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)', array(
				                        $a['accountid'],
				                        time(),
				                        $flagtype,
				                        $flagsubtype,
				                        \Osmium\Flag\FLAG_STATUS_NEW,
				                        $flagsubtype == \Osmium\Flag\FLAG_SUBTYPE_OTHER ? $other : null,
				                        $id,
				                        $otherid1,
				                        $otherid2));

			header('Location: '.$uri);
			die();
		}
	}
}

\Osmium\Chrome\print_header($title, '..');
echo "<h1>$ftitle</h1>\n";

echo "<div id='castflag'>\n";
\Osmium\Forms\print_form_begin();

foreach($options as $type => $a) {
	$v = "<h2><label for='flagtype$type'>".$a[0]."</label></h2>";
	if(isset($a[1])) {
		$v .= "\n<p>".$a[1]."</p>";
	}

	$checked = isset($_POST['flagtype']) && $_POST['flagtype'] == $type ? 'checked="checked" ' : '';
	\Osmium\Forms\print_generic_row('flagtype'.$type, "<input type='radio' name='flagtype' id='flagtype$type' value='$type' {$checked}/>", $v);
	\Osmium\Forms\print_separator();
}

\Osmium\Forms\print_submit('Cast flag');
\Osmium\Forms\print_form_end();
echo "</div>\n";

\Osmium\Chrome\print_js_snippet('castflag');
\Osmium\Chrome\print_footer();