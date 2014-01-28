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

namespace Osmium\StaticPassthrough;

if(isset($_GET['dontcompress']) && $_GET['dontcompress']) {
	ini_set('zlib.output_compression', 'Off');
}

require __DIR__.'/../inc/root.php';

if(isset($_GET['sitemap'])) {
	$_GET['f'] = 'static/cache/sitemap-'.$_GET['sitemap'].'.xml.gz';
}

$allowed = realpath(__DIR__.'/../static/');
$fname = \Osmium\ROOT.'/'.$_GET['f'];

if(strpos($fname, $allowed) !== 0) {
	\Osmium\fatal(404);
}

$f = @fopen($fname, 'rb');
if($f === false) {
	\Osmium\fatal(404);
}

$etag = '"'.\Osmium\STATICVER.'-'.($mtime = filemtime($fname)).'-'.filesize($fname).'"';

header_remove('Pragma');
header_remove('Expires');
header_remove('Set-Cookie');
header('Cache-Control: public');

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
	header('HTTP/1.1 304 Not Modified');
	die();
}

header('Content-Type: '.$_GET['type']);

if(isset($_GET['mexpire'])) {
	$cutoff = $mtime + (int)$_GET['mexpire'];
	header('Expires: '.date(DATE_RFC1123, $cutoff));
}

header('ETag: '.$etag);

fpassthru($f);
