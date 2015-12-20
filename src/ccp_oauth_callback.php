<?php
/* Osmium
 * Copyright (C) 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Page\CCPOAuthCallback;

require __DIR__.'/../inc/root.php';
require \Osmium\ROOT.'/inc/login-common.php';

$json = \Osmium\State\ccp_oauth_verify($estr);
if($json === false) \Osmium\fatal(400, '(stage 1) '.$estr);

if(!isset($json['access_token']) || !isset($json['token_type']) || $json['token_type'] !== 'Bearer') {
	\Osmium\fatal(400, '(stage 1) CCP OAuth returned nonsensical JSON, please report.');
}

$cjson = \Osmium\State\ccp_oauth_get_characterid($json['access_token'], $estr);
if($cjson === false) \Osmium\fatal(400, '(stage 2) '.$estr);

if(!isset($cjson['CharacterID']) || !isset($cjson['CharacterOwnerHash']) || !is_numeric($cjson['CharacterID']) || $cjson['CharacterID'] <= 0) {
	\Osmium\fatal(400, '(stage 2) CCP OAuth returned nonsensical JSON, please report.');
}

$cid = $cjson['CharacterID'];
$oid = $cjson['CharacterOwnerHash'];

$payload = \Osmium\State\get_state('ccp_oauth_payload');

if(!isset($payload['action'])) \Osmium\fatal(400, 'Missing payload.');

switch((string)$payload['action']) {

case 'import':
	\Osmium\State\assume_logged_in();
	\Osmium\State\put_state('access_token', $json['access_token']);
	\Osmium\State\put_state('refresh_token', $json['refresh_token']);
	\Osmium\State\put_state('import_CharacterID', $cjson['CharacterID']);
	\Osmium\State\put_state('import_CharacterName', $cjson['CharacterName']);
	header('Location: ../../import', true, 303);
	die();
break;

case 'export':
	\Osmium\State\assume_logged_in();
	\Osmium\State\put_state('access_token', $json['access_token']);
	\Osmium\State\put_state('refresh_token', $json['refresh_token']);
	$target = isset($payload['request_uri']) ? $payload['request_uri'] : '../../';
	$loadoutid = $payload['loadoutid'];
	$fit = \Osmium\Fit\get_fit($loadoutid);

	$modules = \Osmium\Fit\get_modules($fit);
	
		$export = [];
		$export['name'] = $fit['metadata']['name'];
		$export['description'] = $fit['metadata']['description'];
		$export['ship']['id'] = (int)$fit['ship']['typeid'];
		$export['ship']['name'] = $fit['ship']['typename'];
		$export['ship']['href'] = "https://public-crest.eveonline.com/types/" . $fit['ship']['typeid'] . "/";
		$export['items'] = [];

		//rigs
		if (isset($modules['rig'])){
		$i=0;
		foreach ($modules['rig'] as $item) {
			$flag = 92 + $i;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		$i++;
		}}

		//subsystem slots
		if (isset($modules['subsystem'])){
		$i=0;
		foreach ($modules['subsystem'] as $item) {
			$flag = 125 + $i;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		$i++;
		}}

		//low slots
		if (isset($modules['low'])){
		$i=0;
		foreach ($modules['low'] as $item) {
			$flag = 11 + $i;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		$i++;
		}}

		//medium slots
		if (isset($modules['medium'])){
		$i=0;
		foreach ($modules['medium'] as $item) {
			$flag = 19 + $i;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		$i++;
		}}

		//high slots
		if (isset($modules['high'])){
		$i=0;
		foreach ($modules['high'] as $item) {
			$flag = 27 + $i;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		$i++;
		}}

		//Drones (default preset only)
		if (isset($fit['dronepresets'][0]['drones'])){
		foreach ($fit['dronepresets'][0]['drones'] as $item) {
			$flag = 87;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = $item['quantityinbay'] + $item['quantityinspace'];
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		}}

		for ($i = 0; $i < count($fit['chargepresets']); $i++) {

		//charges high slots (all presets)
		if (isset($fit['chargepresets'][$i]['charges']['high'])){
		foreach (array_unique($fit['chargepresets'][$i]['charges']['high'], SORT_REGULAR) as $item) {
			$flag = 5;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		}}

		//charges medium slots (all presets)
		if (isset($fit['chargepresets'][$i]['charges']['medium'])){
		foreach (array_unique($fit['chargepresets'][$i]['charges']['medium'], SORT_REGULAR) as $item) {
			$flag = 5;
			$nextItem = [];	
			$nextItem['flag'] = $flag;
			$nextItem['quantity'] = 1;
			$nextItem['type']['id'] = $item['typeid'];
			$nextItem['type']['name'] = $item['typename'];
			$nextItem['type']['href'] = "https://public-crest.eveonline.com/types/" . $item['typeid'] . "/";
			$export['items'][] = $nextItem;
		}}

		}

	$fitting_data = json_encode($export);

	$result = \Osmium\State\ccp_oauth_post_fitting($cjson['CharacterID'],$fitting_data);

	if($result['httpCode'] == '201') {
	header('Location: '.$target, true, 303);
	} else {
	\Osmium\fatal(400, $result['message']);
	}
	
break;

case 'associate':
	\Osmium\State\assume_logged_in();
	$a = \Osmium\State\get_state('a');
	$c = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT ccpoauthownerhash  FROM osmium.accountcredentials
		WHERE ccpoauthcharacterid = $1',
		[ $cid ]
	));

	if($c !== false && $c['ccpoauthownerhash'] === $oid) {
		/* TODO: offer to delete the old association when it is safe */
		\Osmium\fatal(400, 'This character is already associated to another account. Please sign in using that character then delete the association in the settings page.');
	} else {
		\Osmium\Db\query('BEGIN');

		if($c !== false) {
			/* Hash is different, meaning the character changed
			 * ownership, so the old association is moot. Safe to
			 * delete. */
			\Osmium\Db\query_params(
				'DELETE FROM osmium.accountcredentials
				WHERE ccpoauthcharacterid = $1',
				[ $cid ]
			);
		}

		\Osmium\Db\query_params(
			'INSERT INTO osmium.accountcredentials (accountid, ccpoauthcharacterid, ccpoauthownerhash)
			VALUES ($1, $2, $3)',
			[ $a['accountid'], $cid, $oid ]
		);
		\Osmium\Db\query('COMMIT');
		header('Location: ../../settings#s_accountauth', true, 303);
		die();
	}
	break;

case 'signin':
case 'signup':
	\Osmium\State\assume_logged_out();
	$target = isset($payload['request_uri']) ? $payload['request_uri'] : '../../';
	$remember = isset($payload['remember']) ? $payload['remember'] : false;

	$a1 = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT accountid FROM osmium.accountcredentials
		WHERE ccpoauthcharacterid = $1 AND ccpoauthownerhash = $2',
		[ $cid, $oid ]
	));

	$a2 = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT accountid FROM osmium.accounts
		WHERE characterid = $1',
		[ $cid ]
	));

	if($a1 === false && $a2 === false) {
		/* First case: brand new character Osmium has never heard
		 * of. Create an account then authenticate the account with
		 * it. */

		if($payload['action'] === 'signin') {
			\Osmium\fatal(400, 'This character is not associated with an Osmium account. Use the "Sign up" page if you want to create an account using this character.');
		}

		\Osmium\Db\query('BEGIN');
		$nickname = 'User'.$cid.'-'.\Osmium\State\get_nonce(); /* XXX: check for collisions… */
		$accountid = \Osmium\Login\register_account($nickname);
		\Osmium\Db\query_params(
			'DELETE FROM osmium.accountcredentials
			WHERE ccpoauthcharacterid = $1',
			[ $cid ]
		);
		\Osmium\Db\query_params(
			'INSERT INTO osmium.accountcredentials (accountid, ccpoauthcharacterid, ccpoauthownerhash)
			VALUES ($1, $2, $3)',
			[ $accountid, $cid, $oid ]
		);
		\Osmium\Db\query('COMMIT');

		/* TODO: this call may fail as it relies on the EVE API,
		 * either roll everything back (difficult) or show the error
		 * to the user */
		\Osmium\State\register_ccp_oauth_character_account_auth($accountid, $cid);

		\Osmium\State\do_post_login($accountid, $remember);
		header('Location: '.$target, true, 303);
	} else if($a2 !== false && $a1 === false) {
		/* Second case: character not yet used as a way of logging in
		 * with OAuth, but an account is verified with this
		 * character. In this case, just associate the character with
		 * the existing account. */

		\Osmium\Db\query_params(
			'INSERT INTO osmium.accountcredentials (accountid, ccpoauthcharacterid, ccpoauthownerhash)
			VALUES ($1, $2, $3)',
			[ $a2['accountid'], $cid, $oid ]
		);

		\Osmium\State\do_post_login($a2['accountid'], $remember);
		header('Location: '.$target, true, 303);
	} else if($a1 !== false) {
		/* Third case: character is already used as a way of logging
		 * in, just log the user in. */

		\Osmium\State\do_post_login($a1['accountid'], $remember);
		header('Location: '.$target, true, 303);
	}
	break;

default:
	\Osmium\fatal(400, 'Unknown payload action.');

}
