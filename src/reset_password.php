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

namespace Osmium\Page\ResetPassword;

require __DIR__.'/../inc/root.php';

\Osmium\State\assume_logged_out('.');
\Osmium\Chrome\print_header('Reset password', '.');

echo "<h1>Password reset</h1>\n";

if(isset($_POST['key_id'])) {
	$keyid = $_POST['key_id'];
	$vcode = $_POST['v_code'];
	$pw = $_POST['password_0'];
	$pw1 = $_POST['password_1'];

	$s = \Osmium\State\check_api_key_sanity(null, $keyid, $vcode, $characterid, $charactername);

	if($s !== true) {
		\Osmium\Forms\add_field_error('key_id', $s);
	} else {
		$a = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT accountid, accountname FROM osmium.accounts WHERE apiverified = true AND characterid = $1', array($characterid)));

		if($a === false) {
			\Osmium\Forms\add_field_error(
				'key_id',
				"Character <strong>".\Osmium\Chrome\escape($charactername)
				."</strong> is not used by any API-validated account."
			);
		} else if(($s = \Osmium\State\is_password_sane($pw)) !== true) {
			\Osmium\Forms\add_field_error('password_0', $s);
		} else if($pw !== $pw1) {
			\Osmium\Forms\add_field_error('password_1', 'The two passwords are not equal.');
		} else {
			$hash = \Osmium\State\hash_password($pw);

			\Osmium\Db\query_params('UPDATE osmium.accounts SET passwordhash = $1 WHERE accountid = $2',
			                        array($hash, $a['accountid']));

			echo "<p class='notice_box'>\nPassword reset was successful. You can now login on the account <strong>".\Osmium\Chrome\escape($a['accountname'])."</strong> using your new password.\n</p>\n";
		}
	}
}

echo "<p>\nIf you forgot the password of your API-verified account, you can reset it by re-entering the API key associated with your account below.<br />\nYou can see a list of your API keys here: <strong><a href='https://support.eveonline.com/api'>https://support.eveonline.com/api</a></strong>\n</p>\n";

\Osmium\Forms\print_form_begin();

\Osmium\Forms\print_generic_field('API Key ID', 'text', 'key_id', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);
\Osmium\Forms\print_generic_field('Verification Code', 'text', 'v_code', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);
\Osmium\Forms\print_separator();

\Osmium\Forms\print_generic_field('New password', 'password', 'password_0', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);
\Osmium\Forms\print_generic_field('New password (confirm)', 'password', 'password_1', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);

\Osmium\Forms\print_submit('Check API key');
\Osmium\Forms\print_form_end();

\Osmium\Chrome\print_footer();