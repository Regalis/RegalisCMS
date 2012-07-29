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
namespace Regalis;

/** Main class - Regalis Kernel */
class Kernel {
	
	/** Get Kernel instance
	* @return Kernel object
	*/
	public static function get() {
		static $kernel = null;
		if ($kernel == null)
			$kernel = new Kernel();
		return $kernel;
	}

	/** Connect signal to specified slot
	* @param signal_emitter reference to signal emitter object
	* @param signal_name name of signal called by signal_emitter
	* @param signal_receiver reference to signal receiver object
	* @param slot_name method name, this method will be called by Kernel
	* on signal emit
	*/
	public function connect(&$signal_emitter, $signal_name, &$signal_receiver, $slot_name) {
		$hash = $this->registerEmitter($signal_emitter);
		$this->registerSignal($hash, $signal_name);
		$this->bindSignal($hash, $signal_name, $signal_receiver, $slot_name);
	}

	/** Same as connect. This function use static Kernel::get() function
	* and call standard connect(...)
	*/
	public static function __connect(&$signal_emitter, $signal_name, &$signal_receiver, $slot_name) {
		Kernel::get()->connect($signal_emitter, $signal_name, $signal_receiver, $slot_name);
	}

	/** Emit signal
	* @param emiter reference to object that emitted the signal
	* @param signal_name name of signal that should be emited by Kernel
	*/
	public function emit(&$emiter, $signal_name) {
		$hash = $this->hash($emiter);
		if (array_key_exists($signal_name, $this->signals[$hash]["signals"])) {
			foreach ($this->signals[$hash]["signals"][$signal_name] as $slot) {
				$slot[0]->$slot[1]();
			}
		}
	}

	private function __construct() {}

	/** Allocate memory for new signal emitter
	* @param emitter reference to new signal emitter
	*/
	private function registerEmitter(&$emitter) {
		$hash = $this->hash($emitter);
		if (!array_key_exists($hash, $this->signals))
			$this->signals[$hash] = array("source" => $emitter, "signals" => array());
		return $hash;
	}

	/** Allocate memory for new signal, emiter must be previously allocated
	* by registerEmitter()
	* @param hash object hash
	* @param signal_name new signal name
	*/
	private function registerSignal($hash, $signal_name) {
		if (!array_key_exists($signal_name, $this->signals[$hash]["signals"]))
			$this->signals[$hash]["signals"][$signal_name] = array();
	}

	/** Bind specified signal to specified slot
	* @param hash signal emitter hash
	* @param signal_name name of signal
	* @param signal_receiver reference to signal receiver object
	* @param slot_name signal receiver method name
	*/
	private function bindSignal($hash, $signal_name, &$signal_receiver, $slot_name) {
		array_push($this->signals[$hash]["signals"][$signal_name], array($signal_receiver, $slot_name));
	}

	/** Get hash from object
	* @param object PHP object
	*/
	private function hash(&$object) {
		return spl_object_hash($object);
	}

	private $signals = array();
}

/** Trait for object that want to emit signals or use slots */
trait Signals {
	
	/** Emit signal from your object
	* @param signal_name name of signal
	*/
	protected function emit($signal_name) {
		Kernel::get()->emit($this, $signal_name);
	}

	/** Connect specified signal to slot in this object
	* @param signal_emitter signal emitter object
	* @param signal_name name of signal
	* @param slot_name method name
	*/
	protected function connect(&$signal_emitter, $signal_name, $slot_name) {
		Kernel::get()->connect($signal_emitter, $signal_name, $this, $slot_name);
	}
}

?>
