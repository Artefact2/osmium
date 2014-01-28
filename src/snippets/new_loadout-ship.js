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

osmium_gen_ship = function() {
	var section = $('div#nlattribs > section#ship');
	var img, h, shipname, groupname;

	if("ship" in osmium_clf && "typeid" in osmium_clf.ship) {
		groupname = osmium_types[osmium_clf.ship.typeid][5];
		shipname = osmium_types[osmium_clf.ship.typeid][1];

		img = $(document.createElement('img'));
		img.prop('src', '//image.eveonline.com/Render/' + osmium_clf.ship.typeid + '_256.png');

		osmium_loadout_can_be_submitted();

		var availslots = osmium_ship_slots[osmium_clf.ship.typeid];
		osmium_clf_slots = {
			high: availslots[0],
			medium: availslots[1],
			low: availslots[2],
			rig: availslots[3],
			subsystem: availslots[4]
		};
		osmium_clf_hardpoints = {
			turret: 0,
			launcher: 0
		};
	} else {
		groupname = '';
		shipname = '｢No ship selected｣';

		img = $(document.createElement('div'));
		img.addClass('notype');

		osmium_clf_slots = {
			high: 8,
			medium: 8,
			low: 8,
			rig: 3,
			subsystem: 5
		};
		osmium_clf_hardpoints = {
			turret: 0,
			launcher: 0
		};
	}

	h = $(document.createElement('h1'));
	h.append(img);
	h.append($(document.createElement('small')).addClass('groupname').text(groupname));
	h.append($(document.createElement('strong')).append(
		$(document.createElement('span')).addClass('name').text(shipname)
	).prop('title', shipname));

	section.children('h1').remove();
	section.append(h);
};

osmium_init_ship = function() {
	osmium_ctxmenu_bind($("section#ship"), function() {
		var menu = osmium_ctxmenu_create();

		osmium_ctxmenu_add_option(menu, "Undo (Ctrl+_)", function() {
			osmium_undo_pop();
			osmium_commit_clf();
			osmium_user_initiated_push(false);
			osmium_gen();
			osmium_user_initiated_pop();
		}, { icon: [ 1, 13, 64, 64 ] });

		osmium_ctxmenu_add_separator(menu);

		osmium_ctxmenu_add_subctxmenu(menu, "Reload times", function() {
			var smenu = osmium_ctxmenu_create();

			osmium_ctxmenu_add_option(smenu, "Include in capacitor time", function() {
				osmium_clf.metadata['X-Osmium-capreloadtime'] = !osmium_clf.metadata['X-Osmium-capreloadtime'];
				osmium_undo_push();
				osmium_commit_clf();
			}, { toggled: osmium_clf.metadata['X-Osmium-capreloadtime'] });

			osmium_ctxmenu_add_option(smenu, "Include in DPS", function() {
				osmium_clf.metadata['X-Osmium-dpsreloadtime'] = !osmium_clf.metadata['X-Osmium-dpsreloadtime'];
				osmium_undo_push();
				osmium_commit_clf();
			}, { toggled: osmium_clf.metadata['X-Osmium-dpsreloadtime'] });

			osmium_ctxmenu_add_option(smenu, "Include in sustained tank", function() {
				osmium_clf.metadata['X-Osmium-tankreloadtime'] = !osmium_clf.metadata['X-Osmium-tankreloadtime'];
				osmium_undo_push();
				osmium_commit_clf();
			}, { toggled: osmium_clf.metadata['X-Osmium-tankreloadtime'] });

			return smenu;
		}, {});

		osmium_ctxmenu_add_separator(menu);

		osmium_ctxmenu_add_option(menu, "DPS graphs…", function() {
			var hdr = $(document.createElement('header')).append(
				$(document.createElement('h2')).text('Damage per second graph ')
					.append($(document.createElement('a'))
							.text('(compare and tweak…)')
							.prop('href', osmium_relative + '/compare/dps/s,0,'
								  + encodeURIComponent(window.location.href)
								  + ',' + encodeURIComponent(osmium_clf.metadata.title)))
			);
			var form = $(document.createElement('form'))
				.prop('id', 'm-dpsg');
			var table = $(document.createElement('table'));
			var tbody = $(document.createElement('tbody'));
			var tr = $(document.createElement('tr'));
			tbody.append(tr);
			table.append(tbody);
			form.append(table);

			var td, label, input;

			td = $(document.createElement('td')).addClass('signatureradius');
			label = $(document.createElement('label'))
				.prop('for', 'm-dpsg-signatureradius')
				.text('Target signature radius: ')
			;
			input = $(document.createElement('input'))
				.prop('type', 'text')
				.prop('id', 'm-dpsg-signatureradius')
			;
			td.append([ label, input, " m" ]);
			tr.append(td);

			td = $(document.createElement('td')).addClass('velocity');
			label = $(document.createElement('label'))
				.prop('for', 'm-dpsg-velocity')
				.text('Target velocity: ')
			;
			input = $(document.createElement('input'))
				.prop('type', 'text')
				.prop('id', 'm-dpsg-velocity')
			;
			td.append([ label, input, " m/s" ]);
			tr.append(td);

			td = $(document.createElement('td')).addClass('distance');
			label = $(document.createElement('label'))
				.prop('for', 'm-dpsg-distance')
				.text('Target distance: ')
			;
			input = $(document.createElement('input'))
				.prop('type', 'text')
				.prop('id', 'm-dpsg-distance')
			;
			td.append([ label, input, " km" ]);
			tr.append(td);

			td = $(document.createElement('td'));
			input = $(document.createElement('input'))
				.prop('type', 'submit')
				.val('Generate graph')
			;
			td.append(input);
			tr.append(td);

			var ctx = $(document.createElement('div')).prop('id', 'm-dpsg-ctx');

			form.on('submit', function(e) {
				e.preventDefault();

				var tsr, tv, td;
				tsr = parseFloat(form.find('td.signatureradius input').val());
				tv = parseFloat(form.find('td.velocity input').val());
				td = parseFloat(form.find('td.distance input').val());

				var nans = 0;
				if(isNaN(tsr)) ++nans;
				if(isNaN(tv)) ++nans;
				if(isNaN(td))  ++nans;

				if(nans !== 1 && nans !== 2) {
					alert('You must fill exactly one or two fields with number values.');
					return false;
				}

				ctx.empty();

				if(nans === 1) {
					var genfunc, xlabel, xmax;
					var limits = osmium_probe_boundaries_from_ia(osmium_ia, tsr, tv, td);

					if(isNaN(tsr)) {
						genfunc = function(x) { return [ x, tv, td ]; };
						xlabel = "Target signature radius (m)";
						xmax = limits[0];
					} else if(isNaN(tv)) {
						genfunc = function(x) { return [ tsr, x, td ]; };
						xlabel = "Target velocity (m/s)";
						xmax = limits[1];
					} else if(isNaN(td)) {
						genfunc = function(x) { return [ tsr, tv, x ]; };
						xlabel = "Target distance (km)";
						xmax = limits[2];
					}

					osmium_draw_dps_graph_1d(
						{ foo: { ia: osmium_ia } },
						{ foo: "hsl(0, 100%, 50%)" },
						ctx, xlabel, 0, xmax, genfunc,
						0, null
					);
				} else if(nans === 2) {
					var genfunc, xlabel, ylabel, xmax, ymax, b;
					b = osmium_probe_boundaries_from_ia(osmium_ia, tsr, tv, td);

					if(!isNaN(tsr)) {
						genfunc = function(x, y) { return [ tsr, y, x ]; };
						ylabel = "Target velocity (m/s)";
						xlabel = "Target distance (km)";
						ymax = b[1]; xmax = b[2];
					} else if(!isNaN(tv)) {
						genfunc = function(x, y) { return [ y, tv, x ]; };
						ylabel = "Target signature radius (m)";
						xlabel = "Target distance (km)";
						ymax = b[0]; xmax = b[2];
					} else if(!isNaN(td)) {
						genfunc = function(x, y) { return [ y, x, td ]; };
						ylabel = "Target signature radius (m)";
						xlabel = "Target velocity (m/s)";
						ymax = b[0]; xmax = b[1];
					}

					var maxdps = osmium_draw_dps_graph_2d(
						{ foo: { ia: osmium_ia } },
						function(fracs, cmap) {
							for(var k in fracs) {
								return osmium_heat_color(fracs[k][0] / fracs[k][1]);
							}
							return osmium_heat_color(0);
						},
						ctx,
						xlabel, 0, xmax,
						ylabel, 0, ymax,
						genfunc,
						4
					);

					osmium_draw_dps_legend(ctx, maxdps, osmium_heat_color);
				}

				return false;
			});

			var limits = osmium_probe_boundaries_from_ia(osmium_ia, NaN, NaN, NaN);
			form.find('td.signatureradius input').val(Math.round(limits[0] / 3).toString());

			osmium_modal([ hdr, form, ctx ]);
			form.trigger('submit');
		}, {});

		if("ship" in osmium_clf && "typeid" in osmium_clf.ship) {
			osmium_ctxmenu_add_separator(menu);

			if(!osmium_loadout_readonly) {
				osmium_add_generic_browse_mg(menu, osmium_clf.ship.typeid);
			}

			osmium_ctxmenu_add_option(menu, "Show ship info", function() {
				osmium_showinfo({ type: "ship" });
			}, { icon: osmium_showinfo_sprite_position, 'default': true });
		}

		return menu;
	});

	/* This isn't pretty */
	$(document).keydown(function(e) {
		/* Chromium doesn't issue a keypress event */
		if(!e.ctrlKey || e.which != 189) return true;
		e.preventDefault();

		osmium_undo_pop();
		osmium_commit_clf();
		osmium_user_initiated_push(false);
		osmium_gen();
		osmium_user_initiated_pop();

		return false;
	}).keypress(function(e) {
		/* Firefox behaves as expected */
		if(!e.ctrlKey || e.which != 95) return true;
		e.preventDefault();

		osmium_undo_pop();
		osmium_commit_clf();
		osmium_user_initiated_push(false);
		osmium_gen();
		osmium_user_initiated_pop();

		return false;
	});
};
