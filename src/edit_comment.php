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

namespace Osmium\Page\EditComment;

require __DIR__.'/../inc/root.php';

\Osmium\State\assume_logged_in('..');
$a = \Osmium\State\get_state('a');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($_GET['type'] == 'comment') {
	$comment = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT commentbody, accountid, loadoutid, latestrevision FROM osmium.loadoutcommentslatestrevision AS lclr JOIN osmium.loadoutcommentrevisions AS lcr ON lcr.commentid = lclr.commentid AND lcr.revision = lclr.latestrevision JOIN osmium.loadoutcomments lc ON lc.commentid = lclr.commentid WHERE lclr.commentid = $1', array($id)));
	
	if($comment === false) {
		\Osmium\fatal(404);
	}
	
	if($a['ismoderator'] !== 't' && $a['accountid'] != $comment['accountid']) {
		\Osmium\fatal(403);
	}
	
	$body = $comment['commentbody'];
	$ftype = 'Comment';
} else if($_GET['type'] == 'commentreply') {
	$reply = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT replybody, accountid, commentid FROM osmium.loadoutcommentreplies WHERE commentreplyid = $1', array($id)));

	if($reply === false) {
		\Osmium\fatal(404);
	}

	if($a['ismoderator'] !== 't' && $a['accountid'] != $reply['accountid']) {
		\Osmium\fatal(403);
	}

	$body = $reply['replybody'];
	$ftype = 'Comment reply';
} else {
	\Osmium\fatal(400);
}

if(isset($_POST['body'])) {
	if($_GET['type'] == 'comment') {
		$body = trim($_POST['body']);
		$formatted = \Osmium\Chrome\format_sanitize_md($body);

		if($_POST['body'] == $comment['commentbody'] && $a['accountid'] == $comment['accountid']) {
			/* Keep the same revision, but update the formattedbody */
			\Osmium\Db\query_params('UPDATE osmium.loadoutcommentrevisions SET commentformattedbody = $1 WHERE commentid = $2 AND revision = $3', array($formatted, $id, $comment['latestrevision']));
		} else {
			/* Insert a new revision */
			$newrevision = $comment['latestrevision'] + 1;
			\Osmium\Db\query_params('INSERT INTO osmium.loadoutcommentrevisions (commentid, revision, updatedbyaccountid, updatedate, commentbody, commentformattedbody) VALUES ($1, $2, $3, $4, $5, $6)', array($id, $newrevision, $a['accountid'], time(), $_POST['body'], $formatted));
		}

		\Osmium\Db\query_params('UPDATE osmium.votes SET cancellableuntil = NULL WHERE targettype = $1 AND targetid1 = $2 AND targetid2 = $3 AND targetid3 IS NULL',
		                        array(
			                        \Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
			                        $id,
			                        $comment['loadoutid'],
			                        ));

		$anchor = 'c'.$id;
		$commentid = $id;
	} else if($_GET['type'] == 'commentreply') {
		$body = trim($_POST['body']);
		$formatted = \Osmium\Chrome\format_sanitize_md_phrasing($body);

		\Osmium\Db\query_params('UPDATE osmium.loadoutcommentreplies SET replybody = $1, replyformattedbody = $2, updatedate = $3, updatedbyaccountid = $4 WHERE commentreplyid = $5', array($_POST['body'], $formatted, time(), $a['accountid'], $id));

		$anchor = 'r'.$id;
		$commentid = $reply['commentid'];
	}

	list($loadoutid) = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT loadoutid FROM osmium.loadoutcomments WHERE commentid = $1', array($commentid)));

	if($_GET['type'] == 'comment') {
		\Osmium\Log\add_log_entry(\Osmium\Log\LOG_TYPE_UPDATE_COMMENT, null, $id, $loadoutid);
	} else if($_GET['type'] == 'commentreply') {
		\Osmium\Log\add_log_entry(\Osmium\Log\LOG_TYPE_UPDATE_COMMENT_REPLY, null, $id, $commentid, $loadoutid);
	}

	header('Location: ../'.\Osmium\Fit\fetch_fit_uri($loadoutid).'?jtc='.$commentid.'#'.$anchor);
	die();
}

\Osmium\Chrome\print_header($t = 'Edit '.lcfirst($ftype).' #'.$id, '..');

echo "<h1>$t</h1>\n";

$_POST['body'] = $body; /* Will be escaped by print_textarea() */
\Osmium\Forms\print_form_begin();
\Osmium\Forms\print_textarea($ftype.' body', 'body', null, \Osmium\Forms\FIELD_REMEMBER_VALUE);
\Osmium\Forms\print_submit('Confirm edit');
\Osmium\Forms\print_form_end();
\Osmium\Chrome\print_footer();
