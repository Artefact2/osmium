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

namespace Osmium\Fit;

/** Get an array of EVE database versions, with associated
 * metadata. Obviously this needs to be updated every time the EVE
 * database has major updates. */
function get_eve_db_versions() {
	return array(
		653401 => array(
			'tag' => 'rubicon-10',
			'name' => 'Rubicon 1.0',
			'build' => 653401,
			'reldate' => gmmktime(0, 0, 0, 11, 19, 2013),
		),
		592399 => array(
			'tag' => 'odyssey-11',
			'name' => 'Odyssey 1.1',
			'build' => 592399,
			'reldate' => gmmktime(0, 0, 0, 9, 3, 2013),
		),
		548234 => array(
			'tag' => 'odyssey-10',
			'name' => 'Odyssey 1.0',
			'build' => 548234,
			'reldate' => gmmktime(0, 0, 0, 6, 4, 2013),
		),
		538542 => array(
			'tag' => 'retribution-12',
			'name' => 'Retribution 1.2',
			'build' => 538542,
			'reldate' => gmmktime(0, 0, 0, 5, 6, 2013),
		),
		529690 => array(
			'tag' => 'retribution-11',
			'name' => 'Retribution 1.1',
			'build' => 529690,
			'reldate' => gmmktime(0, 0, 0, 2, 19, 2013),
		),
		476047 => array(
			'tag' => 'retribution-10',
			'name' => 'Retribution 1.0',
			'build' => 476047,
			'reldate' => gmmktime(0, 0, 0, 12, 4, 2012),
		),
		433763 => array(
			'tag' => 'inferno-13',
			'name' => 'Inferno 1.3',
			'build' => 433763,
			'reldate' => gmmktime(0, 0, 0, 10, 16, 2012),
		),
		404131 => array(
			'tag' => 'inferno-12',
			'name' => 'Inferno 1.2',
			'build' => 404131,
			'reldate' => gmmktime(0, 0, 0, 8, 8, 2012),
		),
		390556 => array(
			'tag' => 'inferno-11',
			'name' => 'Inferno 1.1',
			'build' => 390556,
			'reldate' => gmmktime(0, 0, 0, 6, 25, 2012),
		),
		377452 => array(
			'tag' => 'inferno-10',
			'name' => 'Inferno 1.0',
			'build' => 377452,
			'reldate' => gmmktime(0, 0, 0, 5, 22, 2012),
		),
		/* Osmium was not released before this time, so there's little
		 * point in including the version prior to this. */
	);
}

/** Get the latest EVE database version. */
function get_latest_eve_db_version() {
	static $v = null;

	if($v === null) {
		$vers = get_eve_db_versions();
		\reset($vers);
		$v = current($vers);
	}

	return $v;
}

/** @internal */
function get_closest_version_by_key($key, $value) {
	$versions = array_values(get_eve_db_versions());
	$i = 0;
	$c = count($versions) - 1;

	if($value >= $versions[0][$key]) return $versions[0];
	if($value <= $versions[$c][$key]) return $versions[$c];

	for($i = 0; $i < $c; ++$i) {
		$v2 = $versions[$i][$key];
		$v1 = $versions[$i + 1][$key];

		if($v1 < $value && $value <= $v2) {
			return $versions[$i];
		}
	}

	trigger_error(
		"get_closest_version_by_key(): $key with value $value did not find a suitable version",
		E_USER_WARNING
	);

	return $versions[0];
}

/** Get the closest EVE db version of a given build number. */
function get_closest_version_by_build($b) {
	$versions = get_eve_db_versions();
	$b = (int)$b;

	if(isset($versions[$b])) return $versions[$b];
	return get_closest_version_by_key('build', $b);
}

/** Get the closest EVE db version of a given timestamp. */
function get_closest_version_by_time($t) {
	return get_closest_version_by_key('reldate', $t);
}
