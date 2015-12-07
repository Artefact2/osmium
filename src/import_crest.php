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

namespace Osmium\Test;

require __DIR__.'/../inc/root.php';
require __DIR__.'/../inc/import-common.php';

const DEFAULT_VP = \Osmium\Fit\VIEW_EVERYONE;
const DEFAULT_EP = \Osmium\Fit\EDIT_OWNER_ONLY;
const DEFAULT_VIS = \Osmium\Fit\VISIBILITY_PRIVATE;
const MAX_FITS = 200;


$a = \Osmium\State\get_state('a');
$p = new \Osmium\DOM\Page();
$ctx = new \Osmium\DOM\RenderContext();
$p->title = 'Import loadouts';
$ctx->relative = '.';

$characterid = \Osmium\State\get_state('import_CharacterID');
$source = \Osmium\State\ccp_oauth_get_fittings($characterid);

if($source !== false) {
	$errors = array();
	$ids = array();
	$fits = false;

		foreach($source["items"] as $list) {
			$fit = \Osmium\State\crest_to_fit($list);
			post_import($fit, $ids, $a, $errors);
		}
	
	if($errors !== []) {
		$p->content->appendCreate('h1', 'Import errors');
		$ol = $p->content->appendCreate('div', [ 'id' => 'import_errors' ])->appendCreate('ol');
		foreach($errors as $e) {
			$ol->appendCreate('li')->appendCreate('code', $e);
		}
	}
	$p->content->appendCreate('h1', 'Import results');
	if($ids !== []) {
		$p->content->append($p->makeLoadoutGridLayout($ids));
	} else {
		$p->content->appendCreate('p', [
			'class' => 'placeholder',
			'No loadouts were imported.',
		]);
	}
}


$p->snippets[] = 'import';
$p->render($ctx);

function post_import(&$fit, &$ids, $a, &$errors) {
	if($fit == false) return;
	$fit['metadata']['view_permission'] = DEFAULT_VP;
	$fit['metadata']['edit_permission'] = DEFAULT_EP;
	$fit['metadata']['visibility'] = DEFAULT_VIS;
	$fittinghash = \Osmium\Fit\get_hash($fit);
	$loadoutid = \Osmium\Db\fetch_row(\Osmium\Db\query_params('SELECT loadoutid FROM osmium.loadouthistory WHERE fittinghash = $1 AND updatedbyaccountid = $2', array($fittinghash,$a['accountid'])));
	if($loadoutid[0] != '') {
		$errors[] = 'Duplicate fit found, ' . $fit['ship']['typename'] . ' / ' . $fit['metadata']['name'] . ', ignored';
		return;
	}
	\Osmium\Fit\sanitize($fit, $errors);
	if(empty($fit['metadata']['name'])) {
		$fit['metadata']['name'] = 'Nameless imported loadout';
		$errors[] = 'Using placeholder name for nameless loadout';
	}
	$ret = \Osmium\Fit\commit_loadout($fit, $a['accountid'], $a['accountid'], $err);
	if($ret === false) {
		$errors[] = 'Error while committing loadout, please report: '.$err;
		return;
	}
	$ids[] = $fit['metadata']['loadoutid'];
}

