/* Osmium
 * Copyright (C) 2013, 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

osmium_gen_drones = function() {
	var drones = {
		bay: {},
		space: {}
	};
	var old_ias = {};

	var dronep = osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']];

	for(var p in dronep) {
		if(p !== 'inbay' && p !== 'inspace') continue;
		var map = (p === 'inbay') ? drones.bay : drones.space;

		for(var i = 0; i < dronep[p].length; ++i) {
			if(dronep[p][i].quantity == 0) continue;
			if(!(dronep[p][i].typeid in map)) {
				map[dronep[p][i].typeid] = 0;
			}

			map[dronep[p][i].typeid] += dronep[p][i].quantity;
		}
	}

	$('section#drones div.drones.space li.hasattribs > small.attribs').each(function() {
		var s = $(this);
		old_ias[s.parent().data('typeid')] = s.clone();
	});

	for(var p in drones) {
		var dronesdiv = $("section#drones div.drones." + p);
		var ul = dronesdiv.children('ul');
		ul.empty();

		for(var t in drones[p]) {
			var li = $(document.createElement('li'));
			var img = $(document.createElement('img'));
			var m = osmium_types[t];
			var qty = drones[p][t];
			var other = (p === 'bay') ? 'space' : 'bay';

			li.data('typeid', m[0]);
			li.data('location', p);
			li.data('quantity', qty);
			li.append($(document.createElement('span')).addClass('name').text(m[1]));
			li.prop('title', m[1]);
			li.prepend($(document.createElement('strong')).addClass('qty').text(qty + '×'));

			img.prop('src', '//image.eveonline.com/Type/' + t + '_64.png');
			img.prop('alt', '');

			li.prepend(img);

			if(t in old_ias) {
				li.append(old_ias[t]);
			}

			ul.append(li);

			osmium_ctxmenu_bind(li, (function(t, p, qty, other) {
				return function() {
					var menu = osmium_ctxmenu_create();

					if(!osmium_loadout_readonly) {
						osmium_ctxmenu_add_option(menu, "Remove 1 drone", function() {
							osmium_remove_drone_from_clf(t, 1, p);
							osmium_gen_drones();
							osmium_undo_push();
							osmium_commit_clf();
						}, { 'default': true });

						if(qty > 5) {
							osmium_ctxmenu_add_option(menu, "Remove 5 drones", function() {
								osmium_remove_drone_from_clf(t, 5, p);
								osmium_gen_drones();
								osmium_undo_push();
								osmium_commit_clf();
							}, { });
						}

						if(qty > 1) {
							osmium_ctxmenu_add_option(menu, "Remove " + qty + " drones", function() {
								osmium_remove_drone_from_clf(t, qty, p);
								osmium_gen_drones();
								osmium_undo_push();
								osmium_commit_clf();
							}, { });
						}

						osmium_ctxmenu_add_separator(menu);
					}

					osmium_ctxmenu_add_option(menu, "Move 1 to " + other, function() {
						osmium_add_drone_to_clf(t, 1, other);
						osmium_remove_drone_from_clf(t, 1, p);
						osmium_gen_drones();
						osmium_undo_push();
						osmium_commit_clf();
					}, { });

					if(qty > 5) {
						osmium_ctxmenu_add_option(menu, "Move 5 to " + other, function() {
							osmium_add_drone_to_clf(t, 5, other);
							osmium_remove_drone_from_clf(t, 5, p);
							osmium_gen_drones();
							osmium_undo_push();
							osmium_commit_clf();
						}, { });
					}

					if(qty > 1) {
						osmium_ctxmenu_add_option(menu, "Move " + qty + " to " + other, function() {
							osmium_add_drone_to_clf(t, qty, other);
							osmium_remove_drone_from_clf(t, qty, p);
							osmium_gen_drones();
							osmium_undo_push();
							osmium_commit_clf();
						}, { });
					}

					osmium_ctxmenu_add_separator(menu);

					if(!osmium_loadout_readonly) {
						osmium_ctxmenu_add_option(menu, "Clear all drones in " + p, function() {
							osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']]['in' + p] = [];
							osmium_gen_drones();
							osmium_undo_push();
							osmium_commit_clf();
						}, { });

						osmium_ctxmenu_add_separator(menu);
					}

					if(!osmium_loadout_readonly) {
						osmium_add_generic_browse_mg(menu, t);
					}

					osmium_ctxmenu_add_option(menu, "Show drone info", function() {
						osmium_showinfo({
							type: "drone",
							typeid: t
						});
					}, { icon: osmium_showinfo_sprite_position, 'default': osmium_loadout_readonly });

					return menu;
				};
			})(m[0], p, qty, other));
		}

		if(ul.children('li').length == 0) {
			/* Add placeholder entry */
			var li = $(document.createElement('li'));
			li.text('No drones in ' + p);
			li.addClass('placeholder');
			li.prepend(osmium_sprite('', 0, 13, 64, 64, 32, 32));
			ul.append(li);
		}
	}

	if(osmium_user_initiated) {
		$('a[href="#drones"]').parent().click();
	}
};

osmium_init_drones = function() {
	$("section#drones > div.drones > ul").sortable({
		items: "li:not(.placeholder)",
		placeholder: "sortable-placeholder",
		connectWith: "section#drones > div.drones > ul",
		remove: function(e, ui) {
			var d = ui.item;
			var t = d.data('typeid');
			var loc = d.data('location');
			var q = d.data('quantity');

			var drones = osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']]['in' + loc];

			for(var i = 0; i < drones.length; ++i) {
				if(drones[i].typeid != t) continue;

				drones[i].quantity -= q;
				if(drones[i].quantity <= 0) {
					drones.splice(i, 1);
				}
			}
		},
		receive: function(e, ui) {
			var d = ui.item;
			var t = d.data('typeid');
			var loc = (d.data('location') === 'bay') ? 'space' : 'bay';
			var q = d.data('quantity');
			var dp = osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']];
			var key = 'in' + loc;

			if(!(key in dp)) dp[key] = [];
			drones = dp[key];
			d.data('location', loc);

			for(var i = 0; i < drones.length; ++i) {
				if(drones[i].typeid != t) continue;

				drones[i].quantity += q;
				osmium_gen_drones();
				osmium_commit_clf();
				osmium_undo_push();
				return;
			}

			drones.push({
				typeid: t,
				quantity: q
			});
			osmium_gen_drones();
			osmium_commit_clf();
			osmium_undo_push();
		}
	});
};

osmium_remove_drone_from_clf = function(typeid, qty, location) {
	var dp = osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']];
	dp = (location === 'space') ? dp.inspace : dp.inbay;
	var toremove = qty;

	for(var i = dp.length - 1; i >= 0; --i) {
		if(toremove == 0) break;
		if(dp[i].typeid != typeid) continue;

		if(dp[i].quantity > qty) {
			/* Easy case */
			dp[i].quantity -= qty;
			toremove = 0;
			break;
		} else {
			/* Annoying case */
			toremove -= dp[i].quantity;
			dp.splice(i, 1);
		}
	}
};

osmium_add_drone_to_clf = function(typeid, qty, location) {
	var dp = osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']];
	if(!("inspace" in dp)) dp.inspace = [];
	if(!("inbay" in dp)) dp.inbay = [];

	dp = (location === 'space') ? dp.inspace : dp.inbay;

	for(var i = 0; i < dp.length; ++i) {
		if(dp[i].typeid === typeid) {
			dp[i].quantity += qty;
			return;
		}
	}

	dp.push({
		typeid: typeid,
		quantity: qty
	});
};
