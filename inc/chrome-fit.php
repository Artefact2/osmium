<?php
/* Osmium
 * Copyright (C) 2012, 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 * Copyright (C) 2013 Josiah Boning <jboning@gmail.com>
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

namespace Osmium\Chrome;

const FATTRIBS_USE_RELOAD_TIME_FOR_CAPACITOR = 1;
const FATTRIBS_USE_RELOAD_TIME_FOR_DPS = 2;
const FATTRIBS_USE_RELOAD_TIME_FOR_TANK = 4;



function get_remotes() {
	static $types = array(
		'hull' => [ "Raw hull hitpoints repaired per second", 524 ],
		'armor' => [ "Raw armor hitpoints repaired per second", 523 ],
		'shield' => [ "Raw shield hitpoints transferred per second", 405 ],
		'capacitor' => [ "Giga joules transferred per second", 529 ],
		'neutralization' => [ "Giga joules neutralized per second", 533 ],
		'leech' => [ "Giga joules leeched per second (in the best case)", 530 ],
	);

	return $types;
}

function print_formatted_attribute_category($identifier, $title, $titledata, $titleclass, $contents) {
	if($titleclass) $titleclass = " class='$titleclass'";
	echo "<section id='$identifier'>\n";
	echo "<h4$titleclass>$title <small>$titledata</small></h4>\n";
	echo "<div>\n$contents</div>\n";
	echo "</section>\n";
}

function print_nested_skill_requirements($typeid, $level, array &$skills, array $tree, array $invtree) {
	if(isset($tree[$typeid])) {
		foreach($tree[$typeid] as $stid => $sl) {
			if(!isset($skills[$stid])) continue;
			$ml = $skills[$stid];
			unset($skills[$stid]);

			print_nested_skill_requirements($stid, $ml, $skills, $tree, $invtree);
		}
	}

	echo "<li>\n";

	echo \Osmium\Fit\get_typename($typeid)." ".format_skill_level($level);

	if(isset($invtree[$typeid])) {
		$reqs = [];

		foreach($invtree[$typeid] as $tid) {
			$reqs[] = escape(\Osmium\Fit\get_typename($tid));
		}

		if($reqs !== []) {
			echo "<div class='reqs'>Required by ".implode(', ', $reqs)."</div>";
		}
	}

	echo "</li>\n";
}

function print_formatted_mastery(&$fit, $relative, array $prereqs_per_type) {
	ob_start();

	$types_per_prereqs = [];
	$prereqs_unique = [];
	$missing_unique = [];
	$missingsp = 0;
	$totalsp = 0;

	foreach($prereqs_per_type as $tid => $arr) {
		foreach($arr as $stid => $level) {
			if(isset($prereqs_unique[$stid])) {
				if($prereqs_unique[$stid] < $level) {
					$prereqs_unique[$stid] = $level;
				} else {
					unset($prereqs_per_type[$tid][$stid]);
				}
			} else {
				$prereqs_unique[$stid] = $level;
			}
		}
	}

	foreach($prereqs_per_type as $typeid => $arr) {
		foreach($arr as $stid => $level) {
			$types_per_prereqs[$stid][$typeid] = true;
		}
	}

	$missing_unique = \Osmium\Skills\get_missing_prerequisites_unique($prereqs_unique, $fit['skillset']);
	list($totalsp, $missingsp) = \Osmium\Skills\sum_sp($prereqs_unique, $fit['skillset']);

	foreach($types_per_prereqs as &$arr) {
		$arr = array_reverse(array_keys($arr));
	}

	if($missing_unique === []) {
		echo "<p class='placeholder'>No missing skills with current skillset ("
			.escape($fit['skillset']['name']).").</p>\n";
	}

	if($missing_unique !== []) {
		echo "<h5>Missing skillpoints</h5>\n";
		echo "<ul>\n";

		echo "<li>".format_integer($missingsp)." SP missing out of ".format_integer($totalsp)." SP required</li>\n";
		$secs = $missingsp / (45.0 / 60.0);
		echo "<li>approximately ".format_long_duration($secs, 2)." of training time</li>\n";

		echo "</ul>\n";
	}

	if($missing_unique !== []) {
		echo "<h5>Missing skills</h5>\n";
		echo "<ul>\n";

		while($missing_unique !== []) {
			reset($missing_unique);
			$typeid = key($missing_unique);
			$level = $missing_unique[$typeid];
			unset($missing_unique[$typeid]);

			print_nested_skill_requirements($typeid, $level, $missing_unique, $prereqs_per_type, $types_per_prereqs);
		}

		echo "</ul>\n";
	}

	print_formatted_attribute_category(
		'mastery', 'Mastery',
		"<span title='Effective skillset'>".escape($fit['skillset']['name'])."</span>",
		($missingsp > 0) ? 'overflow' : '',
		ob_get_clean()
	);
}

function print_formatted_engineering(&$fit, $relative, $capacitor) {
	ob_start();
	$overall = '';

	$slotsLeft = \Osmium\Dogma\get_ship_attribute($fit, 'turretSlotsLeft');
	$slotsTotal = \Osmium\Dogma\get_ship_attribute($fit, 'turretSlots');
	$formatted = format_used(
		$slotsTotal - $slotsLeft, $slotsTotal, 0,
		F_USED_SHOW_ABSOLUTE
	);
	$overturrets = $slotsLeft < 0 ? 'overflow' : '';
	echo "<p class='{$overturrets}'>"
		.sprite($relative, 'Turret hardpoints', 0, 0, 64, 64, 32)
		."<span id='turrethardpoints'>".$formatted."</span></p>\n";

	$slotsLeft = \Osmium\Dogma\get_ship_attribute($fit, 'launcherSlotsLeft');
	$slotsTotal = \Osmium\Dogma\get_ship_attribute($fit, 'launcherSlots');
	$formatted = format_used(
		$slotsTotal - $slotsLeft, $slotsTotal, 0,
		F_USED_SHOW_ABSOLUTE
	);
	$overlaunchers = $slotsLeft < 0 ? 'overflow' : '';
	echo "<p class='{$overlaunchers}'>"
		.sprite($relative, 'Launcher hardpoints', 0, 1, 64, 64, 32)
		."<span id='launcherhardpoints'>".$formatted."</span></p>\n";

	list($captime, $capdelta) = \Osmium\Chrome\format_capacitor($capacitor);
	echo "<p id='capacitor' data-capacity='"
		.$capacitor['capacity']
		."' data-usage='"
		.($capacitor['stable'] ? ($capacitor['capacity'] * $capacitor['stable_fraction']) : 0)."'>"
		.sprite($relative, 'Capacitor', 2, 0, 64, 64, 32)
		."<span><span title='Capacitor stability percentage / depletion time (estimated)'>".$captime."</span><br /><span title='Capacitor delta at peak recharge rate'>".$capdelta."</span></span></p>\n";

	$cpuUsed = \Osmium\Dogma\get_ship_attribute($fit, 'cpuLoad');
	$cpuTotal = \Osmium\Dogma\get_ship_attribute($fit, 'cpuOutput');
	$formatted = format_used(
		$cpuUsed, $cpuTotal, 2,
		F_USED_SHOW_DIFFERENCE | F_USED_SHOW_PERCENTAGE | F_USED_SHOW_PROGRESS_BAR
	);
	$overcpu = $cpuUsed > $cpuTotal ? 'overflow' : '';
	echo "<p class='{$overcpu}'>"
		.sprite($relative, 'CPU', 1, 0, 64, 64, 32)
		."<span id='cpu'>".$formatted."</span></p>\n";

	$powerUsed = \Osmium\Dogma\get_ship_attribute($fit, 'powerLoad');
	$powerTotal = \Osmium\Dogma\get_ship_attribute($fit, 'powerOutput');
	$formatted = format_used(
		$powerUsed, $powerTotal, 2,
		F_USED_SHOW_DIFFERENCE | F_USED_SHOW_PERCENTAGE | F_USED_SHOW_PROGRESS_BAR
	);
	$overpower = $powerUsed > $powerTotal ? 'overflow' : '';
	echo "<p class='{$overpower}'>"
		.sprite($relative, 'Powergrid', 1, 1, 64, 64, 32)
		."<span id='power'>".$formatted."</span></p>\n";

	$upgradeCapacityUsed = \Osmium\Dogma\get_ship_attribute($fit, 'upgradeLoad');
	$upgradeCapacityTotal = \Osmium\Dogma\get_ship_attribute($fit, 'upgradeCapacity');
	$formatted = format_used(
		$upgradeCapacityUsed, $upgradeCapacityTotal, 2,
		F_USED_SHOW_DIFFERENCE | F_USED_SHOW_PERCENTAGE | F_USED_SHOW_PROGRESS_BAR
	);
	$overupgrade = $upgradeCapacityUsed > $upgradeCapacityTotal ? 'overflow' : '';
	echo "<p class='{$overupgrade}'>"
		.sprite($relative, 'Calibration (upgrade capacity)', 1, 2, 64, 64, 32)
		."<span id='upgradecapacity'>".$formatted."</span></p>\n";

	print_formatted_attribute_category(
		'engineering', 'Engineering',
		"<span title='Capacitor stability percentage / depletion time (estimated)'>".$captime.' </span>',
		$overturrets || $overlaunchers || $overcpu || $overpower || $overupgrade ? 'overflow' : '',
		ob_get_clean()
	);
}

function print_formatted_offense(&$fit, $relative, array $ia, $reload = false) {
	ob_start();
	$ndtypes = 0;

	list($turretdps, $turretalpha) = \Osmium\Fit\get_damage_from_turrets($fit, $ia, $reload);
	if($turretalpha > 0) {
		++$ndtypes;
		echo "<p>"
			.sprite($relative, 'Turret damage', 0, 0, 64, 64, 32)
			."<span><span title='Turret volley (alpha)'>"
			.format_number($turretalpha)."</span><br /><span title='Turret DPS'>"
			.format_number($turretdps)."</span></span></p>\n";
	}

	list($missiledps, $missilealpha) = \Osmium\Fit\get_damage_from_missiles($fit, $ia, $reload);
	if($missilealpha > 0) {
		++$ndtypes;
		echo "<p>"
			.sprite($relative, 'Missile damage', 0, 1, 64, 64, 32)
			."<span><span title='Missile volley (alpha)'>"
			.format_number($missilealpha)."</span><br /><span title='Missile DPS'>"
			.format_number($missiledps)."</span></span></p>\n";
	}

	list($sbdps, $sbalpha) = \Osmium\Fit\get_damage_from_smartbombs($fit, $ia);
	if($sbalpha > 0) {
		++$ndtypes;
		echo "<p>"
			.sprite($relative, 'Smartbomb damage', 0, 3, 64, 64, 32)
			."<span><span title='Smartbomb volley (alpha)'>"
			.format_number($sbalpha)."</span><br /><span title='Smartbomb DPS'>"
			.format_number($sbdps)."</span></span></p>\n";
	}

	list($dronedps, $dronealpha) = \Osmium\Fit\get_damage_from_drones($fit, $ia);
	if($dronedps > 0) {
		++$ndtypes;
		echo "<p>"
			.sprite($relative, 'Drone damage', 0, 2, 64, 64, 32)
			."<span title='Drone DPS'>".format_number($dronedps)."</span></p>\n";
	}

	if($ndtypes > 0) {
		$dps = format_number($missiledps + $turretdps + $dronedps + $sbdps, -1);
		print_formatted_attribute_category('offense', 'Offense', "<span title='Total damage per second'>".$dps." dps</span>", '', ob_get_clean());
	} else {
		ob_end_clean();
	}
}

function print_formatted_defense(&$fit, $relative, $ehp, $cap, $reload = false) {
	ob_start();

	$resists = array();
	foreach($ehp as $k => $a) {
		if($k === 'ehp') continue;
		$resists[$k][] = "<td class='capacity'>".\Osmium\Chrome\format_number($a['capacity'])."</td>\n";
		$resists[$k][] = "<td class='emresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['em'])."</td>\n";
		$resists[$k][] = "<td class='thermalresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['thermal'])."</td>\n";
		$resists[$k][] = "<td class='kineticresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['kinetic'])."</td>\n";
		$resists[$k][] = "<td class='explosiveresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['explosive'])."</td>\n";
	}

	$mehp = format_number($ehp['ehp']['min']);
	$aehp = format_number($ehp['ehp']['avg']);
	$Mehp = format_number($ehp['ehp']['max']);

	echo "<table id='resists'>\n<thead>\n<tr>\n";
	echo "<th><abbr title='Effective Hitpoints'>EHP</abbr></th>\n";
	echo "<th id='ehp'>\n";
	echo "<span title='EHP in the worst case (dealing damage with the lowest resistance)'>≥".$mehp."</span><br />\n";
	echo "<strong title='EHP using the selected damage profile'>".$aehp."</strong><br />\n";
	echo "<span title='EHP in the best case (dealing damage with the highest resistance)'>≤".$Mehp."</span></th>\n";
	echo "<td>".sprite($relative, 'EM Resistance', 7, 29, 32)."</td>\n";
	echo "<td>".sprite($relative, 'Thermal Resistance', 4, 29, 32)."</td>\n";
	echo "<td>".sprite($relative, 'Kinetic Resistance', 5, 29, 32)."</td>\n";
	echo "<td>".sprite($relative, 'Explosive Resistance', 6, 29, 32)."</td>\n";
	echo "</tr>\n</thead>\n<tfoot></tfoot>\n<tbody>\n<tr id='shield'>\n";
	echo "<th>".sprite($relative, 'Shield hitpoints', 3, 0, 64, 64, 32)."</th>\n";
	echo implode('', $resists['shield']);
	echo"</tr>\n<tr id='armor'>\n";
	echo "<th>".sprite($relative, 'Armor hitpoints', 3, 1, 64, 64, 32)."</th>\n";
	echo implode('', $resists['armor']);
	echo"</tr>\n<tr id='hull'>\n";
	echo "<th>".sprite($relative, 'Structure (hull) hitpoints', 3, 2, 64, 64, 32)."</th>\n";
	echo implode('', $resists['hull']);
	echo "</tr>\n</tbody>\n</table>\n";

	$layers = array(
		'hull' => array('Hull repairs', 'Hull EHP repaired per second', true, 4, 3),
		'armor' => array('Armor repairs', 'Armor EHP repaired per second', true, 4, 2),
		'shield' => array('Shield boost', 'Shield EHP boost per second', true, 4, 1),
		'shield_passive' => array('Passive shield recharge', 'Peak shield EHP recharged per second', false, 4, 0),
		);

	$rimg = sprite($relative, 'Reinforced tank', 2, 2, 64, 64, 16);
	$simg = sprite($relative, 'Sustained tank', 2, 1, 64, 64, 16);

	$rtotal = 0;
	$stotal = 0;
	foreach(\Osmium\Fit\get_tank($fit, $ehp, $cap['delta'], $reload) as $lname => $a) {
		list($reinforced, $sustained) = $a;
		if($reinforced == 0) continue;

		list($alt, $title, $showboth, $posx, $posy) = $layers[$lname];

		echo "<p>".sprite($relative, $title, $posx, $posy, 64, 64, 32);
		if($showboth) {
			echo "<span>";
			echo $rimg."<span title='$title'>".format_number(1000 * $reinforced)."</span><br />";
			echo $simg."<span title='$title'>".format_number(1000 * $sustained)."</span>";
			echo "</span>";
		} else {
			echo "<span title='$title'>".format_number(1000 * $reinforced)."</span>";
		}
		echo "</p>\n";

		$rtotal += $reinforced;
		$stotal += $sustained;
	}

	$tehp = format_number($ehp['ehp']['avg'], -2);
	$subtitles[] = '<span title="Average EHP">'.$tehp.' ehp</span>';

	if($rtotal > 0) {
		$rtotal = format_number(1000 * $rtotal, -1);
		$subtitles[] = '<span title="Combined reinforced tank">'.$rtotal.' dps</span>';
	}
	if($stotal > 0) {
		$stotal = format_number(1000 * $stotal, -1);
		if($stotal !== $rtotal) {
			$subtitles[] = '<span title="Combined sustained tank">'.$stotal.' dps</span>';
		}
	}
	
	print_formatted_attribute_category('defense', 'Defense <span class="pname">'.escape($fit['damageprofile']['name']).'</span>', implode(' – ', $subtitles), '', ob_get_clean());
}

function print_formatted_incoming(&$fit, $relative, $ehp) {
	ob_start();

	$incoming = \Osmium\Fit\get_incoming($fit);
	$best = 0;
	$fbest = '';

	foreach(get_remotes() as $t => $info) {
		if(!isset($incoming[$t])) continue;
		list($amount, $unit) = $incoming[$t];
		list($title, $img) = $info;
		if($amount < 1e-300) continue;

		if(isset($ehp[$t]) && $unit === 'HP') {
			$avgresonance = 0;
			foreach($fit['damageprofile']['damages'] as $type => $dmg) {
				$avgresonance += $dmg * $ehp[$t]['resonance'][$type];
			}

			$amount /= $avgresonance;
			$unit = 'EHP';
			$title = str_replace('Raw ', 'Effective ', $title);
		}

		$unit .= '/s';
		$amount *= 1000;
		$famount = format($amount).' '.$unit;

		if($amount > $best) {
			$best = $amount;
			$fbest = "<span title='{$title}'>{$t}: {$famount}</span>";
		}

		echo "<p title='{$title}'>"
			."<img src='//image.eveonline.com/Type/{$img}_64.png' alt='{$t}' />"
			.$famount
			."</p>\n";
	}

	$contents = ob_get_clean();

	if($contents) {
		print_formatted_attribute_category(
			'incoming', 'Incoming', $fbest,
			'', $contents
		);
	}
}

function print_formatted_outgoing(&$fit, $relative) {
	ob_start();

	$outgoing = \Osmium\Fit\get_outgoing($fit);
	$best = 0;
	$fbest = '';

	foreach(get_remotes() as $t => $info) {
		if(!isset($outgoing[$t])) continue;
		list($amount, $unit) = $outgoing[$t];
		list($title, $img) = $info;
		if($amount < 1e-300) continue;

		$unit .= '/s';
		$amount *= 1000;
		$famount = format($amount).' '.$unit;

		if($amount > $best) {
			$best = $amount;
			$fbest = "<span title='{$title}'>{$t}: {$famount}</span>";
		}

		echo "<p title='{$title}'>"
			."<img src='//image.eveonline.com/Type/{$img}_64.png' alt='{$t}' />"
			.$famount
			."</p>\n";
	}

	$contents = ob_get_clean();

	if($contents) {
		print_formatted_attribute_category(
			'outgoing', 'Outgoing', $fbest,
			'', $contents
		);
	}
}

function print_formatted_navigation(&$fit, $relative) {
	ob_start();

	$maxvelocity = round(\Osmium\Dogma\get_ship_attribute($fit, 'maxVelocity'));
	$agility = \Osmium\Dogma\get_ship_attribute($fit, 'agility');
	$aligntime = -log(0.25) * \Osmium\Dogma\get_ship_attribute($fit, 'mass') * $agility / 1000000;
	$warpspeed = \Osmium\Dogma\get_ship_attribute($fit, 'warpSpeedMultiplier') * \Osmium\Dogma\get_ship_attribute($fit, 'baseWarpSpeed');
	$warpstrength = -\Osmium\Dogma\get_ship_attribute($fit, 'warpScrambleStatus');
	$fpoints = 'point'.(abs($warpstrength) == 1 ? '' : 's');

	echo "<p>"
		.sprite($relative, 'Propulsion', 5, 0, 64, 64, 32)
		."<span title='Maximum velocity'>".format_number($maxvelocity)." m/s</span></p>\n";

	echo "<p>"
		.sprite($relative, 'Agility', 10, 2, 32)
		."<span><span title='Agility modifier'>"
		.format_number($agility, 3)."x</span><br /><span title='Time to align'>"
		.format_number($aligntime)." s</span></span></p>\n";

	echo "<p>"
		.sprite($relative, 'Warp Core', 5, 2, 64, 64, 32)
		."<span><span title='Warp speed'>"
		.round($warpspeed, 1)." AU/s</span><br /><span title='Warp core strength'>"
		.$warpstrength." ".$fpoints."</span></span></p>\n";

	print_formatted_attribute_category('navigation', 'Navigation', '<span title="Maximum velocity">'.format_number($maxvelocity, -1).' m/s</span>', '', ob_get_clean());
}

function print_formatted_targeting(&$fit, $relative) {
	ob_start();

	static $types = array(
		'radar' => [ 12, 0 ],
		'ladar' => [ 13, 0 ],
		'magnetometric' => [ 12, 1 ],
		'gravimetric' => [ 13, 1 ],
	);

	$scanstrength = array();
	$maxtype = null;
	foreach($types as $t => $p) {
		$scanstrength[$t] = \Osmium\Dogma\get_ship_attribute($fit, 'scan'.ucfirst($t).'Strength');
		if($maxtype === null || $scanstrength[$t] > $scanstrength[$maxtype]) {
			$maxtype = $t;
		}
	}

	$targetrange = \Osmium\Dogma\get_ship_attribute($fit, 'maxTargetRange');
	$numtargets = (int)min(\Osmium\Dogma\get_char_attribute($fit, 'maxLockedTargets'),
	                  \Osmium\Dogma\get_ship_attribute($fit, 'maxLockedTargets'));
	$ftargets = 'target'.($numtargets !== 1 ? 's' : '');
	$scanres = \Osmium\Dogma\get_ship_attribute($fit, 'scanResolution');
	$sigradius = \Osmium\Dogma\get_ship_attribute($fit, 'signatureRadius');

	echo "<p>"
		.sprite($relative, ucfirst($maxtype).' Sensor Strength', $types[$maxtype][0], $types[$maxtype][1], 32)
		."<span><span title='Maximum locked targets'>"
		.$numtargets." ".$ftargets."</span><br /><span title='Sensor strength'>"
		.round($scanstrength[$maxtype], 1)." points</span></span></p>\n";

	echo "<p>"
		.sprite($relative, 'Targeting', 6, 1, 64, 64, 32)
		."<span><span title='Targeting range'>".format_range($targetrange)
		."</span><br /><span title='Scan resolution' id='scan_resolution' data-value='".$scanres."'>"
		.format_number($scanres)." mm</span></span></p>\n";

	echo "<p id='signature_radius' title='Signature radius' data-value='".$sigradius."'>"
		.sprite($relative, 'Signature Radius', 12, 4, 32)
		."<span>".format_range($sigradius)."</span></p>\n";

	print_formatted_attribute_category(
		'targeting', 'Targeting',
		$numtargets.' '.$ftargets.' @ '.format_range($targetrange, true),
		'', ob_get_clean()
	);
}

function print_formatted_misc(&$fit) {
	ob_start();

	echo "<table>\n<tbody>\n";

	$missing = array();
	$prices = \Osmium\Fit\get_estimated_price($fit, $missing);
	$grand_total = 0;
	$fprices = array();

	foreach($prices as $section => $p) {
		if($p !== 'N/A') {
			$grand_total += $p;
			$p = format_isk($p);
		}

		$fprices[] = $section.': '.$p;
	}

	if($grand_total === 0 && count($missing) > 0) {
		$grand_total = 'N/A';
	} else {
		$grand_total = format_isk($grand_total);
		if(count($missing) > 0) {
			$grand_total = '≥ '.$grand_total;
		}
	}

	$fprices = implode(',<br />', $fprices);
	if($fprices === '') $fprices = '<small>N/A</small>';

	if(count($missing) > 0) {
		$missing = implode(",&#10;", array_unique(array_map('Osmium\Fit\get_typename', $missing)));
		$fprices = "<span title='Estimate of the following items unavailable:&#10;"
			.escape($missing)."'>".$fprices.'</span>';
	}

	echo "<tr><th>Estimated price:</th><td>{$fprices}</td></tr>\n";

	$yield = \Osmium\Fit\get_mining_yield($fit);
	if($yield > 0) {
		$yield *= 3600000; /* From m³/ms to m³/h */
		$yield = number_format($yield);
		echo "<tr><th>Mining yield:</th><td>$yield m<sup>3</sup>/h</td></tr>\n";
	}

	$cargo = \Osmium\Dogma\get_ship_attribute($fit, 'capacity');
	echo "<tr><th>Cargo capacity:</th><td>".round($cargo, 2)." m<sup>3</sup></td></tr>\n";

	if(count($fit['drones']) > 0) {
		$dcrange = \Osmium\Dogma\get_char_attribute($fit, 'droneControlDistance');
		echo "<tr><th>Drone control range:</th><td>".format_range($dcrange)."</td></tr>\n";
	}

	echo "</tbody>\n</table>\n";
	print_formatted_attribute_category('misc', 'Miscellaneous', $grand_total, '', ob_get_clean());
}

/**
 * Print every section in the attributes bar for a given $fit.
 *
 * @param $opts An associative array, which can contain the following
 * keys:
 * 
 * - flags: a mixture of FATTRIBS_* constants
 * - cap: capacitor information to format (if unspecified, generate it on the fly)
 * - ehp: effective hitpoints to format (if unspecified, generate it on the fly)
 */
function print_formatted_loadout_attributes(&$fit, $relative = '.', $opts = array()) {
	/* NB: if you change the defaults here, also change the default
	 * exported values in CLF export function. */
	$flags = isset($opts['flags']) ? $opts['flags'] : FATTRIBS_USE_RELOAD_TIME_FOR_CAPACITOR;

	if(isset($opts['cap'])) {
		$cap = $opts['cap'];
	} else {
		\Osmium\Dogma\auto_init($fit);
	    dogma_get_capacitor_all(
			$fit['__dogma_context'],
			$flags & FATTRIBS_USE_RELOAD_TIME_FOR_CAPACITOR,
			$result
	    );
	    $cap = $result[dogma_get_hashcode($fit['__dogma_context'])];
	}

	$ia = isset($opts['ia']) ? $opts['ia'] : \Osmium\Fit\get_interesting_attributes($fit);

	$ehp = isset($opts['ehp']) ? $opts['ehp'] :
		\Osmium\Fit\get_ehp_and_resists($fit);

	$prereqs = isset($opts['prerequisites']) ? $opts['prerequisites'] :
		\Osmium\Fit\get_skill_prerequisites_and_missing_prerequisites($fit)[0];

	print_formatted_engineering($fit, $relative, $cap);
	print_formatted_offense($fit, $relative, $ia, $flags & FATTRIBS_USE_RELOAD_TIME_FOR_DPS);
	print_formatted_defense($fit, $relative, $ehp, $cap, $flags & FATTRIBS_USE_RELOAD_TIME_FOR_TANK);
	print_formatted_incoming($fit, $relative, $ehp);
	print_formatted_outgoing($fit, $relative);
	print_formatted_navigation($fit, $relative);
	print_formatted_targeting($fit, $relative);
	print_formatted_mastery($fit, $relative, $prereqs);
	print_formatted_misc($fit);
}

/**
 * Same usage as print_formatted_loadout_attributes(), but returns the
 * result as a string instead of printing it.
 */
function get_formatted_loadout_attributes(&$fit, $relative = '.', $opts = array()) {
	ob_start();
	print_formatted_loadout_attributes($fit, $relative, $opts);
	return ob_get_clean();
}

function print_loadout_common_footer(&$fit, $relative, $clftoken) {
	$cdp = \Osmium\State\get_state_trypersist('custom_damage_profiles', []);
	if($cdp === []) {
		$cdp = "{}";
	} else {
		$cdp = json_encode($cdp);
	}

	add_js_data('cdatastaticver', \Osmium\CLIENT_DATA_STATICVER);
	add_js_data('staticver', \Osmium\STATICVER);
	add_js_data('relative', $relative);
	add_js_data('token', \Osmium\State\get_token());
	add_js_data('clftoken', $clftoken);
	add_js_data('clf', \Osmium\Fit\export_to_common_loadout_format(
		$fit,
		\Osmium\Fit\CLF_EXPORT_MINIFY
		| \Osmium\Fit\CLF_EXPORT_EXTRA_PROPERTIES
		| \Osmium\Fit\CLF_EXPORT_INTERNAL_PROPERTIES
	));
	add_js_data('customdamageprofiles', $cdp);
	add_js_data('skillsets', json_encode(
		\Osmium\Fit\get_available_skillset_names_for_account()
	));

	/* If these scripts change, update the license info in about.php */
	include_js($relative."/static-1/jquery.jsPlumb-1.5.4-min.js");
	include_js($relative."/static-1/rawdeflate.min.js");

	print_js_snippet('tabs');
	print_js_snippet('modal');
	print_js_snippet('context_menu');
	print_js_snippet('loadout_common');
	print_js_snippet('graph_common');
	print_js_snippet('sprite');
	print_js_snippet('show_info');
	print_js_snippet('formatted_attributes');
	print_js_snippet('capacitor');
	print_js_snippet('new_loadout-fattribs');
}
