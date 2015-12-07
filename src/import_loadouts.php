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

namespace Osmium\Page\Convert;

require __DIR__.'/../inc/root.php';
require __DIR__.'/../inc/import-common.php';

const DEFAULT_VP = \Osmium\Fit\VIEW_EVERYONE;
const DEFAULT_EP = \Osmium\Fit\EDIT_OWNER_ONLY;
const DEFAULT_VIS = \Osmium\Fit\VISIBILITY_PRIVATE;
const MAX_FITS = 100;

if(!\Osmium\State\is_logged_in()) {
	$_POST['editimport'] = 'on';
}

if(isset($_POST['ccpssoinit'])) {

\Osmium\State\ccp_oauth_redirect(['action' => 'import',], 'characterFittingsRead characterFittingsWrite');

}

if(isset($_POST['importcrest'])) {

header ("Location: ../importcrest"); 

}


$a = \Osmium\State\get_state('a');

$p = new \Osmium\DOM\Page();
$ctx = new \Osmium\DOM\RenderContext();
$p->title = 'Import loadouts';
$ctx->relative = '.';

$source = \Osmium\Import\get_source($p, 'source', 'url', 'file');
if($source !== false) {
	$format = $_POST['format'];
	$errors = array();
	$ids = array();
	$fits = false;

	$importfmts = \Osmium\Fit\get_import_formats();
	if(isset($importfmts[$format])) {
		$fits = $importfmts[$format][2]($source, $errors);
	}

	if($fits !== false) {
		$fits = array_slice($fits, 0, MAX_FITS);

		foreach($fits as &$fit) {
			post_import($fit, $ids, $a, $errors);
		}
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



$p->content->appendCreate('h1', 'Import loadouts');
$form = $p->content->appendCreate('o-form', [
	'method' => 'post',
	'o-rel-action' => '/import',
]);

$tbody = $form->appendCreate('table')->appendCreate('tbody');

$select = $p->element('select', [ 'name' => 'format', 'id' => 'format' ]);
foreach(\Osmium\Fit\get_import_formats() as $k => $f) {
	$select->appendCreate('option', [
		'value' => $k,
		$f[0].' ('.$f[1].')'
	]);
}

$tbody->appendCreate('tr', [
	[ 'th', [[ 'label', [ 'for' => 'format', 'Input format' ] ]] ],
	[ 'td', $select ],
]);

$tbody->append(\Osmium\Import\make_tri_choice($p, 'source', 'url', 'file'));

$checkbox = $p->element('o-input', [
	'type' => 'checkbox',
	'id' => 'editimport',
	'name' => 'editimport',
]);

if(!\Osmium\State\is_logged_in()) {
	$checkbox->setAttribute('remember', 'off');
	$checkbox->setAttribute('disabled', 'disabled');
	$checkbox->setAttribute('checked', 'checked');
}

$tbody->appendCreate('tr', [
	[ 'th' ], [ 'td', [
		$checkbox,
		[ 'label', [ 'for' => 'editimport', 'Immediately edit the first loadout (instead of saving them all)' ] ]
	]],
]);

$tbody->appendCreate('tr', [
	[ 'th' ], [ 'td', [
		[ 'input', [ 'type' => 'submit', 'value' => 'Import' ] ]
	]],
]);

if(\Osmium\get_ini_setting('ccp_oauth_available') AND \Osmium\State\is_logged_in()) {

$p->content->appendCreate('h1', 'Import CREST loadouts');

$tbody = $p->content->appendCreate('o-form', [
	'o-rel-action' => $_SERVER['REQUEST_URI'],
	'method' => 'post',
])->appendCreate('table')->appendCreate('tbody');

$tr = $tbody->appendCreate('tr');

$tr->appendCreate('td')->appendCreate('input', [
		'name' => 'ccpssoinit',
		'value' => 'Sign in with an EVE character',
		'type' => 'submit',
	]);

if(\Osmium\State\get_state('import_CharacterID') != ''){

$tr = $tbody->appendCreate('tr');

$characterid = \Osmium\State\get_state('import_CharacterID');
$charactername = \Osmium\State\get_state('import_CharacterName');

$tr->appendCreate('tr.separator');

$tr->appendCreate('td')->append([[ 'o-eve-img', [ 'src' => '/Character/'.$characterid.'_64.jpg','alt' => $charactername , 'title' => $charactername  ] ]
	])->append( ' ' . $charactername . ' ')->appendCreate('input', [
		'id' => 'importcrest',
		'name' => 'importcrest',
		'value' => 'Fetch CREST fits',
		'characterid' => $characterid,
		'type' => 'submit',
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

	if(isset($_POST['editimport']) && $_POST['editimport']) {
		/* Do not commit the loadout */
		$tok = \Osmium\State\get_unique_loadout_token();
		\Osmium\State\put_loadout($tok, $fit);
		header('Location: ./new/'.$tok);
		die();
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
