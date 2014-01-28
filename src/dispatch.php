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

namespace Osmium\Dispatch;

require __DIR__.'/../inc/dispatchroot.php';

/* Dispatch rules, of the form <RegEx> => <Path>;
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
$osmium_dispatch_rules = array(
	/* Very common pages */
	'%^/$%D' => '/src/main.php',
	\Osmium\PUBLIC_LOADOUT_RULE => '/src/view_loadout.php',
	\Osmium\NEW_LOADOUT_RULE => '/src/new_loadout.php',
	'%^/browse/(?<type>best|new)$%D' => '/src/browse.php',
	'%^/search$%D' => '/src/search.php',
	'%^/profile/(?<accountid>[1-9][0-9]*)$%D' => '/src/view_profile.php',
	\Osmium\PRIVATE_LOADOUT_RULE => '/src/view_loadout.php',

	'%^/internal/nc$%D' => '/src/ajax/get_notification_count.php',
	'%^/internal/syncclf/(?<clftoken>[0-9]+|___demand___)$%D' => '/src/json/process_clf.php',
	'%^/internal/searchtypes/(?<q>.*)$%D' => '/src/json/search_types.php',
	'%^/internal/showinfo/(?<clftoken>[0-9]+|___demand___)$%D' => '/src/json/show_info.php',

	'%^/login$%D' => '/src/login.php',


	/* Atom feeds */
	'%^/atom/newfits\.xml$%D' => ['/src/atom/recentfits.php', ['type' => 'newfits']],
	'%^/atom/recentlyupdated\.xml$%D' => ['/src/atom/recentfits.php', ['type' => 'recentlyupdated']],



	/* Loadout-related operations */
	'%^/import$%D' => '/src/import_loadouts.php',
	'%^/convert%D' => '/src/convert.php',

	'%^/edit/(?<loadoutid>[1-9][0-9]*)$%D' => ['/src/new_loadout.php', ['edit' => 1]],
	'%^/delete/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/delete_fit.php',
	'%^/fork/(?<loadoutid>[1-9][0-9]*)$%D' => ['/src/new_loadout.php', ['fork' => 1]],

	'%^/compare/dps($|/)%D' => '/src/compare_dps.php',
	'%^/internal/compare/dps/ia$%D' => '/src/json/compare_dps_ia.php',



	/* API calls */
    '%^/loadout/(?<import>dna)/(?<dna>[0-9:;]+)$%D' => '/src/view_loadout.php',
    '%^/new/(?<import>dna)/(?<dna>[0-9:;]+)$%D' => '/src/new_loadout.php',

    '%^/api/convert/(?<source_fmt>[1-9][0-9]*|clf|gzclf|evexml|eft|dna|autodetect)/(?<target_fmt>clf|md|evexml|eft|dna)(/.*)?$%D' => '/src/api/convert.php',
	'%^/api$%D' => ['/src/mdstatic.php', ['relative' => '.', 'title' => 'Osmium API', 'f' => 'api.md']],
	'%^/api/json/query_loadouts\.json$%D' => '/src/api/json/query_loadouts.php',



	/* Less common pages */
	'%^/register$%D' => '/src/register.php',
	'%^/logout$%D' => '/src/logout.php',
	'%^/settings$%D' => '/src/settings.php',
	'%^/editskillset/(?<name>.+)$%D' => '/src/edit_skillset.php',
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
	'%^/help/search$%D' => [ '/src/mdstatic.php',
	                         ['relative' => '..', 'title' => 'Search help', 'f' => 'search.md']
	],
	'%^/help/formats%D' => [ '/src/mdstatic.php',
	                         ['relative' => '..', 'title' => 'Loadout formats', 'f' => 'formats.md']
	],

	'%^/editcomment/(?<id>[1-9][0-9]*)$%D' => ['/src/edit_comment.php', ['type' => 'comment']],
	'%^/editcommentreply/(?<id>[1-9][0-9]*)$%D' => ['/src/edit_comment.php', ['type' => 'commentreply']],
	'%^/deletecomment/(?<id>[1-9][0-9]*)$%D' => ['/src/delete_comment.php', ['type' => 'comment']],
	'%^/deletecommentreply/(?<id>[1-9][0-9]*)$%D' => ['/src/delete_comment.php', ['type' => 'commentreply']],

	'%^/favorite/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/toggle_favorite.php',

	'%^/loadouthistory/(?<loadoutid>[1-9][0-9]*)$%D' => '/src/view_loadout_history.php',
	'%^/flagginghistory/(?<accountid>[1-9][0-9]*)$%D' => '/src/view_flagging_history.php',

	'%^/flag/(?<id>[1-9][0-9]*)$%D' => ['/src/cast_flag.php', ['type' => 'loadout']],
	'%^/flagcomment/(?<id>[1-9][0-9]*)$%D' => ['/src/cast_flag.php', ['type' => 'comment']],
	'%^/flagcommentreply/(?<id>[1-9][0-9]*)$%D' => ['/src/cast_flag.php', ['type' => 'commentreply']],

	'%^/moderation/$%D' => '/src/moderation/main.php',
	'%^/moderation/flags$%D' => '/src/moderation/view_flags.php',

	/* Stuff for robots */
	'%^/robots\.txt$%D' => [ '/src/staticpassthrough.php', [ 'f' => 'static/robots.txt',
	                                                         'type' => 'text/plain', ]
	],
	'%^/sitemap\.xml\.gz$%D' => [ '/src/staticpassthrough.php', [ 'f' => 'static/cache/sitemap-root.xml.gz',
	                                                              'type' => 'application/x-gzip',
	                                                              'dontcompress' => true,
	                                                              'mexpire' => 93600, ]
	],
	'%^/sitemap-(?<sitemap>[a-z0-9-]+)\.xml\.gz$%D' => [ '/src/staticpassthrough.php',
	                                                     [ 'type' => 'application/x-gzip',
	                                                       'dontcompress' => true,
	                                                       'mexpire' => 93600, ]
	],
);

$relativeroot = \Osmium\get_ini_setting('relative_path');
$request = '/'.substr(explode('?', $_SERVER['REQUEST_URI'], 2)[0], strlen($relativeroot));

foreach($osmium_dispatch_rules as $rule => $target) {
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
