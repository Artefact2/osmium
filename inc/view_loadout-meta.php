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

function print_list($heading, array $elements) {
	if(count($elements) === 0) return;
	echo "<h2>".$heading."</h2>\n<ul>\n";

	foreach($elements as $v) {
		if(is_array($v)) {
			if(is_array($v[0])) {
				$img = \Osmium\Chrome\sprite(RELATIVE, '', $v[0][0], $v[0][1], $v[0][2], $v[0][3], 16);
			} else {
				$img = "<img src='".RELATIVE."/static-".\Osmium\STATICVER."/icons/".$v[0]."' alt='' />";
			}
			$v = $img." ".$v[1];
			$c = ' class="hasleadicon"';
		} else {
			$c = '';
		}

		echo "<li$c>".$v."</li>\n";
	}

	echo "</ul>\n";
}

$perms = array();

switch($fit['metadata']['view_permission']) {

case \Osmium\Fit\VIEW_EVERYONE:
	$perms[] =  "This loadout can be viewed by <strong>anyone</strong>.";
	break;

case \Osmium\Fit\VIEW_PASSWORD_PROTECTED:
	$perms[] = [
		[ 0, 25, 32, 32 ],
		"This loadout can be viewed by <strong>anyone</strong>, provided they have the <strong>password</strong>."
	];
	break;

case \Osmium\Fit\VIEW_ALLIANCE_ONLY:
	if($author['apiverified'] === 't' && $author['allianceid'] > 0) {
		$perms[] = [
			[ 2, 13, 64, 64 ],
			"This loadout can only be viewed by members of <strong>"
			.\Osmium\Chrome\escape($author['alliancename'])."</strong> only"
		];
	} else {
		$perms[] = [
			[ 1, 25, 32, 32 ],
			"This loadout is marked as alliance only, but the author is not in any alliance (or has not verified his account). This loadout can effectively be viewed by its <strong>owner only</strong>."
		];
	}
	break;

case \Osmium\Fit\VIEW_CORPORATION_ONLY:
	if($author['apiverified'] === 't') {
		$perms[] = [
			[ 3, 13, 64, 64 ],
			"This loadout can only be viewed by members of <strong>"
			.\Osmium\Chrome\escape($author['corporationname'])."</strong> only."
		];
	} else {
		$perms[] = [
			[ 1, 25, 32, 32 ],
			"This loadout is marked as corporation only, but the author is not in any corporation (or has not verified his account). This loadout can effectively be viewed by its <strong>owner only</strong>."
		];
	}
	break;

case \Osmium\Fit\VIEW_OWNER_ONLY:
	$perms[] = [
		[ 1, 25, 32, 32 ],
		"This loadout can be viewed by its <strong>owner only</strong>."
	];
	break;

case \Osmium\Fit\VIEW_GOOD_STANDING:
	$perms[] = [
		[ 5, 28, 32, 32 ],
		"This loadout can be view by its owner and his contacts with <strong>good standings</strong> only (includes corporation and alliance)."
	];
	break;

case \Osmium\Fit\VIEW_EXCELLENT_STANDING:
	$perms[] = [
		[ 4, 28, 32, 32 ],
		"This loadout can be view by its owner and his contacts with <strong>excellent standings</strong> only (includes corporation and alliance)."
	];
	break;

}

if($loadoutid !== false) {
	switch($fit['metadata']['edit_permission']) {

	case \Osmium\Fit\EDIT_OWNER_ONLY:
		$perms[] = [
			[ 1, 25, 32, 32 ],
			"This loadout can be edited by its <strong>owner only</strong>."
		];
		break;

	case \Osmium\Fit\EDIT_OWNER_AND_FITTING_MANAGER_ONLY:
		if($author['apiverified'] === 't') {
			$perms[] = [
				[ 3, 13, 64, 64 ],
				"This loadout can be edited by its <strong>owner</strong> or by people in <strong>"
				.\Osmium\Chrome\escape($author['corporationname'])
				."</strong> with the <strong>fitting manager</strong> role."
			];
		} else {
			$perms[] = [
				[ 1, 25, 32, 32 ],
				"This loadout is marked as editable its owner and by people in the same corporation with the fitting manager role, but the owner is not in a corporation (or has not verified his account). This loadout can effectively be edited by its <strong>owner only</strong>."
			];
		}
		break;

	case \Osmium\Fit\EDIT_CORPORATION_ONLY:
		if($author['apiverified'] === 't') {
			$perms[] = [
				[ 3, 13, 64, 64 ],
				"This loadout can be edited by any person in <strong>"
				.\Osmium\Chrome\escape($author['corporationname'])."</strong>."
			];
		} else {
			$perms[] = [
				[ 1, 25, 32, 32 ],
				"This loadout is marked as editable by the corporation of the owner, but the owner is not in a corporation (or has not verified his account). This loadout can effectively be edited by its <strong>owner only</strong>."
			];
		}
		break;

	case \Osmium\Fit\EDIT_ALLIANCE_ONLY:
		if($author['apiverified'] === 't' && $author['allianceid'] > 0) {
			$perms[] = [
				[ 2, 13, 64, 64 ],
				"This loadout can be edited by any person in <strong>"
				.\Osmium\Chrome\escape($author['alliancename'])."</strong>."
			];
		} else {
			$perms[] = [
				[ 1, 25, 32, 32 ],
				"This loadout is marked as editable by the alliance of the owner, but the owner is not in an alliance (or has not verified his account). This loadout can effectively be edited by its <strong>owner only</strong>."
			];
		}
		break;

	}
} else {
	$perms[] = "This loadout <strong>cannot</strong> be edited, but it can still be forked by anyone.";
}

if($loadoutid === false) {
	$fit['metadata']['visibility'] = \Osmium\Fit\VISIBILITY_PRIVATE;
}

switch($fit['metadata']['visibility']) {

case \Osmium\Fit\VISIBILITY_PUBLIC:
	$perms[] = "This loadout is <strong>public</strong>. It will be indexed and appear on the search results of anyone who can access the loadout.";
	break;

case \Osmium\Fit\VISIBILITY_PRIVATE:
	$perms[] = [
		[ 4, 13, 64, 64 ],
		"This loadout is <strong>private</strong>. It will not be indexed and will not appear on any search results. Only people with the correct URI will be able to access the loadout."
	];
	break;

}

$moderated = $loadoutid !== false && \Osmium\Reputation\is_fit_public($fit);
if(!$moderated) {
	$perms[] = "Due to the nature of this loadout, it is not subject to public moderation, and votes cast on it or its comments yield no reputation.";
}

print_list("Permissions and visibility", $perms);

$actions = array();

if($loadoutid !== false && \Osmium\Flag\is_fit_flaggable($fit)) {
	$actions[] = "<strong><a href='".RELATIVE."/flag/".$loadoutid."' class='dangerous'>⚑ Flag this loadout</a></strong>: report that this loadout requires moderator attention.";
}

if($can_edit) {
	$actions[] = "<strong><a href='".RELATIVE."/edit/".$loadoutid."?tok=".\Osmium\State\get_token()."&amp;revision=".$fit['metadata']['revision']."' rel='nofollow'>{$modprefix}Edit this loadout</a></strong>: change the lodaout (older versions will still be visible and can be restored through the history).";
	$actions[] = "<strong><a class='dangerous confirm' href='".RELATIVE."/delete/".$loadoutid."?tok=".\Osmium\State\get_token()."' rel='nofollow'>{$modprefix}Delete this loadout</a></strong>: permanently remove the loadout and all its history.";
}

if($loggedin && $loadoutid !== false) {
	list($fav) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT COUNT(loadoutid) FROM osmium.accountfavorites
		WHERE accountid = $1 AND loadoutid = $2',
		array(
			$a['accountid'],
			$loadoutid
		)
	));

	if($fav) {
		$title = 'Remove from your favorite loadouts';
		$favimg = [ 3, 25, 32, 32 ];
	} else {
		$title = 'Add to your favorite loadouts';
		$favimg = [ 2, 25, 32, 32 ];
	}

	$actions[] = [
		$favimg,
		"<a href='".RELATIVE."/favorite/".$loadoutid."?tok=".\Osmium\State\get_token()."&amp;redirect=loadout'>"
		.$title."</a>: favorite loadouts are listed on your <a href='".RELATIVE."/profile/".$a['accountid']."#pfavorites'>profile</a> page."
	];
}

if(isset($fit['ship']['typename'])) {
	$shipname = \Osmium\Chrome\escape($fit['ship']['typename']);
	$actions[] = "<a href='".RELATIVE."/search?q=".urlencode('@ship "'.$fit['ship']['typename'].'"')
		."'>Browse all ".$shipname." loadouts</a>";
}

if(isset($rauthorname)) {
	$actions[] = "<a href='".RELATIVE."/search?q="
		.urlencode('@author "'.\Osmium\Chrome\escape($rauthorname).'"')
		."'>Browse loadouts from the same author</a>";
}

if(isset($fit['ship']['typeid'])) {
	$actions[] = [
		"external.svg",
		"<a href='//zkillboard.com/ship/".$fit['ship']['typeid']."/'>".$shipname." activity on zKillboard</a>"
	];
}

print_list("Actions", $actions);
