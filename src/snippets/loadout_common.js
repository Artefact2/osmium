/* Osmium
 * Copyright (C) 2012, 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 * Copyright (C) 2013 Josiah Boning <jboning@gmail.com>
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

osmium_user_initiated = false;
osmium_user_initiated_stack = [];

osmium_undo_stack = [];
osmium_undo_stack_position = 0;

osmium_clfspinner = undefined;
osmium_clfspinner_level = 0;

osmium_must_send_clf = false;
osmium_sending_clf = false;
osmium_next_clf_opts = undefined;

osmium_load_static_client_data = function(staticver, onsuccess) {
	var idx = 'osmium_static_client_data_' + staticver;

	var onsuccess2 = function(json) {
		osmium_groups = json.groups;
		osmium_types = json.groups.types;
		osmium_charges = json.charges;
		osmium_metagroups = json.metagroups;
		osmium_module_states = json.modulestates;
		osmium_module_state_names = json.modulestatenames;
		osmium_slot_types = json.slottypes;
		osmium_ship_slots = json.shipslots;
		osmium_chargedmg = json.chargedmg;
		osmium_targetclass = json.targetclass;
		osmium_damage_profiles = json.dmgprofiles;
		osmium_booster_side_effects = json.boostersideeffects;

		/* Module states as they are defined in the CLF specification */
		osmium_states = ['offline', 'online', 'active', 'overloaded'];

		return onsuccess(json);
	};

	try {
		for(var i = 0; i < staticver; ++i) {
			localStorage.removeItem('osmium_static_client_data_' + i);
		}

		var cdata = localStorage.getItem(idx);
		if(cdata !== null) {
			return onsuccess2(JSON.parse(cdata));
		}
	} catch(e) { /* Incognito mode probably */ }

	$.ajax({
		type: 'GET',
		url: osmium_relative + '/static-' + staticver + '/cache/clientdata.json',
		dataType: 'json',
		error: function(xhr, error, httperror) {
			if(xhr.readyState === 0 || xhr.status === 0) {
				/* User probably clicked cancel button or is closing
				 * the page */
				return;
			}

			alert('Could not fetch static client data: ' + error + ' (' + httperror
				  + '). Try refreshing the page and report if the problem persists.');
		},
		success: function(json) {
			try { localStorage.setItem(idx, JSON.stringify(json)); }
			catch(e) { /* Incognito mode probably */ }
			return onsuccess2(json);
		}
	});
};

osmium_user_initiated_push = function(value) {
	osmium_user_initiated_stack.push(osmium_user_initiated);
	osmium_user_initiated = value;
};

osmium_user_initiated_pop = function() {
	osmium_user_initiated = osmium_user_initiated_stack.pop();
};

osmium_undo_trim = function() {
	while(osmium_undo_stack.length > 512) {
		osmium_undo_stack.shift();
		--osmium_undo_stack_position;
	}
}

osmium_undo_push = function() {
	osmium_undo_stack.push($.extend(true, {}, osmium_clf));
	osmium_undo_stack_position = osmium_undo_stack.length - 1;
	osmium_undo_trim();
};

osmium_undo_pop = function() {
	if(osmium_undo_stack_position < 1) {
		/* No more history to undo */
		return;
	}

	/*  Very similar to the "undo" feature of Emacs. Powerful and
	 *  cannot lose data by undoing stuff then doing modifications. */
	--osmium_undo_stack_position;
	osmium_clf = $.extend(true, {}, osmium_undo_stack[osmium_undo_stack_position]);
	osmium_undo_stack.push($.extend(true, {}, osmium_clf));
	osmium_undo_trim();
}

/**
 * Synchronize the CLF with the server and update the attribute list,
 * etc. It is safe to call this function repeatedly in a short amount
 * of time, it has built-in rate limiting. Requires osmium_clftype and
 * osmium_on_clf_payload global variables to be set.
 *
 * If specified, opts is an object which can contain any of the following properties:
 *
 * - params: send these additional params to process_clf
 * - before: function(), called before actually sending the CLF
 * - after: function(), called when process_clf is done
 * - success: function(payload), called when process_clf is done and didn't throw an error
 */
osmium_commit_clf = function(opts) {
	osmium_must_send_clf = true;

	if(osmium_sending_clf) {
		if(opts !== undefined) {
			osmium_next_clf_opts = opts;
		}

		return;
	}
	osmium_sending_clf = true;

	osmium_send_clf(opts);
};

/** @internal */
osmium_send_clf = function(opts) {
	if(!osmium_must_send_clf) {
		osmium_sending_clf = false;
		return;
	}
	osmium_must_send_clf = false;

	if(opts === undefined) {
		opts = osmium_next_clf_opts;
		osmium_next_clf_opts = undefined;
	}

	if(opts === undefined) {
		opts = {};
	}

	var postopts = $.extend({
		type: osmium_clftype,
		token: osmium_token,
		relative: osmium_relative,
		clf: osmium_compress_json(osmium_clf)
	}, (("params" in opts) ? opts.params : {}));

	osmium_clfspinner_push();

	if("before" in opts) opts.before();

	$.ajax({
		type: 'POST',
		url: osmium_relative + '/internal/syncclf/' + osmium_clftoken,
		data: postopts,
		dataType: 'json',
		complete: function() {
			osmium_clfspinner_pop();
			if("after" in opts) {
				opts.after();
			}
		},
		error: function(xhr, error, httperror) {
			if(xhr.readyState === 0 || xhr.status === 0) {
				return;
			}

			alert('Could not sync loadout with remote: ' + error + ' (' + httperror 
				  + '). This shouldn\'t normally happen, try again or refresh the page. Please report if the problem persists.');
			setTimeout(osmium_send_clf, 500);
		},
		success: function(payload) {
			osmium_clftoken = payload.clftoken;
			osmium_capacitors = payload.capacitors;
			osmium_ia = payload.ia;

			for(var key in osmium_capacitors) {
				osmium_regen_remote_capacitor(key);
			}

			$("tr.error.clferror").removeClass('error').removeClass('clferror');
			$("tr.error_message.clferror").remove();
			$("p.error_box.clferror").remove();
			$(".clferror").removeClass('clferror');

			if("form-errors" in payload) {
				for(var i = 0; i < payload['form-errors'].length; ++i) {
					var err = payload['form-errors'][i];

					var tr = $(document.createElement('tr'));
					var td = $(document.createElement('td'));
					var p = $(document.createElement('p'));

					tr.addClass('error_message').addClass('clferror');
					td.attr('colspan', '2');
					p.text(err[2]);
					td.append(p);
					tr.append(td);

					$(err[1]).closest('tr').addClass('error').addClass('clferror').before(tr);
					if(err[0]) {
						$("a[href='#"  + err[0] +  "']").click();
					}
				}
			}

			if("p-errors" in payload) {
				for(var i = 0; i < payload['p-errors'].length; ++i) {
					var err = payload['p-errors'][i];

					var p = $(document.createElement('p'));
					p.addClass('error_box').addClass('clferror').text(err[2]);

					$(err[1]).before(p);
					if(err[0]) {
						$("a[href='#"  + err[0] +  "']").click();
					}
				}
			}

			$('div#computed_attributes').html(payload.attributes);
			osmium_clf_rawattribs = payload.rawattribs;
			osmium_gen_fattribs();
			osmium_init_fattribs();

			$("section#modules div.slots li.hasattribs, section#drones div.drones li.hasattribs")
				.removeClass('hasattribs')
				.children('small.attribs').remove()
			;

			var s, el;

			for(var i = 0; i < osmium_ia.length; ++i) {
				if(!("fshort" in osmium_ia[i])) continue;

				s = $(document.createElement('small'));
				s.text(osmium_ia[i].fshort);
				if("flong" in osmium_ia[i]) {
					s.prop('title', osmium_ia[i].flong);
				}
				s.addClass('attribs');

				if(osmium_ia[i].location[0] === "module") {
					el = $("section#modules div.slots." + osmium_ia[i].location[1] + " li")
						.filter(function() {
							return $(this).data('index') == osmium_ia[i].location[2];
						});
				} else if(osmium_ia[i].location[0] === "drone") {
					el = $("section#drones div.drones.space li").filter(function() {
						return $(this).data('typeid') == osmium_ia[i].location[1];
					});
				} else {
					continue;
				}

				el.addClass('hasattribs').append(s);
			}

			$("section#modules div.slots li > span.charge.hasncycles").removeClass('hasncycles')
				.children('span.ncycles').remove();
			for(var i = 0; i < payload.ncycles.length; ++i) {
				var s = $(document.createElement('span'));
				s.text(payload.ncycles[i][2]);
				s.prop('title', 'Number of module cycles before having to reload');
				s.addClass('ncycles');

				$("section#modules div.slots." + payload.ncycles[i][0] + " li").filter(function() {
					return $(this).data('index') == payload.ncycles[i][1];
				}).children('span.charge').addClass('hasncycles').append(s);
			}

			$("section#modules div.slots li.missingprereqs").removeClass('missingprereqs');
			for(var i = 0; i < payload.missingprereqs.length; ++i) {
				$("section#modules div.slots." + payload.missingprereqs[i][0] + " li").filter(function() {
					return $(this).data('index') == payload.missingprereqs[i][1];
				}).addClass('missingprereqs');
			}

			$("section#drones small.bayusage").text(
				osmium_clf_rawattribs.dronecapacityused
					+ ' / ' + osmium_clf_rawattribs.dronecapacity + ' m³'
			).toggleClass(
				'overflow',
				osmium_clf_rawattribs.dronecapacityused > osmium_clf_rawattribs.dronecapacity
			);
			$("section#drones small.bandwidth").text(
				osmium_clf_rawattribs.dronebandwidthused
					+ ' / ' + osmium_clf_rawattribs.dronebandwidth + ' Mbps'
			).toggleClass(
				'overflow',
				osmium_clf_rawattribs.dronebandwidthused > osmium_clf_rawattribs.dronebandwidth
			);
			var ndrones = 0;
			var dp = osmium_clf.drones[osmium_clf['X-Osmium-current-dronepresetid']];
			if("inspace" in dp) {
				for(var i = 0; i < dp.inspace.length; ++i) {
					ndrones += dp.inspace[i].quantity;
				}
			}
			$("section#drones small.maxdrones").text(
				ndrones + ' / ' + osmium_clf_rawattribs.maxactivedrones
			).toggleClass(
				'overflow',
				ndrones > osmium_clf_rawattribs.maxactivedrones
			);
			osmium_clf_rawattribs.activedrones = ndrones;

			osmium_on_clf_payload(payload);
			if("success" in opts) opts.success(payload);
			setTimeout(osmium_send_clf, 500);
		}
	});
};

osmium_compress_json = function(json) {
	return btoa(RawDeflate.deflate(
		JSON.stringify(json).replace(/[\u007F-\uFFFF]/g, function(m) {
			/* Thanks to Jason S. for this neat code, see
			 * http://stackoverflow.com/a/4901205/615776 */
			return "\\u" + ('0000' + m.charCodeAt(0).toString(16)).slice(-4);
		})
	));
};

osmium_clfspinner_push = function() {
	if(osmium_clfspinner_level === 0) {
		if(osmium_clfspinner === undefined) {
			osmium_clfspinner = $(document.createElement('span'))
				.prop('id', 'clfspinner')
				.addClass('spinner')
				.hide();
			$("body").append(osmium_clfspinner);
		}

		osmium_clfspinner.fadeIn(100);
	}

	++osmium_clfspinner_level;
};

osmium_clfspinner_pop = function() {
	--osmium_clfspinner_level;

	if(osmium_clfspinner_level === 0) {
		osmium_clfspinner.fadeOut(250);
	}
};

osmium_load_common_data = function() {
	var d = $("div#osmium-data");

	osmium_cdatastaticver = d.data('cdatastaticver');
	osmium_staticver = d.data('staticver');
	osmium_relative = d.data('relative');
	osmium_token = d.data('token');
	osmium_clftoken = d.data('clftoken');
	osmium_clf = d.data('clf');
	osmium_custom_damage_profiles = d.data('customdamageprofiles');
	osmium_skillsets = d.data('skillsets');
};
