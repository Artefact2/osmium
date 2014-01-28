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

osmium_gen_modules = function() {
	var p = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']];
	var cpid = osmium_clf['X-Osmium-current-chargepresetid'];
	var m, i, j, type, c, chargeid;
	var old_ias = {};
	var old_ncycles = {};

	$('section#modules > div.slots > ul > li.hasattribs > small.attribs').each(function() {
		var s = $(this);
		var li = s.parent();
		var typeid = li.data('typeid');
		var index = li.data('index');

		if(!(typeid in old_ias)) old_ias[typeid] = {};
		old_ias[typeid][index] = s.clone();
	});

	$('section#modules > div.slots > ul > li > span.charge > span.ncycles').each(function() {
		var s = $(this);
		var li = s.parent().parent();
		var typeid = li.data('typeid');
		var index = li.data('index');

		if(!(typeid in old_ncycles)) old_ncycles[typeid] = {};
		old_ncycles[typeid][index] = s.clone();
	});

	$('section#modules > div.slots > ul > li').not('.placeholder').remove();

	if(!("modules" in p)) p.modules = [];

	for(i = 0; i < p.modules.length; ++i) {
		m = p.modules[i];
		chargeid = null;
		if("charges" in m) {
			for(j = 0; j < m.charges.length; ++j) {
				if(!("cpid" in m.charges[j])) {
					m.charges[j].cpid = 0;
				}

				if(m.charges[j].cpid == cpid) {
					chargeid = m.charges[j].typeid;
					break;
				}
			}
		}

		var li = osmium_add_module(m.typeid, m.index, m.state, chargeid);

		if(m.typeid in old_ias && m.index in old_ias[m.typeid]) {
			li.addClass('hasattribs')
				.append(old_ias[m.typeid][m.index]);
		}

		if(m.typeid in old_ncycles && m.index in old_ncycles[m.typeid]) {
			li.children('span.charge')
				.addClass('hasncycles')
				.append(old_ncycles[m.typeid][m.index]);
		}
	}

	for(type in osmium_clf_slots) {
		$('section#modules > div.slots.' + type).data('type', type);
	}

	osmium_update_slotcounts();
	osmium_projected_regen_local();
};

osmium_init_modules = function() {
	$("section#modules > div.slots > h3 > span > small.groupcharges").on('click', function() {
		var t = $(this);
		var slotsdiv = t.parents("div.slots");

		if(slotsdiv.hasClass('grouped')) {
			slotsdiv.removeClass('grouped').addClass('ungrouped');
			t.text('Charges are not grouped');
		} else {
			slotsdiv.removeClass('ungrouped').addClass('grouped');
			t.text('Charges are grouped');
		}

		t.prop('title', t.text());
	}).each(function() {
		var t = $(this);
		t.prop('title', t.text());
	});

	if(osmium_loadout_readonly) return;

	$("section#modules > div.slots > ul").sortable({
		items: "li:not(.placeholder)",
		placeholder: "sortable-placeholder",
		start: function() {
			$("section#modules > div.slots").css('opacity', 0.5);
			$(this).addClass('sorting').parent().css('opacity', 1.0);
		},
		stop: function() {
			$("section#modules > div.slots").css('opacity', 1.0);
			$(this).removeClass('sorting');
		},
		update: function() {
			var z = 0;
			var mp = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules;
			var map = {};

			$("section#modules > div.slots").find('li').each(function() {
				var t = $(this).data('typeid');
				var i = $(this).data('index');

				if(!t) return;
				if(!(t in map)) map[t] = {};

				map[t][i] = z;
				$(this).data('index', z);
				++z;
			});

			for(var i = 0; i < mp.length; ++i) {
				mp[i].index = map[mp[i].typeid][mp[i].index];
			}

			mp.sort(function(x, y) {
				return x.index - y.index;
			});

			osmium_commit_clf();
			osmium_undo_push();
		}
	});
};

osmium_update_slotcounts = function() {
	$('section#modules > div.slots').each(function() {
		var t = $(this);
		osmium_update_overflow(t);
		osmium_maybe_hide_slot_type(t);
	});
};

osmium_maybe_hide_slot_type = function(slotsdiv) {
	if(slotsdiv.children('ul').find('li').length > 0) {
		slotsdiv.show();
	} else {
		slotsdiv.hide();
	}
};

osmium_update_overflow = function(slotsdiv) {
	var smallcount = slotsdiv.find('h3 > span > small.counts');
	var used = slotsdiv.children('ul').children('li').not('.placeholder').length;
	var total = osmium_clf_slots[slotsdiv.data('type')];

	smallcount.text(used + '/' + total);

	var placeholders = slotsdiv.find('li.placeholder').length;
	var ideal = total - used;
	while(placeholders < ideal) {
		osmium_add_placeholder_module(slotsdiv);
		++placeholders;
	}
	while(placeholders > ideal) {
		slotsdiv.find('li.placeholder').last().remove();
		--placeholders;
	}

	if(used > total) {
		smallcount.addClass('overflow');
		slotsdiv.children('ul').children('li').slice(total - used).addClass('overflow');
	} else {
		smallcount.removeClass('overflow');
		slotsdiv.children('ul').children('li.overflow').removeClass('overflow');
	}
};

osmium_add_module = function(typeid, index, state, chargeid) {
	var m = osmium_types[typeid];
	var div = $('section#modules > div.' + m[3]);
	var ul = div.children('ul');
	var placeholders = ul.children('li.placeholder');
	var li, img, a, stateimg;
	var stateful, hascharges;

	if(state === null) {
		if(osmium_module_states[typeid][2]) {
			/* Active state, if possible */
			state = osmium_states[2];
		} else if(osmium_module_states[typeid][1]) {
			/* Online state */
			state = osmium_states[1];
		} else {
			/* Offline state */
			state = osmium_states[0];
		}
	}

	li = $(document.createElement('li'));
	li.data('typeid', typeid);
	li.data('slottype', m[3]);
	li.data('index', index);
	li.data('state', state);
	li.append($(document.createElement('span')).addClass('name').text(m[1]));
	li.prop('title', m[1]);

	img = $(document.createElement('img'));
	img.prop('src', '//image.eveonline.com/Type/' + typeid + '_64.png');

	li.prepend(img);

	if(hascharges = (typeid in osmium_charges)) {
		li.on('remove_charge_nogroupcheck', function() {
			var span = li.children('span.charge');
			var charge = span.children('span.name');

			li.data('chargetypeid', null);
			span.children('img, span.mainsprite').replaceWith(
				osmium_sprite('', 0, 28, 32, 32, 32, 32)
			);
			charge.empty();
			charge.append($(document.createElement('em')).text('(No charge)'));

			var modules = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules;
			for(var i = 0; i < modules.length; ++i) {
				if(modules[i].index === index && modules[i].typeid === typeid) {
					/* Removing nonexistent charge */
					if(!("charges" in modules[i])) break;

					var charges = modules[i].charges;
					var curcpid = osmium_clf['X-Osmium-current-chargepresetid'];
					var cpid;
					for(var j = 0; j < charges.length; ++j) {
						if(!("cpid" in charges[j])) {
							cpid = 0; /* The spec says a value of 0 must be assumed */
						} else {
							cpid = charges[j].cpid;
						}

						if(curcpid == cpid) {
							osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
								.modules[i].charges.splice(j, 1);
							break;
						}
					}
					break;
				}
			}
		}).on('remove_charge', function() {
			if(li.parents('div.slots').hasClass('grouped')) {
				li.parents('div.slots').find('li.hascharge').filter(function() {
					var t = $(this);
					return t.data('typeid') === li.data('typeid') 
						&& t.data('chargetypeid') === li.data('chargetypeid');
				}).trigger('remove_charge_nogroupcheck');
			} else {
				li.trigger('remove_charge_nogroupcheck');
			}
		});

		var charge = $(document.createElement('span'));
		charge.addClass('charge');
		charge.text(',').append($(document.createElement('br')));
		charge.append($(document.createElement('img')).prop('alt', ''));
		charge.append($(document.createElement('span')).addClass('name'));

		li.append(charge);
		li.addClass('hascharge');

		if(chargeid === null) {
			li.trigger('remove_charge_nogroupcheck');
		} else {
			osmium_add_charge(li, chargeid);
		}

		if(!osmium_loadout_readonly) {
			charge.on('dblclick', function(e) {
				/* When dblclicking the charge, remove the charge but not the module */
				li.trigger('remove_charge');
				osmium_commit_clf();
				osmium_undo_push();
				e.stopPropagation();
				return false;
			});
		}
	}

	if(stateful = osmium_slot_types[m[3]][2]) {
		a = $(document.createElement('a'));
		stateimg = $(document.createElement('img'));
		a.addClass('toggle_state');
		stateimg.prop('alt', '');
		a.append(stateimg);
		li.append(a);

		li.on('state_changed', function() {
			var a = li.children('a.toggle_state');
			var s = osmium_module_state_names[li.data('state')];
			a.empty();

			a.append(osmium_sprite(
				s[0],
				s[1][0],
				s[1][1],
				s[1][2],
				s[1][3],
				16, 16
			));

			a.prop('title', s[0]);
		}).trigger('state_changed');

		a.on('click', function() {
			var a = $(this);
			var li = a.parent();
			var state = li.data('state');
			var nextstate;

			a.blur();

			if(state === 'offline') {
				nextstate = (osmium_module_states[typeid][1] ? 'online' : 'offline');
			} else if(state === 'online') {
				nextstate = (osmium_module_states[typeid][2] ? 'active' : 'offline');
			} else if(state === 'active') {
				nextstate = (osmium_module_states[typeid][3] ? 'overloaded' : 'offline');
			} else if(state === 'overloaded') {
				nextstate = 'offline';
			}

			osmium_set_module_state(li, nextstate);
			osmium_commit_clf();
			osmium_undo_push();
			return false;
		}).on('contextmenu', function() {
			var a = $(this);
			var li = a.parent();
			var state = li.data('state');
			var prevstate;

			a.blur();

			if(state === 'offline') {
				if(osmium_module_states[typeid][3]) {
					prevstate = 'overloaded';
				} else if(osmium_module_states[typeid][2]) {
					prevstate = 'active';
				} else if(osmium_module_states[typeid][1]) {
					prevstate = 'online';
				} else {
					prevstate = 'offline';
				}
			} else if(state === 'online') {
				prevstate = 'offline';
			} else if(state === 'active') {
				prevstate = 'online';
			} else if(state === 'overloaded') {
				prevstate = 'active';
			}

			osmium_set_module_state(li, prevstate);
			osmium_commit_clf();
			osmium_undo_push();
			return false;
		}).on('dblclick', function(e) {
			/* Prevent dblclick fire on the <li> itself */

			e.stopPropagation();
			return false;
		});
	}

	if(placeholders.length > 0) {
		placeholders.first().before(li);
	} else {
		ul.append(li);
	}

	if(osmium_user_initiated) {
		$('a[href="#modules"]').parent().click();
	}

	li.on('remove_module', function() {
		var modules = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules;
		var slotsdiv = li.closest('div.slots');

		if(osmium_types[typeid][8] === 1) {
			osmium_user_initiated_push(false);
			$("section#projected div.pr-loadout.projected-local").find('li').filter(function() {
				var li = $(this);
				return li.data('typeid') === typeid && li.data('index') === index;
			}).each(function() {
				var li = $(this);
				jsPlumb.select({ source: $(this) }).detach();
			}).remove();
			osmium_user_initiated_pop();
		}

		for(var i = 0; i < modules.length; ++i) {
			if(modules[i].index === index && modules[i].typeid === typeid) {
				osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules.splice(i, 1);
				break;
			}
		}

		li.remove();

		if(slotsdiv.find('li.overflow').length > 0) {
			slotsdiv.find('li.overflow').first().removeClass('overflow');
		} else {
			osmium_add_placeholder_module(slotsdiv);
		}

		if(osmium_types[typeid][8] === 1) {
			osmium_user_initiated_push(false);
			osmium_projected_regen_local();
			osmium_user_initiated_pop();
		}
	});

	osmium_ctxmenu_bind(li, function() {
		var menu = osmium_ctxmenu_create();

		if(!osmium_loadout_readonly) {
			osmium_ctxmenu_add_option(menu, "Unfit module", function() {
				li.trigger('remove_module');
				osmium_commit_clf();
				osmium_undo_push();
			}, { 'default': true });

			osmium_ctxmenu_add_option(menu, "Unfit all of the same type", function() {
				li.parent().find('li').filter(function() {
					return $(this).data('typeid') === typeid
				}).trigger('remove_module');

				osmium_commit_clf();
				osmium_undo_push();
				osmium_update_slotcounts();
			}, {});

			osmium_ctxmenu_add_separator(menu);
		}

		if(hascharges) {
			osmium_ctxmenu_add_subctxmenu(menu, "Charges", function() {
				var chargemenu = osmium_ctxmenu_create();

				var charges_mg_pt = {}; /* metagroup -> parenttypeid -> chargetypeid */
				var charges_mg_pt_sorted = {}; /* metagroup -> [ [ parenttypeid, chargetypeids ], … ] */
				var mgcount = 0;

				var append_charges = function(menu, chargetypeids, showhelpers) {
					showhelpers = showhelpers & (
						chargetypeids.length >= 2 && (chargetypeids[0] in osmium_chargedmg)
							&& osmium_chargedmg[chargetypeids[0]] > 0
					);

					if(showhelpers) {
						osmium_ctxmenu_add_option(menu, "More damage", function() {}, { enabled: false });
						osmium_ctxmenu_add_separator(menu);
					}

					for(var i = 0; i < chargetypeids.length; ++i) {
						osmium_ctxmenu_add_option(
							menu,
							osmium_types[chargetypeids[i]][1],
							(function(chargeid) {
								return function() {
									var modules = osmium_clf.presets[
										osmium_clf['X-Osmium-current-presetid']
									].modules;

									for(var i = 0; i < modules.length; ++i) {
										if(modules[i].typeid === typeid && modules[i].index === index) {
											osmium_auto_add_charge_to_location(i, chargeid);
											osmium_commit_clf();
											osmium_undo_push();
											return;
										}
									}
								};
							})(chargetypeids[i]),
							{ icon: "//image.eveonline.com/Type/" + chargetypeids[i] + "_64.png" }
						);
					}

					if(showhelpers) {
						osmium_ctxmenu_add_separator(menu);
						osmium_ctxmenu_add_option(menu, "Less damage", function() {}, { enabled: false });
					}
				};

				var pgroup = function(menu, charges_pt) {
					var showhelpers = charges_pt.length >= 2 && (charges_pt[0][1][0] in osmium_chargedmg)
						&& osmium_chargedmg[charges_pt[0][1][0]] > 0;

					if(showhelpers) {
						osmium_ctxmenu_add_option(menu, "More damage", function() {}, { enabled: false });
						osmium_ctxmenu_add_separator(menu);
					}

					for(var z = 0; z < charges_pt.length; ++z) {
						if(charges_pt[z][1].length === 1) {
							append_charges(menu, charges_pt[z][1], false);
						} else {
							var c = osmium_types[charges_pt[z][0]];

							osmium_ctxmenu_add_subctxmenu(menu, c[1], (function(typeids) {
								return function() {
									var smenu = osmium_ctxmenu_create();
									append_charges(smenu, typeids, true);
									return smenu;
								};
							})(charges_pt[z][1]), {
								icon: "//image.eveonline.com/Type/" + c[0] + "_64.png"
							});
						}
					}

					if(showhelpers) {
						osmium_ctxmenu_add_separator(menu);
						osmium_ctxmenu_add_option(menu, "Less damage", function() {}, { enabled: false });
					}
				};

				compare_damage = function(a, b) {
					var x = (a in osmium_chargedmg) ? osmium_chargedmg[a] : 0;
					var y = (b in osmium_chargedmg) ? osmium_chargedmg[b] : 0;
					return y < x ? -1 : (y > x ? 1 : 0);
				};

				for(var i = 0; i < osmium_charges[typeid].length; ++i) {
					var t = osmium_types[osmium_charges[typeid][i]];
					var parent = t[7] > 0 ? t[7] : t[0];

					if(!(t[4] in charges_mg_pt)) {
						charges_mg_pt[t[4]] = {};
						charges_mg_pt_sorted[t[4]] = {};
						++mgcount;
					}
					if(!(parent in charges_mg_pt[t[4]])) {
						charges_mg_pt[t[4]][parent] = [];
					}
					charges_mg_pt[t[4]][parent].push(t[0]);
				}

				for(var mg in charges_mg_pt) {
					var arr = [];

					for(var pt in charges_mg_pt[mg]) {
						charges_mg_pt[mg][pt].sort(compare_damage);
						arr.push([ pt, charges_mg_pt[mg][pt] ]);
					}

					arr.sort(function(a, b) {
						return compare_damage(a[1][0], b[1][0]);
					});

					charges_mg_pt_sorted[mg] = arr;
				}

				if(mgcount === 1) {
					for(var g in charges_mg_pt_sorted) {
						pgroup(chargemenu, charges_mg_pt_sorted[g]);
					}
				} else {
					for(var g in charges_mg_pt_sorted) {
						osmium_ctxmenu_add_subctxmenu(chargemenu, osmium_metagroups[g], (function(group) {
							return function() {
								var submenu = osmium_ctxmenu_create();
								pgroup(submenu, group);
								return submenu;
							};
						})(charges_mg_pt_sorted[g]), {
							icon: "//image.eveonline.com/Type/" + charges_mg_pt_sorted[g][0][0] + "_64.png"
						});
					}
				}

				return chargemenu;
			}, { icon: "//image.eveonline.com/Type/" + osmium_charges[typeid][0] + "_64.png" });

			osmium_ctxmenu_add_option(menu, "Remove charge", function() {
				li.trigger('remove_charge');
				osmium_commit_clf();
				osmium_undo_push();
			}, { icon: [ 0, 28, 32, 32 ] });

			osmium_ctxmenu_add_separator(menu);
		}

		if(stateful) {
			if(osmium_module_states[typeid][0]) {
				osmium_ctxmenu_add_option(menu, "Offline module", function() {
					osmium_set_module_state(li, "offline");
					osmium_commit_clf();
					osmium_undo_push();
				}, { icon: osmium_module_state_names['offline'][1] });
			}
			if(osmium_module_states[typeid][1]) {
				osmium_ctxmenu_add_option(menu, "Online module", function() {
					osmium_set_module_state(li, "online");
					osmium_commit_clf();
					osmium_undo_push();
				}, { icon: osmium_module_state_names['online'][1] });
			}
			if(osmium_module_states[typeid][2]) {
				osmium_ctxmenu_add_option(menu, "Activate module", function() {
					osmium_set_module_state(li, "active");
					osmium_commit_clf();
					osmium_undo_push();
				}, { icon: osmium_module_state_names['active'][1] });
			}
			if(osmium_module_states[typeid][3]) {
				osmium_ctxmenu_add_option(menu, "Toggle overload", function() {
					if(li.data('state') !== "overloaded") {
						osmium_set_module_state(li, "overloaded");
					} else {
						osmium_set_module_state(li, "active");
					}
					osmium_commit_clf();
					osmium_undo_push();
				}, { icon: osmium_module_state_names['overloaded'][1] });
			}

			osmium_ctxmenu_add_separator(menu);

			osmium_ctxmenu_add_option(menu, "Overload rack", function() {
				if(li.data('state') !== "overloaded") {
					li.closest('div.slots').find('li').each(function() {
						var t = $(this);
						var typeid = t.data('typeid');
						if(!(typeid in osmium_module_states)) return;

						if(osmium_module_states[typeid][3]) {
							osmium_set_module_state(t, "overloaded");
						}
					});
				} else {
					li.closest('div.slots').find('li').each(function() {
						var t = $(this);
						if(t.data('state') !== 'overloaded') return;
						osmium_set_module_state(t, "active");
					});
				}

				osmium_commit_clf();
				osmium_undo_push();
			}, { icon: osmium_module_state_names['overloaded'][1] });

			osmium_ctxmenu_add_separator(menu);
		}

		if(!osmium_loadout_readonly) {
			if(hascharges && li.data('chargetypeid') !== null) {
				osmium_add_generic_browse_mg(menu, typeid,
											 { title: "Browse market group (m)" });
				osmium_add_generic_browse_mg(menu, li.data('chargetypeid'),
											 { title: "Browse market group (c)" });
			} else {
				osmium_add_generic_browse_mg(menu, typeid);
			}
		}

		osmium_ctxmenu_add_option(menu, "Show module info", function() {
			osmium_showinfo({
				type: "module",
				slottype: li.data('slottype'),
				index: li.data('index')
			});
		}, { icon: osmium_showinfo_sprite_position, 'default': osmium_loadout_readonly });

		if(hascharges && li.data('chargetypeid') !== null) {
			osmium_ctxmenu_add_option(menu, "Show charge info", function() {
				osmium_showinfo({
					type: "charge",
					slottype: li.data('slottype'),
					index: li.data('index')
				});
			}, { icon: osmium_showinfo_sprite_position });
		}

		return menu;
	});

	return li;
};

osmium_add_placeholder_module = function(slotsdiv) {
	var ul = slotsdiv.children('ul');
	var li;
	var type = slotsdiv.data('type');

	li = $(document.createElement('li'));
	li.addClass('placeholder');
	li.text('Unused ' + type + ' slot');

	li.prepend(osmium_sprite(
		'',
		osmium_slot_types[type][1][0],
		osmium_slot_types[type][1][1],
		osmium_slot_types[type][1][2],
		osmium_slot_types[type][1][3],
		32, 32
	));
	ul.append(li);
};

osmium_set_module_state = function(li, newstate) {
	var index = li.data('index');
	var typeid = li.data('typeid');

	li.data('state', newstate);

	var modules = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules;
	for(var i = 0; i < modules.length; ++i) {
		if(modules[i].index === index && modules[i].typeid === typeid) {
			osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
				.modules[i].state = newstate;
			break;
		}
	}

	li.trigger('state_changed');
};

osmium_add_charge = function(li, chargetypeid) {
	var span = li.children('span.charge');
	var img = $(document.createElement('img'));
	var charge = span.children('span.name');

	li.data('chargetypeid', chargetypeid);
	img.prop('src', '//image.eveonline.com/Type/' + chargetypeid + '_64.png');
	img.prop('alt', '');
	span.children('img, span.mainsprite').replaceWith(img);

	charge.empty();
	charge.text(osmium_types[chargetypeid][1]);
	charge.prop('title', osmium_types[chargetypeid][1]);
};

osmium_add_charge_by_location = function(locationtypeid, locationindex, chargetypeid) {
	var li = $('section#modules > div.slots li.hascharge').filter(function() {
		var t = $(this);
		return t.data('typeid') === locationtypeid && t.data('index') === locationindex;
	}).first();

	return osmium_add_charge(li, chargetypeid);
};

osmium_get_best_location_for_charge = function(typeid) {
	var location = null;
	var candidatelevel;

	for(var i = 0; i < osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
		.modules.length; ++i) {
		m = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules[i];

		var validcharge = false, currentchargeid = null;
		if(!(m.typeid in osmium_charges)) {
			/* Module can't accept charges */
			continue;
		}
		for(var j = 0; j < osmium_charges[m.typeid].length; ++j) {
			if(osmium_charges[m.typeid][j] === typeid) {
				validcharge = true;
				break;
			}
		}
		if(!validcharge) continue;

		if(location === null) {
			/* As a fallback, fit the charge to the first suitable location */
			location = i;
			candidatelevel = 0; /* This candidate is not very good */
		}

		if(!("charges" in m)) {
			/* The module has no charges and can accept the charge, perfect */
			location = i;
			break;
		}

		var charges = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
			.modules[i].charges;
		var cpid;

		/* Check if charge already present */
		for(var j = 0; j < charges.length; ++j) {
			if("cpid" in charges[j]) {
				cpid = charges[j].cpid;
			} else {
				cpid = 0;
			}

			if(cpid == osmium_clf['X-Osmium-current-chargepresetid']) {
				currentchargeid = charges[j].typeid;
				break;
			}
		}
		if(currentchargeid === null) {
			/* The module has no charge in this preset and can accept the charge */
			location = i;
			break;
		} else if(currentchargeid !== typeid && candidatelevel < 1) {
			/* The module has a different charge, but it's still a
			 * better candidate than the fallback */
			location = i;
			candidatelevel = 1;
		}
	}

	return location;
};

osmium_auto_add_charge_to_location = function(location, typeid) {
	var m = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
		.modules[location];
	var moduletypeid = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
		.modules[location].typeid;
	var moduletype = osmium_types[moduletypeid][3];
	var previouschargeid = null;

	if(!("charges" in m)) {
		osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
			.modules[location].charges = [];
	}

	var charges = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
		.modules[location].charges;
	var cpid;

	/* Remove previous charge (if there is one) */
	for(var j = 0; j < charges.length; ++j) {
		if("cpid" in charges[j]) {
			cpid = charges[j].cpid;
		} else {
			cpid = 0;
		}

		if(cpid !== osmium_clf['X-Osmium-current-chargepresetid']) {
			continue;
		}

		previouschargeid = charges[j].typeid;

		osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
			.modules[location].charges.splice(j, 1);
		break;
	}

	/* Finally, add the new charge */
	osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
		.modules[location].charges.push({
			typeid: typeid,
			cpid: osmium_clf['X-Osmium-current-chargepresetid']
		});

	osmium_add_charge_by_location(m.typeid, m.index, typeid);

	if($("section#modules > div.slots." + moduletype).hasClass('grouped')) {
		/* Also add this charge to identical modules with identical charges */

		var curchargeid;
		var curchargeidx;
		for(var i = 0; i < osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
			.modules.length; ++i) {
			curchargeid = null;
			curchargeidx = 0;

			m = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules[i];
			if(m.typeid !== moduletypeid) {
				continue;
			}

			if(!("charges" in m)) {
				osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
					.modules[i].charges = [];
			}

			charges = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
				.modules[i].charges;

			for(var j = 0; j < charges.length; ++j) {
				if("cpid" in charges[j]) {
					cpid = charges[j].cpid;
				} else {
					cpid = 0;
				}

				if(cpid !== osmium_clf['X-Osmium-current-chargepresetid']) {
					continue;
				}

				curchargeid = charges[j].typeid;
				curchargeidx = j;
				break;
			}

			if(curchargeid !== previouschargeid) {
				continue;
			}

			osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
				.modules[i].charges.splice(curchargeidx, 1);

			osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
				.modules[i].charges.push({
					typeid: typeid,
					cpid: osmium_clf['X-Osmium-current-chargepresetid']
				});

			osmium_add_charge_by_location(m.typeid, m.index, typeid);
		}
	}
};
