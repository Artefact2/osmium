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

namespace Osmium\Page\NewLoadout;

require __DIR__.'/../inc/root.php';
require __DIR__.'/../inc/ajax_common.php';

const RELATIVE = '..';

if(isset($_GET['import']) && $_GET['import'] === 'dna') {
    $dna = $_GET['dna'];
    $ckey = 'dnafit_'.$dna;

    $fit = \Osmium\State\get_cache_memory_fb($ckey, null);
    if($fit === null) {
	    $fit = \Osmium\Fit\try_parse_fit_from_shipdna($dna, 'New DNA-imported loadout', $errors);

	    if($fit === false) {
		    \Osmium\Fatal(400);
	    }

	    \Osmium\State\put_cache_memory_fb($ckey, $fit, 7200);
    }

    $tok = \Osmium\State\get_unique_loadout_token();
    \Osmium\State\put_loadout($tok, $fit);

    header('Location: ../'.$tok);
    die();
}

if(isset($_GET['edit']) && $_GET['edit'] && isset($_GET['loadoutid'])
   && \Osmium\State\is_logged_in() && $_GET['tok'] == \Osmium\State\get_token()) {
	$loadoutid = (int)$_GET['loadoutid'];
	$revision = isset($_GET['revision']) ? (int)$_GET['revision'] : null;

	if(!\Osmium\State\can_view_fit($loadoutid)) {
		\Osmium\Fatal(404);
	}
	if(!\Osmium\State\can_edit_fit($loadoutid)) {
		\Osmium\Fatal(403);
	}

	$fit = \Osmium\Fit\get_fit($loadoutid, $revision);

	if(!\Osmium\State\can_access_fit($fit)) {
		\Osmium\Fatal(403);
	}

	$tok = \Osmium\State\get_unique_loadout_token();
	\Osmium\State\put_loadout($tok, $fit);

	header('Location: ../new/'.$tok);
	die();
}

if(isset($_GET['fork']) && $_GET['fork'] && isset($_GET['loadoutid'])) {
	$loadoutid = (int)$_GET['loadoutid'];
	$revision = isset($_GET['revision']) ? (int)$_GET['revision'] : null;

	if(!\Osmium\State\can_view_fit($loadoutid)) {
		\Osmium\Fatal(404);
	}

	$fit = \Osmium\Fit\get_fit($loadoutid, $revision);

	if(!\Osmium\State\can_access_fit($fit)) {
		\Osmium\Fatal(403);
	}

	$fork = $fit; /* Since $fit is an array, this makes a copy */

	/* Make $fork look like a new loadout */
	unset($fork['metadata']['loadoutid']);
	unset($fork['metadata']['revision']);
	unset($fork['metadata']['accountid']);

	/* Make a few adjustments */
	$fork['metadata']['visibility'] = \Osmium\Fit\VISIBILITY_PRIVATE;
	if(preg_match(
		'%^(?<title>.+?) \(fork( (?<forknumber>[1-9][0-9]*))?\)$%D',
		$fork['metadata']['name'],
		$matches)
	) {
		$fork['metadata']['name'] = $matches['title'];
		$forknum = isset($matches['forknumber']) ? (int)$matches['forknumber'] : 1;
		$fork['metadata']['name'] .= ' (fork '.($forknum + 1).')';
	} else {
		$fork['metadata']['name'] .= ' (fork)';
	}

	if($fit['metadata']['visibility'] != \Osmium\Fit\VISIBILITY_PRIVATE) {
		$fork['metadata']['description'] = trim(
			"*This loadout is a fork of loadout [#".(int)$fit['metadata']['loadoutid']
			."](".\Osmium\get_ini_setting('relative_path').\Osmium\Fit\get_fit_uri(
				$fit['metadata']['loadoutid'],
				$fit['metadata']['visibility'],
				0 /* No need to risk showing the real private token here */
			)."R".(int)$fit['metadata']['revision'].") (revision "
			.(int)$fit['metadata']['revision'].").*\n\n"
			.$fit['metadata']['description']
		);
	}

	if(isset($_GET['remote'])) {
		$key = $_GET['remote'];

		if($key !== 'local' && !isset($fit['remote'][$key])) {
			\Osmium\fatal(404);
		}

		\Osmium\Fit\set_local($fork, $key);
		/* XXX refactor this */
		$fork['metadata']['description'] = trim(
			"*This loadout is a fork of remote loadout #".\Osmium\Chrome\escape($key)
			." of loadout [#".(int)$fit['metadata']['loadoutid']
			."](".\Osmium\get_ini_setting('relative_path').\Osmium\Fit\get_fit_uri(
				$fit['metadata']['loadoutid'],
				$fit['metadata']['visibility'],
				0
			)."R".(int)$fit['metadata']['revision']."/remote/".urlencode($key).") (revision "
			.(int)$fit['metadata']['revision'].").*\n\n"
			.$fit['metadata']['description']
		);
	}

	if(isset($_GET['fleet'])) {
		$t = \Osmium\Chrome\escape($_GET['fleet']);

		if(!isset($fit['fleet'][$t]) || !isset($fit['fleet'][$t]['ship']['typeid'])
		|| !$fit['fleet'][$t]['ship']['typeid']) {
			\Osmium\fatal(404);
		}

		$fork = $fit['fleet'][$t];
		/* XXX refactor this */
		$fork['metadata']['description'] = trim(
			"*This loadout is a fork of the {$t} booster of loadout [#".(int)$fit['metadata']['loadoutid']
			."](".\Osmium\get_ini_setting('relative_path').\Osmium\Fit\get_fit_uri(
				$fit['metadata']['loadoutid'],
				$fit['metadata']['visibility'],
				0
			)."R".(int)$fit['metadata']['revision']."/booster/{$t}) (revision "
			.(int)$fit['metadata']['revision'].").*\n\n"
			.$fit['metadata']['description']
		);
	}

	$tok = \Osmium\State\get_unique_loadout_token();
	\Osmium\State\put_loadout($tok, $fork);

	header('Location: ../new/'.$tok);
	die();
}

if(!isset($_GET['token'])) {
	$tok = \Osmium\State\get_unique_loadout_token();

	\Osmium\Fit\create($fit);
	\Osmium\State\put_loadout($tok, $fit);

	header('Location: ./new/'.$tok);
	die();
} else {
	$tok = $_GET['token'];
	$fit = \Osmium\State\get_loadout($tok);

	if(!is_array($fit)) {
		/* Invalid token? */
		header('Location: ../new');
		die();
	}
}

if(isset($fit['metadata']['loadoutid']) && $fit['metadata']['loadoutid'] > 0) {
	$basetitle = 'Editing loadout #'.$fit['metadata']['loadoutid'];
	$title = "Editing loadout <a href='../".\Osmium\Fit\get_fit_uri(
		$fit['metadata']['loadoutid'],
		$fit['metadata']['visibility'],
		$fit['metadata']['privatetoken']
	)."'>#".$fit['metadata']['loadoutid']."</a>";
} else {
	$title = $basetitle = 'Creating a new loadout';
}

\Osmium\Chrome\print_header(
	$basetitle, RELATIVE, false,
	"<link href='//cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/0.4.6/perfect-scrollbar.css' rel='stylesheet' type='text/css' />\n"
);

echo "<h1>".$title."</h1>\n";

echo "<div id='nlattribs'>
<section id='ship'></section>
<section id='control'>
<form method='GET' action='./".$tok."'>
<input type='button' name='reset_loadout' id='reset_loadout' value='Reset loadout' />\n";


echo "<input type='button' name='export_loadout' id='export_loadout' value='Export loadout' />\n";

if(\Osmium\State\is_logged_in()) {
	echo "<input type='button' name='submit_loadout' id='submit_loadout' value='Save loadout' />\n";
}

echo "<br />\nExport format: <select name='export_type' id='export_type'>\n";
foreach(\Osmium\Fit\get_export_formats() as $k => $f) {
	echo "<option value='".\Osmium\Chrome\escape($k)."'>".\Osmium\Chrome\escape($f[0])."</option>\n";
}
echo "</select>\n";

echo "</form>
</section>
<section id='attributes'>
<div class='compact' id='computed_attributes'>
<p class='placeholder loading'>
Loading attributes…<span class='spinner'></span>
</p>
</div>
</section>
</div>\n";

echo "<div id='nlsources'>
<ul class='tabs'>
<li><a href='#search'>Search</a></li>
<li><a href='#browse'>Browse</a></li>
<li><a href='#shortlist'>Shortlist</a></li>
</ul>\n";

$searchexamples = array(
	/* Module synonyms */
	'dc2', '10mn ab', 'rf tp', 'large asb',
	'cn bcs', 'med cdfe', 'rcu 2', '1mn mwd',
	'fn web', 'c-type aif', 'eanm ii', 'pds',
	'mjd', 'rf disru', 'pwnage', 'tachyon ii',

	/* Ship synonyms */
	'sni', 'rni', 'sfi', 'pirate frig',

	/* Meta level filter */
	'gyro @ml 4', 'web @ml 4', 'hml @ml 4', 'eanm @ml 1',

	/* Implants synonyms */
	'lg snake', 'crystal implant',
);
echo "<section id='search'>
<form method='get' action='?'>
<ul class='filters'></ul>
<div class='query'>
<div><input type='search' name='q' placeholder='Example query: ".
\Osmium\Chrome\escape($searchexamples[mt_rand(0, count($searchexamples) - 1)])
."' title='Search items by name, by group, by abbreviation' /></div>
<input type='submit' value='Search' />
</div>
</form>
<ul class='results'></ul>
</section>\n";

echo "<section id='browse'>
<ul class='filters'></ul>
<p class='placeholder loading'>
Fetching the list of types…<span class='spinner'></span>
</p>
</section>\n";

echo "<section id='shortlist'>
<ul class='filters'></ul>
<p class='placeholder loading'>
Fetching shortlist…<span class='spinner'></span>
</p>
</section>\n";

echo "</div>\n";

echo "<div id='nlmain'>
<ul class='tabs'>
<li><a href='#modules'>Modules &amp; Charges</a></li>
<li><a href='#drones'>Drones</a></li>
<li><a href='#implants'>Implants &amp; Boosters</a></li>
<li><a href='#remote'>Remote</a></li>
<li><a href='#presets'>Presets</a></li>
<li><a href='#metadata'>Metadata</a></li>
</ul>\n";

echo "<section id='presets'>\n";
$presetactions = "<input type='button' class='createpreset' value='Create preset' />\n<input type='button' class='renamepreset' value='Rename preset' />\n<input type='button' class='clonepreset' value='Clone preset' />\n<input type='button' class='deletepreset' value='Delete preset' />";
\Osmium\Forms\print_form_begin();
\Osmium\Forms\print_generic_row('spreset', "<label for='spreset'>Preset</label>", "<select id='spreset' name='spreset'></select><br />\n".$presetactions."\n", 'rpresets');
\Osmium\Forms\print_textarea('Preset description', 'tpresetdesc');
\Osmium\Forms\print_separator();
\Osmium\Forms\print_generic_row('scpreset', "<label for='scpreset'>Charge preset</label>", "<select id='scpreset' name='scpreset'></select><br />\n".$presetactions."\n", 'rchargepresets');
\Osmium\Forms\print_textarea('Charge preset description', 'tcpresetdesc');
\Osmium\Forms\print_separator();
\Osmium\Forms\print_generic_row('sdpreset', "<label for='sdpreset'>Drone preset</label>", "<select id='sdpreset' name='sdpreset'></select><br />\n".$presetactions."\n", 'rdronepresets');
\Osmium\Forms\print_textarea('Drone preset description', 'tdpresetdesc');
\Osmium\Forms\print_form_end();
echo "</section>\n";

echo "<section id='metadata'>\n";
\Osmium\Forms\print_form_begin();
\Osmium\Forms\print_generic_field('Loadout title', 'text', 'name', 'name');
\Osmium\Forms\print_textarea('Description<br /><small>(optional,<br />Markdown and some HTML allowed)</small>', 'description', 'description');
\Osmium\Forms\print_generic_field('Tags<br /><small>(space-separated, '
                                  .(int)\Osmium\get_ini_setting('min_tags').'-'
                                  .(int)\Osmium\get_ini_setting('max_tags').')</small>',
                                  'text', 'tags', 'tags');
$commontags = array(
	/* General usage */
	'pve', 'pvp',
	'solo', 'fleet',
	'small-gang',

	/* Defense related */
	'shield-tank', 'armor-tank', 
	'buffer-tank', 'active-tank', 
	'passive-tank',

	/* Offense related */
	'gun-boat', 'missile-boat',
	'drone-boat', 'support',
	'short-range', 'long-range',

	/* ISK/SP */
	'cheap', 'expensive',
	'low-sp', 'high-sp',
);
\Osmium\Forms\print_generic_row(
	'common_tags', '',
	'Common tags:<ul class="tags">'
	.implode(
		' ',
		array_map(
			function($tag) { return '<li><a title="Add this tag">'.$tag.'</a></li>'; },
			$commontags
		)
	).'</ul>',
	'common_tags'
);

$versions = \Osmium\Fit\get_eve_db_versions();
foreach($versions as &$v) {
	$v = $v['name']." (".$v['tag'].", build ".$v['build'].")";
}
\Osmium\Forms\print_select('Expansion<br /><small>(for experts only)</small>', 'evebuildnumber', $versions);

if(\Osmium\State\is_logged_in()) {
	\Osmium\Forms\print_separator();

	\Osmium\Forms\print_select(
		'Can be seen by', 'view_perms', 
		array(
			\Osmium\Fit\VIEW_EVERYONE => 'everyone',
			\Osmium\Fit\VIEW_PASSWORD_PROTECTED => 'everyone but require a password',
			\Osmium\Fit\VIEW_ALLIANCE_ONLY => 'my alliance only',
			\Osmium\Fit\VIEW_CORPORATION_ONLY => 'my corporation only',
			\Osmium\Fit\VIEW_OWNER_ONLY => 'only me',
			\Osmium\Fit\VIEW_GOOD_STANDING => 'my contacts with good standing (≥0.01, includes corporation and alliance)',
			\Osmium\Fit\VIEW_EXCELLENT_STANDING => 'my contacts with excellent standing (≥5.01, includes corporation and alliance)',
			), null, 'view_perms');

	\Osmium\Forms\print_select(
		'Can be edited by', 'edit_perms', 
		array(
			\Osmium\Fit\EDIT_OWNER_ONLY => 'only me',
			\Osmium\Fit\EDIT_OWNER_AND_FITTING_MANAGER_ONLY => 'me and anyone in my corporation with the Fitting Manager role',
			\Osmium\Fit\EDIT_CORPORATION_ONLY => 'anyone in my corporation',
			\Osmium\Fit\EDIT_ALLIANCE_ONLY => 'anyone in my alliance',
			), null, 'edit_perms');

	\Osmium\Forms\print_select(
		'Visibility', 'visibility', 
		array(
			\Osmium\Fit\VISIBILITY_PUBLIC => 'public (will appear on the homepage and in search results)',
			\Osmium\Fit\VISIBILITY_PRIVATE => 'private (will not appear in search results)',
			), null, 'visibility');

	\Osmium\Forms\print_generic_field('Password', 'password', 'pw', 'pw');
}
\Osmium\Forms\print_form_end();
echo "</section>\n";

echo "<section id='modules'>\n";
$stypes = \Osmium\Fit\get_slottypes();
foreach($stypes as $type => $tdata) {
    if($type === "high" || $type === "medium") {
        $groupstatus = "grouped";
        $groupedcharges = "<small class='groupcharges'>Charges are grouped</small>";
    } else {
        $groupstatus = "ungrouped";
        $groupedcharges = "";
    }

	echo "<div class='slots $type $groupstatus'>\n<h3>".\Osmium\Chrome\escape($tdata[0])
		." <span>$groupedcharges<small class='counts'></small></span></h3>\n";
	echo "<ul></ul>\n";
	echo "</div>\n";
}
echo "</section>\n";

echo "<section id='drones'>\n";
foreach(array('space' => 'In space', 'bay' => 'In bay') as $type => $fname) {
	echo "<div class='drones $type'>\n<h3>".\Osmium\Chrome\escape($fname)." <span>";
	if($type === 'space') {
		echo "<small title='Maximum number of drones in space' class='maxdrones'></small>";
		echo "<small> — </small>";
		echo "<small title='Drone bandwidth usage' class='bandwidth'></small>";
	} else if($type === 'bay') {
		echo "<small title='Drone bay usage' class='bayusage'></small>";
	}
	echo "</span></h3>\n<ul></ul>\n</div>\n";
}
echo "</section>\n";

echo "<section id='implants'>\n";
echo "<div class='implants'>\n<h3>Implants</h3>\n<ul></ul>\n</div>\n";
echo "<div class='boosters'>\n<h3>Boosters</h3>\n<ul></ul>\n</div>\n";
echo "</section>\n";

echo "<section id='remote'>\n";
echo "<section id='fleet'>\n<h2>Fleet boosters</h2>\n";
echo "<p>The fittings you use as fleet, wing or squad boosters will be visible by anyone who also has access to this loadout.<br />\nThe skills will be reset to \"All V\" when saving the loadout.</p>\n";
echo "<form>\n<table>\n<tbody>\n";

foreach(array('fleet', 'wing', 'squad') as $ft) {
	echo "<tr data-type='{$ft}'>\n";
	echo "<td rowspan='3'><input type='checkbox' id='{$ft}_enabled' name='{$ft}_enabled' class='{$ft} enabled' />";
	echo " <label for='{$ft}_enabled'><strong>".ucfirst($ft)." booster</strong></label></td>\n";
	echo "<td><label for='{$ft}_skillset'>Use skills: </label></td>\n";
	echo "<td><select name='{$ft}_skillset' id='{$ft}_skillset' class='skillset {$ft}'></select></td>\n";
	echo "</tr>\n";

	echo "<tr data-type='{$ft}'>\n";
	echo "<td rowspan='2'><label for='{$ft}_fit'>Use fitting: </label></td>\n";
	echo "<td><input type='text' name='{$ft}_fit' id='{$ft}_fit' class='fit {$ft}' placeholder='Loadout URI, DNA string or gzclf:// data' /></td>\n";
	echo "</tr>\n";

	echo "<tr data-type='{$ft}'>\n<td>";
	echo "<input type='button' class='set {$ft}' value='Set fit' /> <input type='button' class='clear {$ft}' value='Clear fit' />";	
	echo "</td></tr>\n";
}

echo "</tbody>\n</table>\n</form>\n</section>\n";

echo "<section id='projected'>
<h2>Projected effects
<form>
<input type='button' value='Add projected fit' id='createprojected' />
<input type='button' value='Toggle fullscreen' id='projectedfstoggle' />
</form>
</h2>
<p id='rearrange'>
Rearrange loadouts: <a id='rearrange-grid'>grid</a>,
<a id='rearrange-circle'>circle</a>
</p>
<form id='projected-list'>
</form>
</section>\n";

echo "</section>\n</div>\n";

\Osmium\Chrome\print_loadout_common_footer($fit, RELATIVE, $tok);

\Osmium\Chrome\add_js_data('shortlist', json_encode(\Osmium\AjaxCommon\get_module_shortlist()));

\Osmium\Chrome\include_js("//cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/0.4.6/jquery.perfect-scrollbar-with-mousewheel.min.js");

\Osmium\Chrome\print_js_snippet('new_loadout');
\Osmium\Chrome\print_js_snippet('new_loadout-control');
\Osmium\Chrome\print_js_snippet('new_loadout-sources');
\Osmium\Chrome\print_js_snippet('new_loadout-ship');
\Osmium\Chrome\print_js_snippet('new_loadout-presets');
\Osmium\Chrome\print_js_snippet('new_loadout-metadata');
\Osmium\Chrome\print_js_snippet('new_loadout-modules');
\Osmium\Chrome\print_js_snippet('new_loadout-drones');
\Osmium\Chrome\print_js_snippet('new_loadout-implants');
\Osmium\Chrome\print_js_snippet('new_loadout-remote');
\Osmium\Chrome\print_footer();
