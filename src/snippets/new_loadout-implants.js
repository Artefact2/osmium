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

osmium_gen_implants = function() {
	var p = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']];
	var i_ul = $("section#implants > div.implants > ul");
	var b_ul = $("section#implants > div.boosters > ul");

	i_ul.empty(); b_ul.empty();

	if('implants' in p && p.implants.length > 0) {
		p.implants.sort(function(x, y) {
			return parseInt(osmium_types[x.typeid][3], 10) - parseInt(osmium_types[y.typeid][3], 10);
		});

		for(var i = 0; i < p.implants.length; ++i) {
			var t = p.implants[i].typeid;
			var imp = osmium_types[t];
			var li = $(document.createElement('li'));
			var img = $(document.createElement('img'));
			var span = $(document.createElement('span'));

			img.prop('src', '//image.eveonline.com/Type/' + t + '_64.png');
			img.prop('alt', '');

			li.append($(document.createElement('span')).addClass('name').text(imp[1]));
			li.prop('title', imp[1]);
			li.prepend(img);
			span.text(', implant slot ' + imp[3]).addClass('slot');
			li.append(span);
			li.data('typeid', t);

			osmium_ctxmenu_bind(li, (function(li, t) {
				return function() {
					var menu = osmium_ctxmenu_create();

					if(!osmium_loadout_readonly) {
						osmium_ctxmenu_add_option(menu, "Remove implant", function() {
							var p_imp = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].implants;

							for(var z = 0; z < p_imp.length; ++z) {
								if(t === p_imp[z].typeid) {
									var ul = li.parent();

									p_imp.splice(z, 1);
									osmium_undo_push();
									osmium_commit_clf();
									li.remove();

									if(ul.children('li').length === 0) {
										osmium_gen_implants();
									}
									break;
								}
							}
						}, { 'default': true });

						osmium_ctxmenu_add_separator(menu);

						osmium_add_generic_browse_mg(menu, t);
					}

					osmium_ctxmenu_add_option(menu, "Show implant info", function() {
						osmium_showinfo({ type: 'implant', typeid: t });
					}, { icon: osmium_showinfo_sprite_position, "default": osmium_loadout_readonly });

					return menu;
				};
			})(li, t));

			i_ul.append(li);
		}
	} else {
		var li = $(document.createElement('li'));
		li.addClass('placeholder');
		li.text('No implants');
		i_ul.append(li);
	}

	if('boosters' in p && p.boosters.length > 0) {
		p.boosters.sort(function(x, y) {
			return parseInt(osmium_types[x.typeid][3], 10) - parseInt(osmium_types[y.typeid][3], 10);
		});

		for(var i = 0; i < p.boosters.length; ++i) {
			var t = p.boosters[i].typeid;
			var imp = osmium_types[t];
			var li = $(document.createElement('li'));
			var img = $(document.createElement('img'));
			var span = $(document.createElement('span'));

			img.prop('src', '//image.eveonline.com/Type/' + t + '_64.png');
			img.prop('alt', '');

			li.text(imp[1]);
			li.prop('title', imp[1]);
			li.prepend(img);
			span.text(', booster slot ' + imp[3]).addClass('slot');
			li.append(span);

			osmium_ctxmenu_bind(li, (function(li, t) {
				return function() {
					var menu = osmium_ctxmenu_create();

					if(!osmium_loadout_readonly) {
						osmium_ctxmenu_add_option(menu, "Remove booster", function() {
							var p_bst = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].boosters;

							for(var z = 0; z < p_bst.length; ++z) {
								if(t === p_bst[z].typeid) {
									var ul = li.parent();

									p_bst.splice(z, 1);
									osmium_undo_push();
									osmium_commit_clf();
									li.remove();

									if(ul.children('li').length === 0) {
										osmium_gen_implants();
									}
									break;
								}
							}
						}, { 'default': true });

						osmium_ctxmenu_add_separator(menu);
					}

					osmium_ctxmenu_add_subctxmenu(menu, "Side effects", function() {
						var smenu = osmium_ctxmenu_create();

						if(t in osmium_booster_side_effects) {
							for(var z = 0; z < osmium_booster_side_effects[t].length; ++z) {
								var effectid = osmium_booster_side_effects[t][z][0];
								var effectname = osmium_booster_side_effects[t][z][1];
								var clfbooster = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']]
									.boosters;
								for(var k = 0; k < clfbooster.length; ++k) {
									if(clfbooster[k].typeid === t) {
										clfbooster = clfbooster[k];
									}
								}

								osmium_ctxmenu_add_option(
									smenu, osmium_format_penalty_effectname(effectname),
									(function(effectid, clfbooster) {
										return function() {
											if("X-sideeffects" in clfbooster) {
												var index = $.inArray(effectid, clfbooster['X-sideeffects']);

												if(index !== -1) {
													clfbooster['X-sideeffects'].splice(index, 1);
												} else {
													clfbooster['X-sideeffects'].push(effectid);
												}
											} else {
												clfbooster['X-sideeffects'] = [ effectid ];
											}

											osmium_undo_push();
											osmium_commit_clf();
										};
									})(effectid, clfbooster),
									{
										toggled: "X-sideeffects" in clfbooster
											&& $.inArray(effectid, clfbooster['X-sideeffects']) !== -1
									}
								);
							}
						} else {
							osmium_ctxmenu_add_option(smenu, "No side effects", function() {}, { enabled: false });
						}

						return smenu;
					}, {});

					osmium_ctxmenu_add_separator(menu);

					osmium_ctxmenu_add_option(menu, "Show booster info", function() {
						osmium_showinfo({ type: 'implant', typeid: t });
					}, { icon: osmium_showinfo_sprite_position, "default": osmium_loadout_readonly });

					return menu;
				};
			})(li, t));

			b_ul.append(li);
		}
	} else {
		var li = $(document.createElement('li'));
		li.addClass('placeholder');
		li.text('No boosters');
		b_ul.append(li);
	}
};

osmium_init_implants = function() {
	
};

osmium_format_penalty_effectname = function(name) {
	var s = name.split(/([A-Z])/);
	var words = [];
	words.push(s.shift());
	while(s.length > 0) {
		words.push(s.shift() + s.shift());
	}

	if(words[0] === "booster") words.shift();
	var p = $.inArray("Penalty", words);
	if(p !== -1) words = words.slice(0, p);

	return words.join(" ");
};
