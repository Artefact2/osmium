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

namespace Osmium\State;

require __DIR__.'/state-cache.php';
require __DIR__.'/state-fit.php';

const MINIMUM_PASSWORD_ENTROPY = 40;

/** Default expire duration for the token cookie. 7 days. */
const COOKIE_AUTH_DURATION = 604800;

const CHARACTER_SHEET_ACCESS_MASK = 8;
const REQUIRED_ACCESS_MASK_WITHOUT_CONTACTS = 33554440; /* CharacterSheet+AccountStatus*/
const REQUIRED_ACCESS_MASK_WITH_CONTACTS = 33554456; /* CharacterSheet+AccountStatus+Contacts*/

/**
 * Checks whether the current user is logged in.
 */
function is_logged_in() {
	$a = get_state('a', array());
	return isset($a['accountid']) && $a['accountid'] > 0;
}

/**
 * Hook that gets called when the user successfully logged in (either
 * directly from the login form, or from a cookie token).
 *
 * @param $account_name the name of the account the user logged into.
 *
 * @param $use_tookie if true, a cookie must be set to mimic a
 * "remember me" feature.
 */
function do_post_login($account_name, $use_cookie = false) {
	/* Get rid of old $_SESSION */
	$_SESSION = array();
	session_write_close();

	$q = \Osmium\Db\query_params(
		'SELECT accountid, accountname, nickname,
		creationdate, lastlogindate, keyid, verificationcode, apiverified,
		characterid, charactername, corporationid, corporationname, allianceid, alliancename,
		ismoderator, isfittingmanager
		FROM osmium.accounts WHERE accountname = $1',
		array($account_name)
	);
	$a = \Osmium\Db\fetch_assoc($q);
	check_api_key($a);

	session_id("Account-".$a['accountid']."-".session_id());
	session_start();
	$_SESSION['__osmium_state'] = array(
		'a' => $a
	);

	if($use_cookie) {
		$token = uniqid('Osmium_', true);
		$account_id = $a['accountid'];
		$attributes = get_client_attributes();
		$expiration_date = time() + COOKIE_AUTH_DURATION;

		\Osmium\Db\query_params(
			'INSERT INTO osmium.cookietokens
			(token, accountid, clientattributes, expirationdate)
			VALUES ($1, $2, $3, $4)',
			array(
				$token,
				$account_id,
				$attributes,
				$expiration_date
			)
		);

		setcookie(
			'Osmium', $token, $expiration_date,
			\Osmium\get_ini_setting('relative_path'),
			\Osmium\HOST,
			\Osmium\HTTPS,
			true
		);
	}

	\Osmium\Db\query_params(
		'UPDATE osmium.accounts SET lastlogindate = $1 WHERE accountid = $2',
		array(
			time(),
			$a['accountid'],
		)
	);
}

/**
 * Logs off the current user.
 *
 * @param $gloal if set to true, also delete all the cookie tokens
 * associated with the current account.
 */
function logoff($global = false) {
	if($global && !is_logged_in()) return;

	if($global) {
		/* Invalidate all cookie tokens */
		\Osmium\Db\query_params(
			'DELETE FROM osmium.cookietokens WHERE accountid = $1',
			array($account_id = get_state('a')['accountid'])
		);
	}

	setcookie(
		'Osmium', false, 0,
		\Osmium\get_ini_setting('relative_path'),
		\Osmium\HOST,
		\Osmium\HTTPS,
		true
	);

	$_SESSION = array();
	session_regenerate_id(true);
	$_SESSION['__osmium_state'] = array();

	if($global) {
		/* Remove all the other sessions with the same account ID */
		shell_exec(
			'find '.escapeshellarg(\Osmium\CACHE_DIRECTORY)
			.' -maxdepth 1 -type f -name "sess_Account-'.(int)$account_id.'-*" -delete'
		);
	}
}

/**
 * Get a string that identifies a visitor. Cookie token-based
 * authentication will only succeed if the client attributes of the
 * visitor match the ones generated when the cookie was set.
 */
function get_client_attributes() {
	if(!isset($_SERVER['REMOTE_ADDR'])) {
		return 'CLI';
	}

	return hash('sha256', serialize(array(
		$_SERVER['REMOTE_ADDR'],
		isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown',
		isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : 'Unknown',
		$_SERVER['HTTP_HOST'],
	)));
}

/**
 * Check whether the client attributes of the current visitor match
 * with given attributes.
 */
function check_client_attributes($attributes) {
	return $attributes === get_client_attributes();
}

/**
 * Prints the login box if the current user is not logged in, or print
 * the logout box if the user is logged in.
 */
function print_login_or_logout_box($relative, $notifications) {
	if(is_logged_in()) {
		print_logoff_box($relative, $notifications);
	} else {
		print_login_box($relative);
	}
}

/** @internal */
function print_login_box($relative) {
	echo "<div id='state_box' class='login'>\n";

	if(\Osmium\get_ini_setting('https_available')
	   && !\Osmium\HTTPS
	   && \Osmium\get_ini_setting('prefer_secure_login')) {
		$relative = rtrim('https://'.$_SERVER['HTTP_HOST'].\Osmium\get_ini_setting('relative_path'), '/');
	}

	echo "<form method='post' action='{$relative}/login'>\n"
		."<p>\n<span class='wide'>\n<input type='text' name='account_name' placeholder='Account name' />\n"
		."<input type='password' name='password' placeholder='Password' />\n"
		."<input type='submit' name='__osmium_login' value='Login' />"
		." (<small><input type='checkbox' name='remember' id='remember' checked='checked' />"
		." <label for='remember'>Remember me</label></small>)</span>\n"
		."<span class='narrow'><a href='{$relative}/login'>Login</a></span>"
		." or <a href='$relative/register'>Register</a>\n"
		."<input type='hidden' name='request_uri' value='"
		.\Osmium\Chrome\escape($_SERVER['REQUEST_URI'])."' />\n"
		."</p>\n</form>\n</div>\n";
}

/** @internal */
function print_logoff_box($relative, $notifications) {
	$a = get_state('a');
	$tok = urlencode(get_token());

	$portrait = '';
	if(isset($a['apiverified']) && $a['apiverified'] === 't' &&
	   isset($a['characterid']) && $a['characterid'] > 0) {
		$id = $a['characterid'];
		$portrait = "<img src='//image.eveonline.com/Character/${id}_128.jpg' alt='' class='portrait' /> ";
	}

	echo "<div id='state_box' class='logout'>\n<p>\n"
		."<span class='wide'>Logged in as </span>$portrait<strong>"
		.\Osmium\Chrome\format_character_name($a, $relative)
		."</strong> (<a class='rep' href='$relative/privileges'>"
		.\Osmium\Chrome\format_reputation(\Osmium\Reputation\get_current_reputation())
		."</a>). <a id='ncount' data-count='$notifications' href='$relative/notifications'"
		." title='$notifications new notification(s)'>$notifications</a>"
		." <a href='$relative/logout?tok=$tok' title='Logs you out on this browser only.'>Logout</a>"
		." <small>(<a href='$relative/logout?tok=$tok&amp;global=1' title='Logs you out on all the machines where you are currently logged in, and also invalidates all cookies issued in the past.'>all</a>)</small>\n"
		."</p>\n</div>\n";
}

/** @internal */
function print_api_link() {
	echo "<p>\nYou can create an API key here:</p>\n<ul>\n"
		."<li>With ContactList access: <strong><a href='https://support.eveonline.com/api/Key/CreatePredefined/"
		.REQUIRED_ACCESS_MASK_WITH_CONTACTS."'>https://support.eveonline.com/api/Key/CreatePredefined/"
		.REQUIRED_ACCESS_MASK_WITH_CONTACTS."</a></strong></li>\n"
		."<li>Without ContactList access: <strong><a href='https://support.eveonline.com/api/Key/CreatePredefined/"
		.REQUIRED_ACCESS_MASK_WITHOUT_CONTACTS."'>https://support.eveonline.com/api/Key/CreatePredefined/"
		.REQUIRED_ACCESS_MASK_WITHOUT_CONTACTS."</a></strong></li>\n"
		."</ul>\n<p>\nBe aware that you need the ContactList access check on your API if you want to use the standings based visibility.<br />\n";
	echo "<strong>Make sure that you only select one character, do not change any of the checkboxes on the right.</strong>\n</p>\n";
	echo "<p>\nIf you are still having errors despite having updated your API key, you will have to wait for the cache to expire, or just create a whole new API key altogether (no waiting involved!).\n</p>\n";
}

/**
 * Check if a given password is strong enough to be used for an
 * account.
 *
 * @returns true if the password is strong enough, or a string
 * containing an error message.
 */
function is_password_sane($pw) {
	$choices = 0;

	if(preg_match('%[a-z]%', $pw)) $choices += 26;
	if(preg_match('%[A-Z]%', $pw)) $choices += 26;
	if(preg_match('%[0-9]%', $pw)) $choices += 10;
	if(preg_match('%[^a-zA-Z0-9]%', $pw)) $choices += 32;

	$entropy = strlen($pw) * log(max(1, $choices)) / log(2);

	if($entropy < MINIMUM_PASSWORD_ENTROPY) {
		return "This password is too weak (score: ".round($entropy, 2).", needs at least ".MINIMUM_PASSWORD_ENTROPY."). Try a longer password, or try adding symbols, numbers, or mixed case letters.";
	}

	return true;
}

/**
 * Returns a password hash string derived from $pw. Already does
 * salting internally.
 */
function hash_password($pw) {
	if(\PHP_VERSION_ID >= 50500) {
		return \password_hash($pw, \PASSWORD_DEFAULT);
	}

	require_once \Osmium\ROOT.'/lib/PasswordHash.php';
	$pwHash = new \PasswordHash(10, false);
	return $pwHash->HashPassword($pw);
}

/**
 * Checks whether a password matches against a hash returned by
 * hash_password().
 *
 * @param $pw the password to test.
 *
 * @param $hash the hash previously returned by hash_password().
 *
 * @returns true if $pw matches against $hash.
 */
function check_password($pw, $hash) {
	if(\PHP_VERSION_ID >= 50500) {
		$ok = \password_verify($pw, $hash);
		if($ok === true) return true;
		/* If password_verify won't check phpass-generated
		 * passwords, so don't return false just yet */
	}

	require_once \Osmium\ROOT.'/lib/PasswordHash.php';
	$pwHash = new \PasswordHash(10, false);
	return $pwHash->CheckPassword($pw, $hash);
}

/**
 * Checks whether a password needs a rehash.
 */
function password_needs_rehash($hash) {
	if(\PHP_VERSION_ID >= 50500) {
		return \password_needs_rehash($hash, \PASSWORD_DEFAULT);
	}

	require_once \Osmium\ROOT.'/lib/PasswordHash.php';
	$pwHash = new \PasswordHash(10, false);
	$foo = $pwHash->HashPassword('foo', $hash);

	$h = substr($hash, 0, strrpos($hash, '$'));
	$f = substr($foo, 0, strrpos($foo, '$'));
	return $h !== $f;
}

/**
 * Try to login the current visitor, taking credentials directly from
 * $_POST.
 *
 * @returns true on success, or a string containing an error message.
 */
function try_login() {
	if(is_logged_in()) return;

	$account_name = $_POST['account_name'];
	$pw = $_POST['password'];
	$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

	$hash = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT passwordhash FROM osmium.accounts WHERE accountname = $1',
		array($account_name)
	));

	if($hash !== false && check_password($pw, $hash[0])) {
		if(password_needs_rehash($hash[0])) {
			$newhash = hash_password($pw);
			\Osmium\Db\query_params(
				'UPDATE osmium.accounts SET passwordhash = $1
				WHERE accountname = $2',
				array($newhash, $account_name)
			);
		}

		do_post_login($account_name, $remember);
		return true;
	} else {
		return 'Invalid credentials. Please check your account name and password.';
	}
}

/**
 * Try to re-log a user from a cookie token. If the token happens to
 * be invalid, the cookie is deleted.
 */
function try_recover() {
	if(is_logged_in()) return;

	if(!isset($_COOKIE['Osmium']) || empty($_COOKIE['Osmium'])) return;
	$token = $_COOKIE['Osmium'];
	$now = time();
	$login = false;

	list($has_token) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT COUNT(token) FROM osmium.cookietokens
		WHERE token = $1 AND expirationdate >= $2',
		array($token, $now)
	));

	if($has_token == 1) {
		list($account_id, $client_attributes) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
			'SELECT accountid, clientattributes
			FROM osmium.cookietokens WHERE token = $1',
			array($token)
		));

		if(check_client_attributes($client_attributes)) {
			$k = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
				'SELECT accountname FROM osmium.accounts WHERE accountid = $1',
				array($account_id)
			));
			if($k !== false) {
				list($name) = $k;
				do_post_login($name, true);
				$login = true;
			}
		}

		\Osmium\Db\query_params('DELETE FROM osmium.cookietokens WHERE token = $1', array($token));
	}

	if(!$login) {
		/* XXX */
		//logoff(false); /* Delete that erroneous cookie */
	}
}

/**
 * Check that a given API key can be used to API-verify an account.
 *
 * @returns true, or a string containing an error message.
 */
function check_api_key_sanity($accountid, $keyid, $vcode, &$characterid = null, &$charactername = null) {
	$api = \Osmium\EveApi\fetch(
		'/account/APIKeyInfo.xml.aspx',
		array('keyID' => $keyid, 'vCode' => $vcode)
	);

	if($api === false) {
		return "API server returned a 403. Invalid credentials?";
	}

	if(!($api instanceof \SimpleXMLElement)) {
		/* Aouch */
		return "API sever did not return well-formed XML. Network issue, or internal CCP screwage, sorry!";
	}

	if(isset($api->error) && !empty($api->error)) {
		return '('.((int)$api->error['code']).') '.\Osmium\Chrome\escape((string)$api->error);
	}

	if((string)$api->result->key["type"] !== 'Character') {
	    return 'Invalid key type. Make sure you only select one character.';
	}

	if((int)$api->result->key["accessMask"] !== REQUIRED_ACCESS_MASK_WITH_CONTACTS
	   && (int)$api->result->key["accessMask"] !== REQUIRED_ACCESS_MASK_WITHOUT_CONTACTS ) {
		return 'Incorrect access mask. Please set it to '.REQUIRED_ACCESS_MASK_WITH_CONTACTS
			.' (with ContactList) or '.REQUIRED_ACCESS_MASK_WITHOUT_CONTACTS
			.' (without ContactList), or use the link above.';
	}

	$characterid = (int)$api->result->key->rowset->row['characterID'];
	$charactername = (string)$api->result->key->rowset->row['characterName'];
	if($accountid !== null) {
		list($c) = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT COUNT(accountid) FROM osmium.accounts WHERE accountid <> $1 AND (characterid = $2 OR charactername = $3)', array($accountid, $characterid, $charactername)));
		if($c > 0) {
			return "Character <strong>".\Osmium\Chrome\escape($charactername)."</strong> is already used by another account.";
		}
	}

	return true;
}

/**
 * Check the API key associated with the current account, and update
 * character/corp/alliance values in the database.
 *
 * @returns null on serious error, or a boolean indicating if the user
 * must revalidate his API key.
 */
function check_api_key($a, $initial = false, $timeout = null) {
	if(!isset($a['keyid']) || !isset($a['verificationcode'])
	   || $a['keyid'] === null || $a['verificationcode'] === null) return null;

	$key_id = $a['keyid'];
	$v_code = $a['verificationcode'];
	$must_renew = false;

	$info = \Osmium\EveApi\fetch(
		'/account/APIKeyInfo.xml.aspx',
		array('keyID' => $key_id, 'vCode' => $v_code),
		$timeout
	);

	if($info === false) {
		$must_renew = true;
	} else if(!($info instanceof \SimpleXMLElement)) {
		/* Could be anything major, ignore */
		return null;
	}

	if(isset($info->error) && !empty($info->error)) {
		$err_code = (int)$info->error['code'];
		/* Error code details: http://wiki.eve-id.net/APIv2_Eve_ErrorList_XML */
		if(200 <= $err_code && $err_code < 300) {
			$must_renew = true;
		} else {
			/* Most likely internal error */
			return null;
		}
	}

	if(!$must_renew && (
		(string)$info->result->key["type"] !== 'Character'
		|| (
			(int)$info->result->key['accessMask'] !== REQUIRED_ACCESS_MASK_WITH_CONTACTS
			&& (int)$info->result->key['accessMask'] !== REQUIRED_ACCESS_MASK_WITHOUT_CONTACTS
		) || (
			!$initial && (int)$info->result->key->rowset->row['characterID'] != $a['characterid']
		)
	)) {
		$must_renew = true;
	}

	if($must_renew) {
		\Osmium\Db\query_params('UPDATE osmium.accounts SET
		characterid = null, charactername = null,
		corporationid = null, corporationname = null,
		allianceid = null, alliancename = null,
		isfittingmanager = false, apiverified = false
		WHERE accountid = $1', array($a['accountid']));

		if(!$initial && $a['apiverified'] == 't') {
			/* Notify the user his API key broke down without user intervention */
			\Osmium\Notification\add_notification(
				\Osmium\Notification\NOTIFICATION_TYPE_ACCOUNT_API_KEY_DISABLED,
				null,
				$a['accountid'],
				$key_id
			);
		}

		$a['characterid'] = null;
		$a['charactername'] = null;
		$a['corporationid'] = null;
		$a['corporationname'] = null;
		$a['allianceid'] = null;
		$a['alliancename'] = null;
		$a['apiverified'] = 'f';
	} else if(isset($a['apiverified']) && $a['apiverified'] === 't') {
		$character_id = (int)$info->result->key->rowset->row['characterID'];

		$cinfo = \Osmium\State\get_character_info($character_id, $a, $timeout);
		if($cinfo === false) {
			/* API unavailable? */
			return null;
		}

		list($character_name,
		     $corporation_id, $corporation_name,
		     $alliance_id, $alliance_name,
		     $is_fitting_manager) = $cinfo;

		if($character_id != $a['characterid']
		   || $character_name != $a['charactername']
		   || $corporation_id != $a['corporationid']
		   || $corporation_name != $a['corporationname']
		   || $alliance_id != $a['allianceid']
		   || $alliance_name != $a['alliancename']
		   || $is_fitting_manager != ($a['isfittingmanager'] === 't')) {

			\Osmium\Db\query_params('UPDATE osmium.accounts SET
			characterid = $1, charactername = $2,
			corporationid = $3, corporationname = $4,
		    allianceid = $5, alliancename = $6,
			isfittingmanager = $7
			WHERE accountid = $8', array($character_id, $character_name,
			                             $corporation_id, $corporation_name,
			                             $alliance_id, $alliance_name,
			                             $is_fitting_manager ? 't' : 'f',
			                             $a['accountid']));

			$a['characterid'] = $character_id;
			$a['charactername'] = $character_name;
			$a['corporationid'] = $corporation_id;
			$a['corporationname'] = $corporation_name;
			$a['allianceid'] = $alliance_id;
			$a['alliancename'] = $alliance_name;
			$a['isfittingmanager'] = $is_fitting_manager ? 't' : 'f';
		}

		if((int)$info->result->key['accessMask'] === REQUIRED_ACCESS_MASK_WITH_CONTACTS) {
			/* Only update the contact list if the key actually allows it */
			$ret = update_character_contactlist($a, $timeout);
			if($ret === false) return null;
		} else {
			/* Be safe, the access mask may have changed since the
			 * last update and there may be stale contacts */
			\Osmium\Db\query_params(
				'DELETE FROM osmium.contacts WHERE accountid = $1',
				array($a['accountid'])
			);
		}
	}

	\Osmium\State\put_state('a', $a);
	return $must_renew;
}

function get_character_info($character_id, $a, $timeout = null) {
	$char_info = \Osmium\EveApi\fetch(
		'/eve/CharacterInfo.xml.aspx',
		array('characterID' => $character_id),
		$timeout
	);
	if($char_info === null || $char_info === false) return false;
  
	$character_name = (string)$char_info->result->characterName;
	$corporation_id = (int)$char_info->result->corporationID;
	$corporation_name = (string)$char_info->result->corporation;
	$alliance_id = (int)$char_info->result->allianceID;
	$alliance_name = (string)$char_info->result->alliance;
  
	if($alliance_id == 0) $alliance_id = null;
	if($alliance_name == '') $alliance_name = null;

	$char_sheet = \Osmium\EveApi\fetch(
		'/char/CharacterSheet.xml.aspx',
		array(
			'characterID' => $character_id,
			'keyID' => $a['keyid'],
			'vCode' => $a['verificationcode'],
		),
		$timeout
	);

	if($char_sheet === null || $char_sheet === false) {
		return false;
	}

	$is_fitting_manager = false;
	foreach(($char_sheet->result->rowset ?: array()) as $rowset) {
		if((string)$rowset['name'] != 'corporationRoles') continue;

		foreach($rowset->children() as $row) {
			$name = (string)$row['roleName'];
			if($name == 'roleFittingManager' || $name == 'roleDirector') {
				/* FIXME: roleFittingManager may be implicitly granted by other roles. */
				$is_fitting_manager = true;
				break;
			}
		}

		break;
	}
  
	return array($character_name, $corporation_id, $corporation_name, $alliance_id, $alliance_name, (int)$is_fitting_manager);
}

function update_character_contactlist($a, $timeout = null) {
	$char_contactlist = \Osmium\EveApi\fetch(
		'/char/ContactList.xml.aspx',
		array(
			'characterID' => $a['characterid'],
			'keyID' => $a['keyid'],
			'vCode' => $a['verificationcode'],
		),
		$timeout
	);

	if($char_contactlist === null || $char_contactlist === false) {
		return false;
	}

	\Osmium\Db\query('BEGIN');
	\Osmium\Db\query_params(
		'DELETE FROM osmium.contacts WHERE accountid = $1',
		array($a['accountid'])
	);
  
	foreach(($char_contactlist->result->rowset ?: array()) as $rowset) {
		foreach($rowset->children() as $row) {
			if((float)$row['standing'] > 0) {
				\Osmium\Db\query_params(
					'INSERT INTO osmium.contacts (accountid, contactid, standing) VALUES ($1, $2, $3)',
					array(
						$a['accountid'],
						(int)$row['contactID'],
						(float)$row['standing'],
					)
				);
			}
		}
	}

	\Osmium\Db\query('COMMIT');
	return true;
}

/**
 * Get the session token of the current session. This is randomly
 * generated data, different than $PHPSESSID. (Mainly used for CSRF
 * tokens.)
 */
function get_token() {
	$tok = get_state('logouttoken', null);

	if($tok === null) {
		put_state('logouttoken', $tok = uniqid('OsmiumTok_', true));
	}

	return $tok;
}

/**
 * Get the clientid of the current user (inserts a new row in the
 * clients table if necessary).
 */
function get_client_id() {
	$remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
	$useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
	$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : 'Unknown';
	$accountid = get_state('a', array('accountid' => null))['accountid'];

	$client = array($remote, $useragent, $accept, $accountid);

	$r = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT clientid FROM osmium.clients WHERE remoteaddress = $1 AND useragent = $2 AND accept = $3 AND ($4::integer IS NULL AND loggedinaccountid IS NULL OR loggedinaccountid = $4)', $client));
	if($r === false) {
		$r = \Osmium\Db\fetch_row(\Osmium\Db\query_params('INSERT INTO osmium.clients (remoteaddress, useragent, accept, loggedinaccountid) VALUES ($1, $2, $3, $4) RETURNING clientid', $client));
	}

	return $r[0];
}

/**
 * Get the EVE accountid of the current user (inserts a new row in the
 * eveaccounts table if necessary). Requires current account to be API
 * verified.
 *
 * @returns integer ID, or false on error
 */
function get_eveaccount_id(&$error = null) {
	$a = get_state('a', null);
	if($a === null || !isset($a['accountid'])) {
		$error = 'Please login first.';
		return false;
	}
	if(!isset($a['apiverified']) || $a['apiverified'] !== 't') {
		$error = 'You must verify your API first.';
		return false;
	}

	$accountstatus = \Osmium\EveApi\fetch(
		'/account/AccountStatus.xml.aspx',
		array(
			'keyID' => $a['keyid'],
			'vCode' => $a['verificationcode'],
		)
	);

	if(!($accountstatus instanceof \SimpleXMLElement)
	   || !isset($accountstatus->result->createDate)) {
		$error = 'Unexpected EVE API error.';
		return false;
	}

	$creationdate = strtotime((string)$accountstatus->result->createDate);

	$r = \Osmium\Db\fetch_row(
		\Osmium\Db\query_params(
			'SELECT eveaccountid FROM osmium.eveaccounts WHERE creationdate = $1',
			array($creationdate)
			));
	if($r === false) {
		$r = \Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'INSERT INTO osmium.eveaccounts (creationdate) VALUES ($1) RETURNING eveaccountid',
				array($creationdate)
				));
	}

	return $r[0];
}

/**
 * Get a random large positive integer.
 */
function get_nonce() {
	/* No perfect way to do this in PHP. This is sad. */
	$q = \Osmium\Db\query('SELECT ((random() * ((2)::double precision ^ (63)::double precision)))::bigint');
	return \Osmium\Db\fetch_row($q)[0];
}

function put_activity() {
	/* Used for getting a rough estimate of the current member of
	 * users browsing the site. */
	if(defined('Osmium\ACTIVITY_IGNORE')) return;
	$a = get_state('a', null);
	$a = ($a === null) ? get_client_attributes() : $a['accountid'];
	if($a !== 'CLI') put_cache_memory($a, 0, 65, 'Activity_');
}

/**
 * Redirect user to login form (which will redirect to the current
 * page) if not currently logged in.
 */
function assume_logged_in($relative) {
	if(is_logged_in()) return;

	/* TODO: also transfer current postdata */

	header('HTTP/1.1 303 See Other', true, 303);
	header('Location: '.$relative.'/login?r='.urlencode($_SERVER['REQUEST_URI']), true, 303);
	die();
}

/**
 * Redirect user to main page if logged in.
 */
function assume_logged_out($relative) {
	if(!is_logged_in()) return;

	header('HTTP/1.1 303 See Other', true, 303);
	header('Location: '.$relative.'/', true, 303);
	die();
}