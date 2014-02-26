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

namespace Osmium\Search;

const SPHINXQL_PORT = 24492;

function get_link() {
	static $link = null;
	if($link === null) {
		$link = mysqli_connect('127.0.0.1', 'root', '', '', SPHINXQL_PORT);
		if(!$link) {
			\Osmium\fatal(500, 'Could not connect to Sphinx.');
		}
	}

	return $link;
}

function query_select_searchdata($cond, array $params = array()) {
	return \Osmium\Db\query_params(
		'SELECT loadoutid, restrictedtoaccountid, restrictedtocorporationid,
		restrictedtoallianceid, viewpermission,
		tags, modules, author, name, description,
		shipid, upvotes, downvotes, score, ship, groups, creationdate,
		updatedate, evebuildnumber, comments, dps, ehp, estimatedprice
		FROM osmium.loadoutssearchdata '.$cond,
		$params
	);
}

function query($q) {
	return mysqli_query(get_link(), $q);
}

function escape($string) {
	/* Taken from the GPL PHP API of Sphinx */
	$from = array ('\\', '(',')','|','-','!','@','~',"'",'&', '/', '^', '$', '=');
	$to   = array ('\\\\', '\(','\)','\|','\-','\!','\@','\~','\\\'', '\&', '\/', '\^', '\$', '\=');
	return str_replace ($from, $to, $string);
}

function unindex($loadoutid) {
	query('DELETE FROM osmium_loadouts WHERE id = '.$loadoutid);
}

function index($loadout) {
	unindex($loadout['loadoutid']);
	$goodstandings = array();
	$excellentstandings = array();

	if($loadout['viewpermission'] == \Osmium\Fit\VIEW_GOOD_STANDING
	|| $loadout['viewpermission'] == \Osmium\Fit\VIEW_EXCELLENT_STANDING) {
		if($loadout['viewpermission'] == \Osmium\Fit\VIEW_GOOD_STANDING) {
			/* Good standings */
			$cutoff = 0;
			$dest =& $goodstandings;
			$excellentstandings[] = 0;
		} else if($loadout['viewpermission'] == \Osmium\Fit\VIEW_EXCELLENT_STANDING) {
			/* Excellent standings */
			$cutoff = 5;
			$dest =& $excellentstandings;
			$goodstandings[] = 0;
		}

		$author = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
			'SELECT l.accountid, a.apiverified, a.characterid, a.corporationid, a.allianceid
			FROM osmium.loadouts l
			JOIN osmium.accounts a ON a.accountid = l.accountid
			WHERE loadoutid = $1',
			array($loadout['loadoutid'])
		));
	
		$q = \Osmium\Db\query_params(
			'SELECT contactid
			FROM osmium.contacts
			WHERE accountid = $1 AND standing > $2',
			array($author['accountid'], $cutoff)
		);

		while($row = \Osmium\Db\fetch_row($q)) {
			$dest[] = $row[0];
		}

		/* As a safety for non API verified accounts, always add the
		 * author account ID to the list of authorized contacts. It's
		 * probably safe to assume that the low range of account IDs
		 * will not overlap with character, corporation or alliance
		 * IDs. */
		$dest[] = $author['accountid'];

		if($author['apiverified'] === 't') {
			if($author['corporationid'] > 0) {
				/* Add the author's corporation to the authorized contacts */
				$dest[] = $author['corporationid'];
			}
			if($author['allianceid'] > 0) {
				/* Add the author's alliance to the authorized contacts */
				$dest[] = $author['allianceid'];
			}
		}
	} else {
		$goodstandings[] = 0;
		$excellentstandings[] = 0;
	}

	$tags = array_map(function($t) { return "'".escape($t)."'"; }, explode(' ', $loadout['tags']));
	sort($tags);
	$tags = implode(' ', $tags);

	return query(
		'INSERT INTO osmium_loadouts (
		id, restrictedtoaccountid, restrictedtocorporationid, restrictedtoallianceid,
		goodstandingids, excellentstandingids,
		shipid, upvotes, downvotes, score, creationdate, updatedate, build,
		comments, dps, ehp, estimatedprice,
		attship, attshipgroup, attname, atttags, attauthor,
		ship, shipgroup, name, author, tags, description, types
		) VALUES ('

		.$loadout['loadoutid'].','
		.$loadout['restrictedtoaccountid'].','
		.$loadout['restrictedtocorporationid'].','
		.$loadout['restrictedtoallianceid'].','
		.'('.implode(', ', $goodstandings).')'.','
		.'('.implode(', ', $excellentstandings).'),'

		.$loadout['shipid'].','
		.$loadout['upvotes'].','
		.$loadout['downvotes'].','
		.$loadout['score'].','
		.$loadout['creationdate'].','
		.$loadout['updatedate'].','
		.$loadout['evebuildnumber'].','
		.$loadout['comments'].','
		.$loadout['dps'].','
		.$loadout['ehp'].','
		.$loadout['estimatedprice'].','

		.'\''.escape($loadout['ship']).'\','
		.'\''.escape($loadout['groups']).'\','
		.'\''.escape($loadout['name']).'\','
		.'\''.escape($tags).'\','
		.'\''.escape($loadout['author']).'\','

		.'\''.escape($loadout['ship']).'\','
		.'\''.escape($loadout['groups']).'\','
		.'\''.escape($loadout['name']).'\','
		.'\''.escape($loadout['author']).'\','
		.'\''.escape($tags).'\','
		.'\''.escape($loadout['description']).'\','
		.'\''.escape($loadout['modules']).'\''
		.')'
	);
}

function parse_search_query_attr_filters(&$search_query, array $intattrs = array(), array $floatattrs = array()) {
	$and = [];

	$search_query = preg_replace_callback(
		$regex = '%(\s|^)@('
		.'(?<intattr>'.implode('|', $intattrs).')'
		.'|(?<floatattr>'.implode('|', $floatattrs).')'
		.')\s+(?<operator>(>=|<=|<>|!=|=|>|<)?)'
		.'\s*(?<value>[+-]?[0-9]*(\.[0-9]+)?([eE][+-]?[0-9]+)?)'
		.'(?<modifier>[kmb]?)%',
		function($m) use(&$and) {
			$value = (float)$m['value'];
			if($m['modifier'] === 'k') $value *= 1e3;
			else if($m['modifier'] === 'm') $value *= 1e6;
			else if($m['modifier'] === 'b') $value *= 1e9;

			if(!$m['operator']) $m['operator'] = '=';

			if($m['intattr']) {
				$and[] = $m['intattr'].' '.$m['operator'].' '.(int)$value;
			} else if($m['floatattr']) {
				$and[] = $m['floatattr'].' '.$m['operator'].' '.sprintf("%.14f", $value);
			}

			return '';
		},
		$search_query
	);

	$fand = '';
	foreach($and as $a) $fand .= ' AND '.$a;
	return $fand;
}

function get_search_query($search_query) {
	$accountids = array(0);
	$corporationids = array(0);
	$allianceids = array(0);
	$characterids = array(0);

	if(\Osmium\State\is_logged_in()) {
		$a = \Osmium\State\get_state('a');
		$accountids[] = intval($a['accountid']);

		if($a['apiverified'] === 't') {
			$corporationids[] = intval($a['corporationid']);
			$characterids[] = intval($a['characterid']);
			if($a['allianceid'] > 0) $allianceids[] = intval($a['allianceid']);
		}
	}

	$fand = parse_search_query_attr_filters(
		$search_query,
		[
			'loadoutid', 'restrictedtoaccountid',
			'restrictedtocorporationid', 'restrictedtoallianceid',
			'shipid', 'upvotes', 'downvotes',
			'creationdate', 'updatedate',
			'build', 'comments',
		],
		[
			'score', 'dps', 'ehp', 'estimatedprice',
		]
	);

	$ac_ids = implode(', ', $accountids);
	$ch_ids = implode(', ', $characterids);
	$co_ids = implode(', ', $corporationids);
	$al_ids = implode(', ', $allianceids);

	return "SELECT id FROM osmium_loadouts
	WHERE MATCH('".escape($search_query)."')
	AND restrictedtoaccountid IN ({$ac_ids})
	AND restrictedtocorporationid IN ({$co_ids})
	AND restrictedtoallianceid IN ({$al_ids})
	AND goodstandingids IN ({$ac_ids}, {$ch_ids}, {$co_ids}, {$al_ids})
	AND excellentstandingids IN ({$ac_ids}, {$ch_ids}, {$co_ids}, {$al_ids})
	".$fand;
}

function get_search_ids($search_query, $more_cond = '', $offset = 0, $limit = 1000) {
	$q = query(
		get_search_query($search_query)
		.' '.$more_cond
		.' LIMIT '.$offset.','.$limit
		.' OPTION field_weights=(ship=100,shipgroup=80,author=100,name=70,description=10,tags=150,types=30)'
	);
	if($q === false) return false; /* Invalid query */

	$ids = array();
	while($row = fetch_row($q)) {
			$ids[] = $row[0];
	}

	return $ids;
}

function get_total_matches($search_query, $more_cond = '') {
	$q = query(get_search_query($search_query).' '.$more_cond);
	
	if($q === false) return 0;

	$meta = get_meta();
	return $meta['total_found'];
}

function fetch_assoc($result) {
	return mysqli_fetch_assoc($result);
}

function fetch_row($result) {
	return mysqli_fetch_row($result);
}

function get_meta() {
	$q = query('SHOW META;');
	$meta = array();

	while($r = fetch_row($q)) {
		$meta[$r[0]] = $r[1];
	}

	return $meta;
}

function print_pretty_results($relative, $query, $more = '', $paginate = false, $perpage = 20, $pagename = 'p', $message = 'No loadouts matched your query.') {
	$total = get_total_matches($query, $more);
	if($paginate) {
		$offset = \Osmium\Chrome\paginate($pagename, $perpage, $total, $pageresult, $pageinfo);
	} else $offset = 0;

	$ids = \Osmium\Search\get_search_ids($query, $more, $offset, $perpage);
	if($ids === false) {
		echo "<p class='placeholder'>The supplied query is invalid.</p>\n";
		return;
	}

	if($paginate) {
		echo $pageinfo;
		echo $pageresult;
		print_loadout_list($ids, $relative, $offset, $message);
		echo $pageresult;
	} else {
		print_loadout_list($ids, $relative, $offset, $message);
	}
}

function print_loadout_list(array $ids, $relative, $offset = 0, $nothing_message = 'No loadouts.') {
	if($ids === array()) {
		echo "<p class='placeholder'>".$nothing_message."</p>\n";
		return;		
	}

	$orderby = implode(',', array_map(function($id) { return 'lsr.loadoutid='.$id.' DESC'; }, $ids));
	$in = implode(',', $ids);
	$first = true;
    
	$lquery = \Osmium\Db\query(
		'SELECT loadoutid, privatetoken, latestrevision, viewpermission, visibility,
		hullid, typename, creationdate, updatedate, name, evebuildnumber, nickname,
		apiverified, charactername, characterid, corporationname, corporationid,
		alliancename, allianceid, accountid, taglist, reputation,
		votes, upvotes, downvotes, comments, dps, ehp, estimatedprice
		FROM osmium.loadoutssearchresults lsr
		WHERE lsr.loadoutid IN ('.$in.') ORDER BY '.$orderby
	);

	$last_groupname = "";

	while($loadout = \Osmium\Db\fetch_assoc($lquery)) {
		if($first === true) {
			$first = false;
			/* Only write the <table> tag if there is at least one loadout */
			echo "<table start='".($offset + 1)."' class='loadout_sr'>\n";
			echo "<tr><th>Ideal</th><th>Ship</th><th>Name</th><th>Tags</th><th>Author</th><th>Social</th></tr>\n";
		}

		$fit = \Osmium\Fit\get_fit($loadout['loadoutid']);
		$a = \Osmium\State\get_state('a');
		if ($a) {
			\Osmium\Fit\use_default_skillset($fit, $a);
		}
		list($prereqs, $missing_prereqs) = \Osmium\Fit\get_skill_prerequisites_and_missing_prerequisites($fit);
		$prereqs_unique = \Osmium\Skills\uniquify_prerequisites($prereqs);
		$missing_unique = \Osmium\Skills\uniquify_prerequisites($missing_prereqs);

		// XXX code copying
		list($groupname) = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
					'SELECT groupname FROM eve.invtypes
					JOIN eve.invgroups ON invtypes.groupid = invgroups.groupid
					WHERE typeid = $1',
					array($loadout['hullid'])
					));

		// TODO only do this if ordered by groupname
		if ($groupname != $last_groupname) {
			echo "<tr><td colspan='6'>$groupname</td></tr>";
			$last_groupname = $groupname;
		}

		$uri = \Osmium\Fit\get_fit_uri(
			$loadout['loadoutid'], $loadout['visibility'], $loadout['privatetoken']
		);

		$sn = \Osmium\Chrome\escape($loadout['typename']);

		$class = "undetermined";
		if ($a) {
			if ($missing_prereqs) {
				if (isset($missing_prereqs[$loadout['hullid']])) {
					$class = "not-sittable";
				} else {
					$class = "sittable";
				}
			} else {
				$class = "flyable";
			}
		}
		echo "<tr class='$class'>";

		echo "<td class='ideal'>";
		$dps = $loadout['dps'] === null ? 'N/A' : \Osmium\Chrome\format($loadout['dps'], 2);
		$ehp = $loadout['ehp'] === null ? 'N/A' : \Osmium\Chrome\format($loadout['ehp'], 2, 'k');
		$esp = $loadout['estimatedprice'] === null ? 'N/A' : \Osmium\Chrome\format($loadout['estimatedprice'], 2);

		echo "<div title='Damage per second of this loadout' class='absnum dps'><span><strong>"
			.$dps."</strong><small>DPS</small></span></div>\n";

		echo "<div title='Effective hitpoints of this loadout (assumes uniform damage pattern)'"
			." class='absnum ehp'><span><strong>"
			.$ehp."</strong><small>EHP</small></span></div>\n";

		echo "<div title='Estimated price of this loadout' class='absnum esp'><span><strong>"
			.$esp."</strong><small>ISK</small></span></div>\n";
		echo "</td>";

		echo "<td class='hull'>";
		echo "\n<a href='$relative/".$uri."'>"
			."<img class='abs hull-icon' src='//image.eveonline.com/Render/"
			.$loadout['hullid']."_256.png' title='".$sn."' alt='".$sn."' />\n";
		echo "<span class='name'>$sn</span>";
		echo "</a>";

		echo "<div class='sideicons'>\n";
		print_viewpermission_sprites($loadout);
		echo "</div>\n";
		echo "</td>";

		echo "<td>";
		echo "<a class='fitname' href='{$relative}/{$uri}'>"
			.\Osmium\Chrome\escape($loadout['name'])."</a>\n";

		if ($a && $missing_prereqs) {
			echo "<br />\n";
			echo "<small>";
			if (isset($missing_prereqs[$loadout['hullid']])) {
				error_log("hello 1");
				list($unused, $hull_needed) = \Osmium\Skills\sum_sp($missing_prereqs[$loadout['hullid']], $fit['skillset']);
				$h = \Osmium\Chrome\format($hull_needed);
				echo "Hull in $h SP – ";
			}
			error_log("hello 2");
			list($unused, $all_needed) = \Osmium\Skills\sum_sp($missing_unique, $fit['skillset']);
			$a = \Osmium\Chrome\format($all_needed);
			echo "Fit in $a SP";
			echo "</small>";
		}
		echo "</td>";

		echo "<td>";
		$tags = array_filter(explode(' ', $loadout['taglist']), function($tag) { return trim($tag) != ''; });
		if(count($tags) == 0) {
			echo "<em class='notags'>(no tags)</em>\n";
		} else {
			echo "<ul class='tags'>\n"
				.implode('', array_map(function($tag) use($relative) {
						$tag = trim($tag);
						return "<li><a href='$relative/search?q="
							.urlencode('@tags "'.$tag.'"')."'>$tag</a></li>\n";
					}, $tags))."</ul>\n";
		}
		echo "</td>\n";

		echo "<td>";
		echo "<small>".\Osmium\Chrome\format_character_name($loadout, $relative);
		echo " (".\Osmium\Chrome\format_reputation($loadout['reputation']).")</small>\n";

		echo "<br/><small>".date('Y-m-d', $loadout['updatedate'])."</small><br />\n";
		echo "</td>";
      
		echo "<td>";
		$votes = (abs($loadout['votes']) == 1) ? 'vote' : 'votes';
		$upvotes = \Osmium\Chrome\format($loadout['upvotes'], -1);
		$downvotes = \Osmium\Chrome\format($loadout['downvotes'], -1);
		echo "+{$upvotes}/-{$downvotes}<br/>\n";

		$comments = ($loadout['comments'] == 1) ? 'comment' : 'comments';
		echo "<small><small><a href='$relative/".$uri."#comments'>"
			.\Osmium\Chrome\format($loadout['comments'], -1)." {$comments}</a></small></small>\n";
		echo "</td>";

		echo "</tr>";
	}

	if($first === false) {
		echo "</table>\n";
	} else {
		echo "<p class='placeholder'>".$nothing_message."</p>\n";
	}
}

function print_viewpermission_sprites($loadout) {
		$vp = $loadout['viewpermission'];
		$vpsize = 16;
		if($vp > 0) {
			switch((int)$vp) {

			case \Osmium\Fit\VIEW_PASSWORD_PROTECTED:
				echo \Osmium\Chrome\sprite($relative, '(password-protected)', 0, 25, 32, 32, $vpsize);
				break;

			case \Osmium\Fit\VIEW_ALLIANCE_ONLY:
				$aname = ($loadout['apiverified'] === 't' && $loadout['allianceid'] > 0) ?
					$loadout['alliancename'] : 'My alliance';
				echo \Osmium\Chrome\sprite($relative, "({$aname} only)", 2, 13, 64, 64, $vpsize);
				break;

			case \Osmium\Fit\VIEW_CORPORATION_ONLY:
				$cname = ($loadout['apiverified'] === 't') ? $loadout['corporationname'] : 'My corporation';
				echo \Osmium\Chrome\sprite($relative, "({$cname} only)", 3, 13, 64, 64, $vpsize);
				break;

			case \Osmium\Fit\VIEW_OWNER_ONLY:
				echo \Osmium\Chrome\sprite($relative, "(only visible by me)", 1, 25, 32, 32, $vpsize);
				break;

			case \Osmium\Fit\VIEW_GOOD_STANDING:
				echo \Osmium\Chrome\sprite(
					$relative,
					"(only visible by my corporation, alliance and contacts with good standing)",
					5, 28, 32, 32, $vpsize
				);
				break;

			case \Osmium\Fit\VIEW_EXCELLENT_STANDING:
				echo \Osmium\Chrome\sprite(
					$relative,
					"(only visible by my corporation, alliance and contacts with excellent standing)",
					4, 28, 32, 32, $vpsize
				);
				break;

			}
		}

		if((int)$loadout['visibility'] === \Osmium\Fit\VISIBILITY_PRIVATE) {
			echo \Osmium\Chrome\sprite($relative, "(hidden loadout)", 4, 13, 64, 64, $vpsize);
		}
}

function get_search_cond_from_advanced() {
	if(!isset($_GET['build']) && !isset($_GET['op'])) {
		/* Use sane defaults, ie hide absurdly outdated loadouts by
		 * default */

		$vercutoff = array_values(\Osmium\Fit\get_eve_db_versions())[2]['build'];

		$_GET['op'] = 'gt';
		$_GET['build'] = $vercutoff;
	}

	static $operators = array(
		'eq' => '=',
		'lt' => '<=',
		'gt' => '>=',
	);

	static $orderby = array(
		//"relevance" => "relevance", /* Does not match to an ORDER BY statement as this is the default */
		"creationdate" => "creation date",
		"attname" => "name",
		"attship" => "ship",
		"attshipgroup" => "ship group",
		"attauthor" => "author",
		"atttags" => "tags",
		"score" => "score (votes)",
		"comments" => "number of comments",
		"dps" => "damage per second",
		"ehp" => "effective hitpoints",
		"estimatedprice" => "estimated price",
	);

	$cond = '';
	if(isset($_GET['op']) && isset($_GET['build']) && isset($operators[$_GET['op']])) {
		$cond .= " AND build ".$operators[$_GET['op']]." ".((int)$_GET['build']);
	}

	if(isset($_GET['sort']) && isset($orderby[$_GET['sort']])) {
		$order = isset($_GET['order']) && in_array($_GET['order'], [ 'asc', 'desc' ]) ? $_GET['order'] : 'DESC';
		$cond .= ' ORDER BY '.$_GET['sort'].' '.$order;
	}

	return $cond;
}

/**
 * Print a basic seach form. Pre-fills the search form from $_GET data
 * if present.
 */
function print_search_form($uri = null, $relative = '.', $label = 'Search loadouts', $icon = null, $advanced = 'Advanced search') {
	static $operands = array(
		"gt" => "or newer",
		"eq" => "exactly",
		"lt" => "or older",
	);

	static $orderby = array(
		"relevance" => "relevance",
		"creationdate" => "creation date",
		"attname" => "name",
		"attship" => "ship",
		"attshipgroup" => "ship group",
		"attauthor" => "author",
		"atttags" => "tags",
		"score" => "score (votes)",
		"comments" => "number of comments",
		"dps" => "damage per second",
		"ehp" => "effective hitpoints",
		"estimatedprice" => "estimated price",
	);

	static $orders = array(
		'desc' => 'in descending order',
		'asc' => 'in ascending order',
	);

	static $examples = array(
		"@ship Drake | Tengu @tags missile-boat",
		"@shipgroup Cruiser -Strategic -Heavy @dps >= 500",
		"@tags -armor-tank",
		"@dps >= 400 @ehp >= 40k @tags pvp",
		"battlecruiser @types \"stasis webifier\"",
		"@tags cheap low-sp @estimatedprice <= 10m",
		"battleship @tags pve|l4|missions",
	);

	if($icon === null) $icon = [ 2, 12, 64, 64 ];

	$val = '';
	if(isset($_GET['q']) && strlen($_GET['q']) > 0) {
		$val = "value='".\Osmium\Chrome\escape($_GET['q'])."' ";
	}

	if($uri === null) {
		$uri = \Osmium\Chrome\escape(explode('?', $_SERVER['REQUEST_URI'], 2)[0]);
	}

	$placeholder = \Osmium\Chrome\escape($examples[mt_rand(0, count($examples) - 1)]);

	echo "<form method='get' action='{$uri}'>\n";
	echo "<h1><label for='search'>"
		.\Osmium\Chrome\sprite($relative, '', $icon[0], $icon[1], $icon[2], $icon[3], 64)
		.$label."</label></h1>\n";

	echo "<p>\n<input id='search' type='search' placeholder='{$placeholder}' name='q' $val/> <input type='submit' value='Go!' /><br />\n";

	if(isset($_GET['ad']) && $_GET['ad']) {
		echo "for \n";

		echo "<select name='build' id='build'>\n";
		foreach(\Osmium\Fit\get_eve_db_versions() as $v) {
			echo "<option value='".$v['build']."'";

			if(isset($_GET['build']) && (int)$_GET['build'] === $v['build']) {
				echo " selected='selected'";
			}

			echo ">".\Osmium\Chrome\escape($v['name'])."</option>\n";
		}
		echo "</select>\n";

		echo "<select name='op' id='op'>\n";
		foreach($operands as $op => $label) {
			echo "<option value='$op'";

			if(isset($_GET['op']) && $_GET['op'] === $op) {
				echo " selected='selected'";
			}

			echo ">$label</option>\n";
		}
		echo "</select><br />\nsort by \n<select name='sort' id='sort'>\n";
		foreach($orderby as $sort => $label) {
			echo "<option value='{$sort}'";
			if(isset($_GET['sort']) && $_GET['sort'] === $sort) {
				echo " selected='selected'";
			}
			echo ">{$label}</option>\n";
		}
		echo "</select>\n";
		echo "<select name='order' id='order'>\n";
		foreach($orders as $k => $label) {
			echo "<option value='{$k}'";
			if(isset($_GET['order']) && $_GET['order'] === $k) {
				echo " selected='selected'";
			}
			echo ">{$label}</option>\n";
		}
		echo "</select>\n";
		echo "<input type='hidden' name='ad' value='1' />\n";
		echo "<br />\n<a href='{$relative}/help/search'><small>Help</small></a>\n";
	} else {
		$get = 'ad=1';
		foreach($_GET as $k => $v) {
			$get .= "&amp;".\Osmium\Chrome\escape($k)."=".\Osmium\Chrome\escape($v);
		}
		echo "<a href='{$uri}?{$get}'><small>{$advanced}</small></a> — <a href='{$relative}/help/search'><small>Help</small></a>\n";
	}

	echo"</p>\n";
	echo "</form>\n";
}
