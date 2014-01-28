/* Osmium
 * Copyright (C) 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

osmium_append_loadoutid_uri = function(append) {
	var public_re = /\/loadout\/([1-9][0-9]*)([^?#\/]*)/;
	var private_re = /\/loadout\/private\/([1-9][0-9]*)([^?#\/]*)/;
	var m;

	window.location.hash = '';

	if((m = window.location.href.match(public_re)) !== null) {
		window.location.replace(window.location.href.replace(public_re, "/loadout/$1" + append));
	} else if((m = window.location.href.match(private_re)) !== null) {
		window.location.replace(window.location.href.replace(private_re, "/loadout/private/$1" + append));
	}
};

osmium_init_presets = function() {
	/* TODO: don't make a page reload, instead replace the URI with
	 * the history API and get the new formatted preset descriptions
	 * from the CLF update payload, and correctly set the preset
	 * descriptions in osmium_gen_presets() */

	$('section#presets select#spreset').change(function() {
		osmium_append_loadoutid_uri('P' + $(this).val() + 'D' + osmium_clf['X-Osmium-current-dronepresetid']);
	});

	$('section#presets select#scpreset').change(function() {
		osmium_append_loadoutid_uri(
			'P' + osmium_clf['X-Osmium-current-presetid']
				+ 'C' + $(this).val()
				+ 'D' + osmium_clf['X-Osmium-current-dronepresetid']
		);
	});

	$('section#presets select#sdpreset').change(function() {
		osmium_append_loadoutid_uri('P' + osmium_clf['X-Osmium-current-presetid'] + 'D' + $(this).val());
	});
};
