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

namespace Osmium\Page\Login;

require __DIR__.'/../inc/root.php';

$redirect = isset($_POST['request_uri']) ? $_POST['request_uri'] : (isset($_GET['r']) ? $_GET['r'] : './');

if(\Osmium\State\is_logged_in()) {
	header('Location: '.$redirect, true, 303);
	die();
}

if(isset($_POST['__osmium_login'])) {
	if(($errormsg = \Osmium\State\try_login()) === true) {
		header('Location: '.$redirect, true, 303);
		die();
	}

	\Osmium\Forms\add_field_error('account_name', $errormsg);
	\Osmium\Forms\add_field_error('password', '');
}

\Osmium\Chrome\print_header('Login', '.');

echo "<h1>Login</h1>\n";

require \Osmium\ROOT.'/inc/login-httpscheck.php';

\Osmium\Forms\print_form_begin();

if(isset($_GET['r'])) {
	echo "<tr class='error_message'><td colspan='2'><p>You need to log in to access <strong>".\Osmium\Chrome\escape($_GET['r'])."</strong>.</p></td></tr>\n";
}

\Osmium\Forms\print_generic_field(
	'Account name', 'text', 'account_name', null, 
	\Osmium\Forms\FIELD_REMEMBER_VALUE
);
\Osmium\Forms\print_generic_field('Password', 'password', 'password');
\Osmium\Forms\print_separator();
\Osmium\Forms\print_checkbox(
	'Remember me <small>(uses a cookie)</small>', 'remember',
	null, !isset($_POST['account_name']), \Osmium\Forms\FIELD_REMEMBER_VALUE
);
\Osmium\Forms\print_submit('Login', '__osmium_login');
\Osmium\Forms\print_form_end();

echo "<h1>Other actions</h1>\n";

echo "<ul>
<li>Don't have an account yet? <a href='./register'>Create one.</a> It takes less than a minute.</li>
<li>Forgot your password? <a href='./resetpassword'>Reset your password.</a></li>
</ul>\n";

\Osmium\Chrome\print_footer();
