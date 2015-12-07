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

select = function(id) {
	$("input#url, textarea#source, input#file").parent().parent().hide();
	$("#" + id).parent().parent().fadeIn(250);

	$("div#methodselect > ul > li > a.selected").removeClass('selected');
	$("div#methodselect > ul > li > a." + id).addClass('selected');
};

$(function() {
	var ul = $(document.createElement('ul'));

	ul.append(
		$(document.createElement('li')).append(
			$(document.createElement('a'))
				.addClass('source')
				.text('Direct input')
				.click(function() {
					select("source");
				})
		)
	).append(
		$(document.createElement('li')).append(
			$(document.createElement('a'))
				.addClass('url')
				.text('Fetch a URI')
				.click(function() {
					select("url");
				})
		)
	).append(
		$(document.createElement('li')).append(
			$(document.createElement('a'))
				.addClass('file')
				.text('File upload')
				.click(function() {
					select("file");
				})
		)
	);

	//$("div#methodselect").html("<ul><li><a class='source' href='javascript:select(\"source\");'>Direct input</a></li><li><a class='url' href='javascript:select(\"url\");'>Fetch a URI</a></li><li><a class='file' href='javascript:select(\"file\");'>File upload</a></li></ul>");
	$("div#methodselect").append(ul);
	select("source");

	$("div#methodselect > ul > li > a").click(function() {
		$(this).blur();
	});
});