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

namespace Osmium\Fit;

/*
 * KEEP THIS NAMESPACE PURE.
 * 
 * Functions in \Osmium\Fit must not affect or rely on the global
 * state at all. This makes it easier to test and debug.
 */

require __DIR__.'/fit-names.php';
require __DIR__.'/fit-presets.php';
require __DIR__.'/fit-attributes.php';
require __DIR__.'/fit-tags.php';
require __DIR__.'/fit-db.php';
require __DIR__.'/fit-db-versions.php';
require __DIR__.'/fit-importexport.php';



/** The loadout can be viewed by everyone. */
const VIEW_EVERYONE = 0;

/** The loadout can be viewed by everyone, provided they have the
 * password. This mode implies VISIBILITY_PRIVATE.*/
const VIEW_PASSWORD_PROTECTED = 1;

/** The loadout can only be viewed by characters in the same alliance
 * than the author. */
const VIEW_ALLIANCE_ONLY = 2;

/** The loadout can only be viewed by characters in the same
 * corporation than the author. */
const VIEW_CORPORATION_ONLY = 3;

/** The loadout can only be viewed by its author. */
const VIEW_OWNER_ONLY = 4;

/** The loadout can only be viewed by contacts with good standing with the author. */
const VIEW_GOOD_STANDING = 5;

/** The loadout can only be viewed by contacts with excellent standing with the author. */
const VIEW_EXCELLENT_STANDING = 6;


/** The loadout can only be edited by its author. */
const EDIT_OWNER_ONLY = 0;

/** The loadout can only be edited by its author and people in the
 * same corporation with the "Fitting Manager" role (or directors). */
const EDIT_OWNER_AND_FITTING_MANAGER_ONLY = 1;

/** The loadout can be edited by its author and anyone in the same
 * corporation. */
const EDIT_CORPORATION_ONLY = 2;

/** The loadout can be edited by its author and everyone in the same
 * alliance. */
const EDIT_ALLIANCE_ONLY = 3;



/** The loadout can be indexed by the Osmium search engine and other
 * search engines, and will appear on search results when appropriate
 * (conforming with the view permission). */
const VISIBILITY_PUBLIC = 0;

/** The loadout can never appear on any search results and will never
 * be indexed. It is still accessible to anyone (conforming with the
 * view permission) provided they have manually been given the URI. */
const VISIBILITY_PRIVATE = 1;



/** Offline module. (Such modules do not use CPU/Power.) */
const STATE_OFFLINE = 0;

/** Online module. */
const STATE_ONLINE = 1;

/** Active module (assumes online). */
const STATE_ACTIVE = 2;

/** Overloaded module (assumes active). */
const STATE_OVERLOADED = 3;



/**
 * Get the different module slot categories.
 *
 * @returns array(slottype => [ title, sprite_position, stateful, attributename ]
 */
function get_slottypes() {
	return array(
		'high' => [ 'High slots', [ 0, 15, 64, 64 ], true, 'hiSlots' ],
		'medium' => [ 'Medium slots', [ 1, 15, 64, 64 ], true, 'medSlots' ],
		'low' => [ 'Low slots', [ 2, 15, 64, 64 ], true, 'lowSlots' ],
		'rig' => [ 'Rig slots', [ 3, 15, 64, 64 ], false, 'upgradeSlotsLeft' ],
		'subsystem' => [ 'Subsystems', [ 4, 15, 64, 64 ], false, 'maxSubSystems' ],
	);
}

/**
 * Get an array of formatted state names, with the name of the picture
 * represting the state.
 *
 * @returns [ STATE_* => [PrettyName, Sprite position, CLFName], … ]
 */
function get_state_names() {
	return array(
		STATE_OFFLINE => array('Offline', [ 2, 58, 16, 16 ], 'offline'),
		STATE_ONLINE => array('Online', [ 2, 59, 16, 16 ], 'online'),
		STATE_ACTIVE => array('Active', [ 3, 58, 16, 16 ], 'active'),
		STATE_OVERLOADED => array('Overloaded', [ 0, 29, 32, 32 ], 'overloaded'),
		);
}



/* ----------------------------------------------------- */



/**
 * Initialize a new fitting in variable $fit.
 *
 * FIXME: do not rely on this structure anywhere outside this
 * namespace (use proper accessors/mutators, especially in src/)
 * 
 * Structure of the created array:
 * 
 * ship => array(typeid, typename)
 *
 * modulepresetid => (integer) 
 * modulepresetname => (string)
 * modulepresetdesc => (string)
 * modules => array(<slot_type> => array(<index> => array(typeid, typename, dogma_index, state, target)))
 *
 * chargepresetid => (integer) 
 * chargepresetname => (string)
 * chargepresetdesc => (string)
 * charges => array(<slot_type> => array(<index> => array(typeid, typename)))
 *
 * dronepresetid => (integer)
 * dronepresetname => (string)
 * dronepresetdesc => (string)
 * drones => array(<typeid> => array(typeid, typename, volume, bandwidth, quantityin{bay, space})))
 *
 * implants => array(<typeid> => array(typeid, typename, slot, dogma_index, sideeffects => array(effectid)))
 *
 * presets => array(<presetid> => array(name, description, modules, chargepresets, implants))
 *
 * chargepresets => array(<presetid> => array(name, description, charges))
 *
 * dronepresets => array(<presetid> => array(name, description, drones))
 *
 * fleet => array('fleet' => $fit, 'wing' => $fit, 'squad' => $fit)
 *
 * remote => array(<key> => $fit)
 *
 * skillset => array(name, default, override)
 *
 * damageprofile => array(name, damages => array(em, explosive, kinetic, thermal))
 *
 * metadata => array(name, description, tags, evebuildnumber,
 *					 view_permission, edit_permission, visibility,
 *					 password, loadoutid, hash, revision,
 *					 privatetoken)
 *
 * __dogma_context => (Dogma context resource)
 * __dogma_fleet_context => (Dogma fleet context resource)
 */
function create(&$fit) {
	$fit = array(
		'ship' => array(),
		'presets' => array(),
		'dronepresets' => array(),
		'fleet' => array(),
		'remote' => array(),
		'skillset' => array(
			'name' => 'All V',
			'default' => 5,
			'override' => [],
		),
		'damageprofile' => array(
			'name' => 'Uniform',
			'damages' => array(
				'em' => .25,
				'explosive' => .25,
				'kinetic' => .25,
				'thermal' => .25,
			),
		),
		'metadata' => array(
			'name' => 'Unnamed loadout',
			'description' => '',
			'tags' => array(),
			'evebuildnumber' => get_latest_eve_db_version()['build'],
			'view_permission' => VIEW_EVERYONE,
			'edit_permission' => EDIT_OWNER_ONLY,
			'visibility' => VISIBILITY_PUBLIC,
			)
		);

	$presetid = create_preset($fit, 'Default preset', '');
	use_preset($fit, $presetid);

	$dpid = create_drone_preset($fit, 'Default drone preset', '');
	use_drone_preset($fit, $dpid);
}

/**
 * Delete a fitting so that memory can be claimed back by the garbage
 * collector.
 */
function destroy(&$fit) {
	$fit = array();
}

/**
 * Reset a fitting. This is the same as calling destroy() then
 * create() immediately after.
 */
function reset(&$fit) {
	destroy($fit);
	create($fit);
}

/** @internal */
function get_gzclf_id(&$fit) {
	if(isset($fit['ship']['typeid'])) {
		goto nempty;
	}

	foreach($fit['presets'] as $p) {
		foreach($p['modules'] as $sub) {
			foreach($sub as $m) {
				goto nempty;
			}
		}

		foreach($p['implants'] as $i) {
			if(isset($i['sideeffects'])) {
				foreach($i['sideeffects'] as $se) {
					goto gzclf;
				}
			}

			goto nempty;
		}
	}

	foreach($fit['dronepresets'] as $dp) {
		foreach($dp['drones'] as $d) {
			if($d['quantityinbay'] > 0 || $d['quantityinspace'] > 0) {
				goto nempty;
			}
		}
	}

	return '(empty fitting)';

nempty:
	if(count($fit['presets']) <= 1
	   && count($fit['chargepresets']) <= 1
	   && count($fit['dronepresets']) <= 1
	   && (!isset($fit['fleet']) || $fit['fleet'] === [])
	   && (!isset($fit['remote']) || $fit['remote'] === [])
	) {
		return export_to_augmented_dna($fit);
	}

gzclf:
	return 'gzclf://'.export_to_gzclf_raw(
		$fit,
		CLF_EXPORT_MINIFY | CLF_EXPORT_INTERNAL_PROPERTIES
	);
}



/* ----------------------------------------------------- */



/**
 * Change the ship of a fitting. This can be done anytime, even when
 * there are fitted modules and whatnot.
 */
function select_ship(&$fit, $new_typeid) {
	$row = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT invships.typename FROM osmium.invships
		JOIN eve.invtypes ON invtypes.typeid = invships.typeid
		WHERE invships.typeid = $1', 
		array($new_typeid)
	));
	if($row === false) return false;

	$fit['ship'] = array(
		'typeid' => $new_typeid,
		'typename' => $row[0],
	);

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_set_ship($fit['__dogma_context'], $new_typeid);
	}

	return true;
}



/* ----------------------------------------------------- */



/**
 * Add a module to the fit at a specific index. This will gracefully
 * overwrite any existing module of the same type at the same index if
 * there is one.
 *
 * @param $state state of the module (on of the STATE_* constants). If
 * unspecified, the default is STATE_ACTIVE or STATE_ONLINE for
 * modules which cannot be activated.
 */
function add_module(&$fit, $index, $typeid, $state = null) {
	$type = get_module_slottype($fit, $typeid);

	if(isset($fit['modules'][$type][$index])) {
		if($fit['modules'][$type][$index]['typeid'] == $typeid) {
            if($state !== null) {
                /* Module is already installed, but state still needs an update */
	            change_module_state_by_typeid($fit, $index, $typeid, $state);
            }

			return;
		}

		remove_module($fit, $index, $fit['modules'][$type][$index]['typeid']);
	}

	list($isactivable, ) = get_module_states($fit, $typeid);
	if($state === null) {
		$state = ($isactivable && get_slottypes()[$type][2]) ? STATE_ACTIVE : STATE_ONLINE;
	}

	$fit['modules'][$type][$index] = array(
		'typeid' => $typeid,
		'typename' => get_typename($typeid),
		'state' => $state
	);

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_add_module_s(
			$fit['__dogma_context'],
			$typeid,
			$fit['modules'][$type][$index]['dogma_index'],
			\Osmium\Dogma\get_dogma_states()[$state]
		);
	}
}

/**
 * Remove a certain module located at a specific index from the
 * current preset. This will also remove charges of this module in all
 * charge presets.
 */
function remove_module(&$fit, $index, $typeid) {
	$type = get_module_slottype($fit, $typeid);

	if(!isset($fit['modules'][$type][$index]['typeid'])) {
		// @codeCoverageIgnoreStart
		trigger_error('remove_module(): trying to remove a nonexistent module!', E_USER_WARNING);
		return;
		// @codeCoverageIgnoreEnd
	}

	if(isset($fit['charges'][$type][$index])) {
		/* Remove charge */
		remove_charge($fit, $type, $index);
	}

	foreach($fit['chargepresets'] as $cpid => &$chargepreset) {
		if(isset($chargepreset['charges'][$type][$index])) {
			/* Remove charge in other presets */
			unset($chargepreset['charges'][$type][$index]);
		}
	}

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_remove_module($fit['__dogma_context'], $fit['modules'][$type][$index]['dogma_index']);
	}

	unset($fit['modules'][$type][$index]);
}

/**
 * Sort fitted modules with a given order. Modules of each type will
 * be sorted by ascending order_position.
 *
 * FIXME: this will NOT sort accociated charges.
 *
 * @param $order array of type array(<slot_type> => array(<index> =>
 * <order_position>)).
 */
function sort_modules(&$fit, $order) {
	foreach($fit['modules'] as $type => &$modules) {
		uksort($modules, function($a, $b) use($type, $order) {
				if(!isset($order[$type][$a])) return -1;
				if(!isset($order[$type][$b])) return 1;

				return $order[$type][$a] - $order[$type][$b];
			});
	}
}

/**
 * Change the state of a module located by its position. Be
 * careful, the new state is not checked, so if you pass a nonsensical
 * value (like STATE_ACTIVE for a passive-only module), weird things
 * may happen (but probably not!).
 *
 * @param $state one of the STATE_* constants.
 */
function change_module_state_by_location(&$fit, $type, $index, $state) {
	$m =& $fit['modules'][$type][$index];
	if($m['state'] === $state) return;

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_set_module_state(
			$fit['__dogma_context'],
			$m['dogma_index'],
			\Osmium\Dogma\get_dogma_states()[$state]
		);
	}

	$m['state'] = $state;
}

/**
 * Change the state of a module located by its typeid. See
 * change_module_state_by_location() for the full caveat.
 */
function change_module_state_by_typeid(&$fit, $index, $typeid, $state) {
	return change_module_state_by_location($fit, get_module_slottype($fit, $typeid), $index, $state);
}

/**
 * Toggle the state of a module located at a specific index. The order
 * of toggling is as follows:
 *
 * offline -> online -> active -> overloaded -> offline
 *
 * If it is not possible to go to the next state (for example if a
 * module cannot be activated or overloaded), it will be toggled back
 * to the offline state.
 *
 * @param $next If set to false, toggle state in the opposite order.
 */
function toggle_module_state(&$fit, $index, $typeid, $next = true) {
	$state = get_module_state_by_typeid($fit, $index, $typeid);

	list($isactivable, $isoverloadable) = get_module_states($fit, $typeid);
	$new_state = $next ? get_next_state($state, $isactivable, $isoverloadable) :
		get_previous_state($state, $isactivable, $isoverloadable);

	change_module_state_by_typeid($fit, $index, $typeid, $new_state);
}

function get_next_state($state, $isactivable, $isoverloadable) {
	if($state === null) {
		// @codeCoverageIgnoreStart
		/* Should theoratically not happen, but handle it anyway */
		return STATE_OFFLINE;
		// @codeCoverageIgnoreEnd
	} else if($state === STATE_OFFLINE) {
		return STATE_ONLINE;
	} if($state === STATE_ONLINE) {
		return $isactivable ? STATE_ACTIVE : STATE_OFFLINE;
	} else if($state === STATE_ACTIVE) {
		return $isoverloadable ? STATE_OVERLOADED : STATE_OFFLINE;
	} else if($state === STATE_OVERLOADED) {
		return STATE_OFFLINE;
	}

	// @codeCoverageIgnoreStart
	/* This is serious. We're fucked up. */
	trigger_error('get_next_state(): unknown module state ('.$state.')', E_USER_ERROR);
	// @codeCoverageIgnoreEnd
}

function get_previous_state($state, $isactivable, $isoverloadable) {
	if($state === STATE_OVERLOADED) {
		return STATE_ACTIVE;
	} else if($state === STATE_ACTIVE) {
		return STATE_ONLINE;
	} if($state === STATE_ONLINE) {
		return STATE_OFFLINE;
	} else if($state === STATE_OFFLINE || $state === null) {
		if($isoverloadable) return STATE_OVERLOADED;
		if($isactivable) return STATE_ACTIVE;
		return STATE_ONLINE;
	}

	// @codeCoverageIgnoreStart
	trigger_error('get_previous_state(): unknown module state ('.$state.')', E_USER_ERROR);
	// @codeCoverageIgnoreEnd
}

/**
 * Get possible states of a module.
 *
 * Returns an array of two booleans, the first one is true iff the
 * module is activable, and the second one is true iff the module is
 * overloadable.
 */
function get_module_states(&$fit, $typeid) {
	dogma_type_has_overload_effects($typeid, $overloadable);
	if($overloadable) return array(true, true);

	dogma_type_has_active_effects($typeid, $activable);
	return array($activable, false);
}



/* ----------------------------------------------------- */



/**
 * Add a charge to the currently selected charge preset.
 */
function add_charge(&$fit, $slottype, $index, $typeid) {
	if(!isset($fit['modules'][$slottype][$index])) {
		// @codeCoverageIgnoreStart
		trigger_error('add_charge(): cannot add charge to an empty module!', E_USER_WARNING);
		return;
		// @codeCoverageIgnoreEnd
	}

	if(isset($fit['charges'][$slottype][$index])) {
		if($fit['charges'][$slottype][$index]['typeid'] == $typeid) {
			return;
		}

		remove_charge($fit, $slottype, $index);
	}

	$fit['charges'][$slottype][$index] = array(
		'typeid' => $typeid,
		'typename' => get_typename($typeid)
	);

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_add_charge(
			$fit['__dogma_context'],
			$fit['modules'][$slottype][$index]['dogma_index'],
			$typeid
		);
	}
}

/**
 * Remove a charge from the currently selected charge preset.
 */
function remove_charge(&$fit, $slottype, $index) {
	if(!isset($fit['charges'][$slottype][$index]['typeid'])) {
		// @codeCoverageIgnoreStart
		trigger_error('remove_charge(): cannot remove a nonexistent charge.', E_USER_WARNING);
		return;
		// @codeCoverageIgnoreEnd
	}

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_remove_charge(
			$fit['__dogma_context'],
			$fit['modules'][$slottype][$index]['dogma_index']
		);
	}

	unset($fit['charges'][$slottype][$index]);
}



/* ----------------------------------------------------- */



/**
 * Add a drone type to a fitting. If drones of the same typeid already
 * are in the fitting, increase the quantities with the supplied
 * values instead.
 */
function add_drone(&$fit, $typeid, $quantityinbay = 1, $quantityinspace = 0) {
	if($quantityinbay == 0 && $quantityinspace == 0) return;

	if(!isset($fit['drones'][$typeid])) {
		$fit['drones'][$typeid] = array(
			'typeid' => (int)$typeid,
			'typename' => get_typename($typeid),
			'volume' => (float)get_volume($typeid),
			'quantityinbay' => 0,
			'quantityinspace' => 0,
		);
	}

	$fit['drones'][$typeid]['quantityinbay'] += $quantityinbay;
	$fit['drones'][$typeid]['quantityinspace'] += $quantityinspace;

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_add_drone(
			$fit['__dogma_context'],
			$typeid,
			$quantityinspace
		);
	}
}

/**
 * Add a drone to a fitting.
 *
 * The drones will be added in priority in space as long as it is
 * possible (in respect to bandwidth and maximum number of drones in
 * space); if not then the drone is added in the bay.
 */
function add_drone_auto(&$fit, $typeid, $quantity) {
	if($quantity == 0) return;

	/* Add drone in space first (because we need to get the bandwidth
	 * usage, and it is only possible to query attributes of drones in
	 * space */
	add_drone($fit, $typeid, 0, $quantity);
	$usedbw = \Osmium\Dogma\get_drone_attribute($fit, $typeid, 'droneBandwidthUsed');

	$available = 
		\Osmium\Dogma\get_ship_attribute($fit, 'droneBandwidth')
		- get_used_drone_bandwidth($fit);

	/* How many drones are over the available bandwidth? */
	$over_bw = min(max(0, ceil(-$available / $usedbw)), $quantity);

	/* How many drones are over the drone limit? */
	$over_cnt = -\Osmium\Dogma\get_char_attribute($fit, 'maxActiveDrones');
	foreach($fit['drones'] as $d) {
		$over_cnt += $d['quantityinspace'];
	}
	$over_cnt = min($over_cnt, $quantity);

	transfer_drone($fit, $typeid, 'space', max($over_bw, $over_cnt));
}

/**
 * Remove drones from a fitting.
 *
 * @param $typeid the typeid of the drones to remove
 * @param $from either "space" or "bay", where to remove the drones from
 * @param $quantity number of drones to remove; defaults to 1
 */
function remove_drone(&$fit, $typeid, $from, $quantity = 1) {
	if($from !== 'bay' && $from !== 'space') {
		// @codeCoverageIgnoreStart
		trigger_error('remove_drone(): unknown origin '.$from, E_USER_WARNING);
		return;
		// @codeCoverageIgnoreEnd
	}

	if(!isset($fit['drones'][$typeid]) || $fit['drones'][$typeid]['quantityin'.$from] < $quantity) {
		// @codeCoverageIgnoreStart
		trigger_error('remove_drone(): not enough drones to remove', E_USER_WARNING);
		$fit['drones'][$typeid]['quantityin'.$from] = 0;
		if($from === 'space' && \Osmium\Dogma\has_context($fit)) {
			dogma_remove_drone($fit['__dogma_context'], $typeid);
		}
		// @codeCoverageIgnoreEnd
	} else {
		$fit['drones'][$typeid]['quantityin'.$from] -= $quantity;
		if($from === 'space' && \Osmium\Dogma\has_context($fit)) {
			dogma_remove_drone_partial($fit['__dogma_context'], $typeid, $quantity);
		}
	}

	if($fit['drones'][$typeid]['quantityinbay'] == 0
		&& $fit['drones'][$typeid]['quantityinspace'] == 0) {
		unset($fit['drones'][$typeid]);
	}
}

/**
 * Toggle state of drones.
 *
 * @param $typeid typeid of the drone to transfer
 *
 * @param $from either "space" or "bay"
 *
 * @param $quantity the amount of drone to move from $from (to the
 * other state). If negative, the transfer occurs in the opposite
 * direction (drones are moved to $from).
 */
function transfer_drone(&$fit, $typeid, $from, $quantity = 1) {
	if($from !== 'bay' && $from !== 'space') {
		// @codeCoverageIgnoreStart
		trigger_error('transfer_drone(): unknown origin '.$from, E_USER_WARNING);
		return;
		// @codeCoverageIgnoreEnd
	}

	$to = ($from === 'bay') ? 'space' : 'bay';
	if($quantity < 0) {
		/* "Reverse" transfer: swap $from and $to */
		$quantity = -$quantity;

		$old_to = $to;
		$to = $from;
		$from = $old_to;
	}
	
	if(!isset($fit['drones'][$typeid]) || $fit['drones'][$typeid]['quantityin'.$from] < $quantity) {
		// @codeCoverageIgnoreStart
		trigger_error('transfer_drone(): not enough drones to move', E_USER_ERROR);
		return;
		// @codeCoverageIgnoreEnd
	}

	if(\Osmium\Dogma\has_context($fit)) {
		if($from === 'space') {
			dogma_remove_drone_partial($fit['__dogma_context'], $typeid, $quantity);
		} else {
			dogma_add_drone($fit['__dogma_context'], $typeid, $quantity);
		}
	}

	$fit['drones'][$typeid]['quantityin'.$from] -= $quantity;
	$fit['drones'][$typeid]['quantityin'.$to] += $quantity;
}



/* ----------------------------------------------------- */



/** Add an implant to a $fit. Boosters are considered to be implants,
 * and can be added using add_implant() as well. Will not add
 * duplicate implants. */
function add_implant(&$fit, $typeid) {
	if(isset($fit['implants'][$typeid])) return;

	$fit['implants'][$typeid] = array(
		'typeid' => (int)$typeid,
		'typename' => get_typename($typeid),
	);

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_add_implant($fit['__dogma_context'], (int)$typeid, $fit['implants'][$typeid]['dogma_index']);
	}

	if(get_groupid($typeid) == GROUP_Booster) {
		$fit['implants'][$typeid]['slot'] = 
			\Osmium\Dogma\get_implant_attribute($fit, $typeid, 'boosterness');
	} else {
		$fit['implants'][$typeid]['slot'] = 
			\Osmium\Dogma\get_implant_attribute($fit, $typeid, 'implantness');
	}
}

/** Remove an implant (or booster) from a $fit. */
function remove_implant(&$fit, $typeid) {
	if(!isset($fit['implants'][$typeid])) return;

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_remove_implant($fit['__dogma_context'], $fit['implants'][$typeid]['dogma_index']);
	}

	unset($fit['implants'][$typeid]);
}

/** Toggle a booster side effect.
 *
 * @param $toggled if true, enable the side effect. If false, disable
 * the side effect. If null, toggle the status (side effects are
 * disabled by default).
 */
function toggle_implant_side_effect(&$fit, $typeid, $effectid, $toggled = null) {
	if(!isset($fit['implants'][$typeid])) {
		trigger_error('Cannot toggle side effect of nonexisting implant', E_USER_WARNING);
		return false;
	}

	$i =& $fit['implants'][$typeid];
	$effectid = (int)$effectid;
	if(!isset($i['sideeffects'])) $i['sideeffects'] = array();

	$currentindex = array_search($effectid, $i['sideeffects'], true);
	$hasit = $currentindex !== false;

	$want = ($toggled === null) ? (!$hasit) : (bool)$toggled;

	if($want && !$hasit) {
		$i['sideeffects'][] = $effectid;
	} else if(!$want && $hasit) {
		unset($i['sideeffects'][$currentindex]);
	}

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_toggle_chance_based_effect(
			$fit['__dogma_context'],
			[ DOGMA_LOC_Implant, "implant_index" => $i['dogma_index'] ],
			$effectid,
			$want
		);
	}
}



/* ----------------------------------------------------- */



/** @internal */
function set_fleet_booster_generic(&$fit, $boosterfit, $type, array $between) {
	/* Remove the current booster */
	if(isset($fit['fleet'][$type])) {
		if(\Osmium\Dogma\has_context($fit)) {
			dogma_remove_fleet_member(
				$fit['__dogma_fleet_context'],
				$fit['fleet'][$type]['__dogma_context'],
				$found
			);
			assert($found === true);
		}
		unset($fit['fleet'][$type]);
	}

	if($boosterfit === null) {
		return;
	}

	/* Set the new booster */
	$fit['fleet'][$type] = $boosterfit;
	unset($fit['fleet'][$type]['remote']);
	unset($fit['fleet'][$type]['fleet']);
	\Osmium\Dogma\clear($fit['fleet'][$type]);

	$fit['fleet'][$type]['__id'] = get_gzclf_id($fit['fleet'][$type]);

	if(\Osmium\Dogma\has_context($fit)) {
		\Osmium\Dogma\auto_init($fit['fleet'][$type], 0);

		array_unshift($between, $fit['__dogma_fleet_context']);
		array_push($between, $fit['fleet'][$type]['__dogma_context']);

		call_user_func_array(
			'dogma_add_'.$type.'_commander',
			$between
		);
	}
}

/**
 * Set the current fleet booster for this fit. If there is already
 * one, it will be overwritten.
 *
 * @param $boosterfit the fleet booster to use. If $boosterfit is
 * null, the fleet booster will be removed.
 */
function set_fleet_booster(&$fit, $boosterfit) {
	return set_fleet_booster_generic($fit, $boosterfit, 'fleet', []);
}

/** @see set_fleet_booster() */
function set_wing_booster(&$fit, $boosterfit) {
	return set_fleet_booster_generic($fit, $boosterfit, 'wing', [ 0 ]);
}

/** @see set_fleet_booster() */
function set_squad_booster(&$fit, $boosterfit) {
	return set_fleet_booster_generic($fit, $boosterfit, 'squad', [ 0, 0 ]);
}



/* ----------------------------------------------------- */



/**
 * Add a fit to the list of remote fits. Remote fits can be used to
 * project effects on one another.
 *
 * @param $key the identifier for the remote fit. Cannot be the string
 * "local". If null, a new unique key will be generated and returned.
 */
function add_remote(&$fit, $key = null, $remote) {
	if($key === 'local') {
		trigger_error('Invalid key', E_USER_WARNING);
		return false;
	}

	if($key === null) {
		$key = 1;
		while(isset($fit['remote'][$key])) ++$key;
	}

	if(isset($fit['remote'][$key])) {
		remove_remote($fit, $key);
	}

	$fit['remote'][$key] = $remote;
	unset($fit['remote'][$key]['remote']);
	\Osmium\Dogma\clear($fit['remote'][$key]);

	$fit['remote'][$key]['__id'] = get_gzclf_id($fit['remote'][$key]);

	if(\Osmium\Dogma\has_context($fit)) {
		\Osmium\Dogma\auto_init(
			$fit['remote'][$key],
			\Osmium\Dogma\DOGMA_INIT_DEFAULT_OPTS & (~\Osmium\Dogma\DOGMA_INIT_REMOTE)
		);
	}

	return $key;
}

/**
 * Remove a fit from the list of remote fits. Use the same key you
 * used with add_remote().
 */
function remove_remote(&$fit, $key) {
	if($key === 'local' || $key === null) {
		trigger_error('Invalid key', E_USER_WARNING);
		return false;
	}

	if(!isset($fit['remote'][$key])) return;

	foreach($fit['modules'] as $type => &$sub) {
		foreach($sub as $index => &$m) {
			if(isset($m['target']) && $m['target'] == $key) {
				set_module_target_by_location($fit, 'local', $type, $index, null);
			}
		}
	}

	foreach($fit['remote'] as $skey => &$rfit) {
		foreach($rfit['modules'] as $type => &$sub) {
			foreach($sub as $index => &$m) {
				if(isset($m['target']) && $m['target'] == $key) {
					set_module_target_by_location($fit, $skey, $type, $index, null);
				}
			}
		}
	}

	unset($fit['remote'][$key]);
}

/** @internal */
function &get_remote(&$fit, $key) {
	if($key === 'local') return $fit;

	if(!isset($fit['remote'][$key])) {
		trigger_error('Fitting has no such remote', E_USER_WARNING);

		/* This isn't pretty. One downside of returning a
		 * reference. */
		static $false;
		$false = false;
		return $false;
	}

	return $fit['remote'][$key];
}

/**
 * Swap the local fit and a remote fit, making it the new local. The
 * old local will take the key of the old remote.
 */
function set_local(&$fit, $localkey) {
	if($localkey === 'local') {
		return;
	}

	if(!isset($fit['remote'][$localkey])) {
		trigger_error('Invalid key', E_USER_WARNING);
		return false;
	}

	if(!isset($fit['__id'])) {
		$fit['__id'] = get_gzclf_id($fit);
	}

	$remotes = $fit['remote'];
	$remotes['local'] = $fit;
	unset($fit['local']['remote']);

	/* Swap target keys */
	foreach($remotes as $rkey => &$rfit) {
		foreach($rfit['presets'] as &$mp) {
			foreach($mp['modules'] as &$sub) {
				foreach($sub as &$m) {
					if(!isset($m['target'])) continue;

					if($m['target'] === 'local') {
						$m['target'] = $localkey;
					} else if($m['target'] == $localkey) {
						$m['target'] = 'local';
					}
				}
			}
		}
	}

	$new = $remotes[$localkey];
	$remotes[$localkey] = $remotes['local'];
	unset($remotes['local']);
	$new['remote'] = $remotes;

	$fit = $new;
}

/**
 * Set the target of a module. Use $targetkey = null to remove a target.
 */
function set_module_target_by_location(&$fit, $sourcekey, $type, $index, $targetkey) {
	$src =& get_remote($fit, $sourcekey);
	if($src === false) return false;

	if(!isset($src['modules'][$type][$index])) {
		trigger_error('Module does not exist', E_USER_WARNING);
		return false;
	}

	$m =& $src['modules'][$type][$index];

	if(($hasctx = \Osmium\Dogma\has_context($fit)) && isset($m['target']) && $m['target'] !== null) {
		dogma_clear_target(
			$src['__dogma_context'],
			[ DOGMA_LOC_Module, 'module_index' => $m['dogma_index'] ]
		);
	}

	if($targetkey === null) {
		unset($m['target']);
		return;
	}

	$target =& get_remote($fit, $targetkey);
	if($target === false) return false;

	$m['target'] = $targetkey;

	if($hasctx) {
		dogma_target(
			$src['__dogma_context'],
			[ DOGMA_LOC_Module, 'module_index' => $m['dogma_index'] ],
			$target['__dogma_context']
		);
	}
}

/** @see set_module_target_by_location() */
function set_module_target_by_typeid(&$fit, $sourcekey, $index, $typeid, $targetkey) {
	return set_module_target_by_location(
		$fit,
		$sourcekey,
		get_module_slottype($fit, $typeid),
		$index,
		$targetkey
	);
}



/* ----------------------------------------------------- */



/** Set the damage profile of this $fit. */
function set_damage_profile(&$fit, $name, $em, $explosive, $kinetic, $thermal) {
	if((string)$name === '') {
		trigger_error('Must supply a non-empty name.', E_USER_WARNING);
		return false;
	}

	if($em < 0 || $explosive < 0 || $kinetic < 0 || $thermal < 0) {
		trigger_error('Nonsensical negative damage values supplied.', E_USER_WARNING);
		return false;
	}

	$sum = $em + $explosive + $kinetic + $thermal;

	if($sum <= 0) {
		trigger_error('Must have at least one nonzero damage type.', E_USER_WARNING);
		return false;
	}

	$fit['damageprofile'] = [
		'name' => $name,
		'damages' => [
			'em' => $em / $sum,
			'explosive' => $explosive / $sum,
			'kinetic' => $kinetic / $sum,
			'thermal' => $thermal / $sum,
		],
	];
}



/* ----------------------------------------------------- */



/**
 * Get all the fitted modules of the current preset.
 *
 * The returned array is guaranteed to have this structure:
 * array(<slot_type> => array(<index> => array(typeid, typename,
 * state))).
 */
function get_modules($fit) {
	return $fit['modules'];
}

/**
 * Get the state of a module located by its slot type and
 * index. Returns one of the STATE_* constants.
 */
function get_module_state_by_location($fit, $type, $index) {
	return $fit['modules'][$type][$index]['state'];
}

/**
 * Get the state of a module located by its typeid and index. Returns
 * one of the STATE_* constants.
 */
function get_module_state_by_typeid(&$fit, $index, $typeid) {
	return get_module_state_by_location($fit, get_module_slottype($fit, $typeid), $index);
}

/**
 * Get the type of slot a module occupies.
 *
 * __deprecated__
 */
function get_module_slottype(&$fit, $typeid) {
	dogma_type_has_effect($typeid, DOGMA_STATE_Offline, EFFECT_LoPower, $t);
	if($t) return 'low';

	dogma_type_has_effect($typeid, DOGMA_STATE_Offline, EFFECT_MedPower, $t);
	if($t) return 'medium';

	dogma_type_has_effect($typeid, DOGMA_STATE_Offline, EFFECT_HiPower, $t);
	if($t) return 'high';

	dogma_type_has_effect($typeid, DOGMA_STATE_Offline, EFFECT_RigSlot, $t);
	if($t) return 'rig';

	dogma_type_has_effect($typeid, DOGMA_STATE_Offline, EFFECT_SubSystem, $t);
	if($t) return 'subsystem';

	return false;
}



/* ----------------------------------------------------- */



/**
 * Try to auto-magically fix issues of a loadout.
 *
 * Potentially destructive operation! Use with caution.
 *
 * @param $errors an array of errors which were corrected
 * 
 * @param $interactive If true, some destructive operations will not
 * be performed, instead an error will be added.
 */
function sanitize(&$fit, &$errors = null, $interactive = false) {
	/* Unset any extra charges of nonexistent modules. */
	foreach($fit['presets'] as &$p) {
		foreach($p['chargepresets'] as &$cp) {
			foreach($cp['charges'] as $type => &$a) {
				foreach($a as $index => &$charge) {
					if(!isset($p['modules'][$type][$index])) {
						$errors[] = 'Removed charge '.$charge['typeid'].' from '.$type.' module '.$index;
						unset($a[$index]);
					}
				}
			}
		}
	}

	/* Enforce tag consistency */
	sanitize_tags($fit, $errors, $interactive);

	/* Enforce permissions consistency */
	if(!in_array($fit['metadata']['view_permission'], array(
		             VIEW_EVERYONE,
		             VIEW_PASSWORD_PROTECTED,
		             VIEW_ALLIANCE_ONLY,
		             VIEW_CORPORATION_ONLY,
		             VIEW_OWNER_ONLY,
		             VIEW_GOOD_STANDING,
		             VIEW_EXCELLENT_STANDING,
		             ))) {
		$errors[] = 'Incorrect view permission, reset to viewable by everyone.';
		$fit['metadata']['view_permission'] = VIEW_EVERYONE;
	}
	if(!in_array($fit['metadata']['edit_permission'], array(
		             EDIT_OWNER_ONLY,
		             EDIT_OWNER_AND_FITTING_MANAGER_ONLY,
		             EDIT_CORPORATION_ONLY,
		             EDIT_ALLIANCE_ONLY,
		             ))) {
		$errors[] = 'Incorrect edit permission, reset to editable by owner only.';
		$fit['metadata']['edit_permission'] = EDIT_OWNER_ONLY;
	}
	if(!in_array($fit['metadata']['visibility'], array(
		             VISIBILITY_PUBLIC,
		             VISIBILITY_PRIVATE,
		             ))) {
		$errors[] = 'Incorrect visibility, reset to public visibility.';
		$fit['metadata']['visibility'] = VISIBILITY_PUBLIC;
	}
	if($fit['metadata']['view_permission'] == VIEW_PASSWORD_PROTECTED) {
		$fit['metadata']['visibility'] = VISIBILITY_PRIVATE;

		if(!isset($fit['metadata']['password']) || !$fit['metadata']['password']) {
			$errors[] = 'Loadout is password-protected but does not have a password, view permission reset to viewable by everyone.';
			$fit['metadata']['view_permission'] = VIEW_EVERYONE;
		}
	}

	/* Sanitize title */
	$origtitle = $fit['metadata']['name'];
	$title =& $fit['metadata']['name'];
	$title = preg_replace('%\p{C}%u', '', $title); /* Remove control chars and other unused codes */
	$title = \Osmium\Chrome\trim($title);
	if($title !== $origtitle) {
		$errors[] = 'Removed blanks and control characters from title.';
	}
}



/* ----------------------------------------------------- */



/**
 * Generate a delta (diff) between two fittings.
 *
 * @returns a string, containing HTML code (safe to print as-is,
 * delta() already does some escaping), or null if a diff could not be
 * generated
 */
function delta($old, $new) {
	@include_once 'Horde/Autoloader/Default.php';
	if(!class_exists('Horde_Text_Diff')) return null;

	$oldt = \Osmium\Fit\export_to_markdown($old, false);
	$newt = \Osmium\Fit\export_to_markdown($new, false);

	$diff = new \Horde_Text_Diff('auto', array(explode("\n", $oldt), 
	                                           explode("\n", $newt)));
	$renderer = new \Horde_Text_Diff_Renderer_Inline();
	return $renderer->render($diff);
}



/* ----------------------------------------------------- */



/**
 * Use a custom skillset for this fitting (default is all skills to
 * V).
 *
 * @param $skillset array(skilltypeid => skilllevel)
 * @param $defaultlevel level to use for skills not in $skillset
 */
function use_skillset(&$fit, array $skillset = array(), $defaultlevel = 5, $name = null) {
	$fit['skillset'] = [ 
		'name' =>$name,
		'default' => $defaultlevel,
		'override' => $skillset
	];

	if(\Osmium\Dogma\has_context($fit)) {
		dogma_reset_skill_levels($fit['__dogma_context']);
		dogma_set_default_skill_level($fit['__dogma_context'], (int)$defaultlevel);

		foreach($skillset as $typeid => $level) {
			dogma_set_skill_level($fit['__dogma_context'], (int)$typeid, (int)$level);
		}
	}
}



/* ----------------------------------------------------- */



/**
 * Get the URI of this loadout (relative to the main page).
 *
 * @warning The rest of the code assumes this function returns a "base
 * path", that is, no GET parameters (so it is possible to append
 * "?foo=bar" after the returned value of get_fit_uri() in all cases).
 */
function get_fit_uri($loadoutid, $visibility, $privatetoken, $revision = null) {
	if($revision !== null) {
		$loadoutid = $loadoutid.'R'.$revision;
	}

	if($visibility == VISIBILITY_PRIVATE) {
		return 'loadout/private/'.$loadoutid.'/'.$privatetoken;
	}

	return 'loadout/'.$loadoutid;
}

/**
 * Get the path needed to go back the root from this loadout's URI.
 */
function get_fit_relative($loadoutid, $visibility) {
	if($visibility == VISIBILITY_PRIVATE) {
		/* Loadout URI looks like /loadout/private/id/tok */
		return '../../..';
	} else {
		/* Loadout URI looks like /loadout/id */
		return '..';
	}
}

/**
 * Given a fit (for skillset) and an array mapping skills to levels, returns an
 * array mapping skills to levels containing the pairs that the skillset is
 * missing.
 */
function get_missing_prereqs($fit, $skills) {
	$skillset = $fit['skillset'];
	$missing = array();
	foreach ($skills as $skill => $level) {
		if (isset($skillset['override'][$skill])) {
			if($skillset['override'][$skill] < $level) {
				$missing[$skill] = $level;
			}
		} else {
			if ($skillset['default'] < $level) {
				$missing[$skill] = $level;
			}
		}
	}
	return $missing;
}

function get_missing_prereqs_for_fit($fit) {
	$skills = get_skill_prereqs_for_fit($fit);
	$missing_by_typeid = array();
	foreach ($skills as $typeid => $required) {
		$missing = get_missing_prereqs($fit, $required);
		if ($missing) {
			$missing_by_typeid[$typeid] = $missing;
		}
	}
	return $missing_by_typeid;
};
