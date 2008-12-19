<?php

include_once("lib/Result.php");
include_once("lib/common-analysis.php");

/**
 * Plugin Class
 *
 * This (abstract) class is the parent of all plugin classes. 
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.0
 * @package Yasca
 */
class Plugin {
	/**
	 * Holds the filename that this Plugin happens to be working on.
	 */
	public $filename = "";
	
	/**
	 * Holds the file contents that this Plugin is working on. This can
	 * be either an array of strings or just a \n-separated string, in which
	 * case it will be exploded when the object is created.
	 */
	public $file_contents = array();
	
	/**
	 * Valid file types that this Plugin can operate on.
	 */
	public $valid_file_types = array();
	
	/**
	 * True iff this object was initialized (i.e. has a valid extension)
	 */
	public $is_valid_filetype = false;
	
	/**
	 * How many lines to include in the context returned.
	 */
	public $context_size = 7;
	
	/**
	 * Holds the results of the scan.
	 */
	public $result_list = array();
	
	/**
	 * Description of this plugin (what it looks for, why it's important, how to
	 * remediate.
	 */
	public $description = "default";
	
	/**
	 * True iff this object is to be only invoked once. The object itself should prevent
	 * multiple executions.
	 */
	public $is_multi_target = false;
	
	/**
	 * Internal variable set to true at the end of the constructor.
	 */
	public $initialized = false;
	
	/**
	 * Creates a new generic Plugin.
	 * @param string $filename that is being examined.
	 * @param mixed $file_contents array or string of the file contents.
	 */
	public function Plugin($filename, &$file_contents) {
		if (check_in_filetype($filename, $this->valid_file_types)) {
			$this->filename = $filename;
			$this->file_contents = $file_contents;
			if (!is_array($this->file_contents)) 
				$this->file_contents = explode("\n", $this->file_contents);
			foreach ($this->file_contents as $content) $content = trim($content);
			$this->is_valid_filetype = true;
		}
		$this->initialized = true;
	}

	/**
	 * This function is called to de-allocate as much of the object as possible.
	 */
	public function destructor() {
		foreach ($this as $item) {
			$item = null;
			unset($item);
		}
	}
	
	/**
	 * This function should not be called, since this class is abstract. The execute() function
	 * should be overridden by child classes.
	 */
	function execute() {
		$yasca =& Yasca::getInstance();
		$yasca->log_message("Plugin::execute() called, but should have been overridden by " . get_class($this) . "::execute()", E_USER_WARNING);
	}
	
	
	/**
	 * Starts execution of the specific plugin. Calls the overridden method of child classes
	 * to perform the scan. This function just wraps that.
	 */
	function run() {
		if (!$this->initialized) return false;
		if (!$this->is_multi_target && !$this->is_valid_filetype) return false;
		$this->execute();
		if (!is_array($this->result_list)) {
			Yasca::log_message("Unable to process results list.", E_USER_ERROR);
			return;
		}
		for ($i=0; $i<count($this->result_list); $i++) {
			$result = &$this->result_list[$i];
			if (!isset($result->filename)) $result->filename = $this->filename;
			if (!isset($result->severity)) $result->severity = 5;
			if (!isset($result->is_source_code)) $result->is_source_code = false;
			if (!isset($result->category)) $result->category = "General";
			if (!isset($result->category_link)) $result->category_link = "";
			if (!isset($result->plugin_name)) $result->plugin_name = get_class($this);
			if (!isset($result->description) || $result->description === '') $result->description = "";	
			if (!isset($result->source_context)) $result->source_context = array_slice( $this->file_contents, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
			if (!isset($result->source)) {
				if ($result->line_number > 0) {
					$result->source = $this->file_contents[ $result->line_number-1 ];
				} else {
					$result->source = "";
				}
			}
		}
	}

	/**		
	 * Checks for the current version of Java. The version must be greater than or equal
	 * to 1.4, or else the function will return true.
	 */
	function check_for_java($minimum_version = 1.40) {
		$result = array();
		exec("java -version 2>&1", $result);
		if (!isset($result[0])) return false;
		if (stripos($result[0], "is not recognized") !== false) return false;
		$matches = array();
		if (preg_match("/\"(\d+\.\d+)/", $result[0], $matches)) {
			$version = $matches[1];
			return (floatval($version) >= floatval($minimum_version)); 
		}
		return false;
	}
}
?>