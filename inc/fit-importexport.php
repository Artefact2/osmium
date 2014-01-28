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

require __DIR__.'/fit-importexport-clf.php';
require __DIR__.'/fit-importexport-dna.php';





/**
 * Get a list of available export formats, with a human-readable name,
 * the content-type of the exported data, and a function taking a $fit
 * and returning the exported data.
 */
function get_export_formats() {
	return array(
		'clf' => array(
			'CLF', 'application/json',
			function($fit, $opts = array()) {
				$clfopts = CLF_EXPORT_DEFAULT_OPTS
					| ((isset($opts['minify']) && $opts['minify']) ? CLF_EXPORT_MINIFY : 0);
				return export_to_common_loadout_format($fit, $clfopts);
			}),
		'md' => array(
			'Markdown+gzCLF', 'text/plain',
			function($fit, $opts = array()) {
				$embedclf = !isset($opts['embedclf']) || $opts['embedclf'];
				return export_to_markdown($fit, $embedclf);
			}),
		'evexml' => array(
			'XML+gzCLF', 'application/xml',
			function($fit, $opts = array()) {
				$embedclf = !isset($opts['embedclf']) || $opts['embedclf'];
				return export_to_eve_xml(array($fit), $embedclf);
			}),
		'eft' => array(
			'EFT', 'text/plain',
			function($fit, $opts = array()) {
				return export_to_eft($fit);
			}),
		'dna' => array(
			'DNA', 'text/plain',
			function($fit, $opts = array()) {
				return export_to_dna($fit);
			}),
		);
}

/**
 * Get a list of available import formats, with a human-readable name
 * and precisions, and a function taking the data and an array of
 * generated errors and returning an array of $fit.
 */
function get_import_formats() {
	return array(
		'autodetect' => array(
			'Autodetect', 'try to autodetect format',
			function($data, &$errors) {
				$fmt = autodetect_format($data);
				$available = get_import_formats();
				if(!isset($available[$fmt])) {
					return false;
				} else {
					return $available[$fmt][2]($data, $errors);
				}
			}),
		'clf' => array(
			'CLF', 'single or array',
			function($data, &$errors) {
				$srcarray = json_decode($data, true);
				if(json_last_error() !== JSON_ERROR_NONE) {
					$errors[] = 'Fatal: source is not valid JSON';
					return false;
				} else if(!is_array($srcarray)) {
					$errors[] = 'Fatal: source is not a JSON array';
					return false;
				}

				$fits = array();
				if(isset($srcarray['clf-version']) && is_int($srcarray['clf-version'])) {
					$fits[] = try_parse_fit_from_common_loadout_format(json_encode($srcarray), $errors);
				} else {
					foreach($srcarray as $clf) {
						if(isset($clf['clf-version']) && is_int($clf['clf-version'])) {
							$fits[] = try_parse_fit_from_common_loadout_format(json_encode($clf), $errors);
						} 
					}
				}
				$fits = array_filter($fits);
				return $fits === array() ? false : $fits;
			}),
		'gzclf' => array(
			'gzCLF', 'supports multiple blocks',
			function($data, &$errors) {
				$start = strpos($data, 'BEGIN gzCLF BLOCK');
				$fits = array();

				while($start !== false) {
					$end = strpos($data, 'END gzCLF BLOCK');
					if($end === false) break;

					$gzclf = substr($data, $start, $end + strlen('END gzCLF BLOCK'));
					$fits[] = \Osmium\Fit\try_parse_fit_from_gzclf($gzclf, $errors);
					$start = strpos($data, 'BEGIN gzCLF BLOCK', $start + strlen('BEGIN gzCLF BLOCK'));
				}

				$fits = array_filter($fits);
				return $fits === array() ? false : $fits;
			}),
		'evexml' => array(
			'EVE XML', 'supports multiple <fitting> elements',
			function($data, &$errors) {
				$fits = array();

				try {
					$old = libxml_use_internal_errors(true);
					$xml = new \SimpleXMLElement($data);
					libxml_use_internal_errors($old);

					if(isset($xml->shipType)) {
						$fits[] = try_parse_fit_from_eve_xml($xml, $errors);
					} else if(isset($xml->fitting) && isset($xml->fitting->shipType)) {
						$fits[] = try_parse_fit_from_eve_xml($xml->fitting, $errors);
					} else if(isset($xml->fitting) && is_array($xml->fitting[0]->shipType)) {
						foreach($xml->fitting as $f) {
							$fits[] = try_parse_fit_from_eve_xml($f, $errors);
						}
					} else {
						$errors[] = 'XML error: root element is neither <fitting> nor <fittings>';
					}
				} catch(\Exception $e) {
					$errors[] = 'Caught exception while creating SimpleXMLElement: '.$e->getMessage();
				}

				$fits = array_filter($fits);
				return $fits === array() ? false : $fits;
			}),
		'dna' => array(
			'DNA', '<url=fitting:> syntax is supported',
			function($data, &$errors) {
				$fits = array();

				$data = preg_replace_callback(
					'%<url=fitting:(?P<dna>'.\Osmium\Fit\DNA_REGEX.')>(?P<name>.+)</url>%U',
					function($match) use(&$fits) {
						$fits[] = try_parse_fit_from_shipdna($match['dna'], $match['name'], $errors);
						return ''; /* To avoid rematching them later */
					},
					$data
				);

				preg_match_all('%'.\Osmium\Fit\DNA_REGEX.'%U', $data, $matches);
				foreach($matches[0] as $dnastring) {
					$fits[] = try_parse_fit_from_shipdna($dnastring, 'DNA-imported loadout', $errors);
				}

				$fits = array_filter($fits);
				return $fits === array() ? false : $fits;
			}),
		'eft' => array(
			'EFT', 'supports multiple fits',
			function($data, &$errors) {
				$fits = array();
				$lines = array_map('trim', explode("\n", $data));

				foreach($lines as $l) {
					if(preg_match('%^\[(.+)(,(.+)?)\]$%U', $l)) {
						if(isset($eft)) {
							$fits[] = try_parse_fit_from_eft_format($eft, $errors);
						}
						$eft = '';
					}

					$eft .= $l."\n";
				}

				if(isset($eft)) {
					$fits[] = try_parse_fit_from_eft_format($eft, $errors);
				}

				$fits = array_filter($fits);
				return $fits === array() ? false : $fits;
			}),
	);
}

/**
 * Try to autodetect a loadout format, based on heuristics.
 *
 * @returns one of "clf", "gzclf", "evexml", "eft", "dna" or false on
 * failure to detect.
 */
function autodetect_format($source) {
	$json = json_decode($source, true);
	if(json_last_error() === JSON_ERROR_NONE && is_array($json)) {
		/* Input is JSON array/object */

		if(isset($json['clf-version']) && is_int($json['clf-version']) || (
			isset($json[0]['clf-version']) && is_int($json[0]['clf-version'])
		)) {
			/* Input looks like CLF */
			return 'clf';
		}
	}

	if(($start = strpos($source, 'BEGIN gzCLF BLOCK')) !== false
	   && ($end = strpos($source, 'END gzCLF BLOCK')) !== false
	   && $start < $end) {
		/* Input looks like gzCLF */
		return 'gzclf';
	}

	try {
		$old = libxml_use_internal_errors(true);
		$xml = new \SimpleXMLElement($source);
		libxml_use_internal_errors($old);

		if(isset($xml->shipType) || isset($xml->fitting->shipType) || isset($xml->fitting[0]->shipType)) {
			return 'evexml';
		}
	} catch(\Exception $e) {}

	if(preg_match('%^\[(.+),(.+)\]%U', $source)) {
		return 'eft';
	}

	if(preg_match('%'.DNA_REGEX.'%U', $source)) {
		return 'dna';
	}

	return false;
}





/**
 * Try to parse a loadout in the EVE XML format, from a string
 * containing the XML.
 *
 * If multiple loadouts are contained in one <loadouts> parent
 * element, only the first will be used (and returned).
 */
function try_parse_fit_from_eve_xml_string($xmlstring, &$errors) {
	try {
		$old = libxml_use_internal_errors(true);
		$xml = new \SimpleXMLElement($xmlstring);
		libxml_use_internal_errors($old);

		if(isset($xml->shipType)) {
			/* Root element is <fitting> */
			return try_parse_fit_from_eve_xml($xml, $errors);
		} else if(isset($xml->fitting)) {
			/* Root element is <fittings> */
			return try_parse_fit_from_eve_xml($xml->fitting[0], $errors);
		} else {
			$errors[] = 'XML error: root element is neither <fitting> nor <fittings>';
		}
	} catch(\Exception $e) {
		$errors[] = 'Caught exception while creating SimpleXMLElement: '.$e->getMessage();
	}

	return false;
}

/**
 * Try to parse a loadout in the EVE XML format.
 *
 * @param $e SimpleXMLElement to parse the loadout from, should be the
 * <loadout> element.
 *
 * @param $errors the array to store any import errors into; errors
 * will be appended at the end of the array.
 */
function try_parse_fit_from_eve_xml(\SimpleXMLElement $e, &$errors) {
	require_once __DIR__.CLF_PATH;
	create($fit);

	if(!isset($e['name'])) {
		$errors[] = 'Expected a name attribute in <fitting> tag, none found. Stopping.';
		return false;
	} else {
		$name = (string)$e['name'];
	}

	if(!isset($e->description) || !isset($e->description['value'])) {
		$errors[] = 'Expected <description> tag with value attribute, none found. Using empty description.';
		$description = '';
	} else {
		$description = (string)$e->description['value'];
	}

	if(!isset($e->shipType) || !isset($e->shipType['value'])) {
		$errors[] = 'Expected <shipType> tag with value attribute, none found. Stopping.';
		return false;
	} else {
		$shipname = (string)$e->shipType['value'];
	}

	if(($shipid = \CommonLoadoutFormat\get_typeid($shipname)) === false) {
		$errors[] = 'Could not fetch typeID of "'.$shipname.'". Obsolete/unpublished ship? Stopping.';
		return false;
	}

	if(!\CommonLoadoutFormat\check_typeof_type($shipid, "ship")) {
		$errors[] = 'Typeid '.$shipid.' is not the typeid of a ship.';
		return false;
	}

	select_ship($fit, $shipid);

	$indexes = array();

	if(!isset($e->hardware)) return $fit;
	foreach($e->hardware as $hardware) {
		if(!isset($hardware['type'])) {
			$errors[] = 'Tag <hardware> has no type attribute. Discarded.';
			continue;
		}

		$type = (string)$hardware['type'];
		$typeid = \CommonLoadoutFormat\get_typeid($type);

		if($typeid === false) {
			$errors[] = 'Could not get typeid of "'.$type.'". Discarded.';
			continue;
		}

		if(\CommonLoadoutFormat\check_typeof_type($typeid, "drone")) {
			if(!isset($hardware['slot']) || (string)$hardware['slot'] !== 'drone bay') {
				$errors[] = 'Nonsensical slot attribute for drone: "'.((string)$hardware['slot']).'". Discarded';
				continue;
			}

			if(!isset($hardware['qty']) || (int)$hardware['qty'] < 0) {
				$errors[] = 'Incorrect qty attribute for drone: "'.((int)$hardware['qty']).'". Discarded.';
				continue;
			}

			add_drone_auto($fit, $typeid, (int)$hardware['qty']);
		} else {
			$slottype = \CommonLoadoutFormat\get_module_slottype($typeid);
			if($slottype === 'unknown') {
				$errors[] = 'Type "'.$type.'" is neither a drone nor a module. Discarded.';
				continue;
			}

			if(isset($hardware['slot'])) {
				preg_match_all('%^(hi|med|low|rig|subsystem) slot ([0-9]+)$%', (string)$hardware['slot'], $matches);
				if(isset($matches[2]) && count($matches[2]) === 1) {
					$index = (int)$matches[2][0];
					add_module($fit, $index, $typeid);
				} else {
					$errors[] = 'Nonsensical slot attribute: "'.((string)$hardware['slot']).'". Discarded.';
					continue;
				}
			} else {
				$errors[] = 'No index found for module "'.$type.'". Discarded.';
				continue;
			}
		}
	}

	$fit['metadata']['name'] = $name;
	$fit['metadata']['description'] = $description;
	$fit['metadata']['tags'] = get_recommended_tags($fit);

	return $fit;
}





/**
 * Try to parse a loadout in the EFT format. Since the format is not
 * documented anywhere, use at your own risk!
 */
function try_parse_fit_from_eft_format($eftstring, &$errors) {
	require_once __DIR__.CLF_PATH;

	$lines = array_map('trim', explode("\n", $eftstring));
	if(count($lines) == 0) {
		$errors[] = 'No input - aborting.';
		return false;
	}

	$meta = array_shift($lines);
	if(!preg_match('%^\[(.+)(,(.+)?)\]$%U', $meta, $match)) {
		$errors[] = 'Nonsensical first line, expected [ShipType, LoadoutName].';
		return false;
	}

	$shipname = trim($match[1]);
	$shiptype = \CommonLoadoutFormat\get_typeid($shipname);
	if($shiptype === false) {
		$errors[] = 'Ship "'.$shipname.'" not found.';
		return false;
	}

	if(!\CommonLoadoutFormat\check_typeof_type($shiptype, 'ship')) {
		$errors[] = 'Type "'.$shipname.'" is not a ship.';
		return false;
	}

	create($fit);
	select_ship($fit, $shiptype);

	$name = isset($match[3]) ? trim($match[3]) : '';
	if(!$name) {
		$name = 'Unnamed loadout';
	}

	$fit['metadata']['name'] = $name;
	$fit['metadata']['description'] = '';
	$fit['metadata']['tags'] = array();

	$indexes = array();
	foreach($lines as $l) {
		if(!$l) continue; /* Ignore empty lines */
		if(preg_match('%^\[empty (low|med|high|rig|subsystem) slot\]$%', trim($l))) continue;
		if(strpos($l, ',') !== false) {
			list($module, $charge) = explode(',', $l, 2);
			$module = trim($module);
			$charge = trim($charge);
		} else {
			$module = $l; /* Already trimmed */
			$charge = false;
		}

		if(preg_match('%^(.+)(\s+)x([0-9]+)$%U', $module, $match)) {
			$module = $match[1];
			$qty = (int)$match[3];
			if(!$qty) continue; /* Foobar x0 ?! */
		} else {
			$qty = 1;
		}

		$moduleid = \CommonLoadoutFormat\get_typeid($module);
		if($moduleid === false) {
			$errors[] = 'Type "'.$module.'" not found.';
			continue;
		}

		if(\CommonLoadoutFormat\check_typeof_type($moduleid, 'drone')) {
			add_drone_auto($fit, $moduleid, $qty);
		} else {
			$slottype = \CommonLoadoutFormat\get_module_slottype($moduleid);
			if($slottype === 'unknown') {
				$errors[] = 'Type "'.$module.'" is neither a drone nor a module. Discarded.';
				continue;
			}

			if(!isset($indexes[$slottype])) {
				$indexes[$slottype] = 0;
			}
			$index = ($indexes[$slottype]++);

			add_module($fit, $index, $moduleid);

			if($charge !== false) {
				$chargeid = \CommonLoadoutFormat\get_typeid($charge);
				if($chargeid === false) {
					$errors[] = 'Type "'.$charge.'" not found.';
					continue;
				}

				if(!\CommonLoadoutFormat\check_typeof_type($chargeid, 'charge')) {
					$errors[] = 'Type "'.$charge.'" is not a charge.';
					continue;
				}

				if(!\CommonLoadoutFormat\check_charge_can_be_fitted_to_module($moduleid, $chargeid)) {
					$errors[] = 'Charge "'.$charge.'" cannot be fitted to module "'.$module.'".';
					continue;
				}

				add_charge($fit, $slottype, $index, $chargeid);
			}
		}
	}

	$fit['metadata']['tags'] = get_recommended_tags($fit);

	return $fit;
}





/**
 * Generate a Markdown-formatted description of a loadout. This is a
 * one-way operation only, unless $embedclf is set to true.
 */
function export_to_markdown($fit, $embedclf = true) {
	static $statenames = null;
	if($statenames === null) $statenames = get_state_names();

	if(isset($fit['ship']['typename'])) {
		$md = "## ".$fit['ship']['typename']." loadout\n";
	} else {
		$md = "## Shipless partial loadout\n";
	}

	$quote = function($text) {
		if((string)$text === "") return "";

		return "> ".str_replace("\n", "\n> ", wordwrap(trim($text), 70));
	};

	if(isset($fit['metadata']['name'])) {
		$md .= "# ".$fit['metadata']['name']."\n\n";
	} else {
		$md .= "\n";
	}

	if(isset($fit['metadata']['description'])) {
		$q = $quote($fit['metadata']['description']);
		if($q !== "") $md .= $q."\n\n";
	}

	if(count($fit['presets']) > 0) $md .= "# Presets\n\n";
	foreach($fit['presets'] as $pid => $preset) {
		$md .= "## ".$preset['name']."\n\n";

		if(isset($preset['description'])) {
			$q = $quote($preset['description']);
			if($q !== "") $md .= $q."\n\n";
		}

		/* Enforce consistent ordering of slot types, instead of just using foreach */
		foreach(get_slottypes() as $type => $tdata) {
			if(!isset($preset['modules'][$type]) || count($preset['modules'][$type]) == 0) continue;

			$md .= "### ".ucfirst($type)." slots\n\n";

			ksort($preset['modules'][$type]);
			foreach($preset['modules'][$type] as $index => $module) {
				$md .= "- ".$module['typename'];

				list($isactivable, ) = get_module_states($fit, $module['typeid']);
				$state = $module['state'] === null ? $module['old_state'] : $module['state'];
				if(($isactivable && $state != STATE_ACTIVE) || (!$isactivable && $state != STATE_ONLINE)) {
					$md .= " (".$statenames[$state][2].")";
				}

				if(isset($module['target']) && $module['target'] !== null) {
					$md .= ', applied on remote fit #'.$module['target'];
				}

				$md .= "\n";
			}

			$md .= "\n";
		}

		if($preset['implants'] !== array()) {
			ksort($preset['implants']);
			$md .= "# Implants and boosters\n\n";

			foreach($preset['implants'] as $i) {
				$md .= "- ".$i['typename']."\n";
			}

			$md .= "\n";
		}

		if(!isset($preset['chargepresets'])) continue;
		$hascharges = false;
		foreach($preset['chargepresets'] as $cp) {
			foreach($cp['charges'] as $type => $a) {
				foreach($a as $index => $c) {
					$hascharges = true;
					break 3;
				}
			}
		}

		if(!$hascharges) continue;

		$md .= "### Charge presets\n\n";

		ksort($preset['chargepresets']);
		foreach($preset['chargepresets'] as $cpid => $cp) {
			$md .= "#### ".$cp['name']."\n\n";

			if(isset($cp['description'])) {
				$q = $quote($cp['description']);
				if($q !== "") $md .= $q."\n\n";
			}

			foreach(get_slottypes() as $type => $tdata) {
				if(!isset($cp['charges'][$type])) continue;

				foreach($cp['charges'][$type] as $index => $charge) {
					$md .= "- ".$charge['typename']."\n";
				}
			}

			$md .= "\n";
		}
	}

	$hasdrones = false;
	if(isset($fit['dronepresets'])) {
		foreach($fit['dronepresets'] as $dp) {
			foreach($dp['drones'] as $drone) {
				if($drone['quantityinbay'] > 0 || $drone['quantityinspace'] > 0) {
					$hasdrones = true;
					break 2;
				}
			}
		}
	}

	if($hasdrones) {
		$md .= "# Drone presets\n\n";

		foreach($fit['dronepresets'] as $dp) {
			$md .= "## ".$dp['name']."\n\n";
			
			if(isset($dp['description'])) {
				$q = $quote($dp['description']);
				if($q !== "") $md .= $q."\n\n";
			}

			foreach($dp['drones'] as $drone) {
				$qties = array();
				if($drone['quantityinspace'] > 0) {
					$qties[] = $drone['quantityinspace']." in space";
				}
				if($drone['quantityinbay'] > 0) {
					$qties[] = $drone['quantityinbay']." in bay";
				}

				if($qties === array()) continue;

				$md .= "- ".$drone['typename']." (".implode(', ', $qties).")\n";
			}

			$md .= "\n";
		}
	}

	$md .= "# Other\n\n";

	$ver = get_closest_version_by_build($fit['metadata']['evebuildnumber']);
	$md .= "- Designed for: ".$ver['name']." (".$ver['tag']."; build ".$fit['metadata']['evebuildnumber'].")\n";

	$md .= "- Tags: ";

	if(isset($fit['metadata']['tags']) && count($fit['metadata']['tags']) > 0) {
		$md .= implode(", ", $fit['metadata']['tags'])."\n";
	} else {
		$md .= "(none)\n";
	}

	$md .= "- Damage profile: ".$fit['damageprofile']['name']
		." (EM: ".\Osmium\Chrome\round_sd(100 * $fit['damageprofile']['damages']['em'], 3)
		."%, Explosive: ".\Osmium\Chrome\round_sd(100 * $fit['damageprofile']['damages']['explosive'], 3)
		."%, Kinetic: ".\Osmium\Chrome\round_sd(100 * $fit['damageprofile']['damages']['kinetic'], 3)
		."%, Thermal: ".\Osmium\Chrome\round_sd(100 * $fit['damageprofile']['damages']['thermal'], 3)."%)\n";

	$md .= "\n";

	if(isset($fit['fleet']) && $fit['fleet'] !== array()) {
		$md .= "# Fleet bonuses\n\n";

		foreach($fit['fleet'] as $k => $f) {
			$md .= "- ".ucfirst($k)." booster: `fittinghash ".get_hash($f)."`\n";
		}

		$md .= "\n";
	}

	if(isset($fit['remote']) && $fit['remote'] !== array()) {
		$md .= "# Remote fits\n\n";

		foreach($fit['remote'] as $k => $rf) {
			$md .= "- Remote fit #{$k}: `fittinghash ".get_hash($rf)."`\n\n";

			foreach($rf['presets'] as $preset) {
				$hastitle = false;

				foreach($preset['modules'] as $type => $sub) {
					$z = 0;
					foreach($sub as $m) {
						if(!isset($m['target']) || $m['target'] === null) {
							++$z;
							continue;
						}
						if(!$hastitle) {
							$hastitle = true;
							$md .= "  ### ".$preset['name']."\n\n";
						}

						if($m['target'] === 'local') {
							$tgt = 'the local fit';
						} else {
							$tgt = 'remote fit #'.$m['target'];
						}

						$md .= "  - ".$m['typename']." ({$type} slot #{$z}) applied on {$tgt}\n";

						++$z;
					}
				}

				if($hastitle) {
					$md .= "\n";
				}
			}
		}
	}

	if($embedclf) {
		$md .= "----------\n\n";
		$md .= "    ".str_replace("\n", "\n    ", trim(export_to_gzclf($fit)))."\n\n";
	}

	return $md;
}





/**
 * Export an array of fits to the EVE XML format (which can be later
 * imported in the client).
 */
function export_to_eve_xml(array $fits, $embedclf = true) {
	$xml = new \DOMDocument();
	$fittings = $xml->createElement('fittings');
	$xml->appendChild($fittings);

	foreach($fits as $fit) {
		$fittings->appendChild(export_to_eve_xml_single($xml, $fit, $embedclf));
	}

	$xml->formatOutput = true;
	return $xml->saveXML();
}

/** @internal */
function export_to_eve_xml_single(\DOMDocument $f, $fit, $embedclf = true) {
	static $modtypes = array(
		'low' => 'low',
		'medium' => 'med',
		'high' => 'hi',
		'rig' => 'rig',
		'subsystem' => 'subsystem',
		);

	$e = $f->createElement('fitting');

	$name = isset($fit['metadata']['name']) ? $fit['metadata']['name'] : 'Unnamed fitting';
	$description = isset($fit['metadata']['description']) ? $fit['metadata']['description'] : '';
	if($embedclf) {
		if($description) $description = rtrim($description)."\n\n";
		$description .= export_to_gzclf($fit);
	}

	$aname = $f->createAttribute('name');
	$aname->appendChild($f->createTextNode($name));
	$e->appendChild($aname);

	$edesc = $f->createElement('description');
	$avalue = $f->createAttribute('value');
	$avalue->appendChild($f->createTextNode($description));
	$edesc->appendChild($avalue);
	$e->appendChild($edesc);

	if(isset($fit['ship']['typename'])) {
		$eshiptype = $f->createElement('shipType');
		$avalue = $f->createAttribute('value');
		$avalue->appendChild($f->createTextNode($fit['ship']['typename']));
		$eshiptype->appendChild($avalue);
		$e->appendChild($eshiptype);
	}

	foreach($fit['modules'] as $type => $a) {
		ksort($a);
		/* Ensure contiguous indexes */
		$i = 0;
		foreach($a as $module) {
			$ehardware = $f->createElement('hardware');
			$aslot = $f->createAttribute('slot');
			$aslot->appendChild($f->createTextNode($modtypes[$type].' slot '.$i));
			$atype = $f->createAttribute('type');
			$atype->appendChild($f->createTextNode($module['typename']));
			$ehardware->appendChild($aslot);
			$ehardware->appendChild($atype);
			$e->appendChild($ehardware);

			++$i;
		}
	}

	foreach($fit['drones'] as $drone) {
		$qty = 0;
		if(isset($drone['quantityinbay'])) $qty += $drone['quantityinbay'];
		if(isset($drone['quantityinspace'])) $qty += $drone['quantityinspace'];
		if($qty == 0) continue;

		$ehardware = $f->createElement('hardware');
		$aqty = $f->createAttribute('qty');
		$aqty->appendChild($f->createTextNode($qty));
		$aslot = $f->createAttribute('slot');
		$aslot->appendChild($f->createTextNode('drone bay'));
		$atype = $f->createAttribute('type');
		$atype->appendChild($f->createTextNode($drone['typename']));
		$ehardware->appendChild($aqty);
		$ehardware->appendChild($aslot);
		$ehardware->appendChild($atype);
		$e->appendChild($ehardware);
	}

	return $e;
}





/**
 * Export a loadout to the EFT format. Use at your own risk.
 */
function export_to_eft($fit) {
	static $slotorder = array(
		'low' => 'low',
		'medium' => 'med',
		'high' => 'high',
		'rig' => false,
		'subsystem' => ' subsystem',
	);

	$r = '[';

	if(isset($fit['ship']['typename'])) {
		$r .= $fit['ship']['typename'];
	} else {
		$r .= 'No ship';
	}

	$name = isset($fit['metadata']['name']) ? $fit['metadata']['name'] : 'unnamed';
	$r .= ', '.$name."]\n";

	$slots = get_slottypes();

	foreach($slotorder as $type => $emptyname) {
		$i = 0;
		$max = \Osmium\Dogma\get_ship_attribute($fit, $slots[$type][3]);

		$m = isset($fit['modules'][$type]) ? $fit['modules'][$type] : array();
		foreach($m as $index => $module) {
			$r .= $module['typename'];
			if(isset($fit['charges'][$type][$index])) {
				$r .= ', '.$fit['charges'][$type][$index]['typename'];
			}
			++$i;
			$r .= "\n";
		}

		while($i < $max && $emptyname !== false) {
			$r .= "[empty {$emptyname} slot]\n";
			++$i;
		}

		$r .= "\n";
	}

	foreach($fit['drones'] as $drone) {
		$qty = 0;
		if(isset($drone['quantityinbay'])) $qty += $drone['quantityinbay'];
		if(isset($drone['quantityinspace'])) $qty += $drone['quantityinspace'];
		if($qty == 0) continue;

		$r .= $drone['typename'].' x'.$qty."\n";
	}

	return $r;
}
