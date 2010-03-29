<?php

include_once("lib/Result.php");
include_once("lib/common-analysis.php");
include_once("lib/PreProcessors.php");

/**
 * Plugin Class
 *
 * This (abstract) class is the parent of all plugin classes.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
abstract class Plugin {
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
	 * This file should exist to indicate that the underlying plugin is accessible, or should
	 * be true to mean ignore it.
	 */
	public $installation_marker = true;

	/**
	 * This is a reference to the static anylzers plugin directory
	 */
	public $sa_home = "";

	/**
	 * This sometimes contains the executable to be called.
	 */
	public $executable = array();

	/**
	 * Interval marker used to prevent objects from being executed.
	 */
	public $canExecute = true;

	private static $ext_classes = array( "JAVA"       => array("java", "jsp", "jsw"),
                                         "C"          => array("c", "cpp", "h"),
                                         "HTML"       => array("html", "css", "js", "htm", "hta"),
                                         "BINARY"     => array("dll", "zip", "jar", "ear", "war", "exe"),
                                         "PHP"        => array("php", "php5", "php4"),
                                         "NET"        => array("aspx", "asp", "vb", "frm", "res", "cs", "ascx"),
                                         "COBOL"      => array("cobol", "cbl", "cob"),
                                         "PERL"       => array("pl", "cgi"),
                                         "PYTHON"     => array("py"),
                                         "COLDFUSION" => array("cfm", "cfml")
	);

	/**
	 * Creates a new generic Plugin.
	 * @param string $filename that is being examined.
	 * @param mixed $file_contents array or string of the file contents.
	 */
	public function Plugin($filename, &$file_contents) {
		$yasca =& Yasca::getInstance();
		$this->sa_home = $yasca->options["sa_home"];

		if (self::check_in_filetype($filename, $this->valid_file_types)) {
			$this->is_valid_filetype = true;
			$this->filename = $filename;
			$this->file_contents =& $file_contents;
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
	public abstract function execute();


	/**
	 * Starts execution of the specific plugin. Calls the overridden method of child classes
	 * to perform the scan. This function just wraps that.
	 */
	public function run() {
		if (!$this->initialized) return false;
		if ((!$this->is_multi_target && !$this->is_valid_filetype) || !$this->canExecute) return false;

		/* These sections handle the installation detection for plugins */
		$yasca =& Yasca::getInstance();
		$no_execute =& $yasca->general_cache["no_execute"];
		if (!is_array($no_execute)) {
			$no_execute = array();
			$yasca->general_cache["no_execute"] =& $no_execute;
		}

		if ($this->installation_marker !== true) {	// installation_marker == true means, "no plugin installation needed"
			if (isset($no_execute[$this->installation_marker]) &&
				$no_execute[$this->installation_marker] === true) {
				return false;
			}
			
			if (!file_exists($yasca->options['sa_home'] . "resources/installed/" . $this->installation_marker)) {
				$yasca->log_message("Plugin \"{$this->installation_marker}\" not installed. Download it at yasca.org.", E_USER_WARNING);

				$no_execute[$this->installation_marker] = true;		// add to the cache so this only happens once
				return false;
			}
		}

		$this->execute();

		if (!is_array($this->result_list)) {
			$yasca->log_message("Unable to process results list.", E_USER_WARNING);
			return;
		}
		foreach($this->result_list as &$result){
			if (!isset($result->filename)) $result->filename = $this->filename;
			if (!isset($result->severity)) $result->severity = 5;
			if (!isset($result->is_source_code)) $result->is_source_code = false;
			if (!isset($result->category)) $result->category = "General";
			if (!isset($result->category_link)) $result->category_link = "";
			if (!isset($result->plugin_name)) $result->plugin_name = get_class($this);
			if (!isset($result->description)) $result->description = "";
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
	 * @return boolean If the version of java is at least the specified minimum version (1.4 if empty)
	 */
	protected static function check_for_java($minimum_version = 1.40) {
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

	/**
	 * Checks to see if the given filename has a passed extension.
	 * $filename does not have to be an accessible file.
	 * @param string $filename filename to check
	 * @param mixed $ext string extension or array of extensions to check. Should not include a period. An empty array means accept all filenames.
	 * @return boolean true iff filename matches one of the extensions, or if $ext was an empty array.
	 */
	protected static function check_in_filetype($filename, $exts = array(), $equiv_classes = null) {
		if (!is_array($exts)) $exts = explode(",", $exts);

		if (count($exts) == 0) return true;      // $ext=() means all accepted
		
		//Because this is a bottlenecking function, check the most common usage first and return early.
		//Stage 1: the caller provided the exact list of extensions that they are interested in.
		foreach ($exts as $ext) {
			if (endsWith($filename, "." . $ext) || fnmatch($ext, $filename))
				return true;
		}
		
		//Stage 2: The caller provided an equivalent class token, ie "JAVA" or "COBOL".
		if (!isset($equiv_classes)) $equiv_classes = self::$ext_classes;
		foreach (array_intersect($exts, array_keys($equiv_classes)) as $ev) {
			foreach ($equiv_classes[$ev] as $eqv) {
				if (endsWith($filename, "." . $eqv)) return true;
			}
		}
		
		return false;
	}


	/**
	 * Replaces standard variables from executable strings.
	 * @param string $executable string to expand out
	 * @return new string with special variables replaced
	 */
	public function replaceExecutableStrings($executable) {
		$executable = str_replace("%SA_HOME%", $this->sa_home, $executable);
		foreach ($_ENV as $key => $value) {
			$executable = str_replace("%$key%", $value, $executable);
		}
		return $executable;
	}

}
?>
