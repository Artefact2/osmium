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

/*<<< require snippet perfectscrollbar >>>*/
/*<<< require snippet mousetrap >>>*/

osmium_modal_clear = function() {
	$("body > div#modalbg").fadeOut(250);
	$("body > div#modal").animate({
		'margin-top': -$('body').width() + "px"
	}, 250);
}

osmium_modal = function(inside) {
	Mousetrap.unbind('esc');
	$("body > div#modalbg, body > div#modal").remove();

	var bg = $(document.createElement('div'));
	bg.prop('id', 'modalbg');
	bg.click(osmium_modal_clear);
	bg.hide();

	var modal = $(document.createElement('div'));
	modal.prop('id', 'modal');

	$('body')
		.append(bg)
		.append(modal)
	;

	Mousetrap.bind('esc', osmium_modal_clear);

	modal
		.css('margin-left', (-modal.width() / 2) + "px")
		.css('margin-top', -$('body').width() + "px")
		.append(inside)
		.animate({
			'margin-top': (-modal.height() / 2) + "px"
		}, 500)
	;

	modal.perfectScrollbar({ wheelSpeed: 40 });

	bg.fadeIn(500);
};

osmium_modal_rotextarea = function(title, contents) {
	var m = $(document.createElement('div'));
	var h = $(document.createElement('header'));
	var textarea = $(document.createElement('textarea'));

	h.append($(document.createElement('h2')).text(title));
	m.append(h);

	textarea.text(contents);
	textarea.prop('readonly', 'readonly').prop('spellcheck', false);
	textarea.css({
		position: 'absolute',
		top: '0',
		left: '0',
		width: '100%',
		height: '100%',
		'font-size': '0.8em'
	});
	m.append($(document.createElement('div')).css({
		position: 'absolute',
		top: '3.25em',
		left: '1em',
		right: '1em',
		bottom: '1em'
	}).append(textarea));

	osmium_modal(m.children());
	textarea.focus().select();
};
