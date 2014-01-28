<?php
/* Osmium
 * Copyright (C) 2012, 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\EveApi;

/* Change this if you want to use an API proxy. BEWARE: whoever
 * controls the proxy will be able to impersonate any character. */
const API_ROOT = 'https://api.eveonline.com';

/* Set a default timeout of 5 seconds for API calls. Higher values can
 * mean longer login times if the API server is busy. */
const DEFAULT_API_TIMEOUT = 5;

if(!function_exists('curl_strerror')) {
	/* Fallback for PHP < 5.5 users */
	function curl_strerror($no) { return $no; }
}

/**
 * Make an EVE API call. Handles caching.
 *
 * @param $name name of the call, with the leading /
 *
 * @param $params an array of POST parameters to send
 *
 * @returns a SimpleXMLElement if the call was successful (although
 * the XML contents itself may be an error), false if the key is
 * invalid, or null on network/other error.
 */
function fetch($name, array $params, $timeout = null) {
	libxml_use_internal_errors(true);

	/* We sort the $params array to always have the same hash even when
	   the paramaters are not given in the same order. It makes
	   sense. */
	ksort($params);
	$key = serialize($name).serialize($params);

	$xmltext = \Osmium\State\get_cache($key, null, 'API_');
	if($xmltext !== null) {
		return new \SimpleXMLElement($xmltext);
	}

	/* Avoid concurrent accesses to the same API call */
	$sem = \Osmium\State\semaphore_acquire('API_'.$key);
	if($sem === false) return null;

	/* See if another process already cached the call while
	 * semaphore_acquire() blocked */
	$xmltext = \Osmium\State\get_cache($key, null, 'API_');
	if($xmltext !== null) {
		\Osmium\State\semaphore_release($sem);
		return new \SimpleXMLElement($xmltext);
	}

	if($timeout === null) $timeout = DEFAULT_API_TIMEOUT;

	$c = curl_init(API_ROOT.$name);
	curl_setopt($c, CURLOPT_POST, true);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_CAINFO, \Osmium\ROOT.'/ext/ca/GeoTrustGlobalCA.pem');
	curl_setopt($c, CURLOPT_POSTFIELDS, $params);
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($c, CURLOPT_TIMEOUT, $timeout);
	$raw_xml = curl_exec($c);
	$http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
	if($http_code === 403) return false;

	if($errno = curl_errno($c)) {
		trigger_error('Got cURL error '.$errno.': '.curl_strerror($errno).' for call '.$name);
		\Osmium\State\semaphore_release($sem);
		return null;
	}

	curl_close($c);
  
	$xml = false;
	try {
		$xml = new \SimpleXMLElement($raw_xml);
	} catch(\Exception $e) {
		trigger_error('Got unparseable XML from the API, HTTP code was '.$http_code.' for call '.$name, E_USER_WARNING);
		$xml = false;
	}

	if($xml === false || $raw_xml === false) {
		\Osmium\State\semaphore_release($sem);
		return null;
	}

	$expires = strtotime((string)$xml->cachedUntil);
	$curtime = strtotime((string)$xml->currentTime);
	/* Cache for at least 1 minute, in case the cachedUntil values are
	 * erroneous */
	$ttl = min($expires - $curtime + 1, 60);

	\Osmium\State\put_cache($key, $raw_xml, $ttl, 'API_');
	\Osmium\State\semaphore_release($sem);
	return $xml;
}
