<?php
/* Osmium
 * Copyright (C) 2015 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\Fit;

/**
 * Export a loadout to a SVG document that can be embedded on another
 * webpage.
 */
function export_to_svg($fit){
	$d = new \Osmium\DOM\RawPage();

	/* XXX: refactor this! */
	$proto = \Osmium\get_ini_setting('https_available') && \Osmium\get_ini_setting('https_canonical')
		? 'https' : 'http';
	$root = $proto.'://'.\Osmium\get_ini_setting('host').rtrim(\Osmium\get_ini_setting('relative_path'), '/');

	$d->registerCustomElement('o-sprite', function(\Osmium\DOM\Element $e, \Osmium\DOM\RenderContext $ctx) use($root) {
			$d = $e->ownerDocument;
			$svg = $d->createElement('svg');
			$svg->appendChild($image = $d->createElement('image'));

			$i = 0;
			while($i < $e->attributes->length) {
				$attr = $e->attributes->item($i);

				if(in_array($attr->name, [ 'spx', 'spy', 'gridwidth', 'gridheight' ], true)) {
					${$attr->name} = $attr->value;
					++$i;
					continue;
				}

				/* o-sprite attributes go to the <svg> element (most important: width, height) */
				$svg->setAttributeNode($attr);
			}

			if(!isset($spx) || !isset($spy) || (!isset($gridwidth) && !isset($gridheight))) {
				throw new \Exception('not enough parameters: need spx, spy and at least gridwidth or gridheight.');
			}

			if(!isset($gridwidth)) $gridwidth = $gridheight;
			if(!isset($gridheight)) $gridheight = $gridwidth;

			$image->setAttribute('x:href', $root.'/static-'.\Osmium\STATICVER.'/icons/sprite.png');
			$image->setAttribute('x', '0');
			$image->setAttribute('y', '0');
			$image->setAttribute('width', '1024');
			$image->setAttribute('height', '1024');
			$svg->setAttribute('viewBox', implode(' ', [
				$spy * $gridwidth,
				$spx * $gridheight,
				$gridwidth,
				$gridheight,
			]));

			/* o-sprite children to in the <image> element (most important: title) */
			while($e->firstChild) {
				$c = $e->firstChild;
				$e->removeChild($c);
				$image->appendChild($c);
			}

			return $svg;
		});

	$name = \Osmium\get_ini_setting('name');

	/* XXX: does this need escaping? */
	$d->appendChild($d->createProcessingInstruction(
		'xml-stylesheet',
		'type="text/css" href="'.$root.'/static-'.\Osmium\CSS_STATICVER.'/svgl.css"'
	));

	$d->appendChild($svg = $d->element('svg', [
		'xmlns' => 'http://www.w3.org/2000/svg',
		'xmlns:x' => 'http://www.w3.org/1999/xlink',
		'width' => '40em',
		'height' => '25em',
		'viewBox' => '0 0 40 25',
	]));
	$svg->appendCreate(
		'title',
		isset($fit['ship']['typename']) ? $fit['ship']['typename'].' loadout' : 'Loadout'
	);
	$svg->appendCreate(
		'desc',
		'Generated by Osmium '
		.\Osmium\get_osmium_version()
		.".\n\n"
		.$root
		."\n\n"
		.export_to_gzclf($fit)
	);

	$g = $svg->appendCreate('g#bg');

	$g->appendCreate('image', [
		'x:href' => '//image.eveonline.com/Render/'.$fit['ship']['typeid'].'_512.png',
		'x' => '0',
		'y' => '-7.5',
		'width' => '40',
		'height' => '40',
	]);

	$g->appendCreate('rect', [
		'x' => '0',
		'y' => '0',
		'width' => '40',
		'height' => '25',
	]);

	$g->appendCreate('a', [
		'x:href' => $root,
		'target' => '_top',
	])->appendCreate('image#logo', [
		'x:href' => $root.'/static-'.\Osmium\STATICVER.'/favicon.png',
		'x' => '37.5',
		'y' => '0.5',
		'width' => '2',
		'height' => '2',
	])->appendCreate('title', 'Visit the '.$name.' main page');

	$g = $svg->appendCreate('g#head', [
		'transform' => 'translate(.5 .5)'
	]);

	$a = $g->appendCreate('a#ship', [
		'target' => '_top',
		'x:href' => $root.'/db/type/'.$fit['ship']['typeid'],
	]);

	$a->appendCreate('image', [
		'x:href' => '//image.eveonline.com/Render/'.$fit['ship']['typeid'].'_512.png',
		'x' => '0',
		'y' => '0',
		'width' => '5',
		'height' => '5',
	]);

	$a->appendCreate('rect', [
		'x' => '0',
		'y' => '4.2',
		'width' => '5',
		'height' => '.8',
	]);

	$a->appendCreate('text', [
		'x' => '2.5',
		'y' => '4.9',
		$fit['ship']['typename'],
	]);

	$g->appendCreate('text#title', [
		'x' => '5.5',
		'y' => '1.5',
	])->append($fit['metadata']['name']);

	$grp = $g->appendCreate('text#group', [
		'x' => '5.5',
		'y' => '3',
	])->append(get_groupname(get_groupid($fit['ship']['typeid'])));

	if(isset($ship['mode']['typeid'])) {
		$grp->append([ ' (', $ship['mode']['typename'], ')' ]);
	}

	$a = $g->appendCreate('a', [ 'target' => '_top', ]);
	$a->appendCreate('text', [
		'x' => '5.5',
		'y' => '4.2',
		'See this loadout on '.$name,
	]);

	if($_GET['source_fmt'] === 'uri') {
		$a->setAttribute('x:href', $_GET['input']);
	} else if(isset($fit['metadata']['loadoutid'])) {
		$a->setAttribute('x:href', $root.'/'.get_fit_uri(
			$fit['metadata']['loadoutid'],
			$fit['metadata']['visibility'],
			$fit['metadata']['privatetoken']
		));
	} else {
		$a->setAttribute('x:href', $root.'/loadout/dna/'.export_to_dna($fit));
	}

	$mg = $svg->appendCreate('g#modules', [
		'transform' => 'translate(0.5 6)',
	]);

	$stypes = get_slottypes();

	$r = 1.75;
	$padding = 0.25;
	$inc = $padding + 2 * $r;
	$typeiconratio = 1.5;
	foreach([ 'high', 'medium', 'low' ] as $i => $type) {
		$tg = $mg->appendCreate('g.module', [
			'id' => $type,
			'transform' => 'translate('.(($i % 2) ? (.5 * $inc) : 0).' '.($i * $inc * .9).')',
		]);

		$z = 0;
		$available = \Osmium\Dogma\get_ship_attribute($fit, $stypes[$type][3]);
		$makecont = function() use(&$tg, &$z, $r, $inc) {
			$g = $tg->appendCreate('g.module', [
				'transform' => 'translate('.($inc * ($z++)).' 0)',
			]);
			$g->appendCreate('circle', [
				'cx' => (string)$r,
				'cy' => (string)$r,
				'r' => (string)$r,
			]);
			return $g;
		};

		foreach($fit['modules'][$type] as $m) {
			$g = $makecont();

			$g->appendCreate('a', [
				'x:href' => $root.'/db/type/'.$m['typeid'],
				'target' => '_top',
			])->appendCreate('image', [
				'x:href' => '//image.eveonline.com/Type/'.$m['typeid'].'_64.png',
				'width' => (string)($typeiconratio * $r),
				'height' => (string)($typeiconratio * $r),
				'x' => (string)((2 - $typeiconratio) * .5 * $r),
				'y' => (string)((2 - $typeiconratio) * .5 * $r),
			])->appendCreate('title', $m['typename']);
		}

		while($z < 8) {
			$g = $makecont();

			if($z > $available) {
				$g->addClass('nd');
			} else {
				$g->appendCreate('o-sprite', [
					'width' => (string)($typeiconratio * $r),
					'height' => (string)($typeiconratio * $r),
					'x' => (string)((2 - $typeiconratio) * .5 * $r),
					'y' => (string)((2 - $typeiconratio) * .5 * $r),
					'spx' => $stypes[$type][1][0],
					'spy' => $stypes[$type][1][1],
					'gridwidth' => $stypes[$type][1][2],
					'gridheight' => $stypes[$type][1][3],
				])->appendCreate('title', 'Unused '.$type.' slot');
			}
		}
	}

	$d->finalize(new \Osmium\DOM\RenderContext());
	return $d->saveXML();
}