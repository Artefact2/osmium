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

osmium_gen_remote = function() {
	osmium_gen_fleet();
	osmium_gen_projected();
};

osmium_gen_fleet = function() {
	var selects = $("section#fleet select");
	selects.empty();

	for(var i = 0; i < osmium_skillsets.length; ++i) {
		selects.append(
			$(document.createElement('option'))
				.text(osmium_skillsets[i])
				.prop('value', osmium_skillsets[i])
		);
	}

	$("section#fleet").find('input, select')
		.not("[type='checkbox']").prop('disabled', true);

	if(!("X-Osmium-fleet" in osmium_clf)) {
		osmium_clf['X-Osmium-fleet'] = {};
	}

	for(var t in osmium_clf['X-Osmium-fleet']) {
		$("section#fleet select#" + t + "_skillset").val(osmium_clf['X-Osmium-fleet'][t].skillset);
		$("section#fleet input#" + t + "_fit").val(osmium_clf['X-Osmium-fleet'][t].fitting);
		$("section#fleet input#" + t + "_enabled").prop('checked', true).change();
		$("section#fleet").find("input, select").filter("." + t).prop('disabled', false);
	}
};

osmium_projected_clean = function() {
	jsPlumb.unbind();
	jsPlumb.doWhileSuspended(function() {
		jsPlumb.detachEveryConnection();
		jsPlumb.deleteEveryEndpoint();
		jsPlumb.unmakeEverySource();
		jsPlumb.unmakeEveryTarget();
	});
}

osmium_gen_projected = function() {
	jsPlumb.Defaults.Container = $("section#projected");
	jsPlumb.Defaults.Endpoints = [ "Blank", "Blank" ];

	osmium_projected_clean();

	jsPlumb.bind("connection", function(info) {
		var src = $(info.source), tgt = $(info.target);

		if(src.closest('div.pr-loadout').get(0) === tgt.get(0)) {
			/* Disallow self-projection. While libdogma can handle it
			 * in theory, it doesn't make a lot of sense to allow it
			 * since real dogma dosen't allow it. */
			jsPlumb.detach(info.connection);
			return;
		}

		/* A module can only target one entity at a time, so remove
		 * other connections */
		jsPlumb.doWhileSuspended(function() {
			jsPlumb.select({ source: info.source }).each(function(conn) {
				if(conn !== info.connection) {
					jsPlumb.detach(conn);
				}
			});
		});

		/* Update the target in CLF */
		var tkey = tgt.data('key');
		var skey = src.closest('div.pr-loadout').data('key');
		var stid = src.data('typeid');
		var sidx = src.data('index');
		var clf;

		if(skey === 'local') {
			clf = osmium_clf;
		} else {
			clf = osmium_clf['X-Osmium-remote'][skey];
		}

		var m = clf.presets[clf['X-Osmium-current-presetid']].modules;
		for(var i = 0; i < m.length; ++i) {
			if(m[i].typeid === stid && m[i].index === sidx) {
				m[i]['X-Osmium-target'] = tkey;
				osmium_commit_undo_deferred();
				src.addClass('hastarget');
				src.append(
					$(document.createElement('div'))
						.addClass('bghue')
						.css('background-color', src.closest('div.pr-loadout').css('border-color'))
				);
				return;
			}
		}

		alert('Could not create connection in CLF – please report!');
	});
	jsPlumb.bind("connectionDetached", function(info) {
		var src = $(info.source);

		/* Delete the target in CLF */
		var skey = src.closest('div.pr-loadout').data('key');
		var stid = src.data('typeid');
		var sidx = src.data('index');
		var clf;

		if(skey === 'local') {
			clf = osmium_clf;
		} else {
			clf = osmium_clf['X-Osmium-remote'][skey];
		}

		var m = clf.presets[clf['X-Osmium-current-presetid']].modules;
		for(var i = 0; i < m.length; ++i) {
			if(m[i].typeid === stid && m[i].index === sidx) {
				m[i]['X-Osmium-target'] = false;
				osmium_commit_undo_deferred();
				src.children('div.bghue').remove();
				src.removeClass('hastarget');
				return;
			}
		}

		alert('Could not delete connection in CLF – please report!');
	});

	var list = $("section#projected form#projected-list");
	list.empty();

	var local = osmium_create_projected("local", osmium_clf, 0);
	list.append(local);

	if(!("X-Osmium-remote" in osmium_clf)) {
		osmium_clf['X-Osmium-remote'] = {};
	}

	var c = 0;
	var remotes = {};
	remotes.local = osmium_clf;

	for(var key in osmium_clf['X-Osmium-remote']) {
		var pfit = osmium_clf['X-Osmium-remote'][key];
		list.append(osmium_create_projected(key, pfit, ++c));
		remotes[key] = pfit;
	}

	for(var key in remotes) {
		var pclf = remotes[key];

		if(!("X-Osmium-current-presetid" in pclf)) continue;
		if(!("presets" in pclf)) continue;
		if(!(pclf['X-Osmium-current-presetid'] in pclf.presets)) continue;
		if(!("modules" in pclf.presets[pclf['X-Osmium-current-presetid']])) continue;

		var modules = pclf.presets[pclf['X-Osmium-current-presetid']].modules;

		for(var i = 0; i < modules.length; ++i) {
			var m = modules[i];
			if("X-Osmium-target" in m && m['X-Osmium-target'] !== false) {
				var source = $('section#projected div.pr-loadout.projected-' + key)
					.find('li').filter(function() {
						var li = $(this);
						return li.data('typeid') === m.typeid && li.data('index') === m.index
					}).first();

				var target = $('section#projected div.pr-loadout.projected-' + m['X-Osmium-target']);

				if(source.length !== 1) {
					alert('Could not find source from CLF connection, please report!');
					continue;
				}
				if(target.length !== 1) {
					alert('Could not find target from CLF connection, please report!');
					continue;
				}

				jsPlumb.connect({ source: source, target: target });
			}
		}
	}
};

osmium_init_remote = function() {
	osmium_init_fleet();
	osmium_init_projected();
};

osmium_init_fleet = function() {
	$("section#fleet").on('change', "input[type='checkbox']", function() {
		var c = $(this);
		var tr = c.closest('tr');
		var table = tr.closest('table');
		var type = tr.data('type');

		if(!("X-Osmium-fleet" in osmium_clf)) {
			osmium_clf['X-Osmium-fleet'] = {};
		}
		var fleet = osmium_clf['X-Osmium-fleet'];

		if(c.is(':checked')) {
			fleet[type] = {
				skillset: table.find('select#' + type + '_skillset').val(),
				fitting: table.find('input#' + type + '_fit').val()
			};
			table.find('input, select')
				.filter('.' + type).prop('disabled', false);
		} else {
			delete(fleet[type]);
			table.find('input, select')
				.filter('.' + type).not("[type='checkbox']")
				.prop('disabled', true);
		}

		if(osmium_user_initiated) {
			osmium_undo_push();
			osmium_commit_clf();
		}
	}).on('change', 'select', function() {
		var s = $(this);
		var tr = s.closest('tr');
		var table = tr.closest('table');
		var checkbox = table.find("input#" + tr.data('type') + "_enabled");

		if(!checkbox.is(':checked')) {
			return;
		}

		checkbox.trigger('change');
	}).on('click', 'input.set', function() {
		var s = $(this);
		var tr = s.closest('tr');
		var table = tr.closest('table');
		var checkbox = table.find("input#" + tr.data('type') + "_enabled");

		if(!checkbox.is(':checked')) {
			return;
		}

		checkbox.trigger('change');
	}).on('click', 'input.clear', function() {
		var s = $(this);
		var tr = s.closest('tr');
		var table = tr.closest('table');
		var checkbox = table.find("input#" + tr.data('type') + "_enabled");

		if(!checkbox.is(':checked')) {
			return;
		}

		table.find('input#' + tr.data('type') + '_fit').val('');
		checkbox.trigger('change');
	}).on('keypress', 'input.fit', function(e) {
		if(e.which != 13) return;
		e.preventDefault();

		var s = $(this);
		var tr = s.closest('tr');
		var table = tr.closest('table');
		var checkbox = table.find("input#" + tr.data('type') + "_enabled");

		if(!checkbox.is(':checked')) {
			return false;
		}

		checkbox.trigger('change');
		return false;
	});
};

osmium_add_projected = function(remotefit, target) {
	if(!("X-Osmium-remote" in osmium_clf)) {
		osmium_clf['X-Osmium-remote'] = {};
	}

	var key = 1;
	while((key + '') in osmium_clf['X-Osmium-remote']) ++key;
	key = key + '';

	osmium_clf['X-Osmium-remote'][key] = {
		fitting: '',
		skillset: 'All V'
	};

	var newproj = osmium_create_projected(
		key, { },
		$("section#projected > form#projected-list > div.pr-loadout").length
	);

	$("section#projected > form#projected-list").append(newproj);

	if(remotefit !== undefined) {
		osmium_clf['X-Osmium-remote'][key].fitting = remotefit;
	}

	if(remotefit !== undefined && target !== undefined) {
		var target = $("section#projected div.pr-loadout.projected-" + target);
		osmium_projected_regen_remote(key, function() {
			$("section#projected div.pr-loadout.projected-" + key).find('ul > li').each(function() {
				jsPlumb.connect({ source: $(this), target: target });
			});
		});
	} else {
		osmium_commit_undo_deferred();
	}

	return newproj;
};

osmium_init_projected = function() {
	if(!osmium_loadout_readonly) {
		$("section#projected input#createprojected").on('click', function() {
			osmium_add_projected('').trigger('dblclick');
		});
	}

	$("section#projected input#projectedfstoggle").on('click', function() {
		var section = $("section#projected");
		var fs = section.hasClass('fs');

		$("body").scrollTop(0);

		if(fs) {
			$("div#fsbg").remove();
		} else {
			var bg = $(document.createElement('div'));
			bg.prop('id', 'fsbg');
			$("section#projected").append(bg);
		}

		jsPlumb.doWhileSuspended(function() {
			jsPlumb.toggleDraggable(
				section.find('div.pr-loadout')
					.draggable("option", "disabled", fs)
			);

			section.toggleClass('fs');

			/* Swap fixed and draggable positions */
			$("section#projected div.pr-loadout").each(function() {
				var t = $(this);
				var otop = t.css('top');
				var oleft = t.css('left');
				t.css('top', t.data('top')).data('top', otop);
				t.css('left', t.data('left')).data('left', oleft);
			});

			/* Auto-rearrange if necessary */
			var localtop = $("section#projected div.pr-loadout.projected-local").css('top');
			if(section.hasClass('fs') && (!localtop || localtop === 'auto')) {
				if($("section#projected div.pr-loadout").length <= 8) {
					$("section#projected a#rearrange-circle").click();
				} else {
					$("section#projected a#rearrange-grid").click();
				}
			}
		});
	});

	$("section#projected a#rearrange-circle").on('click', function() {
		var s = $("section#projected");
		var f = s.children("form#projected-list");

		var so = s.offset();
		var mx = ($(window).width() - so.left) / 2;
		var my = ($(window).height() - so.top) / 2;
		var m = Math.min(mx, my);
		var divs = f.find('div.pr-loadout');

		jsPlumb.doWhileSuspended(function() {
			divs.each(function() {
				var d = $(this);
				var angle = d.index() / divs.length * 2 * Math.PI;
				var w = d.width();
				var h = d.height();

				d.offset({
					left: (so.left + mx - (m - d.outerWidth() / 2 - 32) * Math.cos(angle) - w / 2).toFixed(0),
					top: (so.top + my - (m - d.outerHeight() / 2 - 32) * Math.sin(angle) - h / 2).toFixed(0)
				});
			});
		});
	});

	$("section#projected a#rearrange-grid").on('click', function() {
		var s = $("section#projected");
		var f = s.children("form#projected-list");

		var so = s.offset();
		var mx = ($(window).width() - so.left);
		var my = ($(window).height() - so.top - 30);
		var divs = f.find('div.pr-loadout');

		var maxw = 1, maxh = 1;
		var rows = 1, cols = 1;
		var cellw = 1, cellh = 1;

		divs.each(function() {
			var d = $(this);
			var w = d.outerWidth();
			var h = d.outerHeight();

			if(w > maxw) maxw = w;
			if(h > maxh) maxh = h;
		});

		/* Add some padding */
		maxw += 50;
		maxh += 50;

		var maxrows = Math.max(1, Math.floor(my / maxh));
		var maxcols = Math.max(1, Math.floor(mx / maxw));

		while((rows * cols) < divs.length) {
			if(cols < maxcols) {
				++cols;
			}

			if((rows * cols) >= divs.length) break;

			++rows;
		}

		if(rows <= maxrows) {
			/* Everything can fit */
			cellw = Math.floor(mx / cols);
			cellh = Math.floor(my / rows);
		} else {
			/* Not enough space, use vertical scrolling */
			cols = Math.max(1, Math.floor(mx / maxw));
			cellw = Math.floor(mx / cols);
			cellh = maxh;
		}

		jsPlumb.doWhileSuspended(function() {
			divs.each(function() {
				var d = $(this);
				var i = d.index();

				d.offset({
					left: (so.left + (i % cols) * cellw + 10 * Math.cos(7 * i)
						   + cellw / 2 - d.width() / 2).toFixed(0),
					top: (so.top + 30 + Math.floor(i / cols) * cellh + 10 * Math.sin(7 * i)
						  + cellh / 2 - d.height() / 2).toFixed(0)
				});
			});
		});
	});
};

osmium_create_projected = function(key, clf, index) {
	var proj = $(document.createElement('div'));
	var ul = $(document.createElement('ul'));

	var angle;
	if(index === undefined) {
		angle = (360 * Math.random()).toFixed(0);
	} else if(index === 0) {
		angle = 0;
	} else {
		if(index < 1 || typeof index !== 'number') throw 'Invalid index parameter';

		var low = 1;
		var up = 2;

		while(!(low <= index && index < up)) {
			low = up;
			up = up + up;
		}

		angle = (360 * (2 * (index - low) + 1) / up).toFixed(0);
	}

	proj.data('hue', angle);
	proj.addClass('projected-' + key);
	proj.data('key', key);
	proj.addClass('pr-loadout');
	proj.css('border-color', 'hsl(' + angle + ', 25%, 50%)');
	proj.css('background-color', 'hsla(' + angle + ', 25%, 50%, 0.1)');

	proj.data('title', key === 'local' ? 'Local' : ('Remote #' + key));

	if("ship" in clf && "typeid" in clf.ship) {
		var img = $(document.createElement('img'));
		img.prop('alt', clf.ship.typename);
		img.prop('src', '//image.eveonline.com/Render/' + clf.ship.typeid + '_512.png');
		img.addClass('render');
		proj.append(img);
		proj.data('shiptypeid', clf.ship.typeid);
		proj.data('title', proj.data('title') + ': ' + osmium_types[clf.ship.typeid][1]);
	}

	if("presets" in clf && "X-Osmium-current-presetid" in clf
	   && "modules" in clf.presets[clf['X-Osmium-current-presetid']]) {
		var p = clf.presets[clf['X-Osmium-current-presetid']].modules;
		var projectable = 0;
		var pindex = 0;

		for(var i = 0; i < p.length; ++i) {
			var t = osmium_types[p[i].typeid];
			if(t[8] !== 1) continue;
			++projectable;
		}

		var size; /* Also diameter of the circle */
		if(projectable === 0) {
			size = 120;
		} else {
			size = 80 * Math.max(6, projectable) / Math.PI;
		}

		proj.css({
			width: size + 'px',
			height: size + 'px'
		});

		for(var i = 0; i < p.length; ++i) {
			var t = osmium_types[p[i].typeid];
			if(t[8] !== 1) continue;

			var source = 
				$(document.createElement('li'))
				.data('location', 'module-' + p[i].typeid + '-' + p[i].index)
				.data('typeid', p[i].typeid)
				.data('index', p[i].index)
				.append(
					$(document.createElement('img'))
						.prop('alt', t[1] + ' (#' + p[i].index + ')')
						.prop('title', t[1] + ' (#' + p[i].index + ')')
						.prop('src', '//image.eveonline.com/Type/' + t[0] + '_64.png')
				);

			var angle = (pindex / projectable) * 2 * Math.PI;
			var top = -size * .5 * Math.cos(angle) - 28;
			var left = size * .5 * Math.sin(angle) - 28

			source.css({ top: top + 'px', left: left + 'px' });

			osmium_ctxmenu_bind(source, (function(source, clfsource) {
				return function() {
					var menu = osmium_ctxmenu_create();
					var t = osmium_types[clfsource.typeid];

					osmium_ctxmenu_add_option(menu, t[1], function() {}, {
						enabled: false
					});

					osmium_ctxmenu_add_separator(menu);

					osmium_ctxmenu_add_subctxmenu(menu, "Project on", function() {
						var smenu = osmium_ctxmenu_create();
						var ntargets = 0;

						$('section#projected div.pr-loadout').each(function() {
							var div = $(this);
							var tkey = div.data('key');
							if(tkey === key) return;
							++ntargets;
							osmium_ctxmenu_add_option(smenu, div.data('title'), function() {
								jsPlumb.connect({
									source: source,
									target: div
								});
							}, {});
						});

						if(ntargets === 0) {
							osmium_ctxmenu_add_option(smenu, "No targets available", function() {}, {
								enabled: false
							});
						}

						return smenu;
					}, {});

					osmium_ctxmenu_add_option(menu, "Clear target", function() {
						jsPlumb.select({ source: source }).detach();
					}, { default: true });

					osmium_ctxmenu_add_separator(menu);

					osmium_ctxmenu_add_option(menu, "Show module info", function() {
						osmium_showinfo({
							remote: key,
							type: "module",
							slottype: osmium_types[source.data('typeid')][3],
							index: source.data('index')
						});
					}, { icon: osmium_showinfo_sprite_position });

					return menu;
				}
			})(source, p[i]));

			jsPlumb.makeSource(source, {
				anchor: [ 0.5, 0.5 ],
				paintStyle: { fillStyle: 'hsl(' + proj.data('hue') + ', 50%, 50%)' },
				connectorStyle: {
					strokeStyle: 'hsla(' + proj.data('hue') + ', 50%, 50%, 0.5)',
					lineWidth: 5
				}
			});

			ul.append(source);
			++pindex;
		}
	}

	proj.append(ul);
	var cap = osmium_gen_capacitor(1000, 1000);
	proj.append(cap);
	osmium_regen_remote_capacitor(proj);

	jsPlumb.makeTarget(proj, {
		anchor: [ 0.5, 0.5 ],
		paintStyle: { fillStyle: 'hsl(' + proj.data('hue') + ', 50%, 50%)' }
	});
	jsPlumb.draggable(proj);

	if(!$("section#projected").hasClass('fs')) {
		jsPlumb.toggleDraggable(proj);
	}

	osmium_ctxmenu_bind(proj, function() {
		var menu = osmium_ctxmenu_create();

		osmium_ctxmenu_add_option(menu, proj.data('title'), function() {}, { enabled: false });

		osmium_ctxmenu_add_separator(menu);

		osmium_ctxmenu_add_subctxmenu(menu, "Use skills", (function(key) {
			return function() {
				var smenu = osmium_ctxmenu_create();
				var clf = key === 'local' ? osmium_clf : osmium_clf['X-Osmium-remote'][key];

				for(var i = 0; i < osmium_skillsets.length; ++i) {
					osmium_ctxmenu_add_option(smenu, osmium_skillsets[i], (function(sname) {
						return function() {
							if(key === 'local') {
								clf.metadata['X-Osmium-skillset'] = sname;
							} else {
								clf.skillset = sname;
							}

							osmium_undo_push();
							osmium_commit_clf();
						};
					})(osmium_skillsets[i]), {
						toggled: (key === 'local' && clf.metadata['X-Osmium-skillset'] === osmium_skillsets[i])
							|| (key !== 'local' && "skillset" in clf && clf.skillset === osmium_skillsets[i])
					});
				}

				return smenu;
			};
		})(proj.data('key')), { icon: "//image.eveonline.com/Type/3327_64.png" });

		if(proj.data('key') !== 'local') {
			osmium_ctxmenu_add_separator(menu);
		}

		if(proj.data('key') !== 'local' && !osmium_loadout_readonly) {
			osmium_ctxmenu_add_option(menu, "Edit fitting…", (function(key) {
				return function() {
					var hdr = $(document.createElement('header'));
					hdr.append($(document.createElement('h2')).text(
						'Edit remote loadout #' + key
					));

					var form = $(document.createElement('form'));
					var tbody = $(document.createElement('tbody'));

					var input = $(document.createElement('input'))
						.prop('type', 'text')
						.prop('placeholder', 'Loadout URI, DNA string or gzclf:// data')
						.prop('name', 'm-remote-fitting')
						.prop('id', 'm-remote-fitting')
					;

					if("X-Osmium-remote" in osmium_clf
					   && key in osmium_clf['X-Osmium-remote']
					   && 'fitting' in osmium_clf['X-Osmium-remote'][key]) {
						input.val(osmium_clf['X-Osmium-remote'][key].fitting);
					}

					tbody.append(
						$(document.createElement('tr'))
							.append(
								$(document.createElement('th'))
									.append(
										$(document.createElement('label'))
											.text('Fitting')
											.prop('for', 'm-remote-fitting')
									)
							)
							.append(
								$(document.createElement('td'))
									.append(input)
							)
					);

					var select = $(document.createElement('select'))
						.prop('name', 'm-remote-skillset')
						.prop('id', 'm-remote-skillset')
						.on('change', function() {
							osmium_clf['X-Osmium-remote'][key].skillset = $(this).val();
							osmium_undo_push();
							osmium_commit_clf();
						})
					;

					for(var i = 0; i < osmium_skillsets.length; ++i) {
						select.append(
							$(document.createElement('option'))
								.text(osmium_skillsets[i])
								.prop('value', osmium_skillsets[i])
						);
					}

					if("X-Osmium-remote" in osmium_clf
					   && key in osmium_clf['X-Osmium-remote']
					   && 'skillset' in osmium_clf['X-Osmium-remote'][key]) {
						select.val(osmium_clf['X-Osmium-remote'][key].skillset);
					}

					tbody.append(
						$(document.createElement('tr'))
							.append(
								$(document.createElement('th'))
									.append(
										$(document.createElement('label'))
											.text('Skills')
											.prop('for', 'm-remote-skillset')
									)
							)
							.append(
								$(document.createElement('td'))
									.append(select)
							)
					);

					tbody.append(
						$(document.createElement('tr'))
							.append($(document.createElement('th')))
							.append(
								$(document.createElement('td'))
									.addClass('l')
									.append(
										$(document.createElement('input'))
											.prop('type', 'submit')
											.prop('value', 'Use fitting')
									)
							)
					);
					
					form
						.prop('id', 'm-remote')
						.append(
							$(document.createElement('table'))
								.append(tbody)
						)
						.on('submit', function(e) {
							e.preventDefault();
							var form = $(this);

							form.find('input, select').prop('disabled', true);
							form
								.find('input[type="submit"]')
								.after(
									$(document.createElement('span'))
										.addClass('spinner')
								)
							;

							osmium_clf['X-Osmium-remote'][key].fitting =
								form.find('input#m-remote-fitting').val();
							osmium_clf['X-Osmium-remote'][key].skillset =
								form.find('select#m-remote-skillset').val();

							osmium_projected_regen_remote(key, function() {
								$("a#closemodal").click();
							}, function(errors) {
								form.find('span.spinner').remove();
								form.find('input, select').prop('disabled', false);

								form.find('tr.error_message').remove();
								form.find('tr.error').removeClass('error');

								for(var i = 0; i < errors.length; ++i) {
									input.closest('tr').addClass('error').before(
										$(document.createElement('tr'))
											.addClass('error_message')
											.append(
												$(document.createElement('td'))
													.prop('colspan', '2')
													.append(
														$(document.createElement('p')).text(errors[i])
													)
											)
									);
								}
							});

							return false;
						})
					;

					osmium_modal([ hdr, form ]);
				};
			})(key), { default: true });

			osmium_ctxmenu_add_option(menu, "Remove fitting", function() {
				jsPlumb.doWhileSuspended(function() {
					jsPlumb.detachAllConnections(proj);
					proj.find('ul > li').each(function() {
						jsPlumb.detachAllConnections($(this));
					});
					proj.remove();
				});

				delete osmium_clf['X-Osmium-remote'][key];
				osmium_commit_undo_deferred();
			}, {});
		}

		if(proj.data('key') !== 'local' && osmium_loadout_readonly) {
			osmium_ctxmenu_add_option(menu, "View fitting", function() {
				var loc = window.location.href.split("#")[0];
				var match = loc.match(/^(.+?)\/remote\/(.+)$/);

				if(match !== null) {
					var localkey;

					if(key.toString() === match[2]) {
						window.location.assign(match[1]);
						return;
					} else {
						localkey = key;
					}

					window.location.assign(match[1] + "/remote/" + encodeURIComponent(localkey));
				} else {
					window.location.assign(loc + '/remote/' + encodeURIComponent(key));
				}
			}, {});
		}

		if(proj.data('shiptypeid')) {
			/* No point allowing show ship info on shallow pools */

			osmium_ctxmenu_add_separator(menu);

			osmium_ctxmenu_add_option(menu, "Show ship info", function() {
				osmium_showinfo({
					remote: key,
					type: "ship"
				});
			}, { icon: osmium_showinfo_sprite_position, default: osmium_loadout_readonly });
		}

		return menu;
	});

	return proj;
};

osmium_projected_regen_local = function() {
	var oldlocal = $("section#projected div.projected-local");
	if(oldlocal.length === 0) return; /* Not yet generated, will be done later */

	var local = osmium_create_projected("local", osmium_clf, oldlocal.index());
	osmium_projected_replace_graceful(oldlocal, local);
};

osmium_projected_regen_remote = function(key, onsuccess, onerror) {
	var t = $("section#projected div.pr-loadout.projected-" + key);

	osmium_commit_clf({
		params: { "remoteclf": t.data('key') },
		success: function(payload) {
			if(!("remote-clf" in payload)) return;

			osmium_clf['X-Osmium-remote'][t.data("key")] = payload['remote-clf'];
			osmium_undo_push();

			osmium_projected_replace_graceful(
				t,
				osmium_create_projected(
					t.data('key'),
					osmium_clf['X-Osmium-remote'][t.data('key')],
					t.index()
				)
			);

			if('remote-errors' in payload) {
				if(typeof onerror === 'function') onerror(payload['remote-errors']);
			} else {
				if(typeof onsuccess === 'function') onsuccess(payload);	
			}
		}
	});
};

osmium_projected_replace_graceful = function(stale, fresh) {
	var cssprops = [ "left", "top", "right", "bottom" ];
	for(var i = 0; i < cssprops.length; ++i) {
		fresh.css(cssprops[i], stale.css(cssprops[i]));
	}

	jsPlumb.doWhileSuspended(function() {
		var newconnections = [];

		osmium_user_initiated_push(false);
		/* Keep all incoming projections */
		jsPlumb.select({
			target: stale
		}).each(function(conn) {
			var source = conn.source;
			jsPlumb.detach(conn);
			newconnections.push({
				source: source,
				target: fresh
			});
		});
		osmium_user_initiated_pop();

		osmium_user_initiated_push(false);
		/* Keep outgoing projections if modules match */
		stale.find('ul > li').each(function() {
			var source = $(this);
			var newsource = fresh.find('ul > li').filter(function() {
				return $(this).data('location') === source.data('location')
			}).first();

			var connections = jsPlumb.select({
				source: source
			});

			if(newsource.length === 1) {
				/* Found a matching module, transfer all connections to it */
				connections.each(function(conn) {
					var target = conn.target;
					jsPlumb.detach(conn);
					newconnections.push({
						source: newsource,
						target: target
					});
				});
			} else {
				/* Drop connections */
				osmium_user_initiated_pop();
				connections.detach();
				osmium_user_initiated_push(false);
			}

			jsPlumb.unmakeSource(source);
		});
		osmium_user_initiated_pop();

		jsPlumb.unmakeTarget(stale);
		stale.before(fresh);
		stale.remove();

		for(var i = 0; i < newconnections.length; ++i) {
			jsPlumb.connect(newconnections[i]);
		}
	});
};

osmium_regen_remote_capacitor = function(key_or_element) {
	if(typeof osmium_capacitors !== "object") return;

	var c, s;

	if(typeof key_or_element !== "object") {
		if(!(key_or_element in osmium_capacitors)) return;
		s = $("section#projected div.pr-loadout.projected-" + key_or_element);
		c = osmium_capacitors[key_or_element];
	} else {
		s = key_or_element;
		if(!(s.data('key') in osmium_capacitors)) return;
		c = osmium_capacitors[s.data('key')];
	}

	if(s.length !== 1) return;
	s = s.find('svg');

	if(c.capacity > 0) {
		var delta = -1000 * c.delta;
		if(delta >= 0) delta = '+' + delta.toFixed(1);
		else delta = delta.toFixed(1);

		s.data({
			capacity: c.capacity,
			current: c.stable ? (c.capacity * c.stable_fraction) : 0
		});
		s.parent().prop(
			'title', delta + ' GJ/s, '
				+ (c.stable ? ((100 * c.stable_fraction).toFixed(1) + '%') : c.depletion_time)
		);
	} else {
		s.data({ capacity: 1000, current: 1000 });
		s.parent().prop('title', ''); /* XXX: .removeProp() gives "undefined" as tooltip */
	}

	s.trigger('redraw');
};

osmium_commit_undo_deferred_timeoutid = undefined;
osmium_commit_undo_deferred = function(delay) {
	if(!osmium_user_initiated) return;

	if(delay === undefined) delay = 100;

	if(osmium_commit_undo_deferred_timeoutid !== undefined) {
		clearTimeout(osmium_commit_undo_deferred_timeoutid);
	} else {
		osmium_clfspinner_push();
	}

	osmium_commit_undo_deferred_timeoutid = setTimeout(function() {
		osmium_commit_undo_deferred_timeoutid = undefined;
		osmium_commit_clf();
		osmium_undo_push();
		osmium_clfspinner_pop();
	}, delay);
};
