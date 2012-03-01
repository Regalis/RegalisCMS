<?php
/*
* 
* Copyright (C) Patryk Jaworski <regalis@regalis.com.pl>
* 
* NOTE! This project is released under the terms of GNU/GPLv3 license
* for *non-commercial* use only.
* For commercial license - contact author.
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
* 
*/
namespace RegalisCMS\Pacman;

//! Package manager exception
class PEception extends \Exception {}

//! Package manager main class
class Pacman {
	private $root = "./"; //!< root directory
	private $db_path = "var/lib/pacman/"; //!< database directory
	private $logger = null; //!< logger instance

	//! Constructor - checks for phar PHP extension
	public function __construct() {
		if(!extension_loaded('phar')) 
			throw new PException(_("Unable to find PHP extension named PHAR. Contact your system administrator"));
	}

	/** Get path relative to root directory
	* @param path relative path
	* @return absolute path
	*/
	private function path($path) {
		return $this->root . "/" . $path;
	}

	//! Get root directory
	public function getRoot() {
		return $this->root;
	}

	/** Get path relative to PHAR archive path
	* @param path absolute PHAR path
	* @param archive relative archive path
	*/
	public function realPath($path, $archive) {
		$start = strpos($path, basename($archive)."/");
		if($start !== false)
			return substr($path, $start + strlen(basename($archive)) + 1);
		return false;
	}
	
	/** Set root directory
	* @param root new root directory
	*/
	public function setRoot($root) {
		if(!is_dir($root))
			throw new PException(sprintf(_("%s is not a directory or does not exists"), $root));
		$this->root = $root;
	}
}

class Version {
	public $epoch = 0;
	public $version = '0.0';
	public $release = 0;

	/** Constructor
	* @param str version string
	*/
	public function __construct($str = '0:0.0-0') {
		$this->parse($str);
	}

	/** Object to string conversion
	* @return string in format [epoch:]version[-release]
	*/
	public function __toString() {
		return sprintf("%s%s%s", ($this->epoch != 0 ? $this->epoch . ':' : ''), $this->version, ($this->release != 0 ? '-' . $this->release : ''));
	}

	/** Parse version string
	*
	* Format:
	*  [epoch:]version[-release]
	* 
	* @param str version string
	* @return false if an error occurred; true in another case
	*/
	public function parse($str) {
		$pos = strpos($str, ':');
		if($pos == false) {
			$this->epoch = 0;
			$pos = -1;
		}
		else
			$this->epoch = substr($str, 0, $pos);
		$this->version = substr($str, $pos + 1);
		$pos = strpos($this->version, '-');
		if($pos === false)
			$this->release = 0;
		else {
			if($pos == 0)
				return false;
			$this->release = substr($this->version, $pos + 1);
			$this->version = substr($this->version, 0, $pos);
		}
	}
	
	//! Version object is empty?
	public function isNull() {
		return ($this->epoch == 0 && $this->version == '0.0' && $this->release == 0);
	}

	/** Compare two version strings and determine which one is 'newer'.
	* Returns 1 if a is newer than b, 0 if a and b are the same version, or -1
	* if b is newer than a.
	*
	* Different epoch values for version strings will override any further
	* comparison. If no epoch is provided, 0 is assumed.
	*
	* Examples:
	*  1.1.2 < 1.1.3
	*  1.2.2-1 > 1.2.2
	*  1:0.1-1 > 1.2-5
	*  0.1.2b > 0.1.2a
	*  0.1.2b < 0.1.2rc3
	*  0.1-5 = 0.1-5
	*  0.1-5 < 0.1-6
	*  0.1-6 > 0.1-1
	*
	* @param a version a
	* @param b version b
	* @return -1, 0 or 1
	*/
	public static function compare(Version $a, Version $b) {
		// Simple check
		if($a->isNull() && $b->isNull())
			return 0;
		if($a->isNull())
			return -1;
		if($b->isNull())
			return 1;
		// Maybe versions are equal?
		if(strcmp((string)$a, (string)$b) == 0)
			return 0;
		// We must check all segments
		if($a->epoch < $b->epoch)
			return -1;
		if($a->epoch > $b->epoch)
			return 1;
		$parts_a = explode('.', $a->version);
		$ia = 0;
		$parts_b = explode('.', $b->version);
		$ib = 0;
		while($ia < count($parts_a) && $ib < count($parts_b)) {
			if(ctype_digit($parts_a[$ia]) && ctype_digit($parts_b[$ib])) {
				if(intval($parts_a[$ia]) < intval($parts_b[$ib])) {
					return -1;
				}
				if(intval($parts_a[$ia]) > intval($parts_b[$ib])) {
					return 1;
				}
			}
			$int_a = 0;
			$int_b = 0;
			while($int_a < strlen($parts_a[$ia]) && ctype_digit($parts_a[$ia][$int_a])) ++$int_a;
			while($int_b < strlen($parts_b[$ib]) && ctype_digit($parts_b[$ib][$int_b])) ++$int_b;
			if(intval(substr($parts_a[$ia], 0, $int_a)) < intval(substr($parts_b[$ib], 0, $int_b)))
				return -1;
			if(intval(substr($parts_a[$ia], 0, $int_a)) > intval(substr($parts_b[$ib], 0, $int_b)))
				return 1;
			$cmp = strcmp(substr($parts_a[$ia], $int_a), substr($parts_b[$ib], $int_b));
			if($cmp < 0)
				return -1;
			if($cmp > 0)
				return 1;
			++$ia;
			++$ib;
		}
		// Epoch and versions are the same - check releases
		if($a->release == 0 && $b->release == 0)
			return 0;
		if(($a->release == 0 && $b->release != 0) || ($a->release < $b->release))
			return -1;
		if(($a->release != 0 && $b->release == 0) || ($a->release > $b->release))
			return 1; 
		// Safeguard
		return 0;
	}
}

class PackageInfo {
	public /*string*/ $name;
	public /*string*/ $version;
	public /*string*/ $description;
	public /*array*/ $depends;
	public /*array*/ $opt_depends;
	public /*array*/ $provides;
	public /*string*/ $author;
	public /*string*/ $license;
	public /*long int*/ $size;
	public /*array*/ $replaces;
}

?>
