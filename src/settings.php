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

namespace Osmium\Page\Settings;

require __DIR__.'/../inc/root.php';

const MASK = '********';

\Osmium\State\assume_logged_in('.');
$a = \Osmium\State\get_state('a');

if(isset($_POST['key_id'])) {
	$key_id = $_POST['key_id'];
	$v_code = $_POST['v_code'];

	if(isset($a['verificationcode']) && substr($v_code, 0, strlen(MASK)) === MASK
	   && strlen($v_code) == strlen(MASK) + 4) {
		$v_code = $a['verificationcode'];
	}

	$s = \Osmium\State\check_api_key_sanity($a['accountid'], $key_id, $v_code);

	if($s !== true) {
		\Osmium\Forms\add_field_error('key_id', $s);
	} else {
		\Osmium\Db\query_params('UPDATE osmium.accounts SET keyid = $1, verificationcode = $2, apiverified = true WHERE accountid = $3', array($key_id, $v_code, $a['accountid']));

		$a['apiverified'] = 't';
		$a['keyid'] = $key_id;
		$a['verificationcode'] = $v_code;

		\Osmium\State\put_state('a', $a);
		\Osmium\State\check_api_key($a, true);
		$a = \Osmium\State\get_state('a');
	}
} else {
	if(isset($a['keyid'])) {
		$_POST['key_id'] = $a['keyid'];
		$_POST['v_code'] = MASK.substr($a['verificationcode'], -4);
	}
}

\Osmium\Chrome\print_header('Account settings', '.');
echo "<div id='account_settings'>\n";

echo "<ul class='tindex'>\n";
echo "<li><a href='#s_changepw'>Change password</a></li>\n";
echo "<li><a href='#s_apiauth'>Account status</a></li>\n";
echo "<li><a href='#s_characters'>Characters and skills</a></li>\n";
echo "</ul>\n";

echo "<section id='s_changepw'>\n<h1>Change password</h1>\n";
\Osmium\Forms\print_form_begin("#s_changepw");

if(isset($_POST['curpw'])) {
	$cur = $_POST['curpw'];
	$new = $_POST['newpw'];

	if($new !== $_POST['newpw2']) {
		\Osmium\Forms\add_field_error('newpw2', 'The two passwords were not equal.');
	} else if(($s = \Osmium\State\is_password_sane($new)) !== true) {
		\Osmium\Forms\add_field_error('newpw', $s);
	} else {
		$accountid = \Osmium\State\get_state('a')['accountid'];
		$apw = \Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT passwordhash FROM osmium.accounts
				WHERE accountid = $1',
				array($accountid)
			)
		)[0];

		if(!\Osmium\State\check_password($cur, $apw)) {
			\Osmium\Forms\add_field_error('curpw', 'Incorrect password. If you forgot your password, log out and use the reset password page.');
		} else {
			$newhash = \Osmium\State\hash_password($new);
			\Osmium\Db\query_params(
				'UPDATE osmium.accounts SET passwordhash = $1
				WHERE accountid = $2',
				array($newhash, $accountid)
			);
			echo "<p class='notice_box'>Password was successfully changed.</p>\n";
		}
	}
}

\Osmium\Forms\print_generic_field('Current password', 'password', 'curpw');
\Osmium\Forms\print_generic_field('New password', 'password', 'newpw');
\Osmium\Forms\print_generic_field('New password (confirm)', 'password', 'newpw2');
\Osmium\Forms\print_submit();
\Osmium\Forms\print_form_end();
echo "</section>\n";

echo "<section id='s_apiauth'>\n<h1>Account status</h1>\n";
echo "<p>".($a['apiverified'] === 't' ? 'Your account is API-verified.'
            : 'Your account is <strong>not</strong> API-verified.')."</p>\n";

if($a['apiverified'] !== 't') {
	echo "<p>\nVerifying your account with an API key will allow you to:</p>\n";
	echo "<ul>\n";
	echo "<li>Share loadouts with your corporation/alliance, and access corporation/alliance-restricted loadouts;</li>\n";
	echo "<li>Have your character name used instead of your nickname;</li>\n";
	echo "<li>Reset your password if you ever forget it.</li>\n";
	echo "</ul>\n";
}


echo "<h2>API credentials</h2>\n";

\Osmium\State\print_api_link();
\Osmium\Forms\print_form_begin("#s_apiauth");

\Osmium\Forms\print_generic_field('API Key ID', 'text', 'key_id', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);
\Osmium\Forms\print_generic_field('Verification Code', 'text', 'v_code', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);

\Osmium\Forms\print_submit();
\Osmium\Forms\print_form_end();

echo "</section>\n";

echo "<section id='s_characters'>\n<h1>Characters</h1>\n";
echo "<p>You can create characters with custom skillsets to use in loadouts.</p>\n";

echo "<h3>Create a new character</h3>\n";

\Osmium\Forms\print_form_begin("#s_characters");

if(isset($_POST['newcharname']) && $_POST['newcharname'] !== '') {
	$name = $_POST['newcharname'];
	list($exists) = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT COUNT(accountid) FROM osmium.accountcharacters WHERE accountid = $1 AND name = $2', array($a['accountid'], $name)));

	if($name == 'All 0' || $name == 'All V') {
		\Osmium\Forms\add_field_error('newcharname', 'You cannot pick this name, it has a special meaning and is reserved.');
	} else if($exists) {
		\Osmium\Forms\add_field_error('newcharname', 'You already have a character with the same name.');
	} else {
		\Osmium\Db\query_params('INSERT INTO osmium.accountcharacters (accountid, name) VALUES ($1, $2)', array($a['accountid'], $name));
		unset($_POST['newcharname']);
	}
}

\Osmium\Forms\print_generic_field('Character name', 'text', 'newcharname', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);

\Osmium\Forms\print_submit('Create character');
\Osmium\Forms\print_form_end();

echo "<h3>Manage characters</h3>\n";
echo "<p>You can use any API key you want for importing skills, just make sure you tick the CharacterSheet box and that you enter the name of the character you want to import the skills from in the \"Import character name\" row.<br />\nCreate an API key here: <strong><a href='https://support.eveonline.com/api/Key/CreatePredefined/8'>https://support.eveonline.com/api/Key/CreatePredefined/8</a></strong></p>\n";

if(isset($_POST['delete']) && is_array($_POST['delete'])) {
	reset($_POST['delete']);
	$cname = key($_POST['delete']);

	\Osmium\Db\query_params('DELETE FROM osmium.accountcharacters WHERE accountid = $1 AND name = $2', array($a['accountid'], $cname));
} else if(isset($_POST['fetch']) && is_array($_POST['fetch'])) {
	reset($_POST['fetch']);
	$cname = key($_POST['fetch']);

	list($keyid, $vcode) = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT keyid, verificationcode FROM osmium.accountcharacters WHERE accountid = $1 AND name = $2', array($a['accountid'], $cname)));

	$pkeyid = $_POST['keyid'][$cname];
	$pvcode = $_POST['vcode'][$cname];
	$piname = $_POST['iname'][$cname];

	if($pkeyid === '') $pkeyid = $keyid;
	if($pvcode === '' || (substr($pvcode, 0, strlen(MASK)) === MASK
	       && strlen($pvcode) == strlen(MASK) + 4)) {
		$pvcode = $vcode;
	}

	if((string)$pkeyid === '') {
		echo "<p class='error_box'>You must specify a key ID.</p>\n";
	} else if((string)$pvcode === '') {
		echo "<p class='error_box'>You must specify a verification code.</p>\n";
	} else {
		$keyinfo = \Osmium\EveApi\fetch('/account/APIKeyInfo.xml.aspx', 
		                                array('keyID' => $pkeyid, 
		                                      'vCode' => $pvcode));
		if(!($keyinfo instanceof \SimpleXMLElement)) {
			echo "<p class='error_box'>Error occured while fetching API key info.</p>\n";
		} else if(isset($keyinfo->error)) {
			echo "<p class='error_box'>(".((int)$keyinfo->error['code']).") ".\Osmium\Chrome\escape((string)$keyinfo->error)."</p>\n";
		} else if(!((int)$keyinfo->result->key['accessMask'] & \Osmium\State\CHARACTER_SHEET_ACCESS_MASK)) {
			echo "<p class='error_box'>API key does not allow accessing the character sheet. Please tick the CharacterSheet box!</p>\n";
		} else {
			$apicharid = null;

			foreach($keyinfo->result->key->rowset->row as $row) {
				if((string)$piname === '') {
					/* Use first character available */
					$piname = (string)$row['characterName'];
					$apicharid = (int)$row['characterID'];
					break;
				}

				if((string)$row['characterName'] === $piname) {
					$apicharid = (int)$row['characterID'];
					break;
				}
			}

			if($apicharid === null) {
				echo "<p class='error_box'>Could not find character <strong>".\Osmium\Chrome\escape($piname)."</strong> in this API key. Leave the import character blank to use the first character available.</p>\n";
			} else {
				\Osmium\Db\query_params('UPDATE osmium.accountcharacters SET keyid = $1, verificationcode = $2, importname = $3 WHERE accountid = $4 AND name = $5',
				                        array(
					                        $pkeyid,
					                        $pvcode,
					                        $piname,
					                        $a['accountid'],
					                        $cname,
					                        ));

				$sheet = \Osmium\EveApi\fetch('/char/CharacterSheet.xml.aspx',
				                              array(
					                              'keyID' => $pkeyid,
					                              'vCode' => $pvcode,
					                              'characterID' => $apicharid,
					                              ));

				if(!($sheet instanceof \SimpleXMLElement)) {
					echo "<p class='error_box'>Error occured while fetching the character sheet.</p>\n";
				} else if(isset($sheet->error)) {
					echo "<p class='error_box'>(".((int)$sheet->error['code']).") ".\Osmium\Chrome\escape((string)$sheet->error)."</p>\n";
				} else {
					$skills = array();
					foreach($sheet->result->rowset as $rowset) {
						if(!isset($rowset['name']) || (string)$rowset['name'] !== 'skills') continue;

						foreach($rowset->row as $row) {
							$skills[(string)$row['typeID']] = (int)$row['level'];
						}

						break;
					}

					ksort($skills);
					\Osmium\Db\query_params('UPDATE osmium.accountcharacters SET importedskillset = $1, lastimportdate = $2 WHERE accountid = $3 AND name = $4', array(
						                        json_encode($skills),
						                        time(),
						                        $a['accountid'],
						                        $cname,
						                        ));
				}
			}
		}
	}
} else if(isset($_POST['editoverrides']) && is_array($_POST['editoverrides'])) {
	reset($_POST['editoverrides']);
	$cname = key($_POST['editoverrides']);

	header('Location: ./editskillset/'.urlencode($cname));
	die();
}

echo "<form method='post' action='#s_characters'>\n";
echo "<table class='d scharacters'>\n<thead>\n";
echo "<tr>\n<th>Name</th>\n<th>Key ID</th>\n<th>Verification code</th>\n<th>Import character name</th>\n<th>Last import date</th>\n<th>Actions</th>\n</tr>\n";
echo "</thead>\n<tfoot></tfoot>\n<tbody>\n";

$cq = \Osmium\Db\query_params('SELECT name, keyid, verificationcode, importname, lastimportdate FROM osmium.accountcharacters WHERE accountid = $1', array($a['accountid']));
$haschars = false;
while($c = \Osmium\Db\fetch_assoc($cq)) {
	$haschars = true;

	$cname = \Osmium\Chrome\escape($c['name']);

	$vcode = $c['verificationcode'];
	if($vcode === null) $vcode = '';
	else $vcode = MASK.substr($vcode, -4);

	echo "<tr>\n";
	echo "<td><strong>".$cname."</strong></td>\n";
	echo "<td><input type='text' name='keyid[$cname]' value='".$c['keyid']."' /></td>\n";
	echo "<td><input type='text' name='vcode[$cname]' value='".\Osmium\Chrome\escape($vcode)."' /></td>\n";
	echo "<td><input type='text' name='iname[$cname]' value='".\Osmium\Chrome\escape($c['importname'])."' /></td>\n";
	echo "<td>".($c['lastimportdate'] === null ? '<em>never</em>' : \Osmium\Chrome\format_relative_date($c['lastimportdate']))."</td>\n";
	echo "<td>\n";
	echo "<input type='submit' name='fetch[$cname]' value='Fetch skillset from API' /> ";
	echo "<input type='submit' name='editoverrides[$cname]' value='Manually edit skill levels' /> ";
	echo "<input type='submit' name='delete[$cname]' value='Delete character' /> ";
	echo "</td>\n";
	echo "</tr>\n";
}
if(!$haschars) {
	echo "<tr>\n<td colspan='6'>\n<p class='placeholder'>No characters.</p></td>\n</tr>\n";
}

echo "</tbody>\n</table>\n</form>\n";

echo "</section>\n";

echo "</div>\n";
\Osmium\Chrome\print_js_snippet('tabs');
\Osmium\Chrome\print_js_snippet('settings');
\Osmium\Chrome\print_footer();