<?php
/* Osmium
 * Copyright (C) 2012, 2013, 2014, 2015 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Dispatch;

require __DIR__.'/../inc/dispatchroot.php';

/* Returns some dispatch rules, of the form <RegEx> => <Path>, that
 * start with the given prefix.
 *
 * <Path> will be included if <RegEx> matches the current request URI
 * (normalized according to the relative root). If <RegEx> has named
 * groups (like (?P<foo>bar)), they will be added to $_GET.
 *
 * If <Path> is an array, it must be of the form array(path, postget)
 * and postget will be merged to the $_GET array (postget has
 * precedence).
 *
 * For best performance, try to put the rules of the most frequent
 * pages first, and try to keep the general number of rules low (worst
 * case being 404 errors, where all the rules have to be checked).
 */
function get_rules($prefix) {
	switch($prefix) {

	case false:
		return [
			'%^/$%D' => '/src/main.php',
			'%^/search$%D' => '/src/search.php',
			'%^/new$%D' => '/src/new_loadout.php',
			'%^/login$%D' => '/src/login.php',
			'%^/import$%D' => '/src/import_loadouts.php',
			'%^/importcrest$%D' => '/src/import_crest.php',
			'%^/convert%D' => '/src/convert.php',
			'%^/register$%D' => '/src/register.php',
			'%^/settings$%D' => '/src/settings.php',
			'%^/db%D' => '/src/dbbrowser/main.php',
			'%^/resetpassword$%D' => '/src/reset_password.php',
			'%^/notifications$%D' => '/src/view_notifications.php',
			'%^/privileges$%D' => '/src/view_privileges.php',
			'%^/help$%D' => [ '/src/mdstatic.php',
			                  ['relative' => '..', 'title' => 'Osmium help', 'f' => 'help.md']
			],
			'%^/about$%D' => '/src/about.php',
			'%^/changelog$%D' => [ '/src/mdstatic.php',
			                       ['relative' => '.', 'title' => 'Changelog', 'f' => 'changelog.md']
			],
			'%^/moderation$%D' => '/src/moderation/main.php',
			'%^/robots\.txt$%D' => [
				'/src/staticpassthrough.php',
				[ 'f' => 'static/robots.txt', 'type' => 'text/plain' ]
			],
			'%^/sitemap\.xml\.gz$%D' => [
				'/src/staticpassthrough.php', [
					'f' => 'static/cache/sitemap-root.xml.gz',
					'type' => 'application/x-gzip',
					'dontcompress' => true,
					'mexpire' => 93600,
				]
			],
			'%^/sitemap-(?<sitemap>[a-z0-9-]+)\.xml\.gz$%D' => [
				'/src/staticpassthrough.php', [
					'type' => 'application/x-gzip',
					'dontcompress' => true,
					'mexpire' => 93600,
				]
			],
		];

	case "loadout":
		return [
			\Osmium\PUBLIC_LOADOUT_RULE => '/src/view_loadout.php',
			\Osmium\PRIVATE_LOADOUT_RULE => '/src/view_loadout.php',
			'%^/loadout/(?<import>dna)/(?<dna>[0-9:;]+)$%D' => '/src/view_loadout.php',
		];

	case "new":
		return [
			\Osmium\NEW_LOADOUT_RULE => '/src/new_loadout.php',
			'%^/new/(?<import>dna)/(?<dna>[0-9:;]+)$%D' => '/src/new_loadout.php',
		];

	case "db":
		return [
			'%^/db/type/(?<typeid>0|[1-9][0-9]*)$%D' => '/src/dbbrowser/type.php',
			'%^/db/group/(?<groupid>0|[1-9][0-9]*)$%D' => '/src/dbbrowser/group.php',
			'%^/db/category/(?<categoryid>0|[1-9][0-9]*)$%D' => '/src/dbbrowser/category.php',
			'%^/db/marketgroup/(?<mgid>[1-9][0-9]*)$%D' => '/src/dbbrowser/marketgroup.php',
			'%^/db/attribute/(?<attributeid>[1-9][0-9]*)$%D' => '/src/dbbrowser/attribute.php',
			'%^/db/effect/(?<effectid>[1-9][0-9]*)$%D' => '/src/dbbrowser/effect.php',
			'%^/db/compare(types/(?<typeids>([1-9][0-9]*,?)+)|group/(?<groupid>[1-9][0-9]*)|marketgroup/(?<marketgroupid>[1-9][0-9]*)|variations/(?<typeid>[1-9][0-9]*))/(?<attributes>[^/]+)$%D' => '/src/dbbrowser/comparetypes.php',
		];

	case "internal":
		return [
			'%^/internal/nc$%D' => '/src/ajax/get_notification_count.php',
			'%^/internal/syncclf$%D' => '/src/json/process_clf.php',
			'%^/internal/searchtypes/(?<q>.*)$%D' => '/src/json/search_types.php',
			'%^/internal/showinfo$%D' => '/src/json/show_info.php',

			'%^/internal/edit/(?<loadoutid>[1-9][0-9]*)$%D' => ['/src/new_loadout.php', ['edit' => 1]],
			'%^/internal/fork/(?<loadoutid>[1-9][0-9]*)$%D' => ['/src/new_loadout.php', ['fork' => 1]],

			'%^/internal/auth/ccpoauthcallback$%D' => '/src/ccp_oauth_callback.php',

			'%^/internal/favorite/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/toggle_favorite.php',
			'%^/internal/delete/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/delete_fit.php',
			'%^/internal/deletecomment/(?<id>[1-9][0-9]*)$%D' => ['/src/delete_comment.php', ['type' => 'comment']],
			'%^/internal/deletecommentreply/(?<id>[1-9][0-9]*)$%D' => ['/src/delete_comment.php', ['type' => 'commentreply']],

			'%^/internal/vote/(?<targettype>[^/]+)/(?<action>[^/]+)$%D' => '/src/json/cast_vote.php',

			'%^/internal/logout$%D' => '/src/logout.php',
			'%^/internal/redirect/(?<hash>[^/]+)$%D' => '/src/redirect.php',
			'%^/internal/compare/dps/ia$%D' => '/src/json/compare_dps_ia.php',
			'%^/internal/ps/(?<name>[^/]+)$%D' => '/src/ajax/put_setting.php',
			'%^/internal/retag/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/json/retag_loadout.php',
		];

	case "help":
		return [
			'%^/help/search$%D' => [ '/src/mdstatic.php',
			                         ['relative' => '..', 'title' => 'Search help', 'f' => 'search.md']
			],
			'%^/help/formats%D' => [ '/src/mdstatic.php',
			                         ['relative' => '..', 'title' => 'Loadout formats', 'f' => 'formats.md']
			],
			'%^/help/db%D' => [ '/src/mdstatic.php',
			                    ['relative' => '..', 'title' => 'Database browser help', 'f' => 'dbbrowser.md']
			],
			'%^/help/api$%D' => [ '/src/mdstatic.php',
			                      ['relative' => '..', 'title' => 'Osmium API', 'f' => 'api.md']
			],
			'%^/help/api/common%D' => [ '/src/mdstatic.php',
			                      ['relative' => '../..', 'title' => 'Common API parameters', 'f' => 'api/common.md']
			],
			'%^/help/api/loadout-dna$%D' => [ '/src/mdstatic.php',
			                      ['relative' => '../..', 'title' => 'DNA helpers', 'f' => 'api/loadout-dna.md']
			],
			'%^/help/api/loadout-convert%D' => [ '/src/mdstatic.php',
			                      ['relative' => '../..', 'title' => 'Convert/export loadouts', 'f' => 'api/loadout-convert.md']
			],
			'%^/help/api/loadout-query%D' => [ '/src/mdstatic.php',
			                      ['relative' => '../..', 'title' => 'Query loadouts', 'f' => 'api/loadout-query.md']
			],
			'%^/help/api/loadout-attributes%D' => [ '/src/mdstatic.php',
			                      ['relative' => '../..', 'title' => 'Loadout attributes', 'f' => 'api/loadout-attributes.md']
			],
		];

	case "atom":
		return [
			'%^/atom/newfits\.xml$%D' => ['/src/atom/recentfits.php', ['type' => 'newfits']],
			'%^/atom/recentlyupdated\.xml$%D' => ['/src/atom/recentfits.php', ['type' => 'recentlyupdated']],
		];

	case "moderation":
		return [
			'%^/moderation/flags$%D' => '/src/moderation/view_flags.php',
		];

	default:
		return [
			'%^/browse/(?<type>best|new)$%D' => '/src/browse.php',
			'%^/profile/(?<accountid>[1-9][0-9]*)$%D' => '/src/view_profile.php',

			'%^/compare/dps($|/)%D' => '/src/compare_dps.php',

			'%^/api/convert/(?<source_fmt>[1-9][0-9]*|uri|clf|gzclf|evexml|eft|dna|autodetect)/(?<target_fmt>clf|md|evexml|eft|dna|svg)(/.*)?$%D' => '/src/api/convert.php',
			'%^/api/json/loadout/(?<source_fmt>[1-9][0-9]*|uri|clf|gzclf|evexml|eft|dna|autodetect)/attributes/(?<attributes>.+)$%D' => '/src/api/json/loadout_attributes.php',
			'%^/api/json/loadout/query/(?<query>.*)$%D' => '/src/api/json/query_loadouts.php',

			'%^/editcharacter/(?<name>.+)$%D' => '/src/edit_character.php',

			'%^/editcomment/(?<id>[1-9][0-9]*)$%D' => ['/src/edit_comment.php', ['type' => 'comment']],
			'%^/editcommentreply/(?<id>[1-9][0-9]*)$%D' => ['/src/edit_comment.php', ['type' => 'commentreply']],

			'%^/loadouthistory/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/view_loadout_history.php',
			'%^/flagginghistory/(?<accountid>[1-9][0-9]*)$%D' => '/src/view_flagging_history.php',

			'%^/flag/(?<id>[1-9][0-9]*)$%D' => ['/src/cast_flag.php', ['type' => 'loadout']],
			'%^/flagcomment/(?<id>[1-9][0-9]*)$%D' => ['/src/cast_flag.php', ['type' => 'comment']],
			'%^/flagcommentreply/(?<id>[1-9][0-9]*)$%D' => ['/src/cast_flag.php', ['type' => 'commentreply']],
		];

	}
}

$relativeroot = \Osmium\get_ini_setting('relative_path');
$request = '/'.substr(explode('?', $_SERVER['REQUEST_URI'], 2)[0], strlen($relativeroot));

$p = strpos($request, '/', 1);
if($p === false) {
	$prefix = false;
} else {
	$prefix = substr($request, 1, $p -1);
}

foreach(get_rules($prefix) as $rule => $target) {
	if(!preg_match($rule, $request, $matches)) continue;

	foreach($matches as $k => $v) {
		if(is_int($k)) continue;
		$_GET[$k] = urldecode($v);
	}

	if(is_array($target)) {
		$_GET = $target[1] + $_GET;
		$target = $target[0];
	}

	require \Osmium\ROOT.$target;
	die();
}

\Osmium\Fatal(404);
