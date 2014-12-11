<?php
/* Osmium
 * Copyright (C) 2012, 2013, 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

require_once __DIR__.'/../inc/root.php';

class FitAttributes extends PHPUnit_Framework_TestCase {
	private function assertExplosiveResistance(&$fit, $resist) {
		$this->assertEquals(
			$resist,
			1 - \Osmium\Dogma\get_ship_attribute($fit, 'armorExplosiveDamageResonance'),
			'', 0.001
			);
	}

	private function assertCapacitorStatus(&$fit, $rate, $stable, $value) {
		$cap = \Osmium\Fit\get_capacitor_stability($fit);
		$this->assertSame($stable, $cap['stable']);
		$this->assertEquals($rate, 1000 * $cap['delta'], '', 0.1); /* 0.1 GJ/s margin (Pyfa rounding) */
		$this->assertEquals(
			$value,
			($cap['stable'] ? ($cap['stable_fraction'] * 100) : ($cap['depletion_time'] / 1000)),
			'', 0.20 * $value /* 20% margin */
		);
	}

	private function assertShieldResistances(&$fit, $em, $thermal, $kinetic, $explosive) {
		$this->assertEquals(
			$em,
			1 - \Osmium\Dogma\get_ship_attribute($fit, 'shieldEmDamageResonance'),
			'', 0.001
			);	
		$this->assertEquals(
			$thermal,
			1 - \Osmium\Dogma\get_ship_attribute($fit, 'shieldThermalDamageResonance'),
			'', 0.001
			);	
		$this->assertEquals(
			$kinetic,
			1 - \Osmium\Dogma\get_ship_attribute($fit, 'shieldKineticDamageResonance'),
			'', 0.001
			);	
		$this->assertEquals(
			$explosive,
			1 - \Osmium\Dogma\get_ship_attribute($fit, 'shieldExplosiveDamageResonance'),
			'', 0.001
			);		
	}

	private function assertDamagePerSecond($funcname, 
	                                       $ship, $numguns, $gunid, $chargeid, 
	                                       $expectedvolley, $expecteddps,
	                                       $numdamagemods, $damagemodid,
	                                       $expectedvolley2, $expecteddps2) {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, $ship);

		for($i = 0; $i < $numguns; ++$i) {
			\Osmium\Fit\add_module($fit, $i, $gunid);
			\Osmium\Fit\add_charge($fit, 'high', $i, $chargeid);
		}

		$ia = \Osmium\Fit\get_interesting_attributes($fit);
		list($dps, $volley) = $funcname($fit, $ia);
		$this->assertEquals($expecteddps, $dps, '', 1);
		$this->assertEquals($expectedvolley, $volley, '', 1);

		for($i = 0; $i < $numdamagemods; ++$i) {
			\Osmium\Fit\add_module($fit, $i, $damagemodid);
		}

		$ia = \Osmium\Fit\get_interesting_attributes($fit);
		list($dps, $volley) = $funcname($fit, $ia);
		$this->assertEquals($expecteddps2, $dps, '', 1);
		$this->assertEquals($expectedvolley2, $volley, '', 1);
	}

	private function assertGunDamagePerSecond() {
		$args = func_get_args();
		array_unshift($args, 'Osmium\Fit\get_damage_from_turrets');

		call_user_func_array(array($this, 'assertDamagePerSecond'), $args);
	}

	private function assertMissileDamagePerSecond() {
		$args = func_get_args();
		array_unshift($args, 'Osmium\Fit\get_damage_from_missiles');

		call_user_func_array(array($this, 'assertDamagePerSecond'), $args);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testStackingPenalties() {
		static $eanm = 14950; /* Draclira's modified EANM */

		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 24692); /* Abaddon */

		/* Source: Pyfa-c67034e (2013-07-01) */

		/* Base resist (with ship bonus) */
		$this->assertExplosiveResistance($fit, 0.3600);

		/* Fit one EANM */
		\Osmium\Fit\add_module($fit, 0, $eanm);
		$this->assertExplosiveResistance($fit, 0.6020);

		/* Add a second one */
		\Osmium\Fit\add_module($fit, 1, $eanm);
		$this->assertExplosiveResistance($fit, 0.7328);

		/* Etc. */
		\Osmium\Fit\add_module($fit, 2, $eanm);
		$this->assertExplosiveResistance($fit, 0.7904);

		\Osmium\Fit\add_module($fit, 3, $eanm);
		$this->assertExplosiveResistance($fit, 0.8129);

		\Osmium\Fit\add_module($fit, 4, $eanm);
		$this->assertExplosiveResistance($fit, 0.8204);

		\Osmium\Fit\add_module($fit, 5, $eanm);
		$this->assertExplosiveResistance($fit, 0.8224);

		/* Now add a Damage Control, its bonuses should not be
		 * penalized by the EANMs since their modifiers are not in the
		 * same category (premul/postpercent) */
		\Osmium\Fit\add_module($fit, 6, 2048);
		$this->assertExplosiveResistance($fit, 0.8490);

		\Osmium\Fit\destroy($fit);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testReactiveArmorHardenerStackingPenalties() {
		/* The Reactive Armor Hardener is penalized by Damage
		 * Controls, but not by regular hardeners. */

		/* Source: Pyfa-c67034e (2013-07-01) */

		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 24692); /* Abaddon */

		\Osmium\Fit\add_module($fit, 0, 11646); /* Armor Explosive Hardener II */
		\Osmium\Fit\add_module($fit, 2, 11269); /* EANM II */
		\Osmium\Fit\add_module($fit, 3, 11269);
		$this->assertExplosiveResistance($fit, 0.8067);

		\Osmium\Fit\add_module($fit, 4, 2048); /* DC II */
		$this->assertExplosiveResistance($fit, 0.8357);

		\Osmium\Fit\change_module_state_by_typeid($fit, 4, 2048, \Osmium\Fit\STATE_ONLINE);
		\Osmium\Fit\add_module($fit, 5, 4403); /* Reactive Armor Hardener */
		$this->assertExplosiveResistance($fit, 0.8357);

		/* Assert penalized resist */
		\Osmium\Fit\remove_module($fit, 0, 11646);
		\Osmium\Fit\remove_module($fit, 2, 11269);
		\Osmium\Fit\remove_module($fit, 3, 11269);
		\Osmium\Fit\change_module_state_by_typeid($fit, 4, 2048, \Osmium\Fit\STATE_ACTIVE);
		$this->assertExplosiveResistance($fit, 0.5269);
		
		\Osmium\Fit\destroy($fit);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testCapacitorStability() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 11978); /* Scimitar */
		/* Large S95a Partial Shield Transporter */
		\Osmium\Fit\add_module($fit, 0, 8641);
		\Osmium\Fit\add_module($fit, 1, 8641);
		\Osmium\Fit\add_module($fit, 2, 8641);
		\Osmium\Fit\add_module($fit, 3, 8641);

		/* Pyfa 1.1.7-git. Its estimates for duration are slightly off
		 * compared to in-game results, but I cannot test these here
		 * (ideally someone with all cap-related skills to V
		 * (including cap reduction usage skills) should go to Sisi to
		 * provide test data). */
		$this->assertCapacitorStatus($fit, 42 - 18.7, false, 52);

		\Osmium\Fit\add_module($fit, 0, 31372); /* Medium CCC */
		$this->assertCapacitorStatus($fit, 42 - 22, false, 58);

		\Osmium\Fit\add_module($fit, 1, 31372);
		$this->assertCapacitorStatus($fit, 42 - 25.9, false, 1 * 60 + 7);

		\Osmium\Fit\add_module($fit, 0, 2032); /* Cap Recharger II */
		$this->assertCapacitorStatus($fit, 42 - 32.3, false, 1 * 60 + 32);

		\Osmium\Fit\add_module($fit, 1, 2032);
		$this->assertCapacitorStatus($fit, 42 - 40.4, false, 4 * 60 + 23);

		\Osmium\Fit\add_module($fit, 2, 2032);
		$this->assertCapacitorStatus($fit, 42 - 50.5, true, 49.8);

		\Osmium\Fit\add_module($fit, 3, 2032);
		$this->assertCapacitorStatus($fit, 42 - 63.1, true, 62.3);

		\Osmium\Fit\add_module($fit, 4, 2032);
		$this->assertCapacitorStatus($fit, 42 - 78.9, true, 70.9);

		\Osmium\Fit\add_module($fit, 0, 1447); /* Capacitor Power Relay II */
		$this->assertCapacitorStatus($fit, 42 - 103.8, true, 78.4);

		\Osmium\Fit\add_module($fit, 1, 1447);
		$this->assertCapacitorStatus($fit, 42 - 136.6, true, 83.9);

		\Osmium\Fit\add_module($fit, 2, 1447);
		$this->assertCapacitorStatus($fit, 42 - 179.8, true, 87.9);

		\Osmium\Fit\add_module($fit, 3, 1447);
		$this->assertCapacitorStatus($fit, 42 - 236.6, true, 90.8);

		\Osmium\Fit\change_module_state_by_typeid($fit, 0, 8641, \Osmium\Fit\STATE_ONLINE);
		$this->assertCapacitorStatus($fit, 31.5 - 236.6, true, 93.1);

		\Osmium\Fit\change_module_state_by_typeid($fit, 1, 8641, \Osmium\Fit\STATE_ONLINE);
		$this->assertCapacitorStatus($fit, 21 - 236.6, true, 95.3);

		\Osmium\Fit\change_module_state_by_typeid($fit, 2, 8641, \Osmium\Fit\STATE_ONLINE);
		$this->assertCapacitorStatus($fit, 10.5 - 236.6, true, 97.4);

		\Osmium\Fit\change_module_state_by_typeid($fit, 3, 8641, \Osmium\Fit\STATE_ONLINE);
		$this->assertCapacitorStatus($fit, -236.6, true, 100);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testCapacitorStabilityWithCapBooster() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 24692); /* Abaddon */

		for($i = 0; $i < 8; ++$i) {
			/* MPL II + Multifrequency L */
			\Osmium\Fit\add_module($fit, $i, 3057);
			\Osmium\Fit\add_charge($fit, 'high', $i, 262);
		}

		/* Source: Pyfa-c67034e (2013-07-01) */

		$this->assertCapacitorStatus($fit, 38.1 - 21.3, false, 5 * 60 + 53);

		/* Heavy Capacitor Booster II, with 800 charges */
		\Osmium\Fit\add_module($fit, 0, 3578);
		\Osmium\Fit\add_charge($fit, 'medium', 0, 11289);

		/* Pyfa factors in reload time of capacitor boosters. */
		$this->assertCapacitorStatus($fit, 38.1 - 78.4, true, 100.0);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testProjectileDPS() {
		/* Tempest with 1400mm artilleries and Quake */
		/* Pyfa 1.1.7-git */
		$this->assertGunDamagePerSecond(639, 6, 2961, 12761, 
		                                8505, 392, 
		                                3, 519,
		                                10749, 648);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testHybridDPS() {
		/* Brutix with Heavy Ion Blasters and Antimatter */
		/* Source: Pyfa-c67034e (2013-07-01) */
		$this->assertGunDamagePerSecond(16229, 6, 3138, 230, 
		                                1210, 374, 
		                                3, 10190,
		                                1530, 617);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testLaserDPS() {
		/* Punisher with Medium Pulse Lasers and faction Multifrequency */
		/* Pyfa 1.1.7-git */
		$this->assertGunDamagePerSecond(597, 3, 3041, 23071, 
		                                295, 117, 
		                                3, 2364,
		                                372, 193);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testMissileDPS() {
		/* Raven with Cruise missiles */
		/* Source: Pyfa-c67034e (2013-07-01) */
		$this->assertMissileDamagePerSecond(638, 6, 19739, 204, 
		                                    3094, 362, 
		                                    4, 22291,
		                                    4021, 635);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testDroneAndSentryDPS() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 12005); /* Ishtar */
		\Osmium\Fit\add_module($fit, 0, 32083); /* Sentry Damage Augmentor I */
		\Osmium\Fit\add_module($fit, 1, 32083);
		\Osmium\Fit\add_module($fit, 0, 4405); /* Drone Damage Amplifier II */
		\Osmium\Fit\add_module($fit, 1, 4405);
		\Osmium\Fit\add_module($fit, 2, 4405);

		/* Source: Pyfa-c67034e (2013-07-01) */

		$ia = \Osmium\Fit\get_interesting_attributes($fit);
		$dps = \Osmium\Fit\get_damage_from_drones($fit, $ia)[0];
		$this->assertSame(0, $dps);

		\Osmium\Fit\add_drone($fit, 28211, 0, 5); /* 5x Garde IIs in space */
		\Osmium\Fit\add_drone($fit, 2488, 5, 0); /* 5x Warrior IIs in bay */

		$this->assertEquals(
			5 * 25 + 5 * 5,
			\Osmium\Fit\get_used_drone_capacity($fit),
			'', 0
		);

		$ia = \Osmium\Fit\get_interesting_attributes($fit);
		$dps = \Osmium\Fit\get_damage_from_drones($fit, $ia)[0];
		$this->assertEquals(781, $dps, '', 1);

		/* Swap the drones */
		\Osmium\Fit\transfer_drone($fit, 28211, 'space', 5);
		\Osmium\Fit\transfer_drone($fit, 2488, 'bay', 5);

		/* Add drones that do no damage */
		\Osmium\Fit\add_drone($fit, 23731, 0,  5);

		$ia = \Osmium\Fit\get_interesting_attributes($fit);
		$dps = \Osmium\Fit\get_damage_from_drones($fit, $ia)[0];
		$this->assertEquals(201, $dps, '', 1);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testAncillaryShieldBoosters() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 16231); /* Cyclone */
		\Osmium\Fit\add_module($fit, 0, 32780); /* X-Large ASB */
		\Osmium\Fit\add_module($fit, 1, 4391); /* Large ASB */
		\Osmium\Fit\add_module($fit, 2, 4391);

		/* Source: Pyfa-c67034e (2013-07-01) */

		$this->assertEquals(400, \Osmium\Dogma\get_ship_attribute($fit, 'cpuLoad'), '', 0.05);
		$this->assertEquals(800, \Osmium\Dogma\get_ship_attribute($fit, 'powerLoad'), '', 0.05);
		$this->assertEquals(656.3, \Osmium\Dogma\get_ship_attribute($fit, 'cpuOutput'), '', 0.05);
		$this->assertEquals(1375.0, \Osmium\Dogma\get_ship_attribute($fit, 'powerOutput'), '', 0.05);

		$ehp = \Osmium\Fit\get_ehp_and_resists($fit);
		$capacitor = \Osmium\Fit\get_capacitor_stability($fit);
		$tank = \Osmium\Fit\get_tank($fit, $ehp, $capacitor['delta']);

		$this->assertEquals(741.6, 1000 * $tank['shield'][0], '', 0.05);
		$this->assertEquals(24.8, 1000 * $tank['shield'][1], '', 0.05);

		\Osmium\Fit\add_charge($fit, 'medium', 0, 11287);
		\Osmium\Fit\add_charge($fit, 'medium', 1, 11283);
		\Osmium\Fit\add_charge($fit, 'medium', 2, 11283);

		$capacitor = \Osmium\Fit\get_capacitor_stability($fit);
		$tank = \Osmium\Fit\get_tank($fit, $ehp, $capacitor['delta']);

		$this->assertSame($tank['shield'][0], $tank['shield'][1]);
		$this->assertEquals(741.6, 1000 * $tank['shield'][0], '', 0.05);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testStrategicCruiserAvailableHardpoints() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 29984); /* Tengu */
		\Osmium\Fit\add_module($fit, 0, 30122); /* Accelerated Ejection Bay */
		\Osmium\Fit\add_module($fit, 0, 2410); /* Heavy Missile Launcher */
		\Osmium\Fit\add_module($fit, 1, 2410);

		$this->assertEquals(5, \Osmium\Dogma\get_ship_attribute($fit, 'launcherSlots'));
		$this->assertEquals(3, \Osmium\Dogma\get_ship_attribute($fit, 'launcherSlotsLeft'));
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testTitanAttributeNaming() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 671); /* Erebus */
		\Osmium\Fit\add_module($fit, 0, 20448); /* Dual 1000mm Railgun I */
		\Osmium\Fit\add_charge($fit, 'high', 0, 17648); /* Antimatter Charge XL */

		/* Pyfa 1.1.8 */
		$ia = \Osmium\Fit\get_interesting_attributes($fit);
		list($dps, $alpha) = \Osmium\Fit\get_damage_from_turrets($fit, $ia);
		$this->assertEquals(465, $dps, '', 0.5);
		$this->assertEquals(4802.4, $alpha, '', 0.05);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testFalloffWithStackingPenalizedRig() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 28665); /* Vargur */
		\Osmium\Fit\add_module($fit, 0, 2929); /* 800mm Repeating Artillery II */
		\Osmium\Fit\add_charge($fit, 'high', 0, 201); /* EMP L */

		\Osmium\Fit\add_module($fit, 0, 15965); /* RF Tracking Enhancer */
		\Osmium\Fit\add_module($fit, 1, 15965);
		\Osmium\Fit\add_module($fit, 0, 15792); /* FN Tracking Computer */
		\Osmium\Fit\add_charge($fit, 'medium', 0, 28999); /* Optimal Range Script */
		\Osmium\Fit\add_module($fit, 0, 26038); /* Large Projectile Ambit Extension I */

		/* Source: Pyfa-c67034e (2013-07-01) */

		$a = \Osmium\Fit\get_module_interesting_attributes($fit, 'high', 0);
		$this->assertEquals(3.998, $a['range'] / 1000, '', 0.005);
		$this->assertEquals(64.783, $a['falloff'] / 1000, '', 0.05);
		$this->assertEquals(0.0888, $a['trackingspeed'], '', 0.00005);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testRocketRange() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 587); /* Rifter */
		\Osmium\Fit\add_module($fit, 0, 10631); /* Rocket Launcher II */
		\Osmium\Fit\add_charge($fit, 'high', 0, 2516); /* Nova Rocket */

		/* Pyfa 1.1.8, the range is only an approximation and is actually slightly wrong */
		$a = \Osmium\Fit\get_module_interesting_attributes($fit, 'high', 0);
		$this->assertEquals(9960, $a['maxrange'], '', 250);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testDefenderMissileRange() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 587); /* Rifter */
		\Osmium\Fit\add_module($fit, 0, 2404); /* Light Missile Launcher II */
		\Osmium\Fit\add_charge($fit, 'high', 0, 32782); /* Light Defender Missile I */

		/* No source for this. Pyfa doesn't show range (although the
		 * flighttime * velocity checks out) and EFT doesn't apply
		 * skill bonuses to the defender missile. */
		$a = \Osmium\Fit\get_module_interesting_attributes($fit, 'high', 0);
		$this->assertEquals(225000, $a['maxrange'], '', 1);
	}

	/**
	 * @group fit
	 * @group engine
	 */
	public function testDamageProfiles() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 24690); /* Hyperion */
		\Osmium\Fit\add_module($fit, 0, 3540); /* LAR II */
		\Osmium\Fit\add_module($fit, 1, 3540); /* LAR II */
		\Osmium\Fit\add_module($fit, 2, 3540); /* LAR II */
		\Osmium\Fit\add_module($fit, 0, 10842); /* XL SB II */
		\Osmium\Fit\add_module($fit, 1, 3665); /* Large Hull Repairer II */
		\Osmium\Fit\add_module($fit, 2, 4391); /* Large ASB */

		$capacitor = \Osmium\Fit\get_capacitor_stability($fit);

		/* Test with uniform damage profile */
		\Osmium\Fit\set_damage_profile($fit, 'Uniform', 1, 1, 1, 1);
		$ehp = \Osmium\Fit\get_ehp_and_resists($fit);
		$tank = \Osmium\Fit\get_tank($fit, $ehp, $capacitor['delta']);

		/* Pyfa-a4d72ca (Oct 7 2013) */

		$this->assertEquals(38370, $ehp['ehp']['avg'], '', 1);
		$this->assertEquals(17.2, 1000 * $tank['shield_passive'][0], '', 0.05);
		$this->assertSame($tank['shield_passive'][0], $tank['shield_passive'][1]);
		$this->assertEquals(324.8, 1000 * $tank['shield'][0], '', 0.05);
		$this->assertEquals(0, $tank['shield'][1]);
		$this->assertEquals(499.8, 1000 * $tank['armor'][0], '', 0.05);
		$this->assertEquals(93.7, 1000 * $tank['armor'][1], '', 0.05);
		$this->assertEquals(6.7, 1000 * $tank['hull'][0], '', 0.05);
		$this->assertEquals(0, $tank['hull'][1]);

		/* Test with a more general profile */
		\Osmium\Fit\set_damage_profile($fit, 'Test', 10, 30, 40, 20);
		$ehp = \Osmium\Fit\get_ehp_and_resists($fit);
		$tank = \Osmium\Fit\get_tank($fit, $ehp, $capacitor['delta']);

		$this->assertEquals(39132, $ehp['ehp']['avg'], '', 1);
		$this->assertEquals(19.2, 1000 * $tank['shield_passive'][0], '', 0.05);
		$this->assertSame($tank['shield_passive'][0], $tank['shield_passive'][1]);
		$this->assertEquals(362.3, 1000 * $tank['shield'][0], '', 0.05);
		$this->assertEquals(0, 1000 * $tank['shield'][1], '', 0.05);
		$this->assertEquals(475.1, 1000 * $tank['armor'][0], '', 0.05);
		$this->assertEquals(89.1, 1000 * $tank['armor'][1], '', 0.05);
		$this->assertEquals(6.7, 1000 * $tank['hull'][0], '', 0.05);
		$this->assertEquals(0, $tank['hull'][1]);
	}

	/**
	 * @group fit
	 * @group engine
	 * @group import
	 */
	public function testMiningYield() {
		/* Source: Pyfa-c67034e (2013-07-01) */

		/* Mackinaw with 2x Ice Harvester II and one upgrade and one ice harvester accelerator rig */
		$fit = \Osmium\Fit\try_parse_fit_from_shipdna('22548:22229;2:32772;2:9568:2553:2048:28578:3888:31360:32819:2488;5:2456;4:32787:264;2::', 'Mackinaw', $errors);
		$this->assertEquals(
			2 * 1000.0 / 95085.4905,
			\Osmium\Fit\get_mining_yield($fit),
			'',
			0.000000001
		);

		/* Venture with 2x Modulated DCM IIs and T2 crystals */
		$fit = \Osmium\Fit\try_parse_fit_from_shipdna('32880:18068;2:18618;2::', 'Venture', $errors);
		$this->assertEquals(
			2 * 820.3125 / 180000.0,
			\Osmium\Fit\get_mining_yield($fit),
			'',
			0.000000001
		);
	}

	/**
	 * @group fit
	 */
	public function testModes() {
		\Osmium\Fit\create($fit);
		\Osmium\Fit\select_ship($fit, 34317); /* Confessor */

		$v0 = \Osmium\Dogma\get_ship_attribute($fit, 'maxVelocity');
		\Osmium\Fit\set_mode($fit, 34323); /* Propulsion mode */
		$v1 = \Osmium\Dogma\get_ship_attribute($fit, 'maxVelocity');
		\Osmium\Fit\set_mode($fit, null);
		$v2 = \Osmium\Dogma\get_ship_attribute($fit, 'maxVelocity');

		$this->assertSame($v0, $v2);
		$this->assertGreaterThan($v0, $v1);
	}
}
