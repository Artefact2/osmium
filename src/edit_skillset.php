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

namespace Osmium\Page\EditSkillset;

require __DIR__.'/../inc/root.php';

\Osmium\State\assume_logged_in('..');
$a = \Osmium\State\get_state('a');
$name = urldecode($_GET['name']);

$row = \Osmium\Db\fetch_assoc(
	\Osmium\Db\query_params(
		'SELECT importedskillset, overriddenskillset FROM osmium.accountcharacters WHERE accountid = $1 AND name = $2',
		array($a['accountid'], $name)
		));

if($row === false) {
	\Osmium\fatal(404);
}

$imported = $row['importedskillset'] !== null ? json_decode($row['importedskillset'], true) : array();
$overridden = $row['overriddenskillset'] !== null ? json_decode($row['overriddenskillset'], true) : array();

$t = 'Edit skills of '.\Osmium\Chrome\escape($name);
\Osmium\Chrome\print_header($t, '..');
echo "<h1>$t</h1>\n";

echo "<form method='post' action='".\Osmium\Chrome\escape($_SERVER['REQUEST_URI'])."'>\n<table id='e_skillset' class='d'>\n<tbody>\n";

$q = \Osmium\Db\query('SELECT typeid, typename, groupname FROM osmium.invskills ORDER BY groupname ASC, typename ASC');
$lastgroup = null;
while($s = \Osmium\Db\fetch_assoc($q)) {
	echo "<tr>\n";

	if(isset($_POST['override'][$s['typeid']])) {
		$v = $_POST['override'][$s['typeid']];
		if($v == -2) {
			unset($overridden[$s['typeid']]);
		} else {
			$v = max(0, min(5, intval($v)));
			$overridden[$s['typeid']] = $v;
		}
	}

	if($lastgroup !== $s['groupname']) {
		$lastgroup = $s['groupname'];
		echo "<th class='groupname' colspan='3'>".\Osmium\Chrome\escape($s['groupname'])."</th>\n</tr>\n";
		echo "<tr>\n<th>Skill</th>\n<th>Imported level</th>\n<th>Overridden level</th>\n</tr>\n";
		echo "<tr>\n";
	}

	echo "<td>".\Osmium\Chrome\escape($s['typename'])."</td>\n";

	$ilevel = isset($imported[$s['typeid']]) ? min(5, max(0, $imported[$s['typeid']])) : null;
	$olevel = isset($overridden[$s['typeid']]) ? min(5, max(0, $overridden[$s['typeid']])) : null;

	echo "<td>";
	if($olevel !== null) echo "<del>".\Osmium\Chrome\format_skill_level($ilevel)."</del>";
	else echo \Osmium\Chrome\format_skill_level($ilevel);
	echo "</td>\n";

	echo "<td><select name='override[".$s['typeid']."]'>\n";
	echo "<option value='-2'>No override</option>\n";
	foreach([ 0, 1, 2, 3, 4, 5 ] as $k) {
		if($k === $olevel) {
			$selected = " selected='selected'";
		} else $selected = '';

		$v = \Osmium\Chrome\format_skill_level($k);
		echo "<option value='$k'$selected>$v</option>\n";
	}
	echo "</select>\n";
	echo "<input type='submit' value='OK' />";
	echo "</td>\n";

	echo "</tr>\n";
}

echo "</tbody>\n</table>\n</form>\n";

\Osmium\Chrome\print_footer();

if(isset($_POST['override'])) {
	\Osmium\Db\query_params('UPDATE osmium.accountcharacters SET overriddenskillset = $1 WHERE accountid = $2 AND name = $3',
	                        array(
		                        json_encode($overridden),
		                        $a['accountid'],
		                        $name,
		                        ));
}