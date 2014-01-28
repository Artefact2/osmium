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

namespace Osmium\Page\Moderation\Main;

require __DIR__.'/../../inc/root.php';

\Osmium\State\assume_logged_in('..');
$a = \Osmium\State\get_state('a');
if($a['ismoderator'] !== 't') {
	\Osmium\fatal(404);
}

\Osmium\Chrome\print_header('Moderation index', '..');

echo "<h1>Moderation index</h1>\n";
echo "<ul>\n";

$a = "<a href='./flags'>View flags</a>";
list($newflags) = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT COUNT(flagid) FROM osmium.flags WHERE status = $1', array(\Osmium\Flag\FLAG_STATUS_NEW)));
if($newflags > 0) {
	echo "<li><strong>$a ($newflags new)</strong></li>\n";
} else {
	echo "<li>$a</li>\n";
}

echo "</ul>\n";

\Osmium\Chrome\print_footer();
