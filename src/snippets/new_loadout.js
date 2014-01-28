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

$(function() {
	osmium_load_common_data();
	osmium_shortlist = $("div#osmium-data").data('shortlist');

	$('div#nlsources > ul.tabs').each(function() {
		osmium_tabify($(this), 0);
	});

	osmium_load_static_client_data(osmium_cdatastaticver, function(cdata) {
		osmium_gen();
		osmium_init();

		osmium_tabify($('div#nlmain > ul.tabs'), 0);

		/* Fetch computed attributes, etc. */
		osmium_commit_clf();

		/* Everything done from now on is user initiated */
		 osmium_user_initiated_push(true);

		 osmium_undo_push();
	 });
 });

osmium_loadout_readonly = false;
osmium_clftype = 'new';
osmium_on_clf_payload = function(payload) {
	if("ship" in osmium_clf && "typeid" in osmium_clf.ship) {
		osmium_clf_slots = payload.slots;
		osmium_clf_hardpoints = payload.hardpoints;
	}
	osmium_update_slotcounts();

	$("section#metadata tr#recommended_tags").remove();
	if(payload.rectags.length > 0) {
		var tr = $(document.createElement('tr'));
		tr.append(document.createElement('th'));
		tr.prop('id', 'recommended_tags');
		var td = $(document.createElement('td'));
		tr.append(td);
		td.append('Recommended tags: ');
		var ul = $(document.createElement('ul'));
		ul.addClass('tags');
		for(var i = 0; i < payload.rectags.length; ++i) {
			var li = $(document.createElement('li'));
			var a = $(document.createElement('a'));
			a.prop('title', 'Add this tag');
			a.text(payload.rectags[i]);
			li.append(a);
			ul.append(li);
			ul.append(' ');
		}
		td.append(ul);

		$("input#tags").closest('tr').after(tr);
	}

	if(window.history && window.history.replaceState) {
		/* Refresh URI in case token changed and user refreshes the page */
		window.history.replaceState(null, null, './' + osmium_clftoken + window.location.hash);
	}
};

/* Generate all the missing DOM elements from the CLF */
osmium_gen = function() {
	osmium_gen_control();
	osmium_gen_ship();
	osmium_gen_metadata();
	osmium_gen_presets();
	osmium_gen_modules();
	osmium_gen_fattribs();
	osmium_gen_drones();
	osmium_gen_implants();
	osmium_gen_remote();
};

/* Set up event listeners that alter the CLF appropriately */
osmium_init = function() {
	osmium_init_control();
	osmium_init_ship();
	osmium_init_sources();
	osmium_init_metadata();
	osmium_init_presets();
	osmium_init_modules();
	osmium_init_fattribs();
	osmium_init_drones();
	osmium_init_implants();
	osmium_init_remote();
};

osmium_add_to_clf = function(item) {
	var cat = item.data('category');
	var sub = item.data('subcategory');
	var typeid = item.data('typeid');

	if(cat === 'ship') {
		osmium_clf.ship = { typeid: typeid };

		/* Regen everything as changing a ship changes pretty much
		 * everything. */
		osmium_user_initiated_push(false);
		osmium_gen();
		osmium_user_initiated_pop();

		osmium_undo_push();
	} else if(cat === 'module') {
		var state, index, m;
		var slotsdiv = $("section#modules > div.slots." + sub);
		var grouped = slotsdiv.hasClass('grouped');
		var other = osmium_types[typeid][6];
		var addcount = 1;
		var remaining = osmium_clf_slots[sub]
			- slotsdiv.children('ul').children('li:not(.placeholder)').length;

		if(grouped && (other === 'turret' || other === 'launcher')) {
			addcount = osmium_clf_hardpoints[other];
			osmium_clf_hardpoints[other] = 0;
		}

		if(addcount > remaining) {
			addcount = remaining;
		}

		index = 0;
		for(var i = 0; i < osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules.length; ++i) {
			m = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules[i];
			if(m.index >= index) index = m.index + 1;
		}

		if(osmium_module_states[typeid][2]) {
			/* Active state, if possible */
			state = osmium_states[2];
		} else {
			/* Online state */
			state = osmium_states[1];
		}

		if(addcount < 1) addcount = 1;
		for(var i = 0; i < addcount; ++i) {
			osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']].modules.push({
				typeid: typeid,
				state: state,
				index: index
			});

			osmium_add_module(typeid, index, state, null);
			++index;
		}

		if(osmium_types[typeid][8] === 1) {
			osmium_projected_regen_local();
		}

		osmium_update_slotcounts();
		osmium_undo_push();
	} else if(cat === 'charge') {
		var location = osmium_get_best_location_for_charge(typeid);

		if(location !== null) {
			osmium_auto_add_charge_to_location(location, typeid);
			osmium_undo_push();

			if(osmium_user_initiated) {
				$('a[href="#modules"]').parent().click();
			}
		} else {
			alert("This charge cannot be used with any fitted type.");
		}
	} else if(cat === 'drone') {
		var qty = item.data('qty');
		var dest = item.data('dest');
		if(qty < 1 || qty === undefined) qty = 1;
		var bw = parseInt(osmium_types[typeid][6], 10);
		if(dest !== 'bay' && dest !== 'space') {
			if(!("dronebandwidth" in osmium_clf_rawattribs)) {
				dest = 'space';
			} else {
				if(osmium_clf_rawattribs.dronebandwidthused + bw <= osmium_clf_rawattribs.dronebandwidth
				  && osmium_clf_rawattribs.activedrones < osmium_clf_rawattribs.maxactivedrones) {
					dest = 'space';
					qty = Math.min(
						Math.floor(
							(osmium_clf_rawattribs.dronebandwidth
							 - osmium_clf_rawattribs.dronebandwidthused) / bw
						),
						osmium_clf_rawattribs.maxactivedrones - osmium_clf_rawattribs.activedrones
					);
				} else {
					dest = 'bay';
				}
			}
		}
		osmium_add_drone_to_clf(typeid, qty, dest);
		osmium_gen_drones();
		osmium_undo_push();
	} else if(cat === 'implant') {
		var p = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']];
		var implantness = osmium_types[typeid][3];
		if(!("implants" in p)) p.implants = [];

		for(var i = 0; i < p.implants.length; ++i) {
			if(implantness === osmium_types[p.implants[i].typeid][3]) {
				p.implants.splice(i, 1);
				--i;
			}
		}

		p.implants.push({ typeid: typeid });

		osmium_gen_implants();
		osmium_undo_push();

		if(osmium_user_initiated) {
			$('a[href="#implants"]').parent().click();
		}
	} else if(cat === 'booster') {
		var p = osmium_clf.presets[osmium_clf['X-Osmium-current-presetid']];
		var boosterness = osmium_types[typeid][3];
		if(!("boosters" in p)) p.boosters = [];

		for(var i = 0; i < p.boosters.length; ++i) {
			if(boosterness === osmium_types[p.boosters[i].typeid][3]) {
				p.boosters.splice(i, 1);
				--i;
			}
		}

		p.boosters.push({ typeid: typeid });

		osmium_gen_implants();
		osmium_undo_push();

		if(osmium_user_initiated) {
			$('a[href="#implants"]').parent().click();
		}
	}

	osmium_commit_clf();
};
