<?php
/* Osmium
 * Copyright (C) 2012, 2013, 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Notification;

/** Loadout has been edited by someone else */
const NOTIFICATION_TYPE_LOADOUT_EDITED = 100;

/** Loadout has been commented */
const NOTIFICATION_TYPE_LOADOUT_COMMENTED = 101;

/** A loadout comment has been replied to */
const NOTIFICATION_TYPE_COMMENT_REPLIED = 102;

/** An account's API key was disabled due to errors. */
const NOTIFICATION_TYPE_ACCOUNT_API_KEY_DISABLED = 200;

function add_notification($type, $fromaccountid, $destaccountid,
                          $targetid1 = null, $targetid2 = null, $targetid3 = null) {
	\Osmium\Db\query_params(
		'INSERT INTO osmium.notifications (accountid, creationdate, type, fromaccountid, targetid1, targetid2, targetid3) VALUES ($1, $2, $3, $4, $5, $6, $7)',
		array(
			$destaccountid,
			time(),
			$type,
			$fromaccountid,
			$targetid1,
			$targetid2,
			$targetid3,
			));
}

function get_new_notification_count() {
	$a = \Osmium\State\get_state('a', null);
	if($a === null) return 0;

	$threshold = \Osmium\State\get_setting('notification_threshold', 0);

	$c = \Osmium\Db\fetch_row(
		\Osmium\Db\query_params(
			'SELECT COUNT(notificationid) FROM osmium.notifications
			WHERE accountid = $1 AND creationdate >= $2',
			array($a['accountid'], $threshold)
			));
	return $c[0];
}

/**
 * Process notifications in reverse chronological order.
 *
 * @param $callback function which takes the following arguments:
 * $row, $isnew
 *
 * @param $newonly if true, only process new notifications
 */
function get_notifications($callback, $newonly = false) {
	/* Hope very hard generators get added in PHP… */

	$a = \Osmium\State\get_state('a', null);
	if($a === null) return;

	$threshold = \Osmium\State\get_setting('notification_threshold', 0);

	$q = \Osmium\Db\query_params(
		'SELECT notificationid, n.creationdate, n.type,
		targetid1, targetid2, targetid3,
		a.accountid, a.nickname, a.apiverified,
		a.characterid, a.charactername, a.ismoderator,
		l.visibility, l.privatetoken
		FROM osmium.notifications AS n
		LEFT JOIN osmium.accounts AS a ON n.fromaccountid = a.accountid
		LEFT JOIN osmium.loadouts l ON (n.type = $3 AND n.targetid1 = l.loadoutid)
		OR (n.type = $4 AND n.targetid2 = l.loadoutid)
		OR (n.type = $5 AND n.targetid3 = l.loadoutid)
		WHERE n.creationdate >= $1 AND n.accountid = $2
		ORDER BY n.creationdate DESC
		'.($newonly ? '' : 'LIMIT 50'),
		array($newonly ? $threshold : 0,
		      $a['accountid'],
		      NOTIFICATION_TYPE_LOADOUT_EDITED,
		      NOTIFICATION_TYPE_LOADOUT_COMMENTED,
		      NOTIFICATION_TYPE_COMMENT_REPLIED,
			));

	while($row = \Osmium\Db\fetch_assoc($q)) {
		$callback($row, $row['creationdate'] >= $threshold);
	}
}

function reset_notification_count($thres = null) {
	if($thres === null) $thres = time();
	\Osmium\State\put_setting('notification_threshold', $thres);
}

function get_notification_body($row) {
	$type = $row['type'];
	$vis = $row['visibility'];
	$ptok = $row['privatetoken'];

	if($type == NOTIFICATION_TYPE_LOADOUT_EDITED) {
		$loadoutid = $row['targetid1'];
		$revision = $row['targetid2'];

		return \Osmium\Chrome\format_character_name($row)
			." has edited loadout <a href='./".\Osmium\Fit\get_fit_uri($loadoutid, $vis, $ptok)."'>#"
			.$loadoutid."</a>. <a href='./loadouthistory/"
			.$loadoutid."#revision".$revision."'>View changes</a>";
	} else if($type == NOTIFICATION_TYPE_LOADOUT_COMMENTED) {
		$commentid = $row['targetid1'];
		$loadoutid = $row['targetid2'];
		$uri = \Osmium\Fit\get_fit_uri($loadoutid, $vis, $ptok);

		return \Osmium\Chrome\format_character_name($row)
			." has commented on loadout <a href='./".$uri."'>#"
			.$loadoutid."</a>. <a href='./".$uri."?jtc="
			.$commentid."#c".$commentid."'>View comment</a>";
	} else if($type == NOTIFICATION_TYPE_COMMENT_REPLIED) {
		$replyid = $row['targetid1'];
		$commentid = $row['targetid2'];
		$loadoutid = $row['targetid3'];
		$uri = \Osmium\Fit\get_fit_uri($loadoutid, $vis, $ptok);

		return \Osmium\Chrome\format_character_name($row)
			." has replied to one of your comments on loadout <a href='./"
			.$uri."'>#".$loadoutid."</a>. <a href='./".$uri."?jtc="
			.$commentid."#r".$replyid."'>View reply</a>";
	} else if($type == NOTIFICATION_TYPE_ACCOUNT_API_KEY_DISABLED) {
		$keyid = (int)$row['targetid1'];

		return "Your API key (KeyID ".$keyid
			.") has been disabled. Maybe it expired or no longer had correct permissions.";
	}


	else {
		return 'Unknown notification type '.intval($type);
	}
}