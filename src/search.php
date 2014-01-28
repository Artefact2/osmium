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

namespace Osmium\Page\Search;

require __DIR__.'/../inc/root.php';

if(isset($_GET['q'])) {
	$query = $_GET['q'];
} else {
	$query = false;
}

$cond = \Osmium\Search\get_search_cond_from_advanced();

if($query === false) {
	$title = (isset($_GET['ad']) && $_GET['ad'] == 1) ? 'Advanced search' : 'Search lodaouts';
	\Osmium\Chrome\print_header(
		$title, '.', true,
		"<link rel='canonical' href='./search' />"
	);
	echo "<div id='search_full'>\n";
	\Osmium\Search\print_search_form(null, '.', $title);
	echo "</div>\n";
	\Osmium\Chrome\print_footer();
	die();
} else {
	$title = 'Search results';
	if($query !== false && strlen($query) > 0) {
		$title .= ' / '.\Osmium\Chrome\escape($query);
	}
	\Osmium\Chrome\print_header(
		$title, '.', false,
		"<link rel='canonical' href='./search' />"
	);
	echo "<div id='search_mini'>\n";
	\Osmium\Search\print_search_form(null, '.', $title);
	echo "</div>\n";

	\Osmium\Search\print_pretty_results('.', $query, $cond, true, 24);
	\Osmium\Chrome\print_footer();
}
