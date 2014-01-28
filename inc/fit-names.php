<?php

namespace Osmium\Fit;

const CATEGORY_Skill = 16;

const EFFECT_ArmorRepair = 27;
const EFFECT_EMPWave = 38;
const EFFECT_EnergyDestabilizationNew = 2303;
const EFFECT_EnergyTransfer = 31;
const EFFECT_FighterMissile = 4729;
const EFFECT_FueledArmorRepair = 5275;
const EFFECT_FueledShieldBoosting = 4936;
const EFFECT_HiPower = 12;
const EFFECT_Leech = 3250;
const EFFECT_LoPower = 11;
const EFFECT_MedPower = 13;
const EFFECT_MiningLaser = 67;
const EFFECT_ProjectileFired = 34;
const EFFECT_RemoteHullRepair = 3041;
const EFFECT_RigSlot = 2663;
const EFFECT_ShieldBoosting = 4;
const EFFECT_ShieldTransfer = 18;
const EFFECT_StructureRepair = 26;
const EFFECT_SubSystem = 3772;
const EFFECT_TargetArmorRepair = 592;
const EFFECT_TargetAttack = 10;
const EFFECT_UseMissiles = 101;

const ATT_Boosterness = 1087;
const ATT_HiSlots = 14;
const ATT_Implantness = 331;
const ATT_LauncherSlotsLeft = 101;
const ATT_LowSlots = 12;
const ATT_MedSlots = 13;
const ATT_ReloadTime = 1795;
const ATT_ScanResolution = 564;
const ATT_SignatureRadius = 552;
const ATT_SkillTimeConstant = 275;
const ATT_TurretSlotsLeft = 102;
const ATT_UpgradeLoad = 1152;

const TYPE_1MNMicrowarpdriveII = 440;
const TYPE_10MNMicrowarpdriveII = 12076;
const TYPE_100MNMicrowarpdriveII = 12084;

const GROUP_Booster = 303;
const GROUP_FighterBomber = 1023;
const GROUP_FighterDrone = 549;



/** @internal */
function get_cached_thing_generic($table, $field, $wherename, $whereval) {
	$key = 'NameCache_'.$table.'_'.$field.'_'.$wherename.'_'.$whereval;
	$cache = \Osmium\State\get_cache_memory($key);

	if($cache !== null) {
		return $cache;
	}

	$r = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		"SELECT $field FROM $table WHERE $wherename = $1",
		array($whereval)
	));

	$val = ($r === false) ? false : $r[0];
	\Osmium\State\put_cache_memory($key, $val, 86400);
	return $val;
}

function get_attributedisplayname($attributeid) {
	return get_cached_thing_generic(
		'eve.dgmattribs',
		/* Use display name if available, if not patch up the attribute name */
		'CASE displayname WHEN \'\' THEN regexp_replace(upper(left(attributename, 1)) || right(attributename, -1), \'([a-z])([A-Z0-9])\', \'\1 \2\', \'g\') ELSE displayname END',
		'attributeid',
		(int)$attributeid
	);
}

function get_attributename($attributeid) {
	return get_cached_thing_generic(
		'eve.dgmattribs', 'attributename', 'attributeid', (int)$attributeid
	);
}

function get_attributeid($attributename) {
	return get_cached_thing_generic(
		'eve.dgmattribs', 'attributeid', 'attributename', $attributename
	);
}

function get_unitid($attributeid) {
	return get_cached_thing_generic(
		'eve.dgmattribs', 'unitid', 'attributeid', (int)$attributeid
	);
}

function get_unitdisplayname($unitid) {
	return get_cached_thing_generic(
		'eve.dgmunits', 'displayname', 'unitid', (int)$unitid
	);
}

function get_typename($typeid) {
	return get_cached_thing_generic(
		'eve.invtypes', 'typename', 'typeid', (int)$typeid
	);
}

function get_typeid($typename) {
	return get_cached_thing_generic(
		'eve.invtypes', 'typeid', 'typename', $name
	);
}

function get_groupid($typeid) {
	return get_cached_thing_generic(
		'eve.invtypes', 'groupid', 'typeid', (int)$typeid
	);
}

function get_volume($typeid) {
	return get_cached_thing_generic(
		'eve.invtypes', 'volume', 'typeid', (int)$typeid
	);
}

function get_average_market_price($typeid) {
	return get_cached_thing_generic(
		'eve.averagemarketprices', 'averageprice', 'typeid', (int)$typeid
	);
}

function get_parent_typeid($typeid) {
	$parent = get_cached_thing_generic(
		'eve.invmetatypes', 'parenttypeid', 'typeid', (int)$typeid
	);

	return $parent ?: $typeid;
}

function get_categoryid($typeid) {
	return get_cached_thing_generic(
		'eve.invtypes JOIN eve.invgroups ON invgroups.groupid = invtypes.groupid',
		'categoryid',
		'typeid',
		(int)$typeid
	);
}

function get_groupname($groupid) {
	return get_cached_thing_generic(
		'eve.invgroups', 'groupname', 'groupid', (int)$groupid
	);
}

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

function get_implant_slot($typeid) {
	$typeid = (int)$typeid;
	$key = 'NameCache_implantness_'.$typeid;
	$cache = \Osmium\State\get_cache_memory($key);

	if($cache !== null) {
		return $cache;
	}

	static $ctx = null;
	if($ctx === null) dogma_init_context($ctx);

	/* XXX */
	dogma_set_ship($ctx, $typeid);

	if(get_groupid($typeid) == GROUP_Booster) {
		dogma_get_ship_attribute($ctx, ATT_Boosterness, $slot);
	} else {
		dogma_get_ship_attribute($ctx, ATT_Implantness, $slot);
	}

	\Osmium\State\put_cache_memory($key, $slot, 86400);
	return $slot;
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

	dogma_get_ship_attribute($ctx, ATT_SkillTimeConstant, $rank);

	\Osmium\State\put_cache_memory($key, $rank, 86400);
	return $rank;
}
