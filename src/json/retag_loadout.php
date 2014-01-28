<?php
/* Osmium
 * Copyright (C) 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Json\RetagLoadout;

require __DIR__.'/../../inc/root.php';

$json = [];

if(!isset($_POST['tags']) || !isset($_POST['loadoutid'])) {
	$json['error'] = 'Must supply tags and loadoutid.';
	\Osmium\Chrome\return_json($json);
	die();
}

$loadoutid = (int)$_POST['loadoutid'];
$a = \Osmium\State\get_state('a');

if(!isset($a['accountid'])) {
	$json['error'] = 'Must be logged in.';
	\Osmium\Chrome\return_json($json);
	die();
}

if(!\Osmium\State\can_view_fit($loadoutid)) {
	$json['error'] = 'Nonexistent loadout.';
	\Osmium\Chrome\return_json($json);
	die();
}

$fit = \Osmium\Fit\get_fit($loadoutid);

if($fit === false) {
	$json['error'] = "get_fit() returned false, please report";
	\Osmium\Chrome\return_json($json);
	die();
}

if(!\Osmium\State\can_access_fit($fit) || !\Osmium\State\is_fit_green($loadoutid)) {
	$json['error'] = "Please refresh page and try again.";
	\Osmium\Chrome\return_json($json);
	die();
}

$authorid = \Osmium\Db\fetch_row(
	\Osmium\Db\query_params(
		'SELECT accountid FROM osmium.loadouts WHERE loadoutid = $1',
		array($loadoutid)
	)
)[0];

if(!($a['accountid'] == $authorid || (
	\Osmium\Reputation\is_fit_public($fit) && \Osmium\Reputation\has_privilege(
		\Osmium\Reputation\PRIVILEGE_RETAG_LOADOUTS
	)
))) {
	$json['error'] = "Missing privelege.";
	\Osmium\Chrome\return_json($json);
	die();
}

$fit['metadata']['tags'] = explode(' ', $_POST['tags']);
$errors = [];

\Osmium\Fit\sanitize_tags($fit, $errors, true);

if($errors !== []) {
	$json['error'] = array_shift($errors);
	\Osmium\Chrome\return_json($json);
	die();
}

$ret = \Osmium\Fit\commit_loadout($fit, $authorid, $a['accountid'], $error);
if($ret === false) {
	$json['error'] = "Could not commit the loadout ({$error}).";
	\Osmium\Chrome\return_json($json);
	die();
}

$json['dest'] = \Osmium\Fit\get_fit_uri(
	$loadoutid, $fit['metadata']['visibility'], $fit['metadata']['privatetoken']
);

\Osmium\Chrome\return_json($json);
