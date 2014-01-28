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

namespace Osmium\ToggleFavorite;

require __DIR__.'/../inc/root.php';

\Osmium\State\assume_logged_in('..');

$loadoutid = intval($_GET['loadoutid']);

if(!isset($_GET['tok']) || $_GET['tok'] != \Osmium\State\get_token()) {
	\Osmium\fatal(400);
}

$accountid = \Osmium\State\get_state('a')['accountid'];

$fav = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
	'SELECT loadoutid FROM osmium.accountfavorites
	WHERE accountid = $1 AND loadoutid = $2',
	array(
		$accountid,
		$loadoutid
	)
));

if($fav === false) {
	if(!\Osmium\State\can_view_fit($loadoutid)) {
		\Osmium\fatal(404);
	}
	$fit = \Osmium\Fit\get_fit($loadoutid);
	if(!\Osmium\State\can_access_fit($fit) || !\Osmium\State\is_fit_green($loadoutid)) {
		\Osmium\fatal(403, "Please view the loadout page first (and eventually enter the password), then retry.");
	}

	\Osmium\Db\query_params(
		'INSERT INTO osmium.accountfavorites
		(accountid, loadoutid, favoritedate)
		VALUES ($1, $2, $3)',
		array(
			$accountid,
			$loadoutid,
			time()
		)
	);
} else {
	/* Allow user to delete a favorite even if he can no longer access
	 * it (to avoid "stale" favorites…) */

	\Osmium\Db\query_params(
		'DELETE FROM osmium.accountfavorites
		WHERE accountid = $1 AND loadoutid = $2',
		array(
			$accountid,
			$loadoutid
		)
	);
}

if(isset($_GET['redirect'])) {
	if($_GET['redirect'] === 'loadout') {
		header('Location: ../'.\Osmium\Fit\fetch_fit_uri($loadoutid)."#meta");
	} else if($_GET['redirect'] === 'profile') {
		header('Location: ../profile/'.$accountid.'#pfavorites');
	}
}
die();
