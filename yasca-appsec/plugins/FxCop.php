<?php

/**
 * The FxCop plugin is used to find potential vulnerabilities in .NET assemblies.
 * Only the security rules set is used.
 *
 * Created by Michael Maass 07/02/2009
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_FxCop extends Plugin {
	public $valid_file_types = array();
	
	public $description = "The FxCop plugin is used to find potential vulnerabilities in .NET assemblies.";

	public $is_multi_target = true;

	public $installation_marker = "fxcop";

	protected static $already_executed = false;

	public function Plugin_FxCop($filename, &$file_contents){
		parent::Plugin($filename, $file_contents);
		
		$yasca =& Yasca::getInstance();

		if (self::$already_executed){
			$this->canExecute = false;
			return;
		}

		if (!class_exists("DOMDocument")) {
			$yasca->log_message("DOMDocument is not available. FxCop results are not available. Please install php-xml.", E_USER_WARNING);
			$this->canExecute = false;
			return;
		}
		
		if (isLinux() && !wineExists()){
			$yasca->log_message("Unable to find wine. Unable to use FxCop.", E_USER_WARNING);
			$this->canExecute = false;
			return;
		}

		//Which version to look for.
		$fxcop_version = "1.3.6";

		//Try to find the required scanner dependency
		//realpath is required because is_file() does not handle mixed slashes while the OS does.
		if (is_file(realpath("{$this->sa_home}resources/utility/fxcop/FxCopCmd.exe"))){
			//Found in sa_home
			$fxcop_path = realpath("{$this->sa_home}resources/utility/fxcop/") . DIRECTORY_SEPARATOR;
		}
		else{
			$yasca->log_message("Unable to find FxCop despite the installation marker.", E_USER_WARNING);
			$this->canExecute = false;
			return;
		}
		
		$identity_xsl = realpath("./resources/utility/fxcop/Identity.xsl");
		if (!is_file($identity_xsl)){
			$yasca->log_message("Unable to find identity xsl. Unable to use FxCop.", E_USER_WARNING);
			$this->canExecute = false;
			return;
		}

		$this->executable = $fxcop_path . "FxCopCmd.exe";
		if (isLinux()) $this->executable = "wine " . $this->executable;
		
		$this->executable = $this->replaceExecutableStrings($this->executable);

		$dir = $yasca->options['dir'];


		//FxCop, as a windows app, does not use different CLI switches on different platforms.
		$this->arguments = " " .
			"/ignoreinvalidtargets " . //Skip rather than crash if it reads a file it can't use
			"/searchgac " . //If when scanning, object references can't be immediately found,
							//check the global assembly cache for it.
			"/rule:\"{$fxcop_path}Rules/SecurityRules.dll\" " .
			"/consolexsl:\"$identity_xsl\" /console /quiet " .
			self::create_target_switches(self::find_targets($dir));
			
		$this->arguments = $this->replaceExecutableStrings($this->arguments);
	}

	/**
	 * Recursively finds folders with a dll or exe. Only folders with unique filenames are counted.
	 * @param string $dir The root directory to begin searching.
	 * @param boolean $recursively_called Whether or not the function should reset it's internal list of unique filenames.
	 * @return array An array of fully-qualified path strings for directories containing a dll or exe.
	 */
	protected static function find_targets($dir, $recursively_called = false) {
		$dir = realpath($dir);

		if (($handle = opendir($dir)) === false){
			return array();
		}

		//Because Visual Studio projects can contain builds of executables and dlls
		//that are in multiple folders - ie Debug, Release, x86, etc,
		//counting every binary will result in duplicate messages from FxCop
		//@todo Ignore "Release" folders if a matching "Debug" folder exists.
		static $unique_filenames;

		static $valid_file_types = array("exe", "dll");


		//Clear it on first run otherwise every time the function runs,
		//it keeps adding the same folders to the switches,
		//causing a lot of dupes.
		if (!$recursively_called) {
			$unique_filenames = array();
		}

		$current_directory_added = false;
		$targets = array();

		while ( ($file = readdir($handle)) !== false) {

			if ($file == "." || $file == "..") continue;

			if (is_dir(realpath($dir . '/' . $file))) {
				$targets = array_merge($targets, self::find_targets($dir . '/' . $file, true));
			} elseif (!$current_directory_added) {
				$pinfo = pathinfo($file);
				if (isset($pinfo['extension']) &&
				in_array($pinfo['extension'], $valid_file_types) &&
				!in_array($file, $unique_filenames)) {
					array_push($targets, $dir);
					$current_directory_added = true;
					array_push($unique_filenames, $file);

				}
			}
		}

		return $targets;
	}

	/**
	 * Flatten an array of path strings into a single chain of /file: switches.
	 * @param array $targets The array of path strings of which to flatten into /file: switches.
	 * @returns string
	 */
	protected static function create_target_switches($targets){
		return array_reduce($targets, function ($acc, $s) { return "$acc /file:\"$s\"";});
	}


	/**
	 * Executes the FxCop executable and processes the output into $this->result_list.
	 * @return nothing
	 */
	function execute() {
		if (self::$already_executed == true) return;
		self::$already_executed = true;
		if (!$this->canExecute) return;
		
		$yasca =& Yasca::getInstance();

		$exec_statement = "\""  .$this->executable . "\"" . $this->arguments . " 2>&1";

		$yasca->log_message("Forking external process (FxCop)...", E_USER_WARNING);
		$yasca->log_message("Executing: [$exec_statement]", E_ALL);

		$output = array();
		exec($exec_statement, $output);
print($exec_statement);
print_r($output);
		if ($yasca->options['debug']) {
			$output = implode("\r\n",$output);
			$yasca->log_message("FxCop returned: " . $output, E_ALL);
		}else{
			$output = implode($output);
		}
		
		$output_dom = new DOMDocument();

		if (@$output_dom->loadXML($output) === false){
			$yasca->log_message("FxCop did not return a valid XML document. Ignoring.", E_USER_WARNING);
			return;
		}
			
		$yasca->log_message("External process completed...", E_USER_WARNING);
		
		$this->result_list = self::process_xml_report($output_dom);
		
		unset($output_dom);
	}
	
	/**
	 * Processes a DOMDocument of an FxCopReport into an array of Result objects.
	 * @param DOMElement $dom The top-level DOMDocument of the report to process.
	 * @returns array An array of Result objects.
	 */
	protected static function &process_xml_report($dom) {
		$results = array();
		
		foreach($dom->getElementsByTagName("Message") as $message){
			$message_result = new Result();
			
			$message_result->line_number = 0;
			$message_result->filename = self::build_location_string($message);
			$message_result->plugin_name = $message->getAttribute("TypeName");
			$message_result->severity = 5;
			$message_result->category = $message->getAttribute("Category") . " (FxCop)";
			$message_result->source = $message->getAttribute("CheckId") . ": " .
								$message->getAttribute("TypeName") . " (" .
								$message->getAttribute("FixCategory") . ")";
			$message_result->is_source_code = false;
			$message_result->source_context = "";
			$message_result->description = $message->nodeValue;
			
			$issues = $message->getElementsByTagName("Issue");
			if (count($issues) == 0){
				array_push($results, $message_result);
				continue;
			}
			foreach($issues as $issue){
				$issue_result = clone $message_result;
				
				$issue_result->line_number = $issue->getAttribute("Line");
				$issue_result->severity = self::decode_severity($issue->getAttribute("Level"));
				$issue_result->description = $issue->nodeValue;
					
				array_push($results, $issue_result);
			}
		}
	
		return $results;
	}
	
	/**
	 * Builds an end-user readable location for a given message node.
	 * @param DOMNode $message_node The current message node
	 * @return string A string representation of the message location in code.
	 */
	protected static function build_location_string($message_node){
		static $location_nodes = array("Module", "Namespace", "Type", "Member", "Accessor");
		static $stop_nodes = array("Target", "Targets", "FxCopReport");
		
		$current_node = $message_node;
		$location = "";
		do{
			if (in_array($current_node->nodeName, $location_nodes)){
				$location = $current_node->getAttribute("Name") . "." . $location;
			}
			$current_node = $current_node->parentNode; 
		} while (!in_array($current_node->nodeName, $stop_nodes));
		return chop($location, ".");
	}
	
	/**
	 * Decodes the textual severity of an FxCop issue into a numerical value, 1-5.
	 * 1 is most critical, 5 is the least.
	 * If an unknown severity is encountered, it returns 5.
	 * @param string $text_severity
	 * @return int Numerical interpretation in the range of {1,5}
	 */
	protected static function decode_severity($text_severity){
		switch(strtok($text_severity,",")){
			case "CriticalError":	return 1;
			case "Error": 			return 2;
			case "CriticalWarning":	return 3;
			case "Warning":			return 4;
			case "Informational": //return 5;
			default:
				return 5;
		}
	}
}
?>
