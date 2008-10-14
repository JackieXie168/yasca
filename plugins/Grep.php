<?php

/**
 * The Grep Plugin is a special plugin that faciliates .grep psuedo-plugins, which 
 * are just files in the PLUGINS directory that contain necessary information to scan
 * the target files.
 *
 * The .grep files have a specific format:
 *  name = <name of plugin> (default: "Grep: <basename of .grep file>"
 *  file_type = <list,of,extensions> (default: scan all extensions)
 *  grep = <PCRE-style regular expression, without the start and end '/' delimiters (required)
 *  category = <category name> (required)
 *  category_link = <link to more information about the issue> (default: none)
 *  severity = <severity on 1-5 scale, 1=critical, 5=informational> (default: 5)
 *  description = <description of the issue> (default: none)
 *
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Grep extends Plugin {
	public $valid_file_types = array();
	public $name;
	public $file_type;
	public $grep = array();
	public $fix;
	public $category;
	public $category_link;
	public $severity = 5;
	public $description;
	public $is_multi_target = true;
	
	private static $union_valid_file_types;
	
	/**
	 * Re-initializes the variables back to their original state.
	 */
	function initialize() {
		$this->name = "";
		$this->valid_file_types = array();
		$this->grep = array();
		$this->fix = "";
		$this->category = "";
		$this->category_link = "";
		$this->severity = 5;
		$this->description = "";
	}
	
	function execute() {
		$grep_plugin_list = array();
		$yasca =& Yasca::getInstance();
		foreach ($yasca->plugin_file_list as $grep_plugin) {
			if ( !startsWith($grep_plugin, "_") &&		// ignore plugins that start with a '_'
			     endsWith($grep_plugin, ".grep") ) {				
				array_push($grep_plugin_list, $grep_plugin);
			}
		}
		// Capture all of the valid file types
		if (!isset(Plugin_Grep::$union_valid_file_types)) {
			$yasca->log_message("Loading all scannable file types for the Grep plugin.", E_ALL);
				
			$union_valid_file_types = array();
			foreach ($grep_plugin_list as $grep_plugin) {
				$grep_content = explode("\n", file_get_contents($grep_plugin));
				$this->initialize(); 		// clear out $this parameters
				// Parse out the .grep files
				for ($i=0; $i<count($grep_content); $i++) {
					$grep = trim($grep_content[$i]);
					$matches = array();
					if (preg_match('/\s*file_type\s*=\s*(.*)/i', $grep, $matches)) $valid_file_types = explode(",", $matches[1]);
				}
				$union_valid_file_types = array_merge($union_valid_file_types, (isset($valid_file_types) ? $valid_file_types : $this->valid_file_types));					
			}
			$union_valid_file_types = array_values(array_unique($union_valid_file_types));			
			Plugin_Grep::$union_valid_file_types = $union_valid_file_types;
		}
		
		// check for valid file type usage
		if (!check_in_filetype($this->filename, Plugin_Grep::$union_valid_file_types)) {
			return;
		}
		
		foreach ($grep_plugin_list as $grep_plugin) {
			$grep_content = explode("\n", file_get_contents($grep_plugin));
			$this->initialize(); 		// clear out $this parameters
		
			// Parse out the .grep files		
			for ($i=0; $i<count($grep_content); $i++) {
				$grep = trim($grep_content[$i]);
				$matches = array();
				if (preg_match('/^\s*name\s*=\s*(.*)/i', $grep, $matches)) $this->name = $matches[1];
				elseif (preg_match('/^\s*file_type\s*=\s*(.*)/i', $grep, $matches)) $this->valid_file_types = explode(",", $matches[1]);
				elseif (preg_match('/^\s*grep\s*=\s*(.*)/i', $grep, $matches)) array_push($this->grep, $matches[1]);
				elseif (preg_match('/^\s*category\s*=\s*(.*)/i', $grep, $matches)) $this->category = $matches[1];
				elseif (preg_match('/^\s*fix\s*=\s*(.*)/i', $grep, $matches)) $this->fix = $matches[1];
				elseif (preg_match('/^\s*category_link\s*=\s*(.*)/i', $grep, $matches)) $this->category_link = $matches[1];
				elseif (preg_match('/^\s*severity\s*=\s*(.*)/i', $grep, $matches)) $this->severity = $matches[1];
				elseif (preg_match('/^\s*description\s*=\s*(.*)/i', $grep, $matches)) {
					$this->description = $matches[1];
					for ($j=$i+1; $j<count($grep_content); $j++) {
						$desc_line = $grep_content[$j];
						if (trim($desc_line) == "END;") {
							break;
						} else {
							if (trim($desc_line) == "") {
								$desc_line = "<br/><br/>";
							}
							$this->description .= $desc_line . "\n";
						} 
					}
				}
			}

			// Check for required fields
			if (!isset($this->grep) ||
			    !isset($this->category))
				break;

			// check for valid file type usage
			if (!check_in_filetype($this->filename, $this->valid_file_types)) {
			    continue;
			}

			// Set the name if it isn't already specified
			if (!isset($this->name) || $this->name == "") 
				$this->name = basename($grep_plugin, ".grep");
			
			foreach ($this->grep as $grep) {
			    // Massage the grep to work below
			    preg_match("/\/(.*)\/([imsxUX])?/", $grep, $matches);
			    $modifier = isset($matches[2]) ? $matches[2] : "";
			    $grep = isset($matches[1]) ? $matches[1] : $grep;

			    // Scan entire file at once
			    $matches = array();

			    $orig_error_level = error_reporting(0);
			    $matches = preg_grep('/' . $grep . '/' . $modifier, $this->file_contents);
			    error_reporting($orig_error_level);

			    if (!is_array($matches)) {
				$yasca->log_message("Invalid grep expression [/$grep/$modifier]. Ignoring.", E_USER_WARNING);
				continue;
			    }

			    foreach ($matches as $line_number => $match) {
				$result = new Result();
				$result->line_number = $line_number + 1;
				$result->filename = $this->filename;
				$result->severity = $yasca->get_adjusted_severity("Grep", $this->name, $this->severity);
				$result->category = $this->category;
				$result->category_link = $this->category_link;
				$result->description = $this->description; //$yasca->get_adjusted_description("Grep", $this->name, $this->description);
				$result->source = $match;
				$result->plugin_name = $yasca->get_adjusted_alternate_name("Grep", $this->name, "Grep: " . $this->name);
				if ($this->fix !== "") {
					$yasca->add_fix($result, $this->filename, $result->line_number, $match, eval($this->fix));
				}
/*				
				if ($this->fix !== "") {
					$modified = eval($this->fix);
					$result->proposed_fix = "Proposal: " . $modified;
					if (!isset($yasca->general_cache["proposed_fixes"])) $yasca->general_cache["proposed_fixes"] = "";
					$yasca->general_cache["proposed_fixes"] .= "<fix filename=\"$this->filename\" line_number=\"$result->line_number\" original=\"" . htmlentities($match) . "\" proposed=\"" . htmlentities($modified) . "\"/>";
				}
*/
				array_push($this->result_list, $result);
			    }
			}
		}
	}
}
?>
