<?php
/* Osmium
 * Copyright (C) 2012, 2013, 2014, 2015 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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



$row = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
	'SELECT accountid, creationdate, greatest(creationdate, lastlogindate) AS lastlogindate, apiverified,
	nickname, characterid, charactername, corporationid, corporationname,
	allianceid, alliancename, ismoderator, flagweight, reputation
	FROM osmium.accounts WHERE accountid = $1',
	array($_GET['accountid'])
));

if($row === false) {
	\Osmium\fatal(404);
}

$a = \Osmium\State\get_state('a', [ 'accountid' => 0 ]);
$myprofile = \Osmium\State\is_logged_in() && $a['accountid'] == $_GET['accountid'];
$ismoderator = isset($a['ismoderator']) && $a['ismoderator'] === 't';

$p = new \Osmium\DOM\Page();
$nameelement = $p->makeAccountLink($row, $name);
$p->title = $name.'\'s profile';

$content = $p->content->appendCreate('div', [ 'id' => 'vprofile' ]);
$header = $content->appendCreate('header');

$header->appendCreate('h2', [
	$nameelement,
	[ 'small', [
		$myprofile ? ' (this is me!) ' : '',
		$row['ismoderator'] === 't' ? ' '.\Osmium\Flag\MODERATOR_SYMBOL.'Moderator ' : '',
	]]
]);


if($row['apiverified'] === 't') {
	/* Shouldn't typically happen, but better safe than sorry */
	$corpid = (($row['corporationid'] == null) ? 1 : $row['corporationid']);
	$corpname = ($corpid === 1) ? '(no corporation)' : $row['corporationname'];

	$allianceid = (($row['allianceid'] == null) ? 1 : $row['allianceid']);
	$alliancename = ($allianceid === 1) ? '(no alliance)' : $row['alliancename'];

	$pp = $header->appendCreate('p');

	$pp->append([
		[ 'a', [
			'o-rel-href' => '/search'.$p->formatQueryString([
				'ad' => 1, 'vr' => 1, 'vrs' => 'private', 'q' => '',
			]),
			[ 'o-eve-img', [ 'src' => '/Character/'.$row['characterid'].'_512.jpg', 'alt' => 'portrait' ] ],
		]],
		[ 'br' ],
		[ 'a', [
			'o-rel-href' => '/search'.$p->formatQueryString([
				'ad' => 1, 'vr' => 1, 'vrs' => 'corporation', 'q' => '',
			]),
			[ 'o-eve-img', [ 'src' => '/Corporation/'.$corpid.'_256.png',
			                 'alt' => 'corporation logo', 'title' => $corpname ] ],
		]],
		[ 'a', [
			'o-rel-href' => '/search'.$p->formatQueryString([
				'ad' => 1, 'vr' => 1, 'vrs' => 'alliance', 'q' => '',
			]),
			[ 'o-eve-img', [ 'src' => '/Alliance/'.$allianceid.'_128.png',
			                 'alt' => 'alliance logo', 'title' => $alliancename ] ],
		]],
	]);
}

$tbody = $header->appendCreate('table')->appendCreate('tbody');
$sep = $p->element('tr', [
	'class' => 'sep',
	[ 'td', [ 'colspan' => '3', ' ' ] ],
]);

if($row['apiverified'] === 't') {
	$tbody->appendCreate('tr', [
		[ 'th', [ 'rowspan' => '2', 'character' ] ],
		[ 'td', 'corporation' ],
		[ 'td', $corpname ],
	]);
	$tbody->appendCreate('tr', [
		[ 'td', 'alliance' ],
		[ 'td', $alliancename ],
	]);
	$tbody->append($sep->cloneNode(true));
}

$tbody->appendCreate('tr', [
	[ 'th', [ 'rowspan' => '2', 'visits' ] ],
	[ 'td', 'member for' ],
	[ 'td', $p->formatRelativeDate($row['creationdate'], -1) ],
]);
$tbody->appendCreate('tr', [
	[ 'td', 'last seen' ],
	[ 'td', $p->formatRelativeDate($row['lastlogindate'], -1) ],
]);
$tbody->append($sep->cloneNode(true));

$tbody->appendCreate('tr', [
	[ 'th', [ 'rowspan' => '2', 'meta' ] ],
	[ 'td', 'api key verified' ],
	[ 'td', $row['apiverified'] === 't' ? 'yes' : 'no' ],
]);
$tbody->appendCreate('tr', [
	[ 'td', 'reputation points' ],
	[ 'td', [
		$p->formatReputation($row['reputation']),
		' ',
		$myprofile ? [ 'a', [ 'o-rel-href' => '/privileges#privlist', '(check my privileges)' ] ] : '',
	]],
]);

if($myprofile || $ismoderator) {
	$tbody->append($sep->cloneNode(true));
	$tbody->appendCreate('tr', [
		[ 'th', [ 'private' ] ],
		[ 'td', 'flag weight' ],
		[ 'td', [
			$p->formatExactInteger($row['flagweight']),
			' ',
			[ 'a', [ 'o-rel-href' => '/flagginghistory/'.$row['accountid'], '(see flagging history)' ] ],
		]],
	]);
}



$content->appendCreate('ul', [
	'class' => 'tabs',
	$myprofile ? [ 'li', [[ 'a', [ 'href' => '#psaved', 'Saved' ] ]] ] : '',
	[ 'li', [[ 'a', [ 'href' => '#ploadouts', 'Recent' ] ]] ],
	[ 'li', [[ 'a', [ 'href' => '#reputation', 'Reputation' ] ]] ],
	[ 'li', [[ 'a', [ 'href' => '#votes', 'Votes' ] ]] ],
]);



$ploadouts = $content->appendCreate('section', [ 'id' => 'ploadouts', 'class' => 'psection' ]);
$ploadouts->appendCreate('h2', 'Loadouts recently submitted')->appendCreate('small')->appendCreate('a', [
	'o-rel-href' => '/search'.$p->formatQueryString([
		'q' => '@author "'.$name.'"',
		/* Show all loadouts by default (don't filter on dogma ver) */
		'ad' => 1,
		'build' => \Osmium\Fit\get_closest_version_by_build(0)['build'],
		'op' => 'gt',
	]),
	'(browse all)'
]);
$ploadouts->append(\Osmium\Search\make_pretty_results(
	$p, '@author "'.$name.'"',
	'ORDER BY creationdate DESC',
	false, 10, 'p',
	$name.' does not yet have any public loadouts.'
)[1]);



if($myprofile) {
	$pfavs = $content->appendCreate('section', [ 'id' => 'psaved', 'class' => 'psection' ]);
	$pfavs->appendCreate('h2', 'Saved loadouts');

	/* TODO pagination */
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

	if($stale !== []) {
		$pfavs->appendCreate(
			'p',
			'These following loadouts you saved are no longer accessible to you:'
		);

		$ol = $pfavs->appendCreate('ol');
		$qs = $p->formatQueryString([ 'redirect' => $_SERVER['REQUEST_URI'] ]);

		foreach($stale as $id) {
			$ol->appendCreate('li', [
				'Loadout ',
				[ 'a', [ 'o-rel-href' => '/loadout/'.$id, '#'.$id ] ],
				' — ',
				[ 'o-state-altering-a', [ 'o-rel-href' => '/internal/favorite/'.$id.$qs, 'unsave' ] ]
			]);
		}
	}

	if($favorites !== []) {
		$pfavs->append($p->makeLoadoutGridLayout($favorites));
	} else {
		$pfavs->appendCreate('p', [
			'class' => 'placeholder',
			'You have no saved loadouts.',
		]);
	}
}



$preputation = $content->appendCreate('section', [ 'id' => 'reputation', 'class' => 'psection' ]);
$preputation->appendCreate('h2', [
	'Reputation changes this month',
	[ 'small', [ $p->formatReputation($row['reputation']), ' reputation points' ] ],
]);

$votetypes = \Osmium\Reputation\get_vote_types();
$repchangesq = \Osmium\Db\query_params(
	'SELECT v.creationdate, reputationgiventodest, type, targettype, targetid1, targetid2, targetid3,
		sl.loadoutid, f.name
	FROM osmium.votes AS v
	LEFT JOIN osmium.searchableloadouts AS sl ON (sl.accountid = $5) AND (
		(v.targettype = $3 AND v.targetid1 = sl.loadoutid
		AND v.targetid2 IS NULL AND v.targetid3 IS NULL)
		OR (v.targettype = $4 AND v.targetid2 = sl.loadoutid AND v.targetid3 IS NULL)
	)
	LEFT JOIN osmium.loadoutslatestrevision AS llr ON llr.loadoutid = sl.loadoutid
	LEFT JOIN osmium.loadouthistory AS lh ON lh.loadoutid = sl.loadoutid AND lh.revision = llr.latestrevision
	LEFT JOIN osmium.fittings AS f ON f.fittinghash = lh.fittinghash
	WHERE v.accountid = $1 AND v.creationdate >= $2 AND reputationgiventodest <> 0
	ORDER BY creationdate DESC',
	array(
		$_GET['accountid'],
		time() - 86400 * 365.25 / 12,
		\Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT,
		\Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
		$a['accountid'],
	)
);
$lastday = null;
$first = true;
$data = array();
$ul = $preputation->appendCreate('ul');



function make_target(\Osmium\DOM\Document $p, $d) {
	if($d['targettype'] == \Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT) {
		if($d['name'] !== null) {
			return $p->element('a', [
				'o-rel-href' => '/loadout/'.$d['loadoutid'],
				$d['name'],
			]);
		} else {
			return $p->element('small', 'Private/hidden loadout');
		}
	} else if($d['targettype'] == \Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT) {
		if($d['name'] !== null) {
			return [
				'Comment ',
				$p->element('a', [
					'o-rel-href' => '/loadout/'.$d['loadoutid']
					.$p->formatQueryString([ 'jtc' => $d['targetid1'] ]).'#c'.$d['targetid1'],

					'#'.$d['targetid1'],
				]),
				' on ',
				$p->element('a', [
					'o-rel-href' => '/loadout/'.$d['loadoutid'],
					$d['name']
				]),
			];
		} else {
			return $p->element('small', 'Comment on a private/hidden loadout');
		}
	}
}

function make_reputation_day(\Osmium\DOM\Document $p, $day, $data) {
	global $votetypes;

	$net = 0;
	foreach($data as $d) $net += $d['reputationgiventodest'];

	$li = $p->createElement('li');
	$li->appendCreate('h4', [
		$day,
		[ 'span', [ 'class' => $net >= 0 ? 'positive' : 'negative', (string)$net ] ],
	]);

	$tbody = $li->appendCreate('table', [ 'class' => 'd' ])->appendCreate('tbody');

	foreach($data as $d) {
		$rep = $d['reputationgiventodest'];
		$tbody->appendCreate('tr', [
			[ 'td', $rep >= 0 ? [ 'class' => 'rep positive', '+'.$rep ]
			  : [ 'class' => 'rep negative', (string)$rep ] ],
			[ 'td', [ 'class' => 'time', date('H:i', $d['creationdate']) ] ],
			[ 'td', [ 'class' => 'type', $votetypes[$d['type']] ] ],
		])->appendCreate('td', [ 'class' => 'l' ])->append(make_target($p, $d));
	}

	return $li;
}

while($r = \Osmium\Db\fetch_assoc($repchangesq)) {
	$day = date('Y-m-d', $r['creationdate']);
	if($lastday !== $day) {
		if($first) $first = false;
		else {
			$ul->appendChild(make_reputation_day($p, $lastday, $data));
		}

		$lastday = $day;
		$data = array();
	}

	$data[] = $r;
}

if($first) {
	$preputation->appendCreate('p', [
		'class' => 'placeholder',
		'No reputation changes this month.',
	]);
} else {
	$ul->appendChild(make_reputation_day($p, $day, $data));
}



$pvotes = $content->appendCreate('section', [ 'id' => 'votes', 'class' => 'psection' ]);

list($total) = \Osmium\Db\fetch_row(
	\Osmium\Db\query_params(
		'SELECT COUNT(voteid) FROM osmium.votes WHERE fromaccountid = $1',
		array($_GET['accountid'])
		));
list($offset, , $pol) = $p->makePagination($total, [ 'name' => 'vp', 'perpage' => 25, 'anchor' => '#votes' ]);

$pvotes->appendCreate('h2', [
	'Votes cast',
	[ 'small', $p->formatExactInteger($total).' votes cast' ]
]);

if($pol !== '') $pvotes->append($pol);

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
		$a['accountid'],
	)
);

$tbody = $pvotes->appendCreate('table', [ 'class' => 'd' ])->appendCreate('tbody');

$first = true;
while($v = \Osmium\Db\fetch_assoc($votesq)) {
	$first = false;
	$tbody->appendCreate('tr', [
		[ 'td', [ 'class' => 'date', $p->formatRelativeDate($v['creationdate']) ] ],
		[ 'td', [ 'class' => 'type', $votetypes[$v['type']] ] ],
	])->appendCreate('td', [ 'class' => 'l' ])->append(make_target($p, $v));
}

if($first) {
	$pvotes->appendCreate('p', [
		'class' => 'placeholder',
		'No votes cast.',
	]);
}



$ctx = new \Osmium\DOM\RenderContext();
$ctx->relative = '..';
$p->snippets[] = 'view_profile';
$p->data['defaulttab'] = $myprofile ? 1 : 0;
$p->render($ctx);
