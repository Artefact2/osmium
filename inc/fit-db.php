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

/** @internal */
function unique_key($stuff) {
	return sha1(json_encode($stuff));
}

/** @internal */
function damage_profile_is_default($dp) {
	return $dp['name'] === 'Uniform'
		&& (double)$dp['damages']['em'] === (double)$dp['damages']['explosive']
		&& (double)$dp['damages']['explosive'] === (double)$dp['damages']['kinetic']
		&& (double)$dp['damages']['kinetic'] === (double)$dp['damages']['thermal'];
}

/**
 * Get an array that contains all the significant data of a fit. Used
 * to compare fits.
 */
function get_unique(&$fit) {
	sanitize($fit);

	$unique = array();

	if(isset($fit['ship']['typeid'])) {
		$unique['ship'] = (int)$fit['ship']['typeid'];
	}

	$unique['metadata'] = array(
		'name' => $fit['metadata']['name'],
		'description' => $fit['metadata']['description'],
		'evebuildnumber' => (int)$fit['metadata']['evebuildnumber'],
		'tags' => $fit['metadata']['tags'],
	);

	foreach($fit['presets'] as $presetid => $preset) {
		$uniquep = array(
			'name' => $preset['name'],
			'description' => $preset['description']
		);

		$newindexes = array();
		foreach($preset['modules'] as $type => $d) {
			$z = 0;
			foreach($d as $index => $module) {
				/* Use the actual order of the array, discard indexes */
				$newindexes[$type][$index] = ($z++);
				$newmodule = array((int)$module['typeid'], (int)$module['state']);

				/* Target info is stored separately on the "local" fit
				 * only */

				$uniquep['modules'][$type][unique_key($newmodule)] = $newmodule;
			}
		}

		foreach($preset['chargepresets'] as $chargepreset) {
			$uniquecp = array(
				'name' => $chargepreset['name'],
				'description' => $chargepreset['description']
			);

			foreach($chargepreset['charges'] as $type => $a) {
				foreach($a as $index => $charge) {
					$newindex = $newindexes[$type][$index];
					$uniquecp['charges'][$type][$newindex] = (int)$charge['typeid'];
				}
			}

			$uniquep['chargepresets'][unique_key($uniquecp)] = $uniquecp;
		}

		foreach($preset['implants'] as $i) {
			$uniquep['implants'][(int)$i['typeid']] = 1;
		}

		$unique['presets'][unique_key($uniquep)] = $uniquep;
	}

	foreach($fit['dronepresets'] as $dronepreset) {
		$uniquedp = array(
			'name' => $dronepreset['name'],
			'description' => $dronepreset['description']
			);

		foreach($dronepreset['drones'] as $drone) {
			$newdrone = array((int)$drone['typeid'],
			                  (int)$drone['quantityinbay'],
			                  (int)$drone['quantityinspace']);
			$uniquedp['drones'][unique_key($newdrone)] = $newdrone;
		}

		$unique['dronepresets'][unique_key($uniquedp)] = $uniquedp;
	}

	if(isset($fit['fleet'])) {
		foreach($fit['fleet'] as $k => $f) {
			if($k !== 'fleet' && $k !== 'wing' && $k !== 'squad') continue;
			$unique['fleet'][$k] = get_hash($f);
		}
	}

	if(isset($fit['remote'])) {
		foreach($fit['remote'] as $k => $rf) {
			if($k === 'local' || $k === null) continue;
			$unique['remote'][$k] = get_hash($rf);
		}

		$remotes = $fit['remote'];
		$remotes['local'] = $fit;

		foreach($remotes as $key => $rfit) {
			foreach($rfit['presets'] as $pid => $preset) {
				foreach($preset['modules'] as $type => $sub) {
					foreach($sub as $index => $m) {
						if(!isset($m['target']) || $m['target'] === null) continue;
						$unique['targets']['modules'][$pid][$type][$index] = (string)$m['target'];
					}
				}
			}
		}
	}

	if(!damage_profile_is_default($fit['damageprofile'])) {
		$unique['damageprofile'] = [
			'name' => $fit['damageprofile']['name'],
			'damages' => [
				'em' => $fit['damageprofile']['damages']['em'],
				'explosive' => $fit['damageprofile']['damages']['explosive'],
				'kinetic' => $fit['damageprofile']['damages']['kinetic'],
				'thermal' => $fit['damageprofile']['damages']['thermal'],
			],
		];
	}

	/* Ensure equality if key ordering is different */
	ksort_rec($unique);
	sort($unique['metadata']['tags']); /* tags should be ordered by value */

	return $unique;
}

function ksort_rec(array &$array) {
	ksort($array);
	foreach($array as &$v) {
		if(is_array($v)) ksort_rec($v);
	}
}

/**
 * Get the hash ("fittinghash" in the database) of a fit. If two fits
 * have the same hash, they are essentially the same, not counting for
 * example order of tags, order of module indexes etc.
 */
function get_hash($fit) {
	return unique_key(get_unique($fit));
}

/** @internal */
function commit_damage_profile($dp, &$error = null) {
	if(damage_profile_is_default($dp)) {
		/* Not inserting the default damage profile */
		return null;
	}

	$dpq = \Osmium\Db\fetch_row(
		\Osmium\Db\query_params(
			'SELECT damageprofileid FROM osmium.damageprofiles
			WHERE name = $1 AND electromagnetic = $2 AND explosive = $3
			AND kinetic = $4 AND thermal = $5',
			array(
				$dp['name'],
				$dp['damages']['em'],
				$dp['damages']['explosive'],
				$dp['damages']['kinetic'],
				$dp['damages']['thermal'],
			)
		)
	);

	if($dpq !== false) return (int)$dpq[0];

	$q = \Osmium\Db\query_params(
		'INSERT INTO osmium.damageprofiles (
		name, electromagnetic, explosive, kinetic, thermal
		) VALUES ($1, $2, $3, $4, $5)
		RETURNING damageprofileid',
		array(
			$dp['name'],
			$dp['damages']['em'],
			$dp['damages']['explosive'],
			$dp['damages']['kinetic'],
			$dp['damages']['thermal'],
		)
	);

	if($q === false) {
		return false;
	}

	return (int)\Osmium\Db\fetch_row($q)[0];
}

/**
 * Insert a fitting in the database, if necessary. The fitting hash
 * will be updated. The function will try to avoid inserting
 * duplicates, so it is safe to call it multiple times even if no
 * changes were made.
 *
 * You must wrap this call in a transaction and rollback if it
 * fails.
 *
 * @returns false on failure, fittinghash on success.
 */
function commit_fitting(&$fit, &$error = null) {
	$fittinghash = get_hash($fit);
	$fit['metadata']['hash'] = $fittinghash;
  
	list($count) = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT COUNT(fittinghash) FROM osmium.fittings WHERE fittinghash = $1', array($fittinghash)));
	if($count == 1) {
		/* Do nothing! */
		return $fittinghash;
	}

	$damageprofileid = commit_damage_profile($fit['damageprofile'], $error);
	if($damageprofileid === false) {
		return false;
	}

	/* Insert the new fitting */
	$ret = \Osmium\Db\query_params(
		'INSERT INTO osmium.fittings (
		fittinghash, name, description, evebuildnumber, hullid, creationdate,
		damageprofileid
		) VALUES ($1, $2, $3, $4, $5, $6, $7)',
		array(
			$fittinghash,
			$fit['metadata']['name'],
			$fit['metadata']['description'],
			$fit['metadata']['evebuildnumber'],
			isset($fit['ship']['typeid']) ? $fit['ship']['typeid'] : null,
			time(),
			$damageprofileid,
		)
	);
	if($ret === false) {
		return false;
	}

	foreach($fit['metadata']['tags'] as $tag) {
		$ret = \Osmium\Db\query_params(
			'INSERT INTO osmium.fittingtags (fittinghash, tagname) VALUES ($1, $2)',
			array($fittinghash, $tag)
		);
		if($ret === false) {
			return false;
		}
	}
  
	$presetid = 0;
	foreach($fit['presets'] as $preset) {
		$ret = \Osmium\Db\query_params(
			'INSERT INTO osmium.fittingpresets (fittinghash, presetid, name, description) VALUES ($1, $2, $3, $4)',
			array(
				$fittinghash,
				$presetid,
				$preset['name'],
				$preset['description']
			)
		);

		if($ret === false) {
			return false;
		}

		$normalizedindexes = array();
		foreach($preset['modules'] as $type => $data) {
			$z = 0;
			foreach($data as $index => $module) {
				$normalizedindexes[$type][$index] = $z;

				$ret = \Osmium\Db\query_params(
					'INSERT INTO osmium.fittingmodules (fittinghash, presetid, slottype, index, typeid, state) VALUES ($1, $2, $3, $4, $5, $6)',
					array(
						$fittinghash,
						$presetid,
						$type,
						$z,
						$module['typeid'],
						$module['state'],
					)
				);

				if($ret === false) {
					return false;
				}

				++$z;
			}
		}
  
		$cpid = 0;
		foreach($preset['chargepresets'] as $chargepreset) {
			$ret = \Osmium\Db\query_params(
				'INSERT INTO osmium.fittingchargepresets (fittinghash, presetid, chargepresetid, name, description) VALUES ($1, $2, $3, $4, $5)',
				array(
					$fittinghash,
					$presetid,
					$cpid,
					$chargepreset['name'],
					$chargepreset['description']
				)
			);

			if($ret === false) {
				return false;
			}

			foreach($chargepreset['charges'] as $type => $d) {
				foreach($d as $index => $charge) {
					if(!isset($normalizedindexes[$type][$index])) continue;
					$z = $normalizedindexes[$type][$index];

					$ret = \Osmium\Db\query_params(
						'INSERT INTO osmium.fittingcharges (fittinghash, presetid, chargepresetid, slottype, index, typeid) VALUES ($1, $2, $3, $4, $5, $6)',
						array(
							$fittinghash,
							$presetid,
							$cpid,
							$type,
							$z,
							$charge['typeid']
						)
					);

					if($ret === false) {
						return false;
					}
				}
			}

			++$cpid;
		}

		foreach($preset['implants'] as $typeid => $i) {
			$ret = \Osmium\Db\query_params(
				'INSERT INTO osmium.fittingimplants (fittinghash, presetid, typeid) VALUES ($1, $2, $3)',
				array(
					$fittinghash,
					$presetid,
					$i['typeid'],
				)
			);

			if($ret === false) {
				return false;
			}
		}

		++$presetid;
	}
  
	$dpid = 0;
	foreach($fit['dronepresets'] as $dronepreset) {
		$ret = \Osmium\Db\query_params(
			'INSERT INTO osmium.fittingdronepresets (fittinghash, dronepresetid, name, description) VALUES ($1, $2, $3, $4)',
			array(
				$fittinghash,
				$dpid,
				$dronepreset['name'],
				$dronepreset['description']
			)
		);

		if($ret === false) {
			return false;
		}

		foreach($dronepreset['drones'] as $drone) {
			$ret = \Osmium\Db\query_params(
				'INSERT INTO osmium.fittingdrones (fittinghash, dronepresetid, typeid, quantityinbay, quantityinspace) VALUES ($1, $2, $3, $4, $5)',
				array(
					$fittinghash,
					$dpid,
					$drone['typeid'],
					$drone['quantityinbay'],
					$drone['quantityinspace']
				)
			);

			if($ret === false) {
				return false;
			}
		}

		++$dpid;
	}

	if(isset($fit['fleet'])) {
		$hashes = array();
		$boostcount = 0;

		foreach(array('fleet', 'wing', 'squad') as $t) {
			if(!isset($fit['fleet'][$t])) {
				$hashes[$t] = [ 'f', null ];
				continue;
			}

			++$boostcount;

			if(!isset($fit['fleet'][$t]['ship']['typeid']) || !$fit['fleet'][$t]['ship']['typeid']) {
				$hashes[$t] = [ 't', null ];
				continue;
			}

			$ret = commit_fitting($fit['fleet'][$t]);
			if($ret === false) {
				return false;
			}

			$hashes[$t] = [ 't', $ret ];
		}

		if($boostcount > 0) {
			$ret = \Osmium\Db\query_params(
				'INSERT INTO osmium.fittingfleetboosters (
				fittinghash, hasfleetbooster, fleetboosterfittinghash,
				haswingbooster, wingboosterfittinghash,
				hassquadbooster, squadboosterfittinghash
				) VALUES ($1, $2, $3, $4, $5, $6, $7)',
				array(
					$fittinghash,
					$hashes['fleet'][0],
					$hashes['fleet'][1],
					$hashes['wing'][0],
					$hashes['wing'][1],
					$hashes['squad'][0],
					$hashes['squad'][1],
				)
			);

			if($ret === false) {
				return false;
			}
		}
	}

	if(isset($fit['remote'])) {
		$nremotes = 0;
		$hashes = [ 'local' => $fittinghash ];

		foreach($fit['remote'] as $k => $rf) {
			if($k === 'local' || $k === null) continue;

			$remotehash = commit_fitting($fit['remote'][$k]);
			if($remotehash === false) {
				return false;
			}

			$ret = \Osmium\Db\query_params(
				'INSERT INTO osmium.fittingremotes (
				fittinghash, key, remotefittinghash
				) VALUES ($1, $2, $3)',
				array(
					$fittinghash,
					$k,
					$remotehash,
				)
			);

			if($ret === false) {
				return false;
			}

			$hashes[$k] = $remotehash;
			++$nremotes;
		}

		if($nremotes !== 0) {
			$ret = \Osmium\Db\query_params(
				'INSERT INTO osmium.fittingremotes (
				fittinghash, key, remotefittinghash
				) VALUES ($1, $2, $3)',
				array(
					$fittinghash,
					'local',
					$fittinghash,
				)
			);

			if($ret === false) {
				return false;
			}

			$remotes = $fit['remote'];
			$remotes['local'] = $fit;

			foreach($remotes as $k => $rf) {
				$presetid = 0;
				foreach($rf['presets'] as $p) {
					foreach($p['modules'] as $type => $sub) {
						$z = 0;
						foreach($sub as $m) {
							if(!isset($m['target']) || $m['target'] === null) {
								++$z;
								continue;
							}

							$ret = \Osmium\Db\query_params(
								'INSERT INTO osmium.fittingmoduletargets (
							    fittinghash, source, sourcefittinghash,
								presetid, slottype, index, target
								) VALUES ($1, $2, $3, $4, $5, $6, $7)',
								array(
									$fittinghash,
									$k,
									$hashes[$k],
									$presetid,
									$type,
									$z,
									$m['target'],
								)
							);

							if($ret === false) {
								return false;
							}

							++$z;
						}
					}

					++$presetid;
				}
			}
		}
	}

	return $fittinghash;
}

/**
 * Insert a loadout in the database.
 *
 * This function will create a new loadout if the fit does not have a
 * loadoutid, or this will insert a new revision of the loadout if it
 * already exists.
 *
 * @param $ownerid The accountid of the owner of the loadout
 * 
 * @param $accountid The accountid of the person updating the loadout
 *
 * @returns false on failure
 */
function commit_loadout(&$fit, $ownerid, $accountid, &$error = null) {
	if(\Osmium\Reputation\is_fit_public($fit)
	   && !\Osmium\Reputation\has_privilege(
		   \Osmium\Reputation\PRIVILEGE_CREATE_LOADOUT, $accountid)
	) {
		$error = 'You lack the privilege to commit public loadouts.';
		return false;
	}

	\Osmium\Db\query('BEGIN;');

	$ret = commit_fitting($fit, $error);

	if($ret === false) {
		if($error === null) {
			$error = \Osmium\Db\last_error();
		}
		\Osmium\Db\query('ROLLBACK;');
		return false;
	}

	$loadoutid = null;
	$password = ($fit['metadata']['view_permission'] == VIEW_PASSWORD_PROTECTED) ?
		$fit['metadata']['password'] : '';

	if(!isset($fit['metadata']['loadoutid'])) {
		/* Insert a new loadout */
		$ret = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
			'INSERT INTO osmium.loadouts (accountid, viewpermission, editpermission, visibility, passwordhash) VALUES ($1, $2, $3, $4, $5) RETURNING loadoutid, privatetoken',
			array(
				$ownerid,
				$fit['metadata']['view_permission'],
				$fit['metadata']['edit_permission'],
				$fit['metadata']['visibility'],
				$password
			)
		));

		if($ret === false) {
			$error = \Osmium\Db\last_error();
			\Osmium\Db\query('ROLLBACK;');
			return false;
		}

		list($loadoutid, $privatetoken) = $ret;

		$fit['metadata']['loadoutid'] = $loadoutid;
		$fit['metadata']['privatetoken'] = $privatetoken;
	} else {
		/* Update a loadout */
		$loadoutid = $fit['metadata']['loadoutid'];

		$ret = \Osmium\Db\query_params(
			'UPDATE osmium.loadouts SET accountid = $1, viewpermission = $2, editpermission = $3, visibility = $4, passwordhash = $5 WHERE loadoutid = $6',
			array(
				$ownerid,
				$fit['metadata']['view_permission'],
				$fit['metadata']['edit_permission'],
				$fit['metadata']['visibility'],
				$password,
				$loadoutid
			)
		);

		if($ret === false) {
			$error = \Osmium\Db\last_error();
			\Osmium\Db\query('ROLLBACK;');
			return false;
		}

		if(!\Osmium\Reputation\is_fit_public($fit)) {
			/* Remove reputation changes */
			$cq = \Osmium\Db\query_params(
				'SELECT commentid FROM loadoutcomments lc WHERE lc.loadoutid = $1',
				array($loadoutid)
			);
			while($row = \Osmium\Db\fetch_row($cq)) {
				\Osmium\Reputation\nullify_votes(
					'targettype = $1 AND targetid1 = $2 AND targetid2 = $3 AND targetid3 IS NULL',
					array(
						\Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
						$row[0], $loadoutid,
					)
				);
			}
			\Osmium\Reputation\nullify_votes(
				'targettype = $1 AND targetid1 = $2',
				array(
					\Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT,
					$loadoutid
				)
			);
		}
	}

	/* If necessary, insert the appropriate history entry */
	$ret = \Osmium\Db\query_params('SELECT fittinghash, loadoutslatestrevision.latestrevision 
	  FROM osmium.loadoutslatestrevision 
	  JOIN osmium.loadouthistory ON (loadoutslatestrevision.loadoutid = loadouthistory.loadoutid 
	                             AND loadoutslatestrevision.latestrevision = loadouthistory.revision) 
	  WHERE loadoutslatestrevision.loadoutid = $1', array($loadoutid));

	if($ret === false) {
		$error = \Osmium\Db\last_error();
		\Osmium\Db\query('ROLLBACK;');
		return false;
	}

	$row = \Osmium\Db\fetch_row($ret);

	if($row === false || $row[0] != $fit['metadata']['hash']) {
		$nextrev = ($row === false) ? 1 : ($row[1] + 1);
		$ret = \Osmium\Db\query_params(
			'INSERT INTO osmium.loadouthistory (loadoutid, revision, fittinghash, updatedbyaccountid, updatedate) VALUES ($1, $2, $3, $4, $5)',
			array(
				$loadoutid,
				$nextrev,
				$fit['metadata']['hash'],
				$accountid,
				time()
			)
		);

		if($ret === false) {
			$error = \Osmium\Db\last_error();
			\Osmium\Db\query('ROLLBACK;');
			return false;
		}

		$fit['metadata']['revision'] = $nextrev;
	} else {
		$fit['metadata']['revision'] = $row[1];
	}

	$fit['metadata']['accountid'] = $ownerid;

	commit_loadout_dogma_attribs($fit);

	$ret = \Osmium\Db\query('COMMIT;');
	if($ret === false) {
		$error = \Osmium\Db\last_error();
		return false;
	}

	/* Assume commit_loadout() was successful, do the post-commit
	 * stuff */

	if($fit['metadata']['visibility'] == VISIBILITY_PRIVATE) {
		\Osmium\Search\unindex($loadoutid);
	} else {
		\Osmium\Search\index(
			\Osmium\Db\fetch_assoc(
				\Osmium\Search\query_select_searchdata(
					'WHERE loadoutid = $1',
					array($loadoutid)
				)
			)
		);
	}

	$revision = $fit['metadata']['revision'];

	$sem = \Osmium\State\semaphore_acquire('Get_Fit_'.$loadoutid.'_'.$revision);
	if($sem !== false) {
		\Osmium\State\invalidate_cache('loadout-'.$loadoutid.'-'.$revision, 'Loadout_Cache_');
		\Osmium\State\invalidate_cache('loadout-'.$loadoutid, 'Loadout_Cache_');
		\Osmium\State\semaphore_release($sem);
	}

	\Osmium\State\invalidate_cache_memory('main_popular_tags');
	insert_fitting_delta_against_previous_revision(\Osmium\Fit\get_fit($loadoutid));

	$type = ($revision == 1) ? \Osmium\Log\LOG_TYPE_CREATE_LOADOUT : \Osmium\Log\LOG_TYPE_UPDATE_LOADOUT;
	\Osmium\Log\add_log_entry($type, null, $loadoutid, $revision);

	if($revision > 1 && $ownerid != $accountid) {
		\Osmium\Notification\add_notification(
			\Osmium\Notification\NOTIFICATION_TYPE_LOADOUT_EDITED,
			$accountid, $ownerid, $loadoutid, $revision
		);
	}

	if($revision > 1) {
		\Osmium\Db\query_params(
			'UPDATE osmium.votes SET cancellableuntil = NULL
			WHERE targettype = $1 AND targetid1 = $2 AND targetid2 IS NULL AND targetid3 IS NULL',
			array(\Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT, $loadoutid)
		);
	}

	return $ret;
}

/**
 * Update the database cache of dogma attributes for a specific
 * fit. Should be wrapped in a transaction.
 */
function commit_loadout_dogma_attribs(&$fit) {
	if(!isset($fit['metadata']['loadoutid'])) return;

	\Osmium\Db\query_params(
		'DELETE FROM osmium.loadoutdogmaattribs WHERE loadoutid = $1',
		array($fit['metadata']['loadoutid'])
	);

	$ia = get_interesting_attributes($fit);

	$dps = 0;
	$dps += get_damage_from_turrets($fit, $ia)[0];
	$dps += get_damage_from_missiles($fit, $ia)[0];
	$dps += get_damage_from_smartbombs($fit, $ia)[0];
	$dps += get_damage_from_drones($fit, $ia)[0];

	$ehp = get_ehp_and_resists(
		$fit, [ 'em' => .25, 'explosive' => .25, 'kinetic' => .25, 'thermal' => .25 ]
	)['ehp']['avg'];

	$missing = array();
	$price = array_sum(get_estimated_price($fit, $missing));
	if($missing !== array()) $price = null;

	\Osmium\Db\query_params(
		'INSERT INTO osmium.loadoutdogmaattribs (loadoutid, dps, ehp, estimatedprice)
		VALUES ($1, $2, $3, $4)',
		array(
			$fit['metadata']['loadoutid'],
			$dps,
			$ehp,
			$price,
		)
	);
}

/**
 * Get a fitting by its fittinghash from the database. Fittings,
 * unlike loadouts, are immutable and are referenced by their fitting
 * hash.
 *
 * @returns a $fit-compatible variable, or false on error.
 */
function get_fitting($fittinghash) {
	$fitting = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT name, description,
		evebuildnumber, hullid, creationdate, damageprofileid
		FROM osmium.fittings
		WHERE fittinghash = $1',
		array($fittinghash)
	));

	if($fitting === false) {
		return false;
	}

	create($fit);
	select_ship($fit, $fitting['hullid']);

	$fit['metadata']['hash'] = $fittinghash;
	$fit['metadata']['name'] = $fitting['name'];
	$fit['metadata']['description'] = $fitting['description'];
	$fit['metadata']['evebuildnumber'] = $fitting['evebuildnumber'];
	$fit['metadata']['creation_date'] = $fitting['creationdate'];

	$fit['metadata']['tags'] = array();
	$tagq = \Osmium\Db\query_params(
		'SELECT tagname FROM osmium.fittingtags WHERE fittinghash = $1',
		array($fittinghash)
	);
	while($r = \Osmium\Db\fetch_row($tagq)) {
		$fit['metadata']['tags'][] = $r[0];
	}

	if($fitting['damageprofileid'] !== null) {
		$dpq = \Osmium\Db\query_params(
			'SELECT name, electromagnetic, explosive, kinetic, thermal
			FROM osmium.damageprofiles
			WHERE damageprofileid = $1',
			array($fitting['damageprofileid'])
		);

		if($dpq === false) {
			return false;
		}

		$dp = \Osmium\Db\fetch_row($dpq);
		set_damage_profile(
			$fit,
			$dp[0],
			$dp[1], $dp[2], $dp[3], $dp[4]
		);
	}

	/* It can be done with only one query, but the code would be
	 * monstrously complex. The result is cached anyway, so
	 * performance isn't an absolute requirement as long as it's fast
	 * enough. */

	$firstpreset = true;
	$presetsq = \Osmium\Db\query_params(
		'SELECT presetid, name, description
		FROM osmium.fittingpresets
		WHERE fittinghash = $1
		ORDER BY presetid ASC',
		array($fittinghash)
	);

	$presetsmap = array();

	while($preset = \Osmium\Db\fetch_assoc($presetsq)) {
		if($firstpreset === true) {
			/* Edit the default preset instead of creating a new preset */
			$fit['modulepresetname'] = $preset['name'];
			$fit['modulepresetdesc'] = $preset['description'];

			$firstpreset = false;
		} else {
			$presetid = create_preset($fit, $preset['name'], $preset['description']);
			use_preset($fit, $presetid);
		}

		$presetsmap[$preset['presetid']] = $fit['modulepresetid'];

		$modulesq = \Osmium\Db\query_params(
			'SELECT index, typeid, state
			FROM osmium.fittingmodules
			WHERE fittinghash = $1 AND presetid = $2
			ORDER BY index ASC',
			array($fittinghash, $preset['presetid'])
		);
		while($row = \Osmium\Db\fetch_row($modulesq)) {
			add_module($fit, (int)$row[0], (int)$row[1], (int)$row[2]);
		}

		$firstchargepreset = true;
		$chargepresetsq = \Osmium\Db\query_params(
			'SELECT chargepresetid, name, description
			FROM osmium.fittingchargepresets
			WHERE fittinghash = $1 AND presetid = $2
			ORDER BY chargepresetid ASC',
			array($fittinghash, $preset['presetid'])
		);
		while($chargepreset = \Osmium\Db\fetch_assoc($chargepresetsq)) {
			if($firstchargepreset === true) {
				$fit['chargepresetname'] = $chargepreset['name'];
				$fit['chargepresetdesc'] = $chargepreset['description'];

				$firstchargepreset = false;
			} else {
				$chargepresetid = create_charge_preset($fit, $chargepreset['name'], $chargepreset['description']);
				use_charge_preset($fit, $chargepresetid);
			}

			$chargesq = \Osmium\Db\query_params(
				'SELECT slottype, index, typeid
				FROM osmium.fittingcharges
				WHERE fittinghash = $1 AND presetid = $2 AND chargepresetid = $3
				ORDER BY slottype ASC, index ASC',
				array($fittinghash, $preset['presetid'], $chargepreset['chargepresetid'])
			);
			while($row = \Osmium\Db\fetch_row($chargesq)) {
				add_charge($fit, $row[0], (int)$row[1], (int)$row[2]);
			}
		}

		$implantsq = \Osmium\Db\query_params(
			'SELECT typeid
			FROM osmium.fittingimplants
			WHERE fittinghash = $1 AND presetid = $2',
			array($fittinghash, $preset['presetid'])
		);
		while($implant = \Osmium\Db\fetch_row($implantsq)) {
			add_implant($fit, $implant[0]);
		}
	}
	
	$firstdronepreset = true;
	$dronepresetsq = \Osmium\Db\query_params(
		'SELECT dronepresetid, name, description
		FROM osmium.fittingdronepresets
		WHERE fittinghash = $1
		ORDER BY dronepresetid ASC',
		array($fittinghash)
	);
	while($dronepreset = \Osmium\Db\fetch_assoc($dronepresetsq)) {
		if($firstdronepreset === true) {
			/* Edit the default preset instead of creating a new preset */
			$fit['dronepresetname'] = $dronepreset['name'];
			$fit['dronepresetdesc'] = $dronepreset['description'];

			$firstdronepreset = false;
		} else {
			$dronepresetid = create_drone_preset($fit, $dronepreset['name'], $dronepreset['description']);
			use_drone_preset($fit, $dronepresetid);
		}

		$dronesq = \Osmium\Db\query_params(
			'SELECT typeid, quantityinbay, quantityinspace
			FROM osmium.fittingdrones
			WHERE fittinghash = $1 AND dronepresetid = $2',
			array($fittinghash, $dronepreset['dronepresetid'])
		);
		while($row = \Osmium\Db\fetch_row($dronesq)) {
			add_drone($fit, (int)$row[0], (int)$row[1], (int)$row[2]);
		}
	}

	$fleet = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT hasfleetbooster,
		fleetboosterfittinghash AS fleet,
		haswingbooster,
		wingboosterfittinghash AS wing,
		hassquadbooster,
		squadboosterfittinghash AS squad
		FROM osmium.fittingfleetboosters
		WHERE fittinghash = $1',
		array($fittinghash)
	));

	if($fleet !== false) {
		foreach(array('fleet', 'wing', 'squad') as $t) {
			if($fleet['has'.$t.'booster'] !== 't') continue;
			$flhash = $fleet[$t];

			if($flhash === null) {
				create($fl);
			} else {
				$fl = get_fitting($flhash);
			}
			call_user_func_array(
				__NAMESPACE__.'\set_'.$t.'_booster',
				array(&$fit, $fl)
			);
		}
	}

	$remote = \Osmium\Db\query_params(
		'SELECT key, remotefittinghash
		FROM osmium.fittingremotes
		WHERE fittinghash = $1 AND key <> $2',
		array($fittinghash, 'local')
	);

	while($row = \Osmium\Db\fetch_row($remote)) {
		$rfit = get_fitting($row[1]);

		if($rfit === false) {
			return false;
		}

		add_remote($fit, $row[0], $rfit);
	}

	$mtargets = \Osmium\Db\query_params(
		'SELECT source, presetid, slottype, index, target
		FROM osmium.fittingmoduletargets
		WHERE fittinghash = $1',
		array($fittinghash)
	);

	while($mtgt = \Osmium\Db\fetch_assoc($mtargets)) {
		use_preset($fit, $presetsmap[$mtgt['presetid']]);
		set_module_target_by_location(
			$fit,
			$mtgt['source'], $mtgt['slottype'], $mtgt['index'],
			$mtgt['target']
		);
	}

	/* Use the 1st presets */
	\reset($fit['presets']);
	\Osmium\Fit\use_preset($fit, key($fit['presets']));
	\reset($fit['chargepresets']);
	\Osmium\Fit\use_charge_preset($fit, key($fit['chargepresets']));
	\reset($fit['dronepresets']);
	\Osmium\Fit\use_drone_preset($fit, key($fit['dronepresets']));

	return $fit;
}

/**
 * Get a loadout from the database, given its loadoutid. Returns an
 * array that can be used with all functions using $fit.
 *
 * This is pretty expensive, so the result is cached, unless a
 * revision number is specified.
 *
 * @param $loadoutid the loadoutid of the loadout to fetch
 *
 * @param $revision Revision number to get, if null the latest
 * revision is used
 *
 * @returns a $fit-compatible variable, or false if unrecoverable
 * errors happened.
 */
function get_fit($loadoutid, $revision = null) {
	if($revision === null && (
		$cache = \Osmium\State\get_cache('loadout-'.$loadoutid, null, 'Loadout_Cache_')
	) !== null) {
		return $cache;
	}

	if($revision === null) {
		/* Use latest revision */
		$row = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
			'SELECT latestrevision FROM osmium.loadoutslatestrevision WHERE loadoutid = $1',
			array($loadoutid)
		));
		if($row === false) return false;
		$revision = $row[0];

		$latest_revision = true;
	}

	if(($cache = \Osmium\State\get_cache('loadout-'.$loadoutid.'-'.$revision, null, 'Loadout_Cache_')) !== null) {
		if(isset($latest_revision) && $latest_revision === true) {
			\Osmium\State\put_cache('loadout-'.$loadoutid, $cache, 0, 'Loadout_Cache_');
		}

		return $cache;
	}

	$sem = \Osmium\State\semaphore_acquire('Get_Fit_'.$loadoutid.'_'.$revision);
	if($sem === false) return false;

	$cache = \Osmium\State\get_cache('loadout-'.$loadoutid.'-'.$revision, null, 'Loadout_Cache_');
	if($cache !== null) {
		\Osmium\State\semaphore_release($sem);
		return $cache;
	}

	$loadout = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT accountid, viewpermission, editpermission, visibility, passwordhash, privatetoken
		FROM osmium.loadouts WHERE loadoutid = $1',
		array($loadoutid)
	));

	if($loadout === false) {
		\Osmium\State\semaphore_release($sem);
		return false;
	}

	$hash = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT loadouthistory.fittinghash
		FROM osmium.loadouthistory
		WHERE loadoutid = $1 AND revision = $2',
		array($loadoutid, $revision)
	));

	if($hash === false) {
		\Osmium\State\semaphore_release($sem);
		return false;
	}

	$fit = get_fitting($hash[0]);

	$fit['metadata']['loadoutid'] = $loadoutid;
	$fit['metadata']['privatetoken'] = $loadout['privatetoken'];
	$fit['metadata']['view_permission'] = $loadout['viewpermission'];
	$fit['metadata']['edit_permission'] = $loadout['editpermission'];
	$fit['metadata']['visibility'] = $loadout['visibility'];
	$fit['metadata']['password'] = $loadout['passwordhash'];
	$fit['metadata']['revision'] = $revision;
	$fit['metadata']['accountid'] = $loadout['accountid'];

	if(isset($latest_revision) && $latest_revision === true) {
		\Osmium\State\put_cache('loadout-'.$loadoutid, $fit, 0, 'Loadout_Cache_');
	}

	\Osmium\State\put_cache('loadout-'.$loadoutid.'-'.$revision, $fit, 0, 'Loadout_Cache_');
	\Osmium\State\semaphore_release($sem);
	return $fit;
}

/**
 * If necessary, generate and insert the delta between the supplied
 * fit's previous revision and its current revision in the database.
 */
function insert_fitting_delta_against_previous_revision($fit) {
	if(!isset($fit['metadata']['revision']) || $fit['metadata']['revision'] == 1) return;

	$old = get_fit($fit['metadata']['loadoutid'], $fit['metadata']['revision'] - 1);

	$row = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT delta FROM osmium.fittingdeltas WHERE fittinghash1 = $1 AND fittinghash2 = $2', array($old['metadata']['hash'], $fit['metadata']['hash'])));

	if($row !== false) return; /* Delta already inserted */

	$delta = delta($old, $fit);
	if($delta === null) return;

	\Osmium\Db\query_params('INSERT INTO osmium.fittingdeltas (fittinghash1, fittinghash2, delta) VALUES ($1, $2, $3)', array($old['metadata']['hash'], $fit['metadata']['hash'], $delta));
}

/**
 * @see \Osmium\Fit\get_fit_uri().
 *
 * @note If you have $fit available, it is more efficient to use
 * get_fit_uri() directly.
 */
function fetch_fit_uri($loadoutid) {
	list($visibility, $ptoken) = 
		\Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT visibility, privatetoken FROM osmium.loadouts WHERE loadoutid = $1',
				array($loadoutid)
				));

	return get_fit_uri($loadoutid, $visibility, $ptoken);
}

/**
 * Parses a skillset title and fetches it using characters of account
 * $a.
 *
 * @see use_skillset()
 */
function use_skillset_by_name(&$fit, $ssname, $a = null) {
	if($fit['skillset']['name'] === $ssname) {
		/* Be lazy */
		return $ssname;
	}

	if($ssname == 'All V') {
		use_skillset($fit, array(), 5, $ssname);
		return $ssname;
	}

	if($ssname == 'All 0') {
		use_skillset($fit, array(), 0, $ssname);
		return $ssname;
	}

	if(isset($a['accountid'])) {
		$row = \Osmium\Db\fetch_assoc(
			\Osmium\Db\query_params(
				'SELECT importedskillset, overriddenskillset FROM osmium.accountcharacters
				WHERE accountid = $1 AND name = $2',
				array($a['accountid'], $ssname)
			));
		if($row === false) return false; /* Incorrect skillset name */

		$skillset = json_decode($row['importedskillset'], true);
		$overridden = json_decode($row['overriddenskillset'], true);
		if(!is_array($skillset)) $skillset = array();
		if(!is_array($overridden)) $overridden = array();
		foreach($overridden as $typeid => $l) {
			$skillset[$typeid] = $l;
		}
		use_skillset($fit, $skillset, 0, $ssname);
		return $ssname;
	}

	return false; /* Nonstandard skillset name and not logged in */
}

function get_available_skillset_names_for_account() {
	$names = array('All V', 'All 0');

	if(\Osmium\State\is_logged_in()) {
		$a = \Osmium\State\get_state('a', array());
		$ssq = \Osmium\Db\query_params(
			'SELECT name FROM osmium.accountcharacters WHERE accountid = $1
			ORDER BY name ASC',
			array($a['accountid'])
		);
		while($r = \Osmium\Db\fetch_row($ssq)) {
			$names[] = $r[0];
		}
	}

	return $names;
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

function get_skill_prerequisites_and_missing_prerequisites($fit) {
	$types = array();

	if (!empty($fit['ship'])) {
		$types[$fit['ship']['typeid']] = true;
	}

	foreach ($fit['modules'] as $type => $by_index) {
		foreach ($by_index as $idx => $module) {
			$types[$module['typeid']] = true;

			if(isset($fit['charges'][$type][$idx]['typeid'])) {
				$types[$fit['charges'][$type][$idx]['typeid']] = true;
			}
		}
	}

	foreach($fit['drones'] as $typeid => $drone) {
		if($drone['quantityinspace'] + $drone['quantityinbay'] > 0) {
			$types[$typeid] = true;
		}
	}

	foreach($fit['implants'] as $typeid => $i) {
		$types[$typeid] = true;
	}

	$types = array_keys($types);
	$prereqs = $missing = array();

	get_skill_prerequisites_for_types($types, $prereqs);

	foreach($types as $tid) {
		if(!isset($prereqs[$tid])) continue;

		foreach($prereqs[$tid] as $stid => $level) {
			$current = isset($fit['skillset']['override'][$stid])
				? $fit['skillset']['override'][$stid] : $fit['skillset']['default'];

			if($current < $level) {
				$missing[$tid] = 1;
				break;
			}
		}
	}

	return [ $prereqs, $missing ];
}
