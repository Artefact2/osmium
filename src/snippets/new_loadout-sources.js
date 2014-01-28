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

osmium_init_sources = function() {
	$('div#nlsources > section#search > form > ul.filters, div#nlsources > section#browse > ul.filters, div#nlsources > section#shortlist > ul.filters').each(function() {
		var t = $(this);
		var li, input, a;

		for(var id in osmium_metagroups) {
			li = $(document.createElement('li'));

			input = $(document.createElement('input'));
			input.prop('type', 'hidden');
			input.prop('name', 'mg[' + id + ']');
			input.val('1');
			input.data('metagroupid', id);
			li.append(input);

			a = $(document.createElement('a'));
			a.addClass('metagroup');
			a.addClass('mg' + id);
			a.text(osmium_metagroups[id]);
			a.prop('title', 'Filter ' + osmium_metagroups[id] + ' types');
			li.append(a);

			t.append(li);
		}
	}).find('a.metagroup').click(function() {
		var t = $(this).parent().children('input:first-child');
		if(t.val() === '1') {
			t.val('0');
		} else {
			t.val('1');
		}

		$(this).closest('form').find('input[type="search"]').trigger('mark-dirty');
		$(this).blur();
	});

	$('div#nlsources > section#search > form').submit(function() {
		var t = $(this);
		var s = t.find('input[type="submit"]');
		var ul = t.parent().children('ul.results');
		var li, img, p, spinner;

		if(t.data('dirty') === false) {
			ul.find('li').first().trigger('dblclick');
			return false;
		}
		t.data('dirty', false);

		if(s.prop('disabled')) return false;
		s.prop('disabled', true);
		ul.empty();
		t.parent().children('p.notice').remove();
		ul.after(
			spinner = $(document.createElement('p'))
				.addClass('loading')
				.addClass('placeholder')
				.text("Searching types…")
				.append(
					$(document.createElement('div'))
						.addClass('spinner')
				)
		);

		var data = {};
		t.find('input[type="hidden"][name][value]').each(function() {
			var i = $(this);
			data[i.prop('name')] = i.val();
		});

		$.ajax({
			type: 'POST',
			dataType: 'json',
			url: "../../internal/searchtypes/" + encodeURIComponent(t.find("input[name='q']").val())
				.replace(/%20/g, "+"),
			data: data,
			success: function(json) {
				for(var i in json.payload) {
					var m = osmium_types[json.payload[i]];
					li = $(document.createElement('li'));
					li.addClass('module');
					li.data('typeid', m[0]);
					li.data('category', m[2]);
					li.data('subcategory', m[3]);
					li.addClass('mg' + m[4]);
					li.text(m[1]);
					li.prop('title', m[1]);

					img = $(document.createElement('img'));
					img.prop('alt', '');
					img.prop('src', '//image.eveonline.com/Type/' + m[0] + '_64.png');
					li.prepend(img);

					osmium_add_non_shortlist_contextmenu(li);

					ul.append(li);
				}

				if('warning' in json) {
					p = $(document.createElement('p'));
					p.addClass('placeholder');
					p.addClass('notice');
					p.html(json.warning);

					ul.after(p);
				}
			},
			complete: function() {
				s.prop('disabled', false);
				spinner.remove();
				s.val('Add');
			}
		});

		return false;
	}).find('input[type="search"]').on('mark-dirty', function() {
		$(this)
			.closest('form').data('dirty', true)
			.find('input[type="submit"]').val('Search')
		;
	}).on('change', function() {
		$(this).trigger('mark-dirty');
	}).on('keydown', function(e) {
		if(e.which != 13) {
			$(this).trigger('mark-dirty');
		}
	});

	osmium_add_metagroup_style("div#nlsources > section#browse > ", "div#nlsources > section#browse > div.mgroot");
	osmium_add_metagroup_style("div#nlsources > section#shortlist > ", "div#nlsources > section#shortlist > ul");

	osmium_init_browser();
	osmium_init_shortlist();

	$("div#nlsources > section").on('osmium-update-overflow', function() {
		var s = $(this);
		var mh;

		s.css(
			'max-height',
			(mh = ($(window).height() - $("div#nlsources").offset().top - 64)) + "px"
		);

		if(s[0].scrollHeight >= mh) {
			if(s.children('div.ps-scrollbar-y-rail').length === 0) {
				s.perfectScrollbar({
					wheelSpeed: 40,
					suppressScrollX: true
				});
			} else {
				s.perfectScrollbar('update');
			}
		} else {
			s.perfectScrollbar('destroy');
		}
	});

	$(window).resize(function() { $("div#nlsources > section").trigger('osmium-update-overflow'); });
	$("div#nlsources > section").trigger('osmium-update-overflow');
};

osmium_init_browser = function() {
	var section = $('div#nlsources > section#browse');
	var div, displaymg;

	div = $(document.createElement('div'));
	div.addClass('mgroot');

	var displaymg = function(parent, mg) {
		var ul, li, img, heading;

		if("children" in osmium_groups[mg] && osmium_groups[mg].children.length > 0) {
			ul = $(document.createElement('ul'));
			ul.addClass('children');

			for(var i in osmium_groups[mg].children) {
				li = $(document.createElement('li'));
				li.addClass('uninitialized');
				li.addClass('folded');
				li.data('mgid', osmium_groups[mg].children[i]);

				heading = $(document.createElement('h4'));
				heading.text(osmium_groups[osmium_groups[mg].children[i]].name);

				li.append(heading);
				ul.append(li);
			}

			parent.append(ul);
		}

		if("types" in osmium_groups[mg] && osmium_groups[mg].types.length > 0) {
			ul = $(document.createElement('ul'));
			ul.addClass('types');

			for(var i in osmium_groups[mg].types) {
				var type = osmium_types[osmium_groups[mg].types[i]];

				li = $(document.createElement('li'));
				li.addClass('module');
				li.data('typeid', type[0]);
				li.text(type[1]);
				li.prop('title', type[1]);
				li.data('category', type[2]);
				li.data('subcategory', type[3]);
				li.addClass('mg' + type[4]);

				img = $(document.createElement('img'));
				img.prop('src', '//image.eveonline.com/Type/' + type[0] + '_64.png');
				img.prop('alt', '');
				li.prepend(img);

				osmium_add_non_shortlist_contextmenu(li);

				ul.append(li);
			}

			parent.append(ul);
		}
	};
	displaymg(div, 'root');

	section.children('p.placeholder.loading').remove();
	section.append(div);

	section.on('click', 'li.uninitialized', function() {
		var t = $(this);
		t.removeClass('uninitialized');
		displaymg(t, t.data('mgid'));
	});

	section.on('click', 'li', function(e) {
		var t = $(this);

		t.toggleClass('folded');
		e.stopPropagation();

		if(t.parent().children('li:not(.folded)').length > 0) {
			t.parent().addClass('partiallyunfolded');
		} else {
			t.parent().removeClass('partiallyunfolded');
		}

		t.closest('section').trigger('osmium-update-overflow');
	});
};

osmium_init_shortlist = function() {
	var ul, li, img, section;

	section = $('div#nlsources > section#shortlist');
	section.children('p.placeholder.loading').remove();

	ul = $(document.createElement('ul'));
	ul.addClass('types');

	for(var i in osmium_shortlist) {
		var type = osmium_types[osmium_shortlist[i]];

		li = $(document.createElement('li'));
		li.addClass('module');
		li.data('typeid', type[0]);
		li.data('category', type[2]);
		li.data('subcategory', type[3]);
		li.addClass('mg' + type[4]);
		li.text(type[1]);
		li.prop('title', type[1]);

		img = $(document.createElement('img'));
		img.prop('alt', '');
		img.prop('src', '//image.eveonline.com/Type/' + type[0] + '_64.png');
		li.prepend(img);

		osmium_add_shortlist_contextmenu(li);

		ul.append(li);
	}

	section.append(ul);
};

osmium_add_metagroup_style = function(aselector, liselector) {
	$(aselector + " ul.filters > li > a.metagroup").click(function() {
		var t = $(this).parent().children('input:first-child');
		var mg = t.data('metagroupid');
		var v = t.val();

		if(v === '1') {
			$('style.mg' + mg).remove();
		} else {
			var style = $(document.createElement('style'));
			style.prop('type', 'text/css');
			style.addClass('mg' + mg);
			style.text(liselector + ' li.module.mg' + mg + ' { display: none; }');
			$('head').append(style);
		}
	});
};

osmium_add_add_to_fit_option = function(menu, source, opts) {
	if(osmium_loadout_readonly) return;

	var name;
	var cat = source.data('category');
	var typeid = source.data('typeid');

	if(cat === 'ship') name = 'Use ship';
	else if(cat === 'module') name = 'Fit module';
	else name = 'Add ' + cat;

	osmium_ctxmenu_add_option(menu, name, function() {
		source.data({ qty: null, dest: null });
		osmium_add_to_clf(source);
	}, opts);

	if(cat === 'drone') {
		osmium_ctxmenu_add_separator(menu);

		osmium_ctxmenu_add_option(menu, 'Add 1 to bay', function() {
			source.data({ qty: 1, dest: 'bay' });
			osmium_add_to_clf(source);
		}, { });

		osmium_ctxmenu_add_option(menu, 'Add 5 to bay', function() {
			source.data({ qty: 5, dest: 'bay' });
			osmium_add_to_clf(source);
		}, { });

		osmium_ctxmenu_add_option(menu, 'Add 1 to space', function() {
			source.data({ qty: 1, dest: 'space' });
			osmium_add_to_clf(source);
		}, { });

		osmium_ctxmenu_add_option(menu, 'Add 5 to space', function() {
			source.data({ qty: 5, dest: 'space' });
			osmium_add_to_clf(source);
		}, { });
	}

	if(cat === 'module' && osmium_types[typeid][8] === 1) {
		osmium_ctxmenu_add_option(menu, 'Project on local', function() {
			$("a[href='#remote']").click();
			osmium_add_projected(typeid + '::', 'local');
		}, { });
	}

	osmium_ctxmenu_add_separator(menu);
};

osmium_add_generic_showinfo = function(menu, typeid) {
	osmium_ctxmenu_add_option(menu, "Show info", function() {
		osmium_showinfo({
			type: 'generic',
			typeid: typeid
		});
	}, { icon: osmium_showinfo_sprite_position });
};

osmium_add_generic_browse_mg = function(menu, typeid, opts) {
	if(opts === undefined) opts = {};

	var title = ("title" in opts) ? opts.title : "Browse market group";

	osmium_ctxmenu_add_option(menu, title, function() {
		var mgroot = $("div#nlsources > section#browse div.mgroot");
		var groupid = osmium_types[typeid][9];
		var stack = [];
		var lasth4;

		while(groupid !== undefined) {
			stack.push(groupid);
			groupid = osmium_groups[groupid].parent;
		}

		mgroot.find('ul.children > li').not('.folded').children('h4').click();

		while((groupid = stack.pop()) !== undefined) {
			lasth4 = mgroot.find('ul.children > li').filter(function() {
				return $(this).data('mgid') === groupid;
			}).children('h4').click();
		}

		$("div#nlsources ul.tabs > li > a[href='#browse']").click();
		mgroot.parent()
			.scrollTop(0)
			.scrollTop(lasth4.offset().top - mgroot.parent().offset().top)
			.trigger('osmium-update-overflow')
		;
	}, { icon: [ 0, 11, 64, 64 ] });
};

osmium_add_non_shortlist_contextmenu = function(li) {
	osmium_ctxmenu_bind(li, function() {
		var menu = osmium_ctxmenu_create();

		osmium_add_add_to_fit_option(menu, li, { 'default': true });
		osmium_ctxmenu_add_option(menu, "Add to shortlist", function() {
			if($('div#nlsources > section#shortlist > ul.types > li.module').filter(function() {
				return $(this).data('typeid') === li.data('typeid');
			}).length >= 1) {
				return;
			}

			var n = li.clone(true).unbind();
			n.find('span.metalevel, span.slot').remove();
			osmium_add_shortlist_contextmenu(n);

			$('div#nlsources > section#shortlist > ul.types').append(n);
			osmium_commit_shortlist();
		}, {});
		osmium_ctxmenu_add_separator(menu);
		osmium_add_generic_browse_mg(menu, li.data('typeid'));
		osmium_add_generic_showinfo(menu, li.data('typeid'));

		return menu;
	});
};

osmium_add_shortlist_contextmenu = function(li) {
	osmium_ctxmenu_bind(li, function() {
		var menu = osmium_ctxmenu_create();

		osmium_add_add_to_fit_option(menu, li, { 'default': true });
		osmium_ctxmenu_add_option(menu, "Remove from shortlist", function() {
			li.remove();
			osmium_commit_shortlist();
		}, {});
		osmium_ctxmenu_add_separator(menu);
		osmium_add_generic_browse_mg(menu, li.data('typeid'));
		osmium_add_generic_showinfo(menu, li.data('typeid'));

		return menu;
	});
};

osmium_commit_shortlist = function() {
	var opts = {
		token: osmium_token
	};

	$("div#nlsources > section#shortlist > ul.types > li.module").each(function() {
		var t = $(this);
		opts[t.index()] = t.data('typeid');
	});

	$.get("../src/json/shortlist_modules.php", opts);
};
