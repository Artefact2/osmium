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

$(function() {
	var fbdiv = $(document.createElement('div')).prop('id', 'glob_feedback');
	var ul = $(document.createElement('ul'));

	fbdiv.append($(document.createElement('span')).text('Feedback'));
	fbdiv.append(ul);

	ul.append(
		$(document.createElement('li')).append(
			$(document.createElement('a'))
				.prop('href', 'https://github.com/Artefact2/osmium/issues/new')
				.text('Report an issue')
		)
	);

	ul.append(
		$(document.createElement('li')).append(
			$(document.createElement('a'))
				.prop('href', 'http://irc.lc/coldfront/osmium/osmiumguest@@@')
				.text('Live chat')
		)
	);

	ul.append(
		$(document.createElement('li')).append(
			$('footer a[rel="help"]').clone().text('Help & FAQ')
		)
	);

	$('div#wrapper').append(fbdiv);
	fbdiv.children('span').first().on('click', function() {
		if(fbdiv.hasClass('extended')) {
			fbdiv.switchClass('extended', '', 500);
		} else {
			fbdiv.switchClass('', 'extended', 500);
		}
	});
});
