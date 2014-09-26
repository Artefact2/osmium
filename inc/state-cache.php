<?php
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

namespace Osmium\State;

/* When $expires parameter is not specified in put_cache (and its
 * variants), set it to this value. (In seconds.) */
const CACHE_DEFAULT_TTL = 86400;

/** @internal */
$__osmium_cache_stack = array();

/** @internal */
$__osmium_cache_enabled = true;

/**
 * Override current cache setting. Nested calls behave correctly as
 * expected.
 *
 * @param $enable whether to enable or disable cache.
 */
function set_cache_enabled($enable = true) {
	global $__osmium_cache_stack;
	global $__osmium_cache_enabled;

	$__osmium_cache_stack[] = $__osmium_cache_enabled;
	$__osmium_cache_enabled = $enable;
}

/**
 * Undo the last override made by set_cache_enabled() and restore the
 * old setting. Supports nested calls.
 */
function pop_cache_enabled() {
	global $__osmium_cache_stack;
	global $__osmium_cache_enabled;

	$__osmium_cache_enabled = array_pop($__osmium_cache_stack);
}

/* NOTE: all the cache types below have their own distinct namespaces
 * for keys. */

/* --------------------- DISK CACHE --------------------- */
/* Disk cache is persistent and global (ie not isolated per
 * account). It is generally the slowest. */

/** @internal */
function get_cache_file($key, $prefix = 'OsmiumCache_') {
	$key = $prefix.$key;

	/* Prevent ultra-long keys which would cause filenames to be too
	 * long. Most modern filesystems have a 255-character limit for
	 * filenames, and no limit on the full path name. */
	if(strlen($key) > 255) {
		$hash = sha1($key);
		$key = substr($key, 0, 214).'_'.$hash;
	}

	$key = strtr($key, '/', '_');
	return \Osmium\CACHE_DIRECTORY.'/'.$key;
}

/**
 * Get a cache variable previously set by put_cache().
 *
 * @param $default the value to return if $key is not found in the
 * cache.
 */
function get_cache($key, $default = null, $prefix = 'OsmiumCache_') {
	global $__osmium_cache_enabled;
	if(!$__osmium_cache_enabled) return $default;

	$f = get_cache_file($key, $prefix);
	if(file_exists($f)) {
		$mtime = filemtime($f);
		if($mtime === 0 || $mtime > time()) {
			return unserialize(file_get_contents($f));
		}
	}
	return $default;
}

/**
 * Store a cache variable. Cache variables are not account-bound.
 *
 * @param $expires if set to zero, the cache value must never expire
 * unless explicitely invalidated. If not, *try* to keep the value in
 * cache for $expires seconds.
 */
function put_cache($key, $value, $expires = null, $prefix = 'OsmiumCache_') {
	global $__osmium_cache_enabled;
	if(!$__osmium_cache_enabled) return;

	if($expires === null) $expires = CACHE_DEFAULT_TTL;

	if($expires > 0) $expires = time() + $expires;
	if($expires < 0) {
		trigger_error("Got a negative ttl in put_cache({$key}): {$expires}", E_USER_WARNING);
	}

	$f = get_cache_file($key, $prefix);
	if(file_put_contents($f, serialize($value)) === false) return false;
	return touch($f, $expires);
}

/**
 * Delete a cached variable.
 */
function invalidate_cache($key, $prefix = 'OsmiumCache_') {
	global $__osmium_cache_enabled;
	if(!$__osmium_cache_enabled) return;

	$f = get_cache_file($key, $prefix);
	if(file_exists($f)) unlink($f);
}

/**
 * Get the expiration date of a cached key.
 *
 * @returns UNIX timestamp of the expiration date, or the special
 * value 0 if the key is cached and set to never expire.
 */
function get_expiration_date($key, $prefix = 'OsmiumCache_') {
	global $__osmium_cache_enabled;
	/* Zero is for cache that does not expires; when cache is
	 * disabled, this function should always indicate all the cache to
	 * be expired. */
	if(!$__osmium_cache_enabled) return 1;

	$f = get_cache_file($key, $prefix);
	return file_exists($f) ? filemtime($f) : 1;
}

/**
 * Count the number of (unexpired) cache entries matching a certain
 * criteria.
 *
 * @param $filters an array which can contain the following keys:
 * 
 * - regex: a regular expression to test the cache key against (be
     careful, the key is truncated for very long keys)
 * 
 * - mmin: entry was modified at least N minutes ago
 */
function count_cache_entries(array $filters = array(), $prefix = 'OsmiumCache_') {
	$command = 'find '.escapeshellarg(\Osmium\CACHE_DIRECTORY)
		.' -maxdepth 1 -type f -mtime -0 -printf "%f\n" -name '
		.escapeshellarg($prefix.'*');

	if(isset($filters['mmin'])) {
		$command .= ' -mmin -'.(int)$filters['mmin'];
	}

	if(isset($filters['amin'])) {
		$command .= ' -amin -'.(int)$filters['amin'];
	}

	if(isset($filters['regex'])) {
		$command .= ' | grep -P '.escapeshellarg($filters['regex']);
	}

	$command .= ' | wc -l';

	return (int)trim(shell_exec($command));
}

/* --------------------- MEMORY CACHE --------------------- */
/* Memory cache is non-persistent and global. It is usually faster
 * than disk cache, but there is no guarantee that a stored item will
 * be retrievable later even if it has not expired yet.. */

if(function_exists('apc_store')) {
	/* Use APC-based memory cache */

	/** @see get_cache() */
	function get_cache_memory($key, $default = null, $prefix = '') {
		global $__osmium_cache_enabled;
		if(!$__osmium_cache_enabled) return;

		$v = apc_fetch('Osmium_'.$prefix.$key, $success);
		return $success ? $v : $default;
	}

	/** @see put_cache() */
	function put_cache_memory($key, $value, $expires = null, $prefix = '') {
		global $__osmium_cache_enabled;
		if(!$__osmium_cache_enabled) return;

		if($expires === null) $expires = CACHE_DEFAULT_TTL;
		return apc_store('Osmium_'.$prefix.$key, $value, $expires);
	}

	/** @see invalidate_cache() */
	function invalidate_cache_memory($key, $prefix = '') {
		global $__osmium_cache_enabled;
		if(!$__osmium_cache_enabled) return;

		return apc_delete('Osmium_'.$prefix.$key);
	}

	/** @see count_cache_entries() */
	function count_memory_cache_entries(array $filters = array(), $prefix = '') {
		if(extension_loaded('apcu')) {
			$iter = new \APCIterator(
				'%^Osmium_'.preg_quote($prefix).'%',
				APC_ITER_KEY | APC_ITER_MTIME,
				128, APC_LIST_ACTIVE
			);
		} else {
			$iter = new \APCIterator(
				'user', '%^Osmium_'.preg_quote($prefix).'%',
				APC_ITER_KEY | APC_ITER_MTIME,
				128, APC_LIST_ACTIVE
			);
		}
		$count = 0;
		$strip = strlen('Osmium_'.$prefix);
		$mcutoff = isset($filters['mmin']) ? (time() - 60 * $filters['mmin']) : 0;
		foreach($iter as $e) {
			if(isset($filters['regex']) && !preg_match($filters['regex'], substr($e['key'], $strip))) {
				continue;
			}

			if($e['mtime'] < $mcutoff) continue;

			++$count;
		}

		return $count;
	}
} else {
	/* Use disk-based cache as a fallback */

	/** @see get_cache() */
	function get_cache_memory($key, $default = null, $prefix = '') {
		return get_cache($key, $default, 'MemoryCache_'.$prefix);
	}

	/** @see put_cache() */
	function put_cache_memory($key, $value, $expires = null, $prefix = '') {
		return put_cache($key, $value, $expires, 'MemoryCache_'.$prefix);
	}

	/** @see invalidate_cache() */
	function invalidate_cache_memory($key, $prefix = '') {
		return invalidate_cache($key, 'MemoryCache_'.$prefix);
	}

	/** @see count_cache_entries() */
	function count_memory_cache_entries(array $filters = array(), $prefix = '') {
		return count_cache_entries($filters, 'MemoryCache_'.$prefix);
	}
}

/* --------------------- PERSISTENT MEMORY CACHE --------------------- */
/* Persistent memory cache is persistent and global, and should be
 * faster than disk-only cache in most cases. */

/** @see get_cache() */
function get_cache_memory_fb($key, $default = null, $prefix = '') {
	$v = get_cache_memory($key, null, $prefix);
	if($v === null) {
		$v = get_cache('MemoryFB_'.$key, null, $prefix);
		if($v === null) {
			return $default;
		}

		/* Re-put the value in the memory cache */
		$expires = get_expiration_date('MemoryFB_'.$key, $prefix);
		$time = time();
		if($expires == 0 || $expires > $time) {
			put_cache_memory($key, $v, ($expires == 0) ? $expires : ($expires - $time), $prefix);
		}
	}

	return $v;
}

/** @see put_cache() */
function put_cache_memory_fb($key, $value, $expires = null, $prefix = '') {
	put_cache('MemoryFB_'.$key, $value, $expires, $prefix);
	put_cache_memory($key, $value, $expires, $prefix);
}

/** @see invalidate_cache() */
function invalidate_cache_memory_fb($key, $prefix = '') {
	invalidate_cache('MemoryFB_'.$key, $prefix);
	invalidate_cache_memory($key, $prefix);
}

/* --------------------- STATE --------------------- */
/* State is non-persistent (it will at least persist for the session
 * lifetime) and isolated (per user). Internally, it can be either
 * disk-based or memory-based, so large data should not be stored in
 * state variables. */

/**
 * Get a state variable previously set via put_state().
 *
 * @param $default the value to return if $key is not found.
 */
function get_state($key, $default = null) {
	if(!isset($_SESSION) && isset($_SERVER['REMOTE_ADDR'])) {
		session_start();
	}

	if(isset($_SESSION['__osmium_state'][$key])) {
		return $_SESSION['__osmium_state'][$key];
	} else return $default;
}

/**
 * Store a state variable. State variables are account-bound but do
 * not persist between sessions.
 */
function put_state($key, $value) {
	if(!isset($_SESSION) && isset($_SERVER['REMOTE_ADDR'])) {
		session_start();
	}

	if(!isset($_SESSION['__osmium_state']) || !is_array($_SESSION['__osmium_state'])) {
		$_SESSION['__osmium_state'] = array();
	}

	return $_SESSION['__osmium_state'][$key] = $value;
}

/* --------------------- SETTINGS --------------------- */
/* Settings are persistent and isolated. They are stored in the
 * database. For anonymous user, regular state is used instead. Each
 * call to get_setting() or put_setting() issues a database query, so
 * use with moderation. */

/**
 * Get a setting previously stored with put_setting().
 *
 * @param $default the value to return if $key is not found.
 */
function get_setting($key, $default = null) {
	if(!is_logged_in()) {
		return get_state('__setting_'.$key, $default);
	}

	$accountid = get_state('a')['accountid'];

	$r = \Osmium\Db\fetch_row(\Osmium\Db\query_params(
		'SELECT value FROM osmium.accountsettings WHERE accountid = $1 AND key = $2',
		[ $accountid, $key ]
	));

	return $r !== false ? unserialize($r[0]) : $default;
}

/**
 * Store a setting. Settings are account-bound, database-stored and
 * persistent between sessions.
 */
function put_setting($key, $value) {
	if(!is_logged_in()) {
		$tempsettings = get_state('__settings', []);
		$tempsettings[$key] = true;
		put_state('__settings', $tempsettings);
		return put_state('__setting_'.$key, $value);
	}

	$accountid = get_state('a')['accountid'];

	\Osmium\Db\query('BEGIN');
	\Osmium\Db\query_params(
		'DELETE FROM osmium.accountsettings WHERE accountid = $1 AND key = $2',
		[ $accountid, $key ]
	);
	\Osmium\Db\query_params(
		'INSERT INTO osmium.accountsettings (accountid, key, value) VALUES ($1, $2, $3)',
		[ $accountid, $key, serialize($value) ]
	);
	\Osmium\Db\query('COMMIT');

	return $value;
}

/* --------------------- SEMAPHORES --------------------- */
/* You can use semaphores to avoid cache slams (ie when a heavily used
 * cache entry expires, the cache will be generated simultaneously by
 * a lot of requests and waste resources). All the functions above
 * will not use semaphores automatically, you will have to do it when
 * necessary. */

/**
 * Acquire a semaphore. Unlike semaphore_acquire(), this call has no
 * chance of colliding with other semaphore names. This is recommended
 * if you plan to acquire the semaphore for a long time (more than a
 * few seconds).
 *
 * Semaphores returned by semaphore_acquire_nc() and
 * semaphore_acquire() use a different namespace.
 */
function semaphore_acquire_nc($name, $prefix = 'Sem_NC') {
	$f = fopen($filename = get_cache_file($name, $prefix), 'cb');
	touch($filename, 0);
	if($f === false) return false;
	if(flock($f, LOCK_EX) === false) return false;
	$ret = [ $f, $filename ];
	register_shutdown_function(__NAMESPACE__.'\semaphore_release_nc', $ret);
	return $ret;
}

/**
 * Release a semaphore acquired with semaphore_acquire_nc(). This is
 * automatically done when the process terminates.
 */
function semaphore_release_nc($semaphore) {
	flock($semaphore[0], LOCK_UN);
}

if(function_exists('sem_acquire')) {
	/* Use the builtin semaphore functions */

	/**
     * Acquire a semaphore. This will block until the semaphore can be
     * acquired.
     *
     * @warning Do NOT nest multiple semaphore_acquire() calls, as
     * they can collide and the second call will block
     * indefinitely. Use the *_nc() variants for all but at most one
     * _acquire() call.
     *
     * @returns the semaphore resource to be given to
     * semaphore_release().
     */
	function semaphore_acquire($name) {
		$key = (int)\Osmium\get_ini_setting('sem_start')
			+ (crc32($name) & 0x8FFFFFFF) % (int)\Osmium\get_ini_setting('num_sems');
		$id = sem_get($key, 1, 0600, 1);
		if($id === false) return false;
		if(sem_acquire($id) === false) return false;
		return $id;
	}

	/**
	 * Release a semaphore. This is automatically done when the
	 * process terminates.
	 */
	function semaphore_release($semaphore) {
		sem_release($semaphore);
	}
} else {
	/* Use the *_nc semaphores as a fallback */
	function semaphore_acquire($name) { return semaphore_acquire_nc($name, 'Sem'); }
	function semaphore_release($sem) { return semaphore_release_nc($sem); }
}
