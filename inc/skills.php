<?php

// XXX NOPUSH http://osmium/loadout/7 doesn't show Drones V missing wtf???

namespace Osmium\Skills;

function get_required_skills($typeid) {
	$typeid = (int)$typeid;
	$key = 'NameCache_required_skills_'.$typeid;
	$cache = \Osmium\State\get_cache_memory($key);

	if($cache !== null) {
		return $cache;
	}

	static $rs = [
		182 => 277, /* RequiredSkill1 => RequiredSkill1Level */
		183 => 278, /* etc… */
		184 => 279,
		1285 => 1286,
		1289 => 1287,
		1290 => 1288,
	];

	$vals = [];

	static $ctx = null;
	if($ctx === null) dogma_init_context($ctx);

	/* XXX: this is hackish */
	dogma_init_context($ctx);
	dogma_set_ship($ctx, $typeid);
	foreach($rs as $rsattid => $rslattid) {
		if(dogma_get_ship_attribute($ctx, $rsattid, $skill) === DOGMA_OK
		   && dogma_get_ship_attribute($ctx, $rslattid, $level) === DOGMA_OK) {
			if($skill > 0 && $level > 0) {
				$vals[$skill] = $level;
			}
		}
	}

	\Osmium\State\put_cache_memory($key, $vals, 86400);
	return $vals;
}

function get_skill_rank($typeid) {
	$typeid = (int)$typeid;
	$key = 'NameCache_skill_rank_'.$typeid;
	$cache = \Osmium\State\get_cache_memory($key);

	if($cache !== null) {
		return $cache;
	}

	static $ctx = null;
	if($ctx === null) dogma_init_context($ctx);

	/* XXX */
	dogma_set_ship($ctx, $typeid);

	dogma_get_ship_attribute($ctx, \Osmium\Fit\ATT_SkillTimeConstant, $rank);

	\Osmium\State\put_cache_memory($key, $rank, 86400);
	return $rank;
}

/**
 * Takes in an array of item/module type IDs; fills the $result array with entries like:
 *     input_type_id => array(
 *         skill_type_id => required_level,
 *         ...
 *     )
 */
function get_skill_prerequisites_for_types(array $types, array &$result) {
	foreach ($types as $typeid) {
		if(!isset($result[$typeid])) {
			$result[$typeid] = [];
		}

		foreach(get_required_skills($typeid) as $stid => $slevel) {
			if(!isset($result[$stid])) {
				get_skill_prerequisites_for_types([ $stid ], $result);
			}

			$result[$typeid][$stid] = $slevel;
		}
	}
}

function uniquify_prerequisites(array $prereqs) {
	$prereqs_unique = array();
	error_log(print_r($prereqs, true));
	foreach($prereqs as $tid => $arr) {
		foreach($arr as $stid => $level) {
			if(isset($prereqs_unique[$stid])) {
				$prereqs_unique[$stid] = max($prereqs_unique[$stid], $level);
			} else {
				$prereqs_unique[$stid] = $level;
			}
		}
	}
	return $prereqs_unique;
}

function get_missing_prerequisites(array $prereqs, array $skillset) {
	$missing = array();
	foreach($prereqs as $tid => $contents) {
		foreach($prereqs[$tid] as $stid => $level) {
			$current = isset($skillset['override'][$stid])
				? $skillset['override'][$stid] : $skillset['default'];

			if($current < $level) {
				if (!isset($missing[$tid])) {
					$missing[$tid] = array();
				}
				$missing[$tid][$stid] = $level;
				break;
			}
		}
	}
	return $missing;
}

function get_missing_prerequisites_unique(array $prereqs_unique, array $skillset) {
	$missing_unique = array();
	foreach($prereqs_unique as $stid => $level) {
		$current = isset($skillset['override'][$stid])
			? $skillset['override'][$stid] : $skillset['default'];

		if($current < $level) {
			$missing_unique[$stid] = $level;
		}
	}
	return $missing_unique;
}

function sp($level, $rank) {
	if($level == 0) return 0;
	return ceil(pow(2, 2.5 * ($level - 1.0)) * 250.0 * $rank);
}

/** returns array($total_sp, $missing_sp) */
function sum_sp(array $prereqs_unique, array $skillset) {
	$total_sp = 0;
	$missing_sp = 0;
	foreach($prereqs_unique as $stid => $level) {
		$current = isset($skillset['override'][$stid])
			? $skillset['override'][$stid] : $skillset['default'];

		$rank = get_skill_rank($stid);
		$needed = sp($level, $rank);

		$total_sp += $needed;

		if($current >= $level) {
			continue;
		}

		$missing_sp += $needed - sp($current, $rank);
	}
	return array($total_sp, $missing_sp);
}
