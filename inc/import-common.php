<?php
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

namespace Osmium\Import;

const MAX_FILESIZE = 1048576;

function print_tri_choice($textarea_name, $uri_name, $file_name) {
	\Osmium\Forms\print_generic_row('', '<label>Method</label>', '<div id="methodselect">Use at most one of the three methods below:</div>');

	\Osmium\Forms\print_textarea('Direct input', $textarea_name, null, 0);
	\Osmium\Forms\print_generic_field('Fetch a URI', $uri_name, $uri_name);
	\Osmium\Forms\print_file('Upload file', $file_name, MAX_FILESIZE);
}

function get_source($textarea_name, $uri_name, $file_name) {
	if(!empty($_POST[$textarea_name])) {
		return truncate($_POST['source']);
	}

	if(!empty($_POST[$uri_name])) {
		$url = $_POST[$uri_name];

		if(filter_var($url, FILTER_VALIDATE_URL) === false) {
			\Osmium\Forms\add_field_error($uri_name, 'Enter a correct URI or leave this field empty.');
			return false;
		}

		$d = parse_url($url);
		if($d['scheme'] != 'http' && $d['scheme'] != 'https') {
			\Osmium\Forms\add_field_error(
				$uri_name,
				'Invalid scheme. Use either <code>http://</code> or <code>https://</code> URLs.'
			);
			return false;
		}

		if(filter_var($d['host'], FILTER_VALIDATE_IP) !== false
		          && !filter_var($d['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
			\Osmium\Forms\add_field_error($uri_name, 'Please enter a public IP address or a domain name.');
			return false;
		}

		return fetch($url);
	}

	if(!empty($_FILES[$file_name]) && $_FILES[$file_name]['error'] != UPLOAD_ERR_NO_FILE) {
		$error = $_FILES[$file_name]['error'];
		if($error == UPLOAD_ERR_INI_SIZE || $error == UPLOAD_ERR_FORM_SIZE 
		   || $_FILES[$file_name]['size'] > MAX_FILESIZE) {
			\Osmium\Forms\add_field_error($file_name, 'The file you tried to upload is too big.');
			return false;
		}

		if($error != UPLOAD_ERR_OK) {
			\Osmium\Forms\add_field_error($file_name, "Internal error ($error), please report.");
			return false;
		}

		return fetch($_FILES[$file_name]['tmp_name']);
	}

	return false;
}

function fetch($uri) {
	$f = fopen($uri, 'rb');
	if($f === false) return false;
	$contents = stream_get_contents($f, MAX_FILESIZE);
	fclose($f);
	return $contents;
}

function truncate($text) {
	return substr(\Osmium\Chrome\trim($text), 0, MAX_FILESIZE);
}
