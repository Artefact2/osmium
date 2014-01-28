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
	osmium_load_common_data();
	osmium_clf_slots = $("div#osmium-data").data('clfslots');
	osmium_capacitors = $("div#osmium-data").data('capacitors');
	osmium_ia = $("div#osmium-data").data('ia');

	osmium_load_static_client_data(osmium_cdatastaticver, function(cdata) {
		osmium_gen();
		osmium_init();
		osmium_tabify($("div#vlmain > ul.tabs"), 0);
		osmium_user_initiated_push(true);
		osmium_undo_push();
	});

	osmium_init_votes();
	osmium_init_comment_replies();
	osmium_init_export();

	$('body').on('click', 'a.confirm', function() {
		return confirm("You are about to do a destructive action.\n\nIt cannot be undone.\n\nContinue?");
	});

	$("h1#vltitle > ul.tags > li.retag").click(function() {
		var t = $(this);
		var ul = t.parent();
		var h1 = ul.parent();

		var form = $(document.createElement('form'));
		form.addClass('retag');
		form.prop('method', 'post');
		form.prop('action', osmium_relative + '/src/json/retag_loadout.php');

		var inp = $(document.createElement('input'));
		inp.prop('type', 'text');
		inp.prop('placeholder', 'Space-separated list of tags…');
		inp.prop('name', 'tags');

		var tags = [];
		ul.children('li').not('.retag').each(function() {
			tags.push($(this).text());
		});

		inp.val(tags.join(' '));
		form.append(inp);

		form.append(
			$(document.createElement('input'))
				.prop('type', 'hidden')
				.prop('name', 'loadoutid')
				.val($("section#ship").data('loadoutid'))
		);

		form.append([
			' ',
			$(document.createElement('input'))
				.prop('type', 'submit')
				.val('Update tags')
		]);

		form.append([
			' ',
			$(document.createElement('a'))
				.addClass('cancel')
				.text('Cancel')
				.click(function() {
					form.remove();
					ul.show();
				})
		]);

		form.submit(function(e) {
			e.preventDefault();
			var hidden = form.find('input[type="submit"], a.cancel');
			var spinner = $(document.createElement('span')).addClass('spinner');
			var postdata = form.serialize();

			hidden.hide();
			form.append(spinner);
			inp.prop('disabled', true);

			$.ajax({
				type: 'POST',
				url: osmium_relative + '/src/json/retag_loadout.php',
				data: postdata,
				dataType: 'json',
				complete: function() {
					inp.prop('disabled', false);
					spinner.remove();
					hidden.show();
				},
				success: function(payload) {
					form.find('p.error_box').remove();

					if("error" in payload) {
						form.append(
							$(document.createElement('p'))
								.addClass('error_box')
								.text(payload.error)
						);
					}

					if("dest" in payload) {
						document.location.replace(osmium_relative + '/' + payload.dest);
					}
				}
			});
		});

		form.hide();
		h1.append(form);
		ul.hide();
		form.show();
		inp.focus();
	});
});

osmium_loadout_readonly = true;
osmium_clftype = 'view';
osmium_on_clf_payload = function(payload) {};

osmium_init = function() {
	osmium_init_ship();
	osmium_init_presets();
	$("section#loadout > section#modules > div.slots > ul > li[data-typeid] span.charge").remove();
	osmium_init_modules();
	osmium_init_fattribs();
	osmium_init_drones();
	osmium_init_implants();
	osmium_init_remote();
};

osmium_gen = function() {
	osmium_gen_modules();
	osmium_gen_fattribs();
	osmium_gen_drones();
	osmium_gen_implants();
	osmium_gen_remote();
};

osmium_init_votes = function() {
	$("section#ship > div.votes > a, section#comments > div.comment > div.votes > a").click(function() {
		var t = $(this);
		var score = t.parent().children('strong');
		var delta = 0;
		var upvoted = t.parent().children('a.upvote').hasClass('voted');
		var downvoted = t.parent().children('a.downvote').hasClass('voted');
		var action;

		if(t.hasClass('voted')) {
			t.removeClass('voted');
			delta += t.hasClass('upvote') ? -1 : 1;
			action = 'rmvote';
		} else {
			t.parent().children('a.voted').each(function() {
				delta += $(this).removeClass('voted').hasClass('upvote') ? -1 : 1;
			});

			t.addClass('voted');
			if(t.hasClass('upvote')) {
				delta += 1;
				action = 'castupvote';
			} else {
				delta += -1;
				action = 'castdownvote';
			}
		}

		score.text(parseInt(score.text(), 10) + delta);

		var targettype = t.parent().data('targettype');
		var opts = {
			targettype: targettype,
			action: action,
			loadoutid: $("section#ship").data('loadoutid')
		};

		if(targettype == 'comment') {
			opts['commentid'] = t.parent().parent().data('commentid');
		}

		$.getJSON(osmium_relative + '/src/json/cast_vote.php', opts, function(data) {
			if(!data['success']) {
				score.text(parseInt(score.text(), 10) - delta);

				if(upvoted) {
					t.parent().children('a.upvote').addClass('voted');
				} else {
					t.parent().children('a.upvote').removeClass('voted');
				}

				if(downvoted) {
					t.parent().children('a.downvote').addClass('voted');
				} else {
					t.parent().children('a.downvote').removeClass('voted');
				}

				var error = $(document.createElement('div'));
				error.addClass('verror');
				error.text(data['error']);
				error.append('<br /><small>(click to close)</small>');
				error.hide();
				error.click(function() {
					$(this).fadeOut(250);
				});
				t.parent().append(error);
				error.fadeIn(250);
			}
		});
	});
};

osmium_init_comment_replies = function() {
	$("section#comments > div.comment > a.add_comment").click(function() {
		$(this).parent().find('ul.replies > li.new').fadeIn(250).find('textarea').focus();
	});
	$("section#comments > div.comment > ul.replies > li.new > form > a.cancel").click(function() {
		$(this).parent().parent().hide().find('textarea').val('');
	});
};

osmium_init_export = function() {
	$("section#export a[type]").click(function(e) {
		e.preventDefault();

		var t = $(this);

		osmium_clfspinner_push();
		$.ajax({
			url: t.attr('href'),
			dataType: 'text',
			success: function(payload) {
				osmium_modal_rotextarea(t.text(), payload);
			},
			error: function() {
				window.location.assign(t.attr('href'));
			},
			complete: function() {
				osmium_clfspinner_pop();
			}
		});
	});
};
