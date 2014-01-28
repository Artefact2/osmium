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

namespace Osmium\Json\ShowInfo;

require __DIR__.'/../../inc/root.php';
require __DIR__.'/../../inc/ajax_common.php';

if(isset($_GET['clftoken'])) {
	$fit = \Osmium\State\get_loadout($_GET['clftoken']);
} else {
	header('HTTP/1.1 400 Bad Request', true, 400);
	\Osmium\Chrome\return_json(array());
}

if(!isset($_POST['type']) || $fit === null) {
	header('HTTP/1.1 400 Bad Request', true, 400);
	\Osmium\Chrome\return_json(array());
}

if(isset($_POST['remote'])) {
	\Osmium\Fit\set_local($fit, $_POST['remote']);
}

function get_attributes($typeid, $getval_callback) {
	$attributes = array();

	$aq = \Osmium\Db\query_params(
		"SELECT dgmtypeattribs.attributeid, attributename, dgmattribs.displayname, value, dgmattribs.unitid,
		dgmunits.displayname AS udisplayname, categoryid
		FROM eve.dgmtypeattribs
		JOIN eve.dgmattribs ON dgmtypeattribs.attributeid = dgmattribs.attributeid
		LEFT JOIN eve.dgmunits ON dgmattribs.unitid = dgmunits.unitid
		WHERE typeid = $1 AND published = true AND dgmattribs.displayname <> ''

		UNION

		SELECT dgmattribs.attributeid, attributename, dgmattribs.displayname,
		invtypes.volume as value, dgmattribs.unitid,
		dgmunits.displayname AS udisplayname, categoryid
		FROM eve.invtypes
		JOIN eve.dgmattribs ON dgmattribs.attributeid = 161
		LEFT JOIN eve.dgmunits ON dgmattribs.unitid = dgmunits.unitid
		WHERE typeid = $1

		UNION

		SELECT dgmattribs.attributeid, attributename, dgmattribs.displayname,
		invtypes.capacity as value, dgmattribs.unitid,
		dgmunits.displayname AS udisplayname, categoryid
		FROM eve.invtypes
		JOIN eve.dgmattribs ON dgmattribs.attributeid = 38
		LEFT JOIN eve.dgmunits ON dgmattribs.unitid = dgmunits.unitid
		WHERE typeid = $1

		UNION

		SELECT dgmattribs.attributeid, attributename, dgmattribs.displayname,
		invtypes.mass as value, dgmattribs.unitid,
		dgmunits.displayname AS udisplayname, categoryid
		FROM eve.invtypes
		JOIN eve.dgmattribs ON dgmattribs.attributeid = 4
		LEFT JOIN eve.dgmunits ON dgmattribs.unitid = dgmunits.unitid
		WHERE typeid = $1

		ORDER BY categoryid ASC, attributeid ASC",
		array($typeid)
	);
	while($a = \Osmium\Db\fetch_assoc($aq)) {
		$rawval = $getval_callback !== null ? $getval_callback($a['attributeid']) : (float)$a['value'];

		if((int)$a['attributeid'] === 38 && $rawval === 0.0) continue;

		$attributes[$a['attributeid']] = array(
			ucfirst($a['displayname']),
			\Osmium\Chrome\format_number_with_unit(
				$rawval,
				$a['unitid'],
				$a['udisplayname']
				),
			$a['categoryid'],
			);
	}

	return $attributes;
}

\Osmium\Dogma\auto_init($fit);

if($_POST['type'] == 'module' && isset($_POST['slottype']) && isset($_POST['index'])
   && isset($fit['modules'][$_POST['slottype']][$_POST['index']])) {
	$st = $_POST['slottype'];
	$idx = $_POST['index'];
	$module = $fit['modules'][$st][$idx];

	$typeid = $module['typeid'];
	$typename = $module['typename'];
	$loc = [ DOGMA_LOC_Module, 'module_index' => $module['dogma_index'] ];
	$attributes = get_attributes($typeid, $getatt = function($aname) use(&$fit, $st, $idx) {
			return \Osmium\Dogma\get_module_attribute($fit, $st, $idx, $aname);
		});
} else if($_POST['type'] == 'charge' && isset($_POST['slottype']) && isset($_POST['index'])
   && isset($fit['charges'][$_POST['slottype']][$_POST['index']])) {
	$st = $_POST['slottype'];
	$idx = $_POST['index'];
	$charge = $fit['charges'][$st][$idx];

	$typeid = $charge['typeid'];
	$typename = $charge['typename'];
	$loc = [ DOGMA_LOC_Charge, 'module_index' => $fit['modules'][$st][$idx]['dogma_index'] ];
	$attributes = get_attributes($typeid, $getatt = function($aname) use(&$fit, $st, $idx) {
			return \Osmium\Dogma\get_charge_attribute($fit, $st, $idx, $aname);
		});
} else if($_POST['type'] == 'ship') {
	$typeid = $fit['ship']['typeid'];
	$typename = $fit['ship']['typename'];
	$loc = DOGMA_LOC_Ship;
	$attributes = get_attributes($typeid, $getatt = function($aname) use(&$fit) {
			return \Osmium\Dogma\get_ship_attribute($fit, $aname);
		});
} else if($_POST['type'] == 'drone' && isset($_POST['typeid']) && isset($fit['drones'][$_POST['typeid']])) {
	$typeid = $_POST['typeid'];
	$typename = $fit['drones'][$typeid]['typename'];
	$loc = [ DOGMA_LOC_Drone, 'drone_typeid' => (int)$typeid ];

	if($temptransfer = ($fit['drones'][$typeid]['quantityinspace'] == 0)) {
		/* libdogma only knows about drones in space */
		\Osmium\Fit\transfer_drone($fit, $typeid, 'bay', 1);
	}

	$attributes = get_attributes($typeid, $getatt = function($aname) use(&$fit, $typeid) {
			return \Osmium\Dogma\get_drone_attribute($fit, $typeid, $aname);
		});

	if($temptransfer) {
		dogma_get_affectors($fit['__dogma_context'], $loc, $affectors);
		\Osmium\Fit\transfer_drone($fit, $typeid, 'space', 1);
	}
} else if(($_POST['type'] === 'implant' || $_POST['type'] === 'booster')
          && isset($_POST['typeid'])
          && isset($fit['implants'][$_POST['typeid']])) {
	$typeid = $_POST['typeid'];
	$typename = $fit['implants'][$typeid]['typename'];
	$loc = [ DOGMA_LOC_Implant, 'implant_index' => $fit['implants'][$typeid]['dogma_index'] ];
	$attributes = get_attributes($typeid, $getatt = function($aname) use(&$fit, $typeid) {
		return \Osmium\Dogma\get_implant_attribute($fit, $typeid, $aname);
	});
} else if($_POST['type'] === 'generic') {
	$typeid = (int)$_POST['typeid'];
	$typename = \Osmium\Fit\get_typename($typeid);
	$attributes = get_attributes($typeid, null);
	$affectors = false;
}

else {
	header('HTTP/1.1 400 Bad Request', true, 400);
	\Osmium\Chrome\return_json(array());
}



$fresult = array(
	'header' => "<img src='//image.eveonline.com/Type/".$typeid."_64.png' alt='' /> "
	.\Osmium\Chrome\escape($typename),
);

if(!isset($affectors)) {
	dogma_get_affectors($fit['__dogma_context'], $loc, $affectors);
}

if($affectors !== false) {
	$affectors_per_type = array();
	$affectors_per_att = array();
	$numaffectors = 0;



	foreach($affectors as $affector) {
		if(!isset($attributes[$affector['destid']])) {
			if(\Osmium\Fit\get_categoryid($affector['id']) == \Osmium\Fit\CATEGORY_Skill) {
				/* XXX: some are relevant (thermodynamics for example)
				 * but hand-filtering them is a pain */
				continue;
			}

			$val = $getatt($affector['destid']);

			if($affector['operator'] === '*' && abs($val) < 1e-300) {
				/* Likely irrelevant since it won't change the final
				 * value */
				continue;
			}

			$attributes[$affector['destid']] = [
				\Osmium\Fit\get_attributedisplayname($affector['destid']),
				\Osmium\Chrome\format_number_with_unit(
					$val,
					$uid = \Osmium\Fit\get_unitid($affector['destid']),
					\Osmium\Fit\get_unitdisplayname($uid)
				),
				0
			];
		}

		$dest = $attributes[$affector['destid']][0];
		$source = \Osmium\Fit\get_typename($affector['id']);
		$fval = $affector['value'];

		switch($affector['operator']) {

		case '*':
			if(abs($affector['value'] - 1.0) < 1e-300) continue 2;
			$affector['operator'] = '×';
			$fval = "<span title='".sprintf("%.14f", $fval)."'>".\Osmium\Chrome\format($fval, 3).'</span>';
			break;

		case '-':
			$fval = -$fval;

		case '+':
			if(abs($affector['value']) < 1e-300) continue 2;

		case '=':
			$fval = \Osmium\Chrome\format($fval, -1);
			break;

		}

		if($affector['flags'] > 0) {
			$flags = array();
			if($affector['flags'] & DOGMA_AFFECTOR_PENALIZED) {
				$flags[] = 'penalized';
			}
			if($affector['flags'] & DOGMA_AFFECTOR_SINGLETON) {
				$flags[] = 'singleton';
			}

			$fval .= ' <small>('.implode(', ', $flags).')</small>';
		}

		$a = [ $affector, $dest, $source, $fval ];
		$affectors_per_type[$affector['id']][] = $a;
		$affectors_per_att[$affector['destid']][] = $a;
		++$numaffectors;
	}



	uasort($affectors_per_type, function($a, $b) { return strcmp($a[0][2], $b[0][2]); });
	uasort($affectors_per_att, function($a, $b) { return strcmp($a[0][1], $b[0][1]); });



	$fresult['affectors_per_type'] = "<ul>\n";
	foreach($affectors_per_type as $a_typeid => &$a) {
		$typename = \Osmium\Chrome\escape($a[0][2]);
		$fresult['affectors_per_type'] .= "<li><img src='//image.eveonline.com/Type/"
			.$a_typeid."_64.png' alt='' /> ".$typename.":\n";
		$fresult['affectors_per_type'] .= "<ul>\n";

		usort($a, function($x, $y) { return strcmp($x[1], $y[1]); });

		foreach($a as $val) {
			list($aff, $dest, $source, $fval) = $val;
			$op = $aff['operator'];

			$fresult['affectors_per_type'] .= "<li><label>".htmlspecialchars($dest)."</label> {$op}{$fval}</li>\n";
		}
		$fresult['affectors_per_type'] .= "</ul>\n</li>\n";
	}
	$fresult['affectors_per_type'] .= "</ul>\n";



	if($numaffectors > 0) {
		$fresult['affectors_per_att'] = "<p><em>The operations are ordered by precedence (lower operations get applied last).</em></p>\n";
	} else {
		$fresult['affectors_per_att'] = '';
	}

	$fresult['affectors_per_att'] .= "<ul>\n";
	foreach($affectors_per_att as $attid => &$a) {
		$attname = \Osmium\Chrome\escape($a[0][1]);
		$fresult['affectors_per_att'] .= "<li>".$attname.":\n";
		$fresult['affectors_per_att'] .= "<ul>\n";

		usort($a, function($x, $y) { return $x[0]['order'] - $y[0]['order']; });

		foreach($a as $val) {
			list($aff, $dest, $source, $fval) = $val;
			$op = $aff['operator'];

			$fresult['affectors_per_att'] .= "<li><label><img src='//image.eveonline.com/Type/".$aff['id']
				."_64.png' alt='' /> ".\Osmium\Chrome\escape($source)."</label> {$op}{$fval}</li>\n";
		}
		$fresult['affectors_per_att'] .= "</ul>\n</li>\n";
	}
	$fresult['affectors_per_att'] .= "</ul>\n";



	if($affectors_per_type === array()) {
		$fresult['affectors_per_type'] .= "<p class='placeholder'>No affectors</p>\n";
		$fresult['affectors_per_att'] .= "<p class='placeholder'>No affectors</p>\n";
	}
}



$fresult['attributes'] = "<table class='d'>\n<tbody>\n";
$previouscatid = null;
foreach($attributes as $a) {
	list($dname, $value, $catid) = $a;
	if($previouscatid !== $catid) {
		if($previouscatid !== null) {
			$class = " class='sep'";
		} else $class = '';
		$previouscatid = $catid;
	} else $class = '';

	$fresult['attributes'] .= "<tr$class><td><strong>"
		.\Osmium\Chrome\escape($dname)
		."</strong></td><td>".$value."</td></tr>\n";
}
$fresult['attributes'] .= "</tbody>\n</table>\n";





$variations = array();
$fvariations = array();
$variationsq = \Osmium\Db\query_params(
	'SELECT t.typeid, metagroupid, mg.value::integer as metalevel
	FROM (
	SELECT typeid FROM eve.invmetatypes WHERE parenttypeid IN ($1,
	( SELECT parenttypeid FROM eve.invmetatypes WHERE typeid = $1 )
	)
	UNION
	SELECT parenttypeid FROM eve.invmetatypes WHERE typeid = $1
	UNION
	SELECT $1
	) t
	LEFT JOIN eve.invmetatypes ON invmetatypes.typeid = t.typeid
	LEFT JOIN eve.dgmtypeattribs mg ON mg.attributeid = 633 AND mg.typeid = t.typeid
	ORDER BY metalevel DESC, t.typeid ASC',
	array($typeid)
);
while($r = \Osmium\Db\fetch_assoc($variationsq)) {
	$variations[$r['metagroupid']][] = [ (int)$r['typeid'], (int)$r['metalevel'] ];
}
usort($variations, function($x, $y) {
	return $x[0][1] - $y[0][1];
});
foreach($variations as $a) {
	usort($a, function($x, $y) {
		return $x[1] - $y[1];
	});
	$fvariations = array_merge($fvariations, $a);
}





list($desc) = \Osmium\Db\fetch_row(
	\Osmium\Db\query_params(
		'SELECT description FROM eve.invtypes WHERE typeid = $1', 
		array($typeid)
	)
);

$desc = \Osmium\Chrome\trim($desc);
if($desc === '') {
	$desc = '<p class="placeholder">This type has no description.</p>';
} else {
	$desc = \Osmium\Chrome\sanitize_html_trust(\Osmium\Chrome\format_md(nl2br($desc)));
}

$suffix = number_format(microtime(true), 6, '', '');

$lis = array(
	"<li><a href='#sidesc-{$suffix}'>Description</a></li>",
	"<li><a href='#siattributes-{$suffix}'>Attributes</a></li>\n",
);
$sections = array(
	"<section class='sidesc' id='sidesc-{$suffix}'>".$desc."</section>\n",
	"<section class='siattributes' id='siattributes-{$suffix}'>\n".$fresult['attributes']."</section>\n",
);

if($affectors !== false) {
	$lis[] = "<li><a href='#siafftype-{$suffix}'>Affectors by type (".count($affectors_per_type).")</a></li>\n";
	$lis[] = "<li><a href='#siaffatt-{$suffix}'>Affectors by attribute (".count($affectors_per_att).")</a></li>\n";

	$sections[] = "<section class='siaff' id='siafftype-{$suffix}'>\n".$fresult['affectors_per_type']."</section>\n";
	$sections[] = "<section class='siaff' id='siaffatt-{$suffix}'>\n".$fresult['affectors_per_att']."</section>\n";
}

if(count($fvariations) > 1) {
	$lis[] = "<li><a href='#sivariations-{$suffix}'>Variations (".count($fvariations).")</a></li>\n";
	$sections[] = "<section class='sivariations' id='sivariations-{$suffix}'>\n<ul class='sivariations'></ul>\n</section>\n";
} else {
	$fvariations = array();
}

\Osmium\Chrome\return_json(array(
	'modal' => "<header class='hsi'><h2>".$fresult['header']."</h2></header>\n"
	."<ul class='showinfotabs'>\n".implode("\n", $lis)."</ul>\n".implode("\n", $sections),
	'variations' => $fvariations,
));
