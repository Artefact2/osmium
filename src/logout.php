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

namespace Osmium\Page\Logout;

require __DIR__.'/../inc/root.php';

if(!\Osmium\State\is_logged_in()) {
	goto redirect; /* Boooo */
}

if(!isset($_GET['tok'])) {
	\Osmium\fatal(400);
}

$tok = $_GET['tok'];
if($tok != \Osmium\State\get_token()) {
	\Osmium\fatal(400);
}

$global = isset($_GET['global']) && $_GET['global'];

\Osmium\State\logoff($global);

redirect:
header('HTTP/1.1 303 See Other', true, 303);
header('Location: ./', true, 303);
die(); /* Our work here is done. */
