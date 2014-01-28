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

namespace Osmium\MarkdownStatic;

require __DIR__.'/../inc/root.php';

$f = __DIR__.'/md/'.$_GET['f'];
$data = $_GET;

$allowed = realpath(__DIR__.'/md/');
$f = realpath($f);

if(strpos($f, $allowed) !== 0) {
	\Osmium\fatal(404);
}

\Osmium\Chrome\print_header($data['title'], $data['relative']);

echo "<div id='mdstatic'>\n";
echo \Osmium\Chrome\format_md(file_get_contents($f));
echo "</div>\n";

\Osmium\Chrome\print_footer();
