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
	var lis = $("div#vprivileges > section#privlist > ol > li");

	lis.filter('.haveit, .donthaveit').addClass('abbrev')
		.filter('.haveit').last().removeClass('abbrev')
		.parent().children('li.donthaveit').first().removeClass('abbrev')
	;

	lis.children("h2").click(function() {
		var t = $(this).parent();
		if(t.hasClass('abbrev')) {
			t.switchClass('abbrev', '');
		} else {
			t.switchClass('', 'abbrev', 250);
		}
	});
});
