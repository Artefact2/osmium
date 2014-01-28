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

namespace Osmium\Page\DeleteFit;

require __DIR__.'/../inc/root.php';

\Osmium\State\assume_logged_in('..');

if($_GET['tok'] != \Osmium\State\get_token()) {
	\Osmium\fatal(403);
}

$loadoutid = isset($_GET['loadoutid']) ? $_GET['loadoutid'] : 0;

$can_edit = \Osmium\State\can_edit_fit($loadoutid);
if(!$can_edit) {
	\Osmium\fatal(403);
}

\Osmium\Db\query('BEGIN;');

\Osmium\Db\query_params(
	'DELETE FROM osmium.loadoutcommentreplies
	USING osmium.loadoutcomments
	WHERE loadoutcomments.loadoutid = $1
	AND loadoutcommentreplies.commentid = loadoutcomments.commentid',
	array($loadoutid)
);
\Osmium\Db\query_params(
	'DELETE FROM osmium.loadoutcommentrevisions
	USING osmium.loadoutcomments
	WHERE loadoutcomments.loadoutid = $1
	AND loadoutcommentrevisions.commentid = loadoutcomments.commentid',
	array($loadoutid)
);

$q = \Osmium\Db\query_params(
	'SELECT commentid FROM loadoutcomments lc WHERE lc.loadoutid = $1',
	array($loadoutid)
);
while($row = \Osmium\Db\fetch_row($q)) {
	\Osmium\Reputation\nullify_votes(
		'targettype = $1 AND targetid1 = $2 AND targetid2 = $3 AND targetid3 IS NULL',
		array(
			\Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
			$row[0], $loadoutid,
		),
		true
	);
}

\Osmium\Db\query_params('DELETE FROM osmium.loadoutcomments WHERE loadoutid = $1', array($loadoutid));
\Osmium\Db\query_params('DELETE FROM osmium.accountfavorites WHERE loadoutid = $1', array($loadoutid));
\Osmium\Db\query_params('DELETE FROM osmium.loadouthistory WHERE loadoutid = $1', array($loadoutid));
\Osmium\Db\query_params('DELETE FROM osmium.loadoutdogmaattribs WHERE loadoutid = $1', array($loadoutid));
\Osmium\Db\query_params('DELETE FROM osmium.loadouts WHERE loadoutid = $1', array($loadoutid));
\Osmium\Reputation\nullify_votes(
	'targettype = $1 AND targetid1 = $2',
	array(
		\Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT,
		$loadoutid
	),
	true
);
\Osmium\Log\add_log_entry(\Osmium\Log\LOG_TYPE_DELETE_LOADOUT, null, $loadoutid);

\Osmium\Db\query('COMMIT;');

/* FIXME check that transaction was successful before unindexing this */
\Osmium\Search\unindex($loadoutid);

header('Location: ../');
die();
