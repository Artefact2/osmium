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

namespace Osmium\Page\Register;

require __DIR__.'/../inc/root.php';

\Osmium\State\assume_logged_out();

if(isset($_POST['account_name'])) {
	$q = \Osmium\Db\query_params('SELECT COUNT(accountid) FROM osmium.accounts WHERE accountname = $1', array($_POST['account_name']));
	list($an) = \Osmium\Db\fetch_row($q);

	$q = \Osmium\Db\query_params('SELECT COUNT(accountid) FROM osmium.accounts WHERE nickname = $1', array($_POST['nickname']));
	list($nn) = \Osmium\Db\fetch_row($q);

	$pw = $_POST['password_0'];
	$pw1 = $_POST['password_1'];

	if($an !== '0') {
		\Osmium\Forms\add_field_error('account_name', 'Sorry, this account name is already taken.');
	} else if($nn !== '0') {
		\Osmium\Forms\add_field_error('nickname', 'Sorry, this nickname is already taken.');
	} else if(mb_strlen($_POST['account_name']) < 3) {
		\Osmium\Forms\add_field_error('account_name', 'Must be at least 3 characters.');
	} else if(mb_strlen($_POST['nickname']) < 3) {
		\Osmium\Forms\add_field_error('nickname', 'Must be at least 3 characters.');
	} else if(($s = \Osmium\State\is_password_sane($pw)) !== true) {
		\Osmium\Forms\add_field_error('password_0', $s);
	} else if($pw !== $pw1) {
		\Osmium\Forms\add_field_error('password_1', 'The two passwords are not equal.');
	} else {
		$hash = \Osmium\State\hash_password($pw);

		\Osmium\Db\query_params('INSERT INTO osmium.accounts (accountname, passwordhash, nickname,
		creationdate, lastlogindate, keyid, verificationcode, apiverified,
		characterid, charactername, corporationid, corporationname, allianceid, alliancename,
		isfittingmanager, ismoderator, flagweight, reputation) VALUES (
		$1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18)', 
		                        array(
			                        $_POST['account_name'], $hash, $_POST['nickname'],
			                        time(), 0, null, null, 'f',
			                        null, null, null, null, null, null,
			                        'f', 'f', \Osmium\Flag\DEFAULT_FLAG_WEIGHT,
			                        \Osmium\Reputation\DEFAULT_REPUTATION,
			                        ));
		
		\Osmium\State\do_post_login($_POST['account_name'], false);
		$_POST = array();
		header('Location: ./', true, 303);
		die();
	}
}

\Osmium\Chrome\print_header('Account creation', '.');

echo "<h1>Account creation</h1>\n";

require \Osmium\ROOT.'/inc/login-httpscheck.php';

echo "<p>Creating an account allows you to:</p>
<ul>
<li>Save and create public loadouts;</li>
<li>Comment on loadouts;</li>
<li>Cast votes and flags to help moderation;</li>
<li>Access and create corporation or alliance-only loadouts <small>(requires API verification)</small>.</li>
</ul>\n";

\Osmium\Forms\print_form_begin();

\Osmium\Forms\print_text('The account name is only used for logging in (and never displayed), while the nickname will be used as a display name.');

\Osmium\Forms\print_generic_field('Account name', 'text', 'account_name', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);

\Osmium\Forms\print_generic_field('Nickname', 'text', 'nickname', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);

\Osmium\Forms\print_separator();

\Osmium\Forms\print_generic_field('Password', 'password', 'password_0', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);
\Osmium\Forms\print_generic_field('Password (confirm)', 'password', 'password_1', null, 
                                  \Osmium\Forms\FIELD_REMEMBER_VALUE);

\Osmium\Forms\print_separator();

\Osmium\Forms\print_submit();
\Osmium\Forms\print_form_end();
\Osmium\Chrome\print_footer();