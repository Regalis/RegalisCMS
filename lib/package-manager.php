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

//! Pacman exception
class PacmanException extends \Exception {}

//TODO: Support for remote repositories
//! Package manager main class
class Pacman {
	private $root = "./"; //!< root directory
	private $db_path = "var/lib/pacman/"; //!< database directory
	private $cache_path = "var/cache/pacman/pkg/"; //!< cache directory
	private $logger = null; //!< logger instance

	//! Constructor - checks for phar PHP extension
	public function __construct() {
		if(!extension_loaded('phar')) 
			throw new PacmanException(_("Unable to find PHP extension named PHAR. Contact your system administrator"));
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
	public static function realPath($path, $archive) {
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
			throw new PacmanException(sprintf(_("%s is not a directory or does not exists"), $root));
		$this->root = $root;
	}

	/** Check if package exists
	* Checking is performed starting from cache directory, then the remote repository is browsed.
	* @param package Package object to check
	* @return true if package is available, false otherwise
	*/
	public function packageExists(Package $package) {
		return(file_exists($this->root . $this->cache_path . $package->name . "-" . $package->version . ".tar"));
		//TODO: Browse remote repository
	}
}

//! Simple class for version manipulations
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
		return true;
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
			$postfix_a = substr($parts_a[$ia], $int_a);
			$postfix_b = substr($parts_b[$ib], $int_b);
			if(empty($postfix_a) && !empty($postfix_b))
				return 1;
			else if(empty($postfix_b) && !empty($postfix_a))
				return -1;
			$cmp = strcmp($postfix_a, $postfix_b);
			if($cmp < 0)
				return -1;
			if($cmp > 0)
				return 1;
			++$ia;
			++$ib;
		}
		// Check version segments length
		if($ia != count($parts_a))
			return 1;
		if($ib != count($parts_b))
			return -1;
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

//! Exception class for Package
class PackageException extends \Exception {}

//! Class to represent package
class Package {
	public /*string*/ $name;
	public /*Version*/ $version;
	public /*string*/ $description;
	public /*array*/ $depends;
	public /*array*/ $opt_depends;
	public /*array*/ $provides;
	public /*string*/ $author;
	public /*string*/ $license;
	public /*long int*/ $size;
	public /*array*/ $replaces;
	public /*array*/ $conflicts;
	public /*string*/ $native_language;
	public /*array*/ $supported_languages;
	public /*string*/ $changelog;
	
	/** Constructor - cleans all public variables
	* @param name package name
	* @param version package Version
	*/
	public function __construct($name = null, Version $version = null) {
		$this->resetInfo();
		$this->name = $name;
		$this->version = $version;
	}

	/** Read package archive and fill all variables
	* @param file_path absolute or relative path to archive file
	* @throws PackageException
	*/
	public function readInfo($file_path) {
		try {
			$archive = new \PharData($file_path);
			if(!isset($archive[".PKGINFO"]))
				throw new PackageException(sprintf(_("Unable to find file .PKGINFO in archive %s."), $file_path));
			$pkg_info = parse_ini_file($archive[".PKGINFO"]);
			$this->resetInfo();
			foreach($pkg_info as $key => $value) {
				switch($key) {
					case "name":
						$this->name = $value;
					break;
					case "version": {
						$this->version = new Version();
						if(!$this->version->parse($value))
							throw new PackageException(sprintf(_("Bad version string in archive %s."), $file_path));
					}
					break;
					case "description":
						$this->description = $value;
					break;
					case "depends": 
						$this->depends = explode(",", $value);
					break;
					case "optdepends":
						$this->opt_depends = explode(",", $value);
					break;
					case "provides":
						$this->privides = explode(",", $value);
					break;
					case "author":
						$this->author = $value;
					break;
					case "license":
						$this->license = $value;
					break;
					case "size":
						$this->size = intval($value);
					break;
					case "replaces":
						$this->replaces = explode(",", $value);
					break;
					case "conflicts":
						$this->conflicts = explode(",", $value);
					break;
					case "native_language":
						$this->native_language = $value;
					break;
					case "supported_languages":
						$this->supported_languages = explode(",", $value);
					break;
				}
			}
			if($this->name == null || $this->version == null)
				throw new PackageException(sprintf(_("Missing name or/and version in archive %s."), $file_path));
			if(isset($archive[".CHANGELOG"])) {
				$this->changelog = file_get_contents($archive[".CHANGELOG"]);
			}
		} catch(\UnexpectedValueException $e) {
			throw new PackageException(sprintf(_("Unable to open archive file %s"), $file_path));
		}
	}

	/** Reset all package info
	* Set all public variables to NULL
	*/
	public function resetInfo() {
		$vars = \get_object_vars($this);
		foreach($vars as $var => $value) {
			$this->$var = null;
		}
	}

}

//! Pacman transaction
class Transaction {
	const INSTALL = 0x01;
	const UPGRADE = 0x02;
	const REMOVE = 0x04;
	const REINSTALL = 0x08;
	const HOLD = 0x10;

	private $transaction = array(); //!< Transaction array
	
	public function install(Package $package) {
		$this->setState($package, INSTALL);
	}

	public function reinstall(Package $package) {
		$this->setState($package, REINSTALL);
	}

	public function upgrade(Package $package) {
		$this->setState($package, UPGRADE);
	}

	public function remove(Package $package) {
		$this->setState($package, REMOVE);
	}

	public function hold(Package $package) {
		$this->setState($package, HOLD);
	}

	private function setState(Package $package, $state) {
		$this->transaction[sprintf("%s-%s", $package->name, $package->version)] = $state;
	}

}


interface Index {
	/** Search index for matching string
	* @param name package name
	* @return array of Package objects (only name and version parametrs is set)
	*/
	public function search($name);

	/** Search index for matching string (detailed version of search())
	* @param name package name
	* @return array of Package objects (detailed)
	*/
	public function searchDetailed($name);

	/** Refresh index
	* @return void
	*/
	public function refresh();

	/** Get list of package contents
	* @param package Package object or package name
	* @return array of package contents or null if no matching package
	*/
	public function listContents($package);

}

?>
