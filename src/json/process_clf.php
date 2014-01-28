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

namespace Osmium\Json\ProcessCLF;

require __DIR__.'/../../inc/root.php';
require \Osmium\ROOT.'/inc/ajax_common.php';

if(!isset($_POST['token']) || $_POST['token'] != \Osmium\State\get_token()
   || !isset($_POST['type']) || !in_array($_POST['type'], array('new', 'view'))
   || !isset($_GET['clftoken']) || !isset($_POST['clf'])) {
	header('HTTP/1.1 400 Bad Request', true, 400);
	\Osmium\Chrome\return_json("Invalid or missing parameters.");
}

$type = $_POST['type'];
$token = $_GET['clftoken'];
$clftext = gzinflate(base64_decode($_POST['clf']));
$relative = $_POST['relative'];
$local = null;

$clf = json_decode($clftext, true);
if(json_last_error() !== JSON_ERROR_NONE) {
	header('HTTP/1.1 400 Bad Request', true, 400);
	\Osmium\Chrome\return_json([ "Could not decode supplied JSON.", json_last_error() ]);
}

$local = \Osmium\State\get_loadout($token);
$payload = array();
$errors = array();

if($local === null) {
	/* Outdated token, generate a new one and send it to client */
	$token = \Osmium\State\get_unique_loadout_token();
	$local = \Osmium\Fit\try_parse_fit_from_common_loadout_format($clftext, $errors, false);

	if(!is_array($local)) {
		header('HTTP/1.1 400 Bad Request', true, 400);
		\Osmium\Chrome\return_json([ "Could not parse supplied CLF.", $errors ]);
	}
} else {
	/* Valid token, just update local loadout from client CLF */

	if(!\Osmium\Fit\synchronize_from_clf_1($local, $clf, $errors)) {
		header('HTTP/1.1 400 Bad Request', true, 400);
		\Osmium\Chrome\return_json([ "Could not synchronize supplied CLF with local CLF.", $errors ]);
	}
}

$attribflags = 0;
if(isset($clf['metadata']['X-Osmium-capreloadtime']) && $clf['metadata']['X-Osmium-capreloadtime']) {
	$attribflags |= \Osmium\Chrome\FATTRIBS_USE_RELOAD_TIME_FOR_CAPACITOR;
}
if(isset($clf['metadata']['X-Osmium-dpsreloadtime']) && $clf['metadata']['X-Osmium-dpsreloadtime']) {
	$attribflags |= \Osmium\Chrome\FATTRIBS_USE_RELOAD_TIME_FOR_DPS;
}
if(isset($clf['metadata']['X-Osmium-tankreloadtime']) && $clf['metadata']['X-Osmium-tankreloadtime']) {
	$attribflags |= \Osmium\Chrome\FATTRIBS_USE_RELOAD_TIME_FOR_TANK;
}

$attribopts = array(
	'flags' => $attribflags
);

$capacitors = \Osmium\Fit\get_all_capacitors(
	$local,
	$attribflags & \Osmium\Chrome\FATTRIBS_USE_RELOAD_TIME_FOR_CAPACITOR
);
$attribopts['cap'] = $capacitors['local'];
foreach($capacitors as &$c) {
	if(isset($c['depletion_time'])) {
		$c['depletion_time'] = \Osmium\Chrome\format_duration($c['depletion_time'] / 1000);
	}
}

$ia = $attribopts['ia'] = \Osmium\Fit\get_interesting_attributes($local);
list($prereqs, $missing) = \Osmium\Fit\get_skill_prerequisites_and_missing_prerequisites($local);
$attribopts['prerequisites'] = $prereqs;

$payload = array(
	'clftoken' => $token,
	'attributes' => \Osmium\Chrome\get_formatted_loadout_attributes($local, $relative, $attribopts),
	'ia' => $ia,
	'ncycles' => array(),
	'rawattribs' => array(
		'dronebandwidth' => \Osmium\Dogma\get_ship_attribute($local, 'droneBandwidth'),
		'dronebandwidthused' => \Osmium\Fit\get_used_drone_bandwidth($local),
		'dronecapacity' => \Osmium\Dogma\get_ship_attribute($local, 'droneCapacity'),
		'dronecapacityused' => \Osmium\Fit\get_used_drone_capacity($local),
		'maxactivedrones' => \Osmium\Dogma\get_char_attribute($local, 'maxActiveDrones'),
	),
	'capacitors' => $capacitors,
	'missingprereqs' => $missing,
);

foreach($local['modules'] as $slottype => $sub) {
	foreach($sub as $index => $m) {
		if(isset($local['charges'][$slottype][$index])) {
			dogma_get_number_of_module_cycles_before_reload(
				$local['__dogma_context'], $m['dogma_index'], $ncycles
			);

			if($ncycles !== -1) {
				$payload['ncycles'][] = array(
					$slottype, $index, $ncycles
				);
			}
		}
	}
}

\Osmium\State\put_loadout(
	$token, $local,
	($type === 'new') ? \Osmium\State\LOADOUT_TYPE_NEW : \Osmium\State\LOADOUT_TYPE_VIEW
);

if($type === 'new') {
	$payload['slots'] = \Osmium\AjaxCommon\get_slot_usage($local);
	$payload['hardpoints'] = array(
		'turret' => \Osmium\Dogma\get_ship_attribute($local, 'turretSlotsLeft'),
		'launcher' => \Osmium\Dogma\get_ship_attribute($local, 'launcherSlotsLeft'),
	);
	$payload['rectags'] = \Osmium\Fit\get_recommended_tags($local);

	if(isset($errors['fleet'])) {
		foreach($errors['fleet'] as $k => $err) {
			foreach($err as $error) {
				$payload['form-errors'][] = array(
					'remote',
					'input#'.$k.'_fit',
					$error,
				);
			}
		}
	}

	if(isset($_POST['submit']) && $_POST['submit']) {
		if(!\Osmium\State\is_logged_in()) {
			header('HTTP/1.1 400 Bad Request', true, 400);
			\Osmium\Chrome\return_json(array());
		}

		\Osmium\Fit\sanitize_tags($local, $tag_errors, true);
		\Osmium\Fit\sanitize($local, $sanitize_errors, true);

		if(!isset($local['ship']) || !isset($local['ship']['typeid']) || !$local['ship']['typeid']) {
			$payload['submit-error'] = 'You must select a ship first.';
		} else if(in_array($local['metadata']['name'], array(
			'', 'Unnamed loadout', 'New DNA-imported loadout',
		))) {
			$payload['form-errors'][] = array(
				'metadata', 'input#name',
				'Please enter a name for your loadout.',
			);
		} else if($local['metadata']['view_permission'] == \Osmium\Fit\VIEW_PASSWORD_PROTECTED
		          && empty($local['metadata']['password'])) {
			$payload['form-errors'][] = array(
				'metadata', 'input#pw',
				'If you want your fit to be password-protected, please enter a non-empty password.',
			);
		} else if($local['metadata']['view_permission'] == \Osmium\Fit\VIEW_PASSWORD_PROTECTED
		          && $local['metadata']['visibility'] != \Osmium\Fit\VISIBILITY_PRIVATE) {
			$payload['form-errors'][] = array(
				'metadata', 'input#visibility',
				'You cannot have a public password-protected fit. Make it private.',
			);
		} else if(count($tag_errors) > 0) {
			foreach($tag_errors as $err) {
				$payload['form-errors'][] = array(
					'metadata', 'input#tags',
					$err,
				);
			}
		} else if(\Osmium\Reputation\is_fit_public($local) && !\Osmium\Reputation\has_privilege(
			\Osmium\Reputation\PRIVILEGE_CREATE_LOADOUT
		)) {
			$payload['form-errors'][] = [
				'metadata', 'select#view_perms',
				"You don't have the privilege to create public loadouts. Please select a different view permission or visibility."
			];
		}

		else {
			/* Looks good, commit the loadout */

			/* Sanitize the loadout, even if it is destructive */
			\Osmium\Fit\sanitize($local, $sanitize_errors, false);

			$accountid = \Osmium\State\get_state('a')['accountid'];
			if(isset($local['metadata']['accountid'])) {
				$ownerid = $local['metadata']['accountid'];
			} else {
				$ownerid = $accountid;
			}

			$ret = \Osmium\Fit\commit_loadout($local, $ownerid, $accountid, $error);
			if($ret === false) {
				$payload['submit-error'] = 'An error occured while committing the loadout. Sorry. Please report! ('.$error.')';
			} else {
				$payload['submit-loadout-uri'] =
					'../'.\Osmium\Fit\get_fit_uri(
						$local['metadata']['loadoutid'],
						$local['metadata']['visibility'],
						$local['metadata']['privatetoken']
					);
			}
		}
	} else if(isset($_POST['export']) && $_POST['export'] && isset($_POST['exportfmt'])) {
		$formats = \Osmium\Fit\get_export_formats();
		if(!isset($formats[$_POST['exportfmt']])) {
			header('HTTP/1.1 400 Bad Request', true, 400);
			\Osmium\Chrome\return_json(array());
		}

		$format = $formats[$_POST['exportfmt']];
		$payload['export-type'] = $format[1];
		$payload['export-payload'] = $format[2]($local);
	} else if(isset($_POST['remoteclf']) && isset($local['remote'][$_POST['remoteclf']])) {
		$key = $_POST['remoteclf'];
		$payload['remote-clf'] = \Osmium\Fit\export_to_common_loadout_format_1(
			$local['remote'][$key],
			\Osmium\Fit\CLF_EXPORT_MINIFY
			| \Osmium\Fit\CLF_EXPORT_EXTRA_PROPERTIES
			| \Osmium\Fit\CLF_EXPORT_INTERNAL_PROPERTIES
		);
		$payload['remote-clf']['fitting'] = $local['remote'][$key]['__id'];

		if(isset($errors['remote'][$key]) && $errors['remote'][$key] !== []) {
			$payload['remote-errors'] = $errors['remote'][$key];
		}
	}
}

\Osmium\Chrome\return_json($payload);
