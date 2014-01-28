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

namespace Osmium\Reputation;

const DEFAULT_REPUTATION = 1;
const MIN_REPUTATION = 1;

/** Cache our current reputation in session for 1 minute */
const REPUTATION_CACHE_TIMER = 60;

const VOTE_TYPE_UP = 1;
const VOTE_TYPE_DOWN = 2;

const VOTE_TARGET_TYPE_LOADOUT = 1;
const VOTE_TARGET_TYPE_COMMENT = 2;
const VOTE_TARGET_TYPE_COMMENTREPLY = 3;

/** Lock votes after 5 minutes */
const VOTE_UNDO_DELAY = 300;

/** Allow 3O up/down votes per day per user */
const VOTE_DAILY_LIMIT_UPDOWN = 30;

const PRIVILEGE_CREATE_LOADOUT = 1;
const PRIVILEGE_COMMENT_LOADOUT = 2;
const PRIVILEGE_REPLY_TO_COMMENTS = 3;
const PRIVILEGE_UPVOTE = 4;
const PRIVILEGE_DOWNVOTE = 5;
const PRIVILEGE_RETAG_LOADOUTS = 6;

/**
 * Get reputation values of up/down flags.
 *
 * @returns array(<vote_type> => <target_type> =>
 * array(rep_change_for_dest, rep_change_for_source))
 */
function get_updown_vote_reputation() {
	return array(
		VOTE_TYPE_UP => array(
			VOTE_TARGET_TYPE_LOADOUT => array(5, 0),
			VOTE_TARGET_TYPE_COMMENT => array(10, 0),
			VOTE_TARGET_TYPE_COMMENTREPLY => array(2, 0),
			),
		VOTE_TYPE_DOWN => array(
			VOTE_TARGET_TYPE_LOADOUT => array(-2, 0),
			VOTE_TARGET_TYPE_COMMENT => array(-5, -2),
			VOTE_TARGET_TYPE_COMMENTREPLY => array(0, 0),
			),
		);
}

/**
 * Get all privileges that can be obtained with reputation points.
 *
 * @param array(<privilege_id> => array( name, desc, req => [ <req>, <bs_req> ] ))
 */
function get_privileges() {
	return array(
		PRIVILEGE_CREATE_LOADOUT => [
			'name' => 'Create loadouts',
			'desc' => '<p>You can create public loadouts and share them with the rest of the world.<br /><small>(You can always create private or hidden loadouts, regardless of whether you have this privilege or not.)</small></p>',
			'req' => [ 1, 1 ],
		],
		PRIVILEGE_COMMENT_LOADOUT => [
			'name' => 'Comment on loadouts',
			'desc' => '<p>You can comment public loadouts.<br />Use them to suggest some improvements, or to share your experience of the loadout in the field!<br /><small>(You can always comment your own loadouts.)</small></p>',
			'req' => [ 1, 1 ],
		],
		PRIVILEGE_REPLY_TO_COMMENTS => [
			'name' => 'Reply to comments',
			'desc' => '<p>You can reply to comments on public loadouts.<br /><small>(You can always reply to your own comments.)</small></p>',
			'req' => [ 10, 1 ],
		],
		PRIVILEGE_UPVOTE => [
			'name' => 'Cast upvotes',
			'desc' => '<p>You can cast upvotes on public loadouts and comments. Upvote content which you believe belongs to the top.<br /><strong>Upvote loadouts that are creative, effective, fill a well-defined role, and/or are nicely formatted and extensively described.<br />Upvote comments that bring up interesting points.</strong><br /><small>(To prevent cheating, only API-verified accounts can cast votes.)</small></p>',
			'req' => [ 25, 1 ],
		],
		PRIVILEGE_DOWNVOTE => [
			'name' => 'Cast downvotes',
			'desc' => '<p>You can cast downvotes on public loadouts and comments.<br /><strong>Downvote loadouts that are badly formatted, show no research effort, or have severe flaws.<br />Downvote troll or otherwise useless comments.<br />You should still flag offensive content, duplicates and spam so the moderation can deal with it.</strong></p>',
			'req' => [ 50, 25 ],
		],
		PRIVILEGE_RETAG_LOADOUTS => [
			'name' => 'Re-tag loadouts',
			'desc' => '<p>You can change tags of all public loadouts by clicking the <strong>✎ Edit tags</strong> link next to the title.<br />Re-tag loadouts which have no tags at all, or that are poorly tagged.<br />Make it easy and consistent for other users to effectively search for loadouts.<br />Re-use common tags if possible.<br /><small>(You can undo your tag changes by viewing the loadout history, and reverting to an older revision.<br />You can obviously always re-tag your own loadouts.)</small></p>',
			'req' => [ 100, 100 ],
		],
		);
}

function get_vote_types() {
	return array(
		VOTE_TYPE_UP => 'upvote',
		VOTE_TYPE_DOWN => 'downvote',
		);
}

function is_fit_public($fit) {
	return $fit['metadata']['visibility'] == \Osmium\Fit\VISIBILITY_PUBLIC
		&& $fit['metadata']['view_permission'] == \Osmium\Fit\VIEW_EVERYONE;	
}

function get_current_reputation() {
	$a = \Osmium\State\get_state('a', null);
	if($a === null) return 0;

	if(!isset($a['reputationexpires']) || $a['reputationexpires'] < time()) {
		$r = \Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT reputation FROM osmium.accounts WHERE accountid = $1',
				array($a['accountid'])));

		$a['reputation'] = $r[0];
		$a['reputationexpires'] = time() + REPUTATION_CACHE_TIMER;
		\Osmium\State\put_state('a', $a);
	}

	return $a['reputation'];
}

function has_privilege($privilege, $accountid = null) {
	static $bootstrap = null;
	static $priv = null;
	if($bootstrap === null) {
		$bootstrap = \Osmium\get_ini_setting('bootstrap_mode');
		$priv = get_privileges();
	}

	$req = $priv[$privilege]['req'];
	$a = \Osmium\State\get_state('a', []);

	if($accountid === null || isset($a['accountid']) && $a['accountid'] == $accountid) {
		$currentrep = get_current_reputation();
	} else {
		$currentrep = (int)\Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT reputation FROM osmium.accounts WHERE accountid = $1',
				array($accountid)
			)
		)[0];
	}

	return $currentrep >= ($bootstrap ? $req[1] : $req[0]);
}

/**
 * Cast an up/down vote on something.
 *
 * This will delete previous up/down votes on the same thing.
 *
 * @param $type VOTE_TYPE_UP or VOTE_TYPE_DOWN or null to delete vote
 * @param $targettype one of the VOTE_TARGET_TYPE_* constants
 * @param $targetaccountid accountid of the account that should receive the reputation
 * @param $givesreputation does this vote give reputation to the target account?
 * @param $id the id of the thing being voted on
 * @param $targetid2 optional supplemental identifier (default null)
 * @param $targetid3 see $targetid2
 * @param $error if provided, error message will be set in this
 */
function cast_updown_vote($type, $targettype, $targetaccountid, $givesreputation, $id, $targetid2 = null, $targetid3 = null, &$error = null) {
	if($type !== VOTE_TYPE_UP && $type !== VOTE_TYPE_DOWN && $type !== null) {
		$error = 'Invalid type (please report)';
		return false;
	}

	$a = \Osmium\State\get_state('a', array());
	if(!isset($a['accountid'])) {
		$error = 'You must login first';
		return false;
	}
	$accountid = $a['accountid'];

	if($type === VOTE_TYPE_UP && !has_privilege(PRIVILEGE_UPVOTE)) {
		$error = 'You don\'t have enough reputation to cast upvotes.';
		return false;
	} else if($type === VOTE_TYPE_DOWN && !has_privilege(PRIVILEGE_DOWNVOTE)) {
		$error = 'You don\'t have enough reputation to cast downvotes.';
		return false;
	}

	if($accountid == $targetaccountid) {
		$error = 'You can\'t vote on yourself.';
		return false;
	}

	\Osmium\Db\query('BEGIN;');

	$eveaccountid = \Osmium\State\get_eveaccount_id($error);
	if($eveaccountid === false) {
		\Osmium\Db\query('ROLLBACK;');
		return false;
	}

	$netrepchangefrom = 0;
	$netrepchangeto = 0;
	$types = '('.VOTE_TYPE_UP.', '.VOTE_TYPE_DOWN.')';

	$pvotes = \Osmium\Db\query_params('SELECT voteid, type, reputationgiventodest, reputationgiventosource, cancellableuntil FROM osmium.votes WHERE fromaccountid = $1 AND type IN '.$types.' AND targettype = $2 AND targetid1 = $3 AND ((targetid2 IS NULL AND $4::integer IS NULL) OR targetid2 = $4) AND ((targetid3 IS NULL AND $5::integer IS NULL) OR targetid3 = $5)', array($accountid, $targettype, $id, $targetid2, $targetid3));
	while($v = \Osmium\Db\fetch_assoc($pvotes)) {
		if($v['cancellableuntil'] < time() && $v['cancellableuntil'] !== null) {
			\Osmium\Db\query('ROLLBACK;');
			$error = 'Your vote is now archived and can no longer be cancelled.';
			return false;
		}

		\Osmium\Db\query_params('DELETE FROM osmium.votes WHERE voteid = $1',
		                        array($v['voteid']));

		if($v['reputationgiventodest'] !== null) {
			$netrepchangeto -= $v['reputationgiventodest'];
		}
		if($v['reputationgiventosource'] !== null) {
			$netrepchangefrom -= $v['reputationgiventosource'];
		}
	}

	if($type !== null) {
		$day_threshold = time();
		$day_threshold -= ($day_threshold % 86400);
		list($today_count) = \Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT COUNT(voteid) FROM osmium.votes WHERE fromaccountid = $1 AND type IN '.$types.' AND creationdate >= $2',
				array($accountid, $day_threshold)
				));
		if($today_count >= VOTE_DAILY_LIMIT_UPDOWN) {
			\Osmium\Db\query('ROLLBACK;');
			$error = 'You have reached your daily limit of votes.';
			return false;
		}

		$sameeveaccount = \Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT voteid FROM osmium.votes WHERE fromeveaccountid = $1 AND type IN '.$types.' AND targettype = $2 AND targetid1 = $3 AND ((targetid2 IS NULL AND $4::integer IS NULL) OR targetid2 = $4) AND ((targetid3 IS NULL AND $5::integer IS NULL) OR targetid3 = $5)',
				array($eveaccountid, $targettype, $id, $targetid2, $targetid3)
				));
		if($sameeveaccount !== false) {
			\Osmium\Db\query('ROLLBACK;');
			$error = 'Sorry, you cannot vote on this.';
			return false;
		}

		if($givesreputation) {
			list($reptodest, $reptosrc) =
				get_updown_vote_reputation()[$type][$targettype];

			if($reptodest < 0) {
				/* Make sure dest has enough reputation, other wise
				 * set $reptodest to the correct value */
				list($rep) = \Osmium\Db\fetch_row(
					\Osmium\Db\query_params(
						'SELECT reputation FROM osmium.accounts WHERE accountid = $1',
						array($targetaccountid)
						));
				if($rep + $netrepchangeto + $reptodest < MIN_REPUTATION) {
					$reptodest = MIN_REPUTATION - $rep - $netrepchangeto;
				}
			}


			$netrepchangeto += $reptodest;
			$netrepchangefrom += $reptosrc;
		} else {
			$reptodest = null;
			$reptosrc = null;
		}

		\Osmium\Db\query_params(
			'INSERT INTO osmium.votes (fromaccountid, fromeveaccountid, fromclientid, accountid, creationdate, cancellableuntil, reputationgiventodest, reputationgiventosource, type, targettype, targetid1, targetid2, targetid3) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)',
			array(
				$accountid,
				$eveaccountid,
				\Osmium\State\get_client_id(),
				$targetaccountid,
				$t = time(),
				$t + VOTE_UNDO_DELAY,
				$reptodest,
				$reptosrc,
				$type,
				$targettype,
				$id,
				$targetid2,
				$targetid3,
				));
	}

	if($netrepchangeto != 0 || $netrepchangefrom != 0) {
		\Osmium\Db\query_params(
			'UPDATE osmium.accounts SET reputation = GREATEST(reputation + $1, $3) WHERE accountid = $2',
			array($netrepchangeto, $targetaccountid, MIN_REPUTATION)
			);
		\Osmium\Db\query_params(
			'UPDATE osmium.accounts SET reputation = GREATEST(reputation + $1, $3) WHERE accountid = $2',
			array($netrepchangefrom, $accountid, MIN_REPUTATION)
			);
	}

	\Osmium\Db\query('COMMIT;');
	return true;
}

/**
 * Undo the reputation changes from a set of votes, and optionally delete them.
 *
 * You should wrap this in a transaction.
 */
function nullify_votes($filter, array $filterparams, $alsodelete = false) {
	$q = \Osmium\Db\query_params(
		'SELECT voteid, fromaccountid, accountid, reputationgiventosource, reputationgiventodest
		FROM osmium.votes v
		WHERE '.$filter,
		$filterparams
	);

	while($v = \Osmium\Db\fetch_assoc($q)) {
		if($v['reputationgiventodest'] != 0 && $v['accountid'] > 0) {
			\Osmium\Db\query_params(
				'UPDATE accounts SET reputation = GREATEST($3, reputation - $1)
				WHERE accountid = $2',
				array($v['reputationgiventodest'], $v['accountid'], MIN_REPUTATION)
			);
		}

		if($v['reputationgiventosource'] != 0 && $v['fromaccountid'] > 0) {
			\Osmium\Db\query_params(
				'UPDATE accounts SET reputation = GREATEST($3, reputation - $1)
				WHERE accountid = $2',
				array($v['reputationgiventosource'], $v['fromaccountid'], MIN_REPUTATION)
			);
		}
	}

	if($alsodelete) {
		\Osmium\Db\query_params('DELETE FROM osmium.votes v WHERE '.$filter, $filterparams);
	} else {
		\Osmium\Db\query_params(
			'UPDATE osmium.votes v
			SET reputationgiventodest = 0, reputationgiventosource = 0
			WHERE '.$filter,
			$filterparams
		);
	}
}
