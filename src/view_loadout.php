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

namespace Osmium\ViewLoadout;

require __DIR__.'/../inc/root.php';
require __DIR__.'/../inc/ajax_common.php';

/* XXX: there is probably a way to slap a Last-Modified: header on
 * this to save some server resources */



/* Check permissions, password, private tokens. Populates the $fit,
 * $revision, $lodaoutid, $forkuri, $historyuri, $exporturi and
 * $revision_overridden variables. Defines the RELATIVE constant. */
require __DIR__.'/../inc/view_loadout-access.php';



$loggedin = \Osmium\State\is_logged_in();
$a = \Osmium\State\get_state('a', array());

if($loadoutid !== false) {
	$author = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT accountid, nickname, apiverified, characterid, charactername,
		corporationid, corporationname, allianceid, alliancename,
		ismoderator, reputation
		FROM osmium.accounts WHERE accountid = $1',
		array($fit['metadata']['accountid'])
	));

	$lastrev = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT updatedate, accountid, nickname, apiverified,
		characterid, charactername, ismoderator, reputation
		FROM osmium.loadouthistory
		JOIN osmium.accounts ON accounts.accountid = loadouthistory.updatedbyaccountid
		WHERE loadoutid = $1 AND revision = $2',
		array($loadoutid, $fit['metadata']['revision'])
	));

	list($truecreationdate, $commentsallowed, $votetype, $totalvotes, $totalupvotes, $totaldownvotes) = 
		\Osmium\Db\fetch_row(
			\Osmium\Db\query_params(
				'SELECT updatedate, allowcomments, v.type, ludv.votes, ludv.upvotes, ludv.downvotes
				FROM osmium.loadouts AS l
				JOIN osmium.loadouthistory AS lh ON (l.loadoutid = lh.loadoutid AND lh.revision = 1)
				LEFT JOIN osmium.votes AS v ON (v.type IN ($2, $3) AND v.targetid1 = l.loadoutid 
					AND v.targetid2 IS NULL AND v.targetid3 IS NULL 
					AND v.fromaccountid = $4 AND v.targettype = $5)
				JOIN osmium.loadoutupdownvotes AS ludv ON ludv.loadoutid = l.loadoutid
				WHERE l.loadoutid = $1',
				array(
					$loadoutid,
					\Osmium\Reputation\VOTE_TYPE_UP,
					\Osmium\Reputation\VOTE_TYPE_DOWN,
					$loggedin ? $a['accountid'] : 0,
					\Osmium\Reputation\VOTE_TARGET_TYPE_LOADOUT
				)
			)
		);

	$commentsallowed = ($commentsallowed === 't');
	$isflaggable = \Osmium\Flag\is_fit_flaggable($fit);
	$can_edit = \Osmium\State\can_edit_fit($loadoutid);
	$revision = $fit['metadata']['revision'];

	\Osmium\State\set_fit_green($fit['metadata']['loadoutid']);



	/* Insert the comment/reply if there is one. Jump to the correct
	 * page if a ?jtc URI is present. Requires $commentsallowed,
	 * $loggedin, $loadoutid, $a and $author to be set. Sets the
	 * $commentperpage variable. */
	require __DIR__.'/../inc/view_loadout-comments.php';
} else {
	/* Thin loadout mode */
	$commentsallowed = false;
	$isflaggable = false;
	$can_edit = false;
}

$ismoderator = $loggedin && isset($a['ismoderator']) && ($a['ismoderator'] === 't');
$canedit = ($loadoutid !== false) && \Osmium\State\can_edit_fit($loadoutid);
$modprefix = $ismoderator ? '<span title="Moderator action">'.\Osmium\Flag\MODERATOR_SYMBOL.'</span> ' : '';



/* Be compatible with old-style ?pid=X-Y&dpid=Z URIs. */
if(isset($_GET['pid'])) {
	$ids = explode('-', $_GET['pid'], 2);
	if(!isset($_GET['preset'])) {
		$_GET['preset'] = $ids[0];
	}
	if(isset($ids[1]) && !isset($_GET['chargepreset'])) {
		$_GET['chargepreset'] = $ids[1];
	}
}
if(isset($_GET['dpid']) && !isset($_GET['dronepreset'])) {
	$_GET['dronepreset'] = $_GET['dpid'];
}

$preset_overridden = false;
foreach(array('', 'charge', 'drone') as $ptype) {
	if(isset($_GET[$ptype.'preset']) && $_GET[$ptype.'preset'] !== '') {
		$p = intval($_GET[$ptype.'preset']);
		if(!isset($fit[$ptype.'presets'][$p])) {
			\Osmium\Fatal(404, "Invalid ".$ptype." preset");
		}
		call_user_func_array(
			'Osmium\Fit\use_'.$ptype.($ptype ? '_' : '').'preset',
			array(&$fit, $p)
		);
		$preset_overridden = true;
	}
}

if(count($fit['metadata']['tags']) > 0) {
	$tags = ' ('.implode(', ', $fit['metadata']['tags']).')';
} else {
	$tags = '';
}

$title = \Osmium\Chrome\escape($fit['metadata']['name'].$tags);

if(isset($fit['ship']['typename'])) {
	$title .= \Osmium\Chrome\escape(' / '.$fit['ship']['typename'].' fitting');
}
if($revision_overridden) {
	$title .= ' (R'.$revision.')';
}

\Osmium\Chrome\print_header(
	$title, RELATIVE,
	($fit['metadata']['visibility'] == \Osmium\Fit\VISIBILITY_PUBLIC
	 && !$revision_overridden
	 && !$preset_overridden),
	"<link rel='canonical' href='".\Osmium\Chrome\escape($canonicaluri)."' />"
);

echo "<h1 id='vltitle'>Viewing loadout: <strong class='fitname'>"
.\Osmium\Chrome\escape($fit['metadata']['name'])."</strong>";

$canretag = ($revision_overridden === false)
	&& isset($a['accountid']) && isset($author['accountid'])
	&& ($a['accountid'] == $author['accountid'] || (
		\Osmium\Reputation\is_fit_public($fit) && \Osmium\Reputation\has_privilege(
			\Osmium\Reputation\PRIVILEGE_RETAG_LOADOUTS
		)
	));

if(count($fit['metadata']['tags']) > 0 || $canretag) {
	echo "\n<ul class='tags'>\n";
	foreach($fit['metadata']['tags'] as $tag) {
		echo "<li><a href='".RELATIVE."/search?q=".urlencode('@tags '.$tag)."'>$tag</a></li>\n";
	}
	if($canretag) {
		echo "<li class='retag'><a><small>✎ Edit tags</small></a></li>";
	}
	echo "</ul>\n";
}
echo "</h1>\n";

if(isset($fit['ship']['typeid'])) {
	list($groupname) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT groupname FROM eve.invtypes
		JOIN eve.invgroups ON invtypes.groupid = invgroups.groupid
		WHERE typeid = $1',
		array($fit['ship']['typeid'])
	));
} else {
	$groupname = '';
}


list($commentcount) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
	'SELECT COUNT(commentid) FROM osmium.loadoutcomments
	WHERE loadoutid = $1 AND revision <= $2',
	array(
		(int)$loadoutid,
		$revision,
	)
));
$commentcount = (int)$commentcount;

echo "<div id='vlattribs'>
<section id='ship' data-loadoutid='".(int)$loadoutid."'>
<h1>\n";

if(isset($fit['ship']['typeid'])) {
	echo "<img src='//image.eveonline.com/Render/".$fit['ship']['typeid']."_256.png' alt='' />\n";
	echo "<small class='groupname'>".\Osmium\Chrome\escape($groupname)."</small>\n";
	echo "<strong><span class='name'>".\Osmium\Chrome\escape($fit['ship']['typename'])."</span></strong>\n";
} else {
	echo "<div class='notype'></div>\n";
	echo "<small class='groupname'></small>\n";
	echo "<strong>N/A</strong>\n";
}

echo "<small class='dbver'>".\Osmium\Chrome\escape(\Osmium\Fit\get_closest_version_by_build($fit['metadata']['evebuildnumber'])['name'])."</small>
</h1>\n";

if($loadoutid === false) {
	$votesclass = ' dummy';
	$totalupvotes = 0;
	$totalvotes = 0;
	$totaldownvotes = 0;
	$votetype = null;
} else {
	$votesclass = '';
}

echo "<div class='votes{$votesclass}' data-targettype='loadout'>\n";
echo "<a title='This loadout is creative, useful, and fills the role it was designed for' class='upvote"
.($votetype == \Osmium\Reputation\VOTE_TYPE_UP ? ' voted' : '')
."'><img src='".RELATIVE."/static-".\Osmium\STATICVER
."/icons/vote.svg' alt='upvote' /></a>\n";
echo "<strong title='".$totalupvotes." upvote(s), "
.$totaldownvotes." downvote(s)'>".$totalvotes."</strong>\n";
echo "<a title='This loadout suffers from severe flaws, is badly formatted, or shows no research effort'"
." class='downvote".($votetype == \Osmium\Reputation\VOTE_TYPE_DOWN ? ' voted' : '')
."'><img src='".RELATIVE."/static-".\Osmium\STATICVER
."/icons/vote.svg' alt='downvote' /></a>\n";
echo "</div>\n";

echo "</section>\n";

if($loadoutid !== false) {
	$class = $fit['metadata']['revision'] > 1 ? 'edited' : 'notedited';
	echo "<section id='credits' class='$class'>\n";

	echo "<div class='author'>\n";
	if($author['apiverified'] === 't') {
		if($author['allianceid'] > 0) {
			echo "<img class='alliance' src='//image.eveonline.com/Alliance/".$author['allianceid']."_128.png' alt='' title='member of ".\Osmium\Chrome\escape($author['alliancename'])."' />";
		} else {
			echo "<img class='corporation' src='//image.eveonline.com/Corporation/".$author['corporationid']."_256.png' alt='' title='member of ".\Osmium\Chrome\escape($author['corporationname'])."' />";
		}
		if($author['characterid'] > 0) {
			echo "<img class='portrait' src='//image.eveonline.com/Character/".$author['characterid']."_256.jpg' alt='' />";
		}
	}
	echo "<small>submitted by</small><br />\n";
	echo \Osmium\Chrome\format_character_name($author, RELATIVE, $rauthorname)."<br />\n";
	echo \Osmium\Chrome\format_reputation($author['reputation']).' – '
		.\Osmium\Chrome\format_relative_date($truecreationdate)."\n";
	echo "</div>\n";

	if($fit['metadata']['revision'] > 1) {
		echo "<div class='author edit'>\n";
		echo "<small>revision #".$fit['metadata']['revision']." edited by</small><br />\n";
		echo \Osmium\Chrome\format_character_name($lastrev, RELATIVE)."<br />\n";
		echo \Osmium\Chrome\format_reputation($lastrev['reputation']).' – '
			.\Osmium\Chrome\format_relative_date($lastrev['updatedate'])."\n";
		echo "</div>\n";
	}

	echo "</section>";
}

$capacitors = \Osmium\Fit\get_all_capacitors($fit);
$ia_ = \Osmium\Fit\get_interesting_attributes($fit);
echo "<section id='attributes'>
<div class='compact' id='computed_attributes'>\n";
\Osmium\Chrome\print_formatted_loadout_attributes(
	$fit, RELATIVE, [
		'cap' => $capacitors['local'],
		'ia' => $ia_,
	]
);
echo "</div>
</section>
</div>\n";



echo "<div id='vlmain'>\n";

if($revision_overridden && isset($lastrev['updatedate'])) {
	echo "<p class='notice_box'>You are viewing revision {$revision} of this loadout, as it was published the ".date('Y-m-d \a\t H:i', $lastrev['updatedate']).". <a href='".RELATIVE."/".\Osmium\Fit\get_fit_uri($loadoutid, $fit['metadata']['visibility'], $fit['metadata']['privatetoken'])."'>Link to the latest revision.</a></p>";
}

echo "<ul class='tabs'>
<li><a href='#loadout'>Loadout</a></li>
<li><a href='#presets'>Presets (".(max(count($fit['presets']), count($fit['chargepresets']), count($fit['dronepresets']))).")</a></li>
<li><a href='#remote'>Remote (".
((isset($fit['fleet']) ? count($fit['fleet']) : 0) + (isset($fit['remote']) ? count($fit['remote']) : 0))
.")</a></li>
<li><a href='#comments'>Comments (".$commentcount.")</a></li>
<li><a href='#meta'>Meta</a></li>
<li><a href='#export'>Export</a></li>\n";

if($maxrev !== false && $historyuri !== false) {
	echo "<li class='external'><a href='".$historyuri."' title='View the different revisions of the loadout and compare the changes that were made'>History (".($maxrev - 1).")</a></li>\n";
}

echo "<li class='external'><a rel='nofollow' href='".$forkuri."' title='Make a private copy of this loadout and edit it immediately'>Fork</a></li>\n";

if($can_edit) {
	echo "<li class='external'><a href='".RELATIVE."/edit/".$loadoutid."?tok=".\Osmium\State\get_token()."&amp;revision=".$fit['metadata']['revision']."' rel='nofollow'>Edit</a></li>\n";
}

echo "</ul>\n";



echo "<section id='loadout'>
<section id='modules'>\n";
$stypes = \Osmium\Fit\get_slottypes();
$slotusage = \Osmium\AjaxCommon\get_slot_usage($fit);
$states = \Osmium\Fit\get_state_names();
$ia = array();
foreach($ia_ as $k) {
	if($k['location'][0] === 'module') {
		$ia['module'][$k['location'][1]][$k['location'][2]] = $k;
	} else if($k['location'][0] === 'drone') {
		$ia['drone'][$k['location'][1]] = $k;
	}
}
$fittedtotal = 0;
foreach($stypes as $type => $tdata) {
	if($slotusage[$type] == 0 && (
		!isset($fit['modules'][$type]) || count($fit['modules'][$type]) === 0
	)) continue;

	$sub = isset($fit['modules'][$type]) ? $fit['modules'][$type] : array();

	if($type === "high" || $type === "medium") {
		$groupstatus = "grouped";
		$groupedcharges = "<small class='groupcharges'>Charges are grouped</small>";
	} else {
		$groupstatus = "ungrouped";
		$groupedcharges = "";
	}

	echo "<div class='slots $type $groupstatus'>\n<h3>";
	echo \Osmium\Chrome\escape($tdata[0])." <span>$groupedcharges";
	$u = count($sub);
	$overflow = $u > $slotusage[$type] ? ' overflow' : '';
	echo "<small class='counts$overflow'>".$u." / ".$slotusage[$type]."</small></span>";
	echo "</h3>\n<ul>\n";

	$fittedtype = 0;
	foreach($sub as $index => $m) {
		$s = $states[$m['state']];
		if(isset($fit['charges'][$type][$index])) {
			$c = $fit['charges'][$type][$index];
		} else $c = null;

		$class = [];
		if(isset($ia['module'][$type][$index])) {
			$class[] = 'hasattribs';
		}
		if($fittedtype >= $slotusage[$type]) {
			$class[] = 'overflow';
		}

		if($class) $class = " class='".implode(' ', $class)."'";
		else $class = '';

		echo "<li{$class} data-typeid='".$m['typeid']."' data-slottype='"
			.$type."' data-index='".$index."' data-state='".$s[2]."' data-chargetypeid='"
			.($c === null ? 'null' : $c['typeid'])."'>\n";
		echo "<img src='//image.eveonline.com/Type/".$m['typeid']."_64.png' alt='' />";
		echo "<span class='name'>".\Osmium\Chrome\escape($m['typename'])."</span>\n";

		if($c !== null) {
			dogma_get_number_of_module_cycles_before_reload(
				$fit['__dogma_context'], $m['dogma_index'], $ncycles
			);

			echo "<span class='charge".($ncycles !== -1 ? ' hasncycles' : '')."'>,<br />";
			echo "<img src='//image.eveonline.com/Type/".$c['typeid']."_64.png' alt='' />";
			echo "<span class='name'>".\Osmium\Chrome\escape($c['typename'])."</span>";
			if($ncycles !== -1) {
				echo "<span class='ncycles' title='Number of module cycles before having to reload'>"
					.$ncycles."</span>";
			}
			echo "</span>\n";
		}

		if($tdata[2]) {
			echo "<a class='toggle_state' title='".$s[0]."'>"
				.\Osmium\Chrome\sprite(RELATIVE, $s[2], $s[1][0], $s[1][1], $s[1][2], $s[1][3], 16)
				."</a>\n";
		}

		if(isset($ia['module'][$type][$index]) && isset($ia['module'][$type][$index]['fshort'])) {
			echo "<small class='attribs' title='".\Osmium\Chrome\escape(
				isset($ia['module'][$type][$index]['flong'])
				? $ia['module'][$type][$index]['flong'] : ''
			)."'>".\Osmium\Chrome\escape(
				$ia['module'][$type][$index]['fshort']
			)."</small>\n";
		}

		echo "</li>\n";
		++$fittedtotal;
		++$fittedtype;
	}

	for($i = count($sub); $i < $slotusage[$type]; ++$i) {
		echo "<li class='placeholder'>"
			.\Osmium\Chrome\sprite(RELATIVE, '', $tdata[1][0], $tdata[1][1], $tdata[1][2], $tdata[1][3], 32)
			."Unused ".$type." slot</li>\n";
		++$fittedtotal;
	}

	echo "</ul>\n</div>\n";
}
if($fittedtotal === 0) {
	echo "<p class='placeholder'>No available slots.</p>";
}

echo "</section>
<section id='drones'>\n";

$dronesin['space'] = 0;
$dronesin['bay'] = 0;
foreach($fit['drones'] as $typeid => $d) {
	if(isset($d['quantityinspace'])) { $dronesin['space'] += $d['quantityinspace']; }
	if(isset($d['quantityinbay'])) { $dronesin['bay'] += $d['quantityinbay']; }
}
$dbw = \Osmium\Dogma\get_ship_attribute($fit, 'droneBandwidth');
foreach(array('space' => 'Drones in space', 'bay' => 'Drones in bay') as $k => $v) {
	if($dbw == 0 && $dronesin['space'] === 0 && $dronesin['bay'] === 0) continue;

	echo "<div class='drones ".$k."'>\n";
	echo "<h3>".\Osmium\Chrome\escape($v)." <span>";

	if($k === 'space') {
		$dbw = \Osmium\Dogma\get_ship_attribute($fit, 'droneBandwidth');
		$dbwu = \Osmium\Fit\get_used_drone_bandwidth($fit);
		$mad = \Osmium\Dogma\get_char_attribute($fit, 'maxActiveDrones');
		$ad = $dronesin[$k];

		$overflow = ($ad > $mad) ? ' overflow' : '';
		echo "<small title='Maximum number of drones in space' class='maxdrones$overflow'>$ad / $mad</small>";
		echo "<small> — </small>";
		$overflow = ($dbwu > $dbw) ? ' overflow' : '';
		echo "<small title='Drone bandwidth usage' class='bandwidth$overflow'>$dbwu / $dbw Mbps</small>";
	} else if($k === 'bay') {
		$dcap = \Osmium\Dogma\get_ship_attribute($fit, 'droneCapacity');
		$dcapu = \Osmium\Fit\get_used_drone_capacity($fit);

		$overflow = ($dcapu > $dcap) ? ' overflow' : '';
		echo "<small title='Drone bay usage' class='bayusage$overflow'>$dcapu / $dcap m³</small>";
	}

	echo "</span></h3>\n<ul>\n";

	foreach($fit['drones'] as $typeid => $d) {
		if(!isset($d['quantityin'.$k])) continue;
		$qty = (int)$d['quantityin'.$k];
		if($qty === 0) continue;

		if($k === 'space' && isset($ia['drone'][$typeid]['fshort'])) {
			$class = " class='hasattribs'";
			$attribs = "<small class='attribs' title='".\Osmium\Chrome\escape(
				isset($ia['drone'][$typeid]['flong'])
				? $ia['drone'][$typeid]['flong'] : ''
			)."'>".\Osmium\Chrome\escape(
				$ia['drone'][$typeid]['fshort']
			)."</small>\n";
		} else $class = $attribs = '';

		echo "<li{$class} data-typeid='".$typeid."' data-location='".$k."' data-quantity='".$qty."'>";
		echo "<img src='//image.eveonline.com/Type/".$typeid."_64.png' alt='' />";
		echo "<strong class='qty'>".$qty."×</strong><span class='name'>".$d['typename']."</span>";
		echo $attribs;
		echo "</li>\n";
	}

	if($dronesin[$k] === 0) {
		echo "<li class='placeholder'>"
			.\Osmium\Chrome\sprite(RELATIVE, '', 0, 13, 64, 64, 32)
			."No drones in ".$k."</li>\n";
	}

	echo "</ul>\n</div>\n";
}

echo "</section>
<section id='implants'>\n";

$implants = array();
$boosters = array();

foreach($fit['implants'] as $i) {
	if(\Osmium\Fit\get_groupid($i['typeid']) == \Osmium\Fit\GROUP_Booster) {
		$boosters[] = $i;
	} else {
		$implants[] = $i;
	}
}

$slotcmp = function($x, $y) { return $x['slot'] - $y['slot']; };
usort($implants, $slotcmp);
usort($boosters, $slotcmp);

foreach(array('implants' => $implants, 'boosters' => $boosters) as $k => $imps) {
	if($imps === array()) continue;

	echo "<div class='".$k."'>\n<h3>".ucfirst($k)."</h3>\n<ul>\n";

	foreach($imps as $i) {
		echo "<li><img src='//image.eveonline.com/Type/".$i['typeid']."_64.png' alt='' />"
			."<span class='name'>".\Osmium\Chrome\escape($i['typename'])."</span>"
			.'<span class="slot">, '.substr($k, 0, -1).' slot '.$i['slot'].'</span>'
			."</li>\n";
	}

	echo "</ul>\n</div>\n";
}

echo "</section>
<section id='description'>
<h3>Fitting description</h3>\n";

$desc = \Osmium\Chrome\trim($fit['metadata']['description']);
if(empty($desc)) {
	echo "<p class='placeholder'>No description given.</p>\n";
} else {
	/* XXX: potential resource hog */
	echo "<div>\n".\Osmium\Chrome\format_sanitize_md($desc)."\n</div>\n";
}
echo "</section>\n";



echo "</section>
<section id='presets'>\n";

\Osmium\Forms\print_form_begin();
$first = true;
foreach([ ['presets', 'modulepreset', 'Preset', 'spreset'],
          ['chargepresets', 'chargepreset', 'Charge preset', 'scpreset'],
          ['dronepresets', 'dronepreset', 'Drone preset', 'sdpreset'] ] as $ptype) {
	list($parraykey, $pkey, $fname, $name) = $ptype;

	if($first) {
		$first = false;
	} else {
		\Osmium\Forms\print_separator();
	}

	$presets = array(); /* FIXME: use array_column later */
	foreach($fit[$parraykey] as $id => $p) {
		$presets[$id] = $p['name'];
	}
	$_POST[$name] = $fit[$pkey.'id'];
	\Osmium\Forms\print_select($fname, $name, $presets, null, null, \Osmium\Forms\FIELD_REMEMBER_VALUE);

	$desc = \Osmium\Chrome\trim($fit[$pkey.'desc']);
	if(empty($desc)) {
		$desc = '<p class="placeholder">No description available.</p>';
	} else {
		$desc = \Osmium\Chrome\format_sanitize_md($desc);
	}
	\Osmium\Forms\print_generic_row($name.'desc', '<label>Description</label>',
	                                "<div class='pdesc'>\n".$desc."\n</div>");
}

\Osmium\Forms\print_form_end();



echo "</section>
<section id='remote'>\n";

echo "<section id='fleet'>\n<h2>Fleet</h2>\n";
echo "<form>\n<table>\n<tbody>\n";

foreach(array('fleet', 'wing', 'squad') as $ft) {
	if(isset($fit['fleet'][$ft])) {
		$fl = \Osmium\Chrome\escape($fit['fleet'][$ft]['__id']);
		$showlink = ($fl !== "(empty fitting)");
		$checked = " checked='checked'";
	} else {
		$fl = '';
		$showlink = false;
		$checked = '';
	}

	echo "<tr data-type='{$ft}' id='{$ft}booster' class='booster'>\n";
	echo "<td rowspan='".($showlink ? 3 : 2)."'><input type='checkbox' id='{$ft}_enabled' name='{$ft}_enabled' class='{$ft} enabled'{$checked} />";
	echo " <label for='{$ft}_enabled'><strong>".ucfirst($ft)." booster</strong></label></td>\n";
	echo "<td><label for='{$ft}_skillset'>Use skills: </label></td>\n";
	echo "<td><select disabled='disabled' name='{$ft}_skillset' id='{$ft}_skillset' class='skillset {$ft}'><option value='All V'>All V</option></select></td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td".($showlink ? " rowspan='2'" : '')."><label for='{$ft}_fit'>Use fitting: </label></td>\n";
	echo "<td><input readonly='readonly' type='text' name='{$ft}_fit' id='{$ft}_fit' class='fit {$ft}' value='{$fl}' placeholder='(empty fitting)' /></td>\n";
	echo "</tr>\n";

	if($showlink) {
		$cur = explode('?', $_SERVER['REQUEST_URI'], 2)[0].'/booster/'.$ft;
		echo "<tr>\n<td>";
		echo "<a href='".\Osmium\Chrome\escape($cur)."'>View fitting</a>";
		echo "</td></tr>\n";
	}
}

echo "</tbody>\n</table>\n</form>\n</section>\n";

echo "<section id='projected'>
<h2>Projected effects
<form>
<input type='button' value='Toggle fullscreen' id='projectedfstoggle' />
</form>
</h2>
<p id='rearrange'>
Rearrange loadouts: <a id='rearrange-grid'>grid</a>,
<a id='rearrange-circle'>circle</a>
</p>
<form id='projected-list'>
<p class='placeholder'>Loading remote fittings…<br />
<span class='spinner'></span></p>
</form>
</section>\n";


echo "</section>
<section id='comments'>\n";

/* Prints paginated comments and the "add comment" form. */
require __DIR__.'/../inc/view_loadout-commentview.php';



echo "</section>
<section id='meta'>\n";

/* Pretty prints permissions, show actions and moderator actions. */
require __DIR__.'/../inc/view_loadout-meta.php';



$dna = \Osmium\Fit\export_to_dna($fit);
echo "</section>
<section id='export'>\n";

if(!isset($fit['ship']['typeid'])) {
	echo "<p class='warning_box'>You are exporting an incomplete loadout. Be careful, other programs may not accept to import such loadouts.</p>\n";
}

echo "<h2>Lossless formats (recommended)</h2>
<ul>
<li><a href='".$exporturi('clf', 'json')."' type='application/json' rel='nofollow'><strong>Export to CLF (Common Loadout Format)</strong></a>: recommended for archival and for usage with other programs supporting it.</li>
<li><a href='".$exporturi('clf', 'json', false, ['minify' => 1])."' type='application/json' rel='nofollow'>Export to minified CLF</a>: same as above, minus all the redundant information. Not readable by humans.</li>
<li><a href='".$exporturi('md', 'txt')."' type='text/plain' rel='nofollow'>Export to Markdown+gzCLF</a>: a Markdown-formatted description of the loadout, with embedded CLF for programs.</li>
<li><a href='".$exporturi('evexml', 'xml', true)."' type='application/xml' rel='nofollow'><strong>Export to XML+gzCLF</strong></a>: recommended if you want to import loadouts from the game client.</li>
</ul>
<h2>Lossy formats</h2>
<p>Only use those formats if none of the lossless option is usable for your situation.</p>
<ul>
<li><a href='".$exporturi('evexml', 'xml', true, ['embedclf' => 0])."' type='application/xml' rel='nofollow'>Export to XML</a>: same as XML+gzCLF, minus the description.</li>
<li><a href='".$exporturi('eft', 'txt', true)."' type='text/plain' rel='nofollow'>Export to EFT</a>: the <em>de-facto</em> format used by the fitting tool EFT.</li>
<li><a href='".$exporturi('dna', 'txt', true)."' type='text/plain' rel='nofollow'>Export to DNA</a>: short format that can be understood by the game client.<br /><small><code>".$dna."</code></small></li>\n";

/* XXX: bleh. Browser sniffing is terrible, also this violates the CSP
 * policy. This will break if the IGB ever supports it one day. */
if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'EVE-IGB') !== false) {
	echo "<li><a onclick='CCPEVE.showFitting(\"{$dna}\");'>Export to in-game DNA</a>: use this link to open the loadout window from the in-game browser.</li>\n";
}

echo "</ul>\n</section>\n";

echo "</div>\n";

/* The phony CLF token is obviously not a valid token, and process_clf
 * will pick it up and create a new token on demand. So if the user
 * never interacts with the loadout, it never gets cached. */
\Osmium\Chrome\print_loadout_common_footer($fit, RELATIVE, '___demand___');

\Osmium\Chrome\add_js_data('clfslots', json_encode(\Osmium\AjaxCommon\get_slot_usage($fit)));

foreach($capacitors as &$c) {
	if(!isset($c['depletion_time'])) continue;
	$c['depletion_time'] = \Osmium\Chrome\format_duration($c['depletion_time'] / 1000);
}
\Osmium\Chrome\add_js_data('capacitors', json_encode($capacitors));
\Osmium\Chrome\add_js_data('ia', json_encode($ia_));

\Osmium\Chrome\print_js_snippet('view_loadout');
\Osmium\Chrome\print_js_snippet('view_loadout-presets');
\Osmium\Chrome\print_js_snippet('new_loadout-ship');
\Osmium\Chrome\print_js_snippet('new_loadout-modules');
\Osmium\Chrome\print_js_snippet('new_loadout-drones');
\Osmium\Chrome\print_js_snippet('new_loadout-implants');
\Osmium\Chrome\print_js_snippet('new_loadout-remote');
\Osmium\Chrome\print_footer();
