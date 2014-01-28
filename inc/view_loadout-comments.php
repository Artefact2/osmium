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

namespace Osmium\ViewLoadout;

$commentsperpage = (int)\Osmium\get_ini_setting('comments_per_page');

if(isset($_GET['jtr']) && $_GET['jtr'] > 0) {
	/* Jump To Reply */
	$jtr = (int)$_GET['jtr'];

	$r = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT commentid
		FROM osmium.loadoutcommentreplies
		WHERE commentreplyid = $1',
		array($jtr)
	));

	if($r !== false) {
		$_GET['jtc'] = $r[0];
		$anchor = '#r'.$jtr;
	}
}

if(isset($_GET['jtc']) && $_GET['jtc'] > 0) {
	/* Jump To Comment */
	$jtc = (int)$_GET['jtc'];

	$r = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT revision, creationdate
		FROM osmium.loadoutcomments WHERE commentid = $1',
		array($jtc)
	));

	if($r !== false) {
		list($before) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
			'SELECT COUNT(commentid) FROM osmium.loadoutcomments
			WHERE loadoutid = $1 AND revision <= $2
			AND (revision > $3 OR (revision = $4 AND creationdate < $4))',
			array(
				$loadoutid,
				$revision,
				$r[0],
				$r[1],
			)
		));

		$page = 1 + floor($before / $commentsperpage);
		if(!isset($anchor)) {
			$anchor = '#c'.$jtc;
		}
		header('Location: ?pagec='.$page.$anchor);
		die();
	}
}

if(!$commentsallowed) return;
if(!$loggedin) return;

if(isset($_POST['commentbody'])) {
	if(\Osmium\Reputation\is_fit_public($fit) && !\Osmium\Reputation\has_privilege(
		\Osmium\Reputation\PRIVILEGE_COMMENT_LOADOUT
	) && $a['accountid'] != $author['accountid']) return;

	$body = \Osmium\Chrome\trim($_POST['commentbody']);
	$formatted = \Osmium\Chrome\format_sanitize_md($body);

	if(empty($body) || empty($formatted)) {
		/* Cowardly refusing to insert a blank comment */
		return;
	}

	\Osmium\Db\query('BEGIN;');

	$r = \Osmium\Db\query_params(
		'INSERT INTO osmium.loadoutcomments (loadoutid, accountid, creationdate, revision)
		VALUES ($1, $2, $3, $4) RETURNING commentid',
		array(
			$loadoutid,
			$a['accountid'],
			$t = time(),
			$fit['metadata']['revision']
		)
	);

	if($r === false) {
		\Osmium\Db\query('ROLLBACK;'); /* XXX: warn user about this failure */
		return;
	}

	list($commentid) = \Osmium\Db\fetch_row($r);
	$r = \Osmium\Db\query_params(
		'INSERT INTO osmium.loadoutcommentrevisions
		(commentid, revision, updatedbyaccountid, updatedate, commentbody, commentformattedbody)
		VALUES ($1, $2, $3, $4, $5, $6)',
		array(
			$commentid,
			1,
			$a['accountid'],
			$t,
			$_POST['commentbody'],
			$formatted
		)
	);

	if($r === false) {
		\Osmium\Db\query('ROLLBACK;'); /* XXX: warn user about this failure */
		return;
	}

	if($a['accountid'] != $author['accountid']) {
		\Osmium\Notification\add_notification(
			\Osmium\Notification\NOTIFICATION_TYPE_LOADOUT_COMMENTED,
			$a['accountid'], $author['accountid'], $commentid, $loadoutid
		);
	}

	\Osmium\Log\add_log_entry(
		\Osmium\Log\LOG_TYPE_CREATE_COMMENT,
		null, $commentid, $loadoutid
	);

	\Osmium\Db\query('COMMIT;');

	header('Location: #c'.$commentid);
	die();
}



else if(isset($_POST['replybody']) && isset($_POST['commentid'])) {
	$commentdata = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT commentid, accountid FROM osmium.loadoutcomments
		WHERE commentid = $1 AND loadoutid = $2 AND revision <= $3',
		array(
			intval($_POST['commentid']),
			$loadoutid,
			$fit['metadata']['revision']
		)
	));

	if($commentdata === false) {
		return;
	}

	if(\Osmium\Reputation\is_fit_public($fit) && !\Osmium\Reputation\has_privilege(
		\Osmium\Reputation\PRIVILEGE_REPLY_TO_COMMENTS
	) && $a['accountid'] != $commentdata['accountid']) return;

	$body = \Osmium\Chrome\trim($_POST['replybody']);
	$formatted = \Osmium\Chrome\format_sanitize_md_phrasing($body);

	if(empty($body) || empty($formatted)) {
		return;
	}

	\Osmium\Db\query('BEGIN;');

	$r = \Osmium\Db\query_params(
		'INSERT INTO osmium.loadoutcommentreplies
		(commentid, accountid, creationdate, replybody, replyformattedbody, updatedate, updatedbyaccountid)
		VALUES ($1, $2, $3, $4, $5, null, null) RETURNING commentreplyid',
		array(
			$_POST['commentid'],
			$a['accountid'],
			time(),
			$body,
			$formatted
		)
	);

	if($r === false) {
		\Osmium\Db\query('ROLLBACK;'); /* XXX: warn user about this failure */
		return;
	}

	list($crid) = \Osmium\Db\fetch_row($r);

	/* Notify the comment author and all other users who replied before */
	$ids = \Osmium\Db\query_params(
		'SELECT accountid FROM osmium.loadoutcomments
		WHERE commentid = $1
		UNION
		SELECT DISTINCT accountid FROM osmium.loadoutcommentreplies
		WHERE commentid = $1',
		array($_POST['commentid'])
	);

	while($id = \Osmium\Db\fetch_row($ids)) {
		$id = $id[0];
		if($id == $a['accountid']) continue;

		\Osmium\Notification\add_notification(
			\Osmium\Notification\NOTIFICATION_TYPE_COMMENT_REPLIED,
			$a['accountid'], $id, $crid, $_POST['commentid'], $loadoutid
		);
	}

	\Osmium\Log\add_log_entry(
		\Osmium\Log\LOG_TYPE_CREATE_COMMENT_REPLY,
		null, $crid, $_POST['commentid'], $loadoutid
	);

	\Osmium\Db\query('COMMIT;');

	header('Location: #r'.$crid);
	die();
}
