<?php
/* Osmium
 * Copyright (C) 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

namespace Osmium\DOM;

trait Formatter {

	/* Format a (large) integer with thousands separator(s), without
	 * loss of precision. */
	static function formatExactInteger($n) {
		return number_format($n);
	}

	/* Format a number with a given number of digits. */
	static function formatNDigits($n, $digits = 3, $trim = true) {
		$n = number_format($n, max(1, $digits));
		return $trim ? rtrim(rtrim($n, '0'), '.') : $n;
	}

	/* Format a number with a given number of /significant/ digits.
	 *
	 * @deprecated use truncateSDigits
	 */
	static function formatSDigits($n, $digits = 3) {
		if(!$n) return $n;

		$normalized = $n / ($m = pow(10, floor(log10(abs($n)))));
		return self::formatNDigits($normalized, $digits - 1) * $m;
	}

	/* Only keep the first $digits significant digits of a number
	 * $n. */
	static function truncateSDigits($n, $digits = 3) {
		if(!$n) return $n;
		return round($n / ($m = pow(10, floor(log10(abs($n))))), $digits - 1) * $m;
	}

	/* Format a number with an optional 'k', 'm' or 'b' prefix. */
	static function formatKMB($n, $sd = 3, $min = '', $space = false) {
		static $suffixes = [
			'b' => 1e9,
			'm' => 1e6,
			'k' => 1e3,
			'' => 1,
		];

		if($n < 0) return '-'.self::formatKMB(-$n, $sd, $min);
		foreach($suffixes as $s => $limit) {
			if($n >= $limit || $s === $min) {
				return self::formatSDigits($n / $limit, $sd).($space ? ' ' : '').$s;
			}
		}
	}



	/* Format an amount of reputation points. */
	function formatReputation($points) {
		return $this->element('span', [
			'class' => 'reputation', 'title' => 'reputation points',
			self::formatExactInteger($points),
		]);
	}



	/* Format an attribute with a given unit. */
	function formatNumberWithUnit($number, $unitid, $unitdisplayname) {
		$rawval = $number;

		switch((int)$unitid) {

		case 0: /* No unit */
			$unitdisplayname = '';
			break;

		case 1: /* Meters */
			if($number >= 10000) {
				$number /= 1000;
				$unitdisplayname = 'km';
			} else {
				$unitdisplayname = 'm';
			}
			break;

		case 101: /* Milliseconds */
			$number /= 1000;
			$unitdisplayname = 's';
			break;

		case 104: /* Multiplier */
			$unitdisplayname = 'x';
			break;

		case 108: /* Inverse absolute percent */
			$number = (1 - $number) * 100;

		case 105: /* Percentage */
		case 121: /* Real percentage */
			$unitdisplayname = '%';
			break;

		case 111: /* Inversed modifier percent */
			$number = 2 - $number;

		case 109: /* Modifier percent */
			$number -= 1;
			$number *= 100;

		case 124: /* Modifier relative percent */
			$unitdisplayname = '%';
			$rounded = ($number >= 0 ? '+' : '').$number;
			break;

		case 127: /* Absolute percent */
			$unitdisplayname = '%';
			$number *= 100;
			break;

		case 112: /* Rotation speed */
			$rounded = self::formatSDigits($number, 4);
			break;

		case 115: /* Group ID */
			$sampletypeid = \Osmium\Fit\get_group_any_typeid($number);
			$gn = \Osmium\Fit\get_groupname($number);

			if($gn !== false) {
				$a = $this->element('a', [ 'o-rel-href' => '/db/group/'.$number, $gn ]);
				if($sampletypeid !== false) {
					$img = $this->element('o-eve-img', [ 'src' => '/Type/'.$sampletypeid.'_64.png', 'alt' => '' ]);
					return [ $img, ' ', $a ];
				}
				return $a;
			}
			$unitdisplayname = 'groupid';
			break;

		case 116: /* Type ID */
			$typename = \Osmium\Fit\get_typename($number);
			if($typename !== false) {
				return [
					$this->element('o-eve-img', [ 'src' => '/Type/'.$number.'_64.png', 'alt' => '' ]),
					$this->element('a', [ 'o-rel-href' => '/db/type/'.$number, $typename ]),
				];
			}
			$unitdisplayname = 'typeid';
			break;

		case 117: /* Size class */
			if($number == 1) return 'Small';
			if($number == 2) return 'Medium';
			if($number == 3) return 'Large';
			if($number == 4) return 'XLarge';
			break;

		case 119: /* Attribute ID */
			$dn = \Osmium\Fit\get_attributedisplayname($number);
			if($dn !== false) {
				return $this->element('a', [
					'o-rel-href' => '/db/attribute/'.$number,
					ucfirst(\Osmium\Fit\get_attributedisplayname($number)),
				]);
			}
			$unitdisplayname = 'attributeid';
			break;

		case 137: /* Boolean */
			if($number == 0) return 'False';
			if($number == 1) return 'True';
			break;

		case 139: /* Bonus */
			$rounded = (($number >= 0) ? '+' : '').$number;
			break;

		case 140: /* Level */
			return 'Level '.$number;

		case 142: /* Sex */
			if($number == 1) return 'Male';
			if($number == 2) return 'Unisex';
			if($number == 3) return 'Female';
			break;

		}

		if(!isset($rounded)) {
			$rounded = self::formatNDigits($number, 3);
		}

		return $this->element('span', [
			'title' => sprintf('%.14f', $rawval),
			(string)$rounded, ' ', $unitdisplayname
		]);
	}



	/* Format <b>, <u>, <a href=showinfo> and <url=showinfo> tags. */
	function formatCCPTagSoup($text) {
		$pieces = preg_split(
			'%(<a href=(\'|"?)showinfo:([1-9][0-9]*)(?:// ?[1-9][0-9]*)?\2>|</a>|<url=(\'|"?)showinfo:([1-9][0-9]*)(?:// ?[1-9][0-9]*)\4>|</url>)%',
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY
		);

		$stack = [ [ '', [] ] ];
		$stackc = 0;

		while($p = array_shift($pieces)) {
			$s = substr($p, 0, 4);

			if($s === '<a h' || $s === '<url') {
				$typeid = array_shift($pieces);
				if ($typeid === '"' || $typeid === "'") $typeid = array_shift($pieces);
				++$stackc;
				$stack[] = [ 'a', [ 'o-rel-href' => '/db/type/'.$typeid ] ];
				continue;
			}

			if($p === '</a>' || $p === '</url>') {
				if($stackc < 1) {
					throw new \Exception('unexpected closing tag');
				}

				--$stackc;
				$p = array_pop($stack);
			} else {
				$p = strip_tags($p); /* XXX: hackish */
			}

			$stack[$stackc][1][] = $p;
		}

		return $stack[0][1];
	}

	/* Format a type's traits. */
	function formatTypeTraits($shiptypeid) {
		$traitsq = \Osmium\Db\query_params(
			'SELECT COALESCE(sourcetypeid, sourceother) AS source,
			bonus, t.message, u.unitid, u.displayname
			FROM eve.fsdtypebonuses ftb
			JOIN eve.tramessages t ON t.nameid = ftb.nameid
			LEFT JOIN eve.dgmunits u ON u.unitid = ftb.unitid
			WHERE ftb.typeid = $1
			ORDER BY sourcetypeid ASC, t.nameid ASC',
			array($shiptypeid)
		);

		$div = $this->element('div', [ 'class' => 'traits' ]);

		$prevsrc = null;
		while($t = \Osmium\Db\fetch_assoc($traitsq)) {
			if($t['source'] !== $prevsrc) {
				$prevsrc = $t['source'];
				$src = (int)$t['source'];

				$h3 = $div->appendCreate('h3');
				if($src >= 0) {
					$h3->append([
						[ 'a', [ 'o-rel-href' => '/db/type/'.$src, \Osmium\Fit\get_typename($src) ] ],
						' bonuses (per skill level):',
					]);
				} else if($src === -1) {
					$h3->append('Role bonuses:');
				} else if($src === -2) {
					$h3->append('Miscellaneous bonuses:');
				}

				$ul = $div->appendCreate('ul');
			}

			$li = $ul->appendCreate('li');

			$li->appendCreate('span', [ 'class' => 'bvalue', $t['bonus'] !== null ?
			                            $this->formatNumberWithUnit($t['bonus'], $t['unitid'], $t['displayname'])
			                            : '·' ]);

			$li->append($this->formatCCPTagSoup($t['message']));
		}

		return $prevsrc === null ? false : $div;
	}



	/* Format a query string from given arrays. If there are duplicate
	 * parameters, the last ones have precedence. */
	static function formatQueryString() {
		$g = call_user_func_array('array_merge', func_get_args()); /* 5.6: use ... */
		if($g === []) return '';
		/* DOM will take care of escaping the ampersands */
		return '?'.http_build_query($g, 'n', '&', \PHP_QUERY_RFC1738);
	}



	/* Format a duration. */
	static function formatDuration($nseconds, $abbrev = false, $precision = \PHP_INT_MAX) {
		list($Y, $M, $D, $H, $I, $S) = explode('-', gmdate('Y-m-d-H-i-s', abs($nseconds)));
		$out = array(
			'year' => (int)$Y - 1970,
			'month' => (int)$M - 1,
			'day' => (int)$D - 1,
			'hour' => (int)$H,
			'minute' => (int)$I,
			'second' => (int)$S,
		);

		foreach($out as $k => &$v) {
			if($v === 0) unset($out[$k]);
			$v = $v.($abbrev ? $k[0] : ' '.$k.($v > 1 ? 's' : ''));
		}
		unset($v);

		if($out === []) return $abbrev ? '1s' : 'less than 1 second';
		$out = array_slice($out, 0, $precision);

		if(!$abbrev) {
			$s = array_pop($out);
			$m = array_pop($out);
			if($m === null) return $s;
			$out[] = $m.' and '.$s;
		}

		return implode($abbrev ? ' ' : ', ', $out);
	}

	/* Format a timestamp. If the given timestamp is near enough in
	 * the past or the future, formatDuration() will be used, if not,
	 * a generic date (Y-m-d) will be returned.
	 *
	 * @param $cutoff after this difference (in seconds) between $now
	 * and $timestamp, use absolute dates. Set to -1 to always use
	 * relative dates, set to 0 to always use absolute dates.
	 *
	 * @param $now relative timestamp to compare $timestamp to,
	 * defaults to time()
	 */
	function formatRelativeDate($timestamp, $cutoff = 432000, $now = null) {
		$fd = date('c', $timestamp);
		$dt = $this->element('time', [ 'datetime' => $fd, 'title' => $fd ]);
		if($now === null) $now = time();

		if($cutoff === -1 || abs($now - $timestamp) < $cutoff) {
			$dt->append([
				$now < $timestamp ? 'in ' : '',
				'about ',
				self::formatDuration($timestamp - $now, false, 2),
				$now >= $timestamp ? ' ago' : '',
			]);
		} else {
			$dt->append(date('Y-m-d', $timestamp));
		}

		return $dt;
	}



	/* Format a skill level, in roman numerals. */
	static function formatSkillLevel($level) {
		static $levels = array(
			null => 'Untrained',
			0 => '0',
			1 => 'I',
			2 => 'II',
			3 => 'III',
			4 => 'IV',
			5 => 'V',
		);

		return isset($levels[$level]) ? $levels[$level] : 'Unknown ('.$level.')';
	}
}
