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
	protected static $union_valid_file_types;
	protected static $grep_plugin_list;
	protected static $grep_content_cache;

	//Not used in the typical way. 
	//It is set after the plugin is instantiated and used in execute() to skip specific grep files.
	public $valid_file_types; //see constructor

	public $is_multi_target = false;


	protected $name;
	protected $file_type;
	protected $grep = array();
	protected $pre_grep;                   /* Pre Lookahead Grep - must exist within $lookahead before a $grep */
	protected $lookahead_length = 10;      /* Index of pre_grep to grep must be within $lookahead_length       */
	protected $fix;
	protected $category;
	protected $category_link;
	protected $severity = 5;
	protected $tags;
	public $description; //Plugin definition uses this to describe the plugin; grep.php uses it to describe an individual Result description.
	protected $preprocess;

	public $installation_marker = true;

	public function Plugin_Grep($filename, &$file_contents){
		if (!isset(self::$grep_plugin_list)){
			$yasca =& Yasca::getInstance();
			$cache =& self::$grep_content_cache;
			self::$grep_plugin_list = array_filter($yasca->plugin_file_list,
				function ($plugin) use (&$cache){	
					if (startsWith($plugin, "_") || !endsWith($plugin, ".grep"))
						return false;
						
					$grep_content = explode("\n", file_get_contents($plugin));
					if (!array_any($grep_content, function($line) {return preg_match('/^\s*grep\s*=\s*(.*)/i', $line);}))
						return false;
						
					if (!array_any($grep_content, function($line) {return preg_match('/^\s*category\s*=\s*(.*)/i', $line);}))
						return false;

					//Code below this line means it's a keeper plugin.
					$cache[$plugin] = $grep_content;
					array_walk($cache[$plugin], function (&$line) {
						$line = trim($line);
					});
					return true;
			});
		}
		 
		// Capture all of the valid file types
		if (!isset(self::$union_valid_file_types)) {
			$yasca =& Yasca::getInstance();
			$yasca->log_message("Loading all scannable file types for the Grep plugin.", E_ALL);

			self::$union_valid_file_types = array();
			foreach (self::$grep_plugin_list as $grep_plugin) {
				$grep_content = self::$grep_content_cache[$grep_plugin];

				// Parse out the .grep files
				foreach($grep_content as $line){
					$matches = array();
					if (preg_match('/\s*file_type\s*=\s*(.*)/i', $line, $matches)){
						self::$union_valid_file_types = array_merge(self::$union_valid_file_types,
						explode(",", $matches[1]));
						break;
					}
				}
			}
			self::$union_valid_file_types = array_unique(self::$union_valid_file_types);			
		}

		$this->valid_file_types = self::$union_valid_file_types;
		parent::Plugin($filename, $file_contents);
	}

	/**
	 * Re-initializes the variables back to their original state.
	 */
	protected function initialize() {
		$this->name = "";
		$this->grep = array();
		$this->pre_grep = "";
		$this->lookahead_length = 10;
		$this->fix = "";
		$this->category = "";
		$this->category_link = "";
		$this->severity = 5;
		$this->description = "";

		unset($this->tags);
		unset($this->preprocess);

	}

	function execute() {
		$yasca =& Yasca::getInstance();
		
		// Perform UTF Conversion, if necessary
          if (is_array($this->file_contents)) {
           	$file_contents_converted = explode("\n", utf16_to_utf8(implode("\n", $this->file_contents)));
         } else {
            $file_contents_converted = utf16_to_utf8($this->file_contents);        
          }

		foreach (self::$grep_plugin_list as $grep_plugin) {
			$yasca->log_message("Using Grep [$grep_plugin] to scan [$this->filename]", E_USER_NOTICE);
			$grep_content = self::$grep_content_cache[$grep_plugin];

			$this->initialize();        // clear out $this parameters
			$count = count($grep_content);
			for ($i=0; $i<$count; $i++) {
				$matches = array();
				if (preg_match('/^\s*name\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->name = $matches[1];
				elseif (preg_match('/^\s*file_type\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->valid_file_types = explode(",", $matches[1]);
				elseif (preg_match('/^\s*grep\s*=\s*(.*)/i', $grep_content[$i], $matches)) array_push($this->grep, $matches[1]);
				elseif (preg_match('/^\s*pre_grep\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->pre_grep = $matches[1];
				elseif (preg_match('/^\s*category\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->category = $matches[1];
				elseif (preg_match('/^\s*preprocess\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->preprocess = trim($matches[1]);
				elseif (preg_match('/^\s*lookahead_length\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->lookahead_length = $matches[1];
				elseif (preg_match('/^\s*fix\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->fix = $matches[1];
				elseif (preg_match('/^\s*tags\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->tags = $matches[1];
				elseif (preg_match('/^\s*category_link\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->category_link = $matches[1];
				elseif (preg_match('/^\s*severity\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->severity = $matches[1];
				elseif (preg_match('/^\s*description\s*=\s*(.*)/i', $grep_content[$i], $matches)) {
					$this->description = $matches[1];
					for ($j=$i+1; $j<$count; $j++) {
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
	

			//If the loaded grep doesn't apply to the current file, then move on to the next grep.
			if (!$this->check_in_filetype($this->filename, $this->valid_file_types)){
				continue;
			}

			// check for a valid preprocessor
			if (isset($this->preprocess) && !function_exists($this->preprocess)) {
				$yasca->log_message("Unable to find preprocessor function [{$this->preprocess}]. Ignoring.", E_USER_WARNING);
				unset($this->preprocess);
			}

			// Set the name if it isn't already specified
			if (!isset($this->name) || $this->name == "")
				$this->name = basename($grep_plugin, ".grep");

			if (isset($this->pre_grep) && strlen($this->pre_grep) >= 1 ) {
				array_unshift($this->grep, "__" . $this->pre_grep);
			}

			$file_contents = $file_contents_converted;

			if (isset($this->preprocess) ) {
				if ($yasca->options["debug"])
				$yasca->log_message("Before pre-processing, file contents are: \n" . implode("\n", $file_contents), E_ALL);

				$file_contents = @call_user_func($this->preprocess, $file_contents);

				if ($yasca->options["debug"])
				$yasca->log_message("After pre-processing with {$this->preprocess} for $grep_plugin, file contents are: \n" . implode("\n", $file_contents), E_ALL);
			}
				
			$pre_matches = array();         // holds line numbers of pre_grep matches
			foreach ($this->grep as $grep) {
				$orig_grep = $grep;
				// Massage the grep to work below
				preg_match("/\/(.*)\/([imsxUX]*)?/", $grep, $matches);
				$modifier = isset($matches[2]) ? $matches[2] : "";
				$grep = isset($matches[1]) ? $matches[1] : $grep;

				$orig_error_level = error_reporting(0);

				// Scan entire file at once
				$matches = preg_grep('/' . $grep . '/' . $modifier, $file_contents);
				 
				if (preg_match("/^__(.*)/", $orig_grep)) {          // for pre_grep
					error_reporting($orig_error_level);
					$pre_matches = array_keys($matches);
					continue;
				}

				error_reporting($orig_error_level);

				//If preg_grep did not return an array, then an error occurred.
				//Assume that the grep string is invalid.
				if (!is_array($matches)) {
					if (!isset($yasca->general_cache["grep.ignored"]))
					$yasca->general_cache["grep.ignored"] = array();

					$c =& $yasca->general_cache["grep.ignored"];

					if (!isset($c["/$grep/$modifier"])) {
						$yasca->log_message("Invalid grep expression [/$grep/$modifier]. Ignoring.", E_USER_WARNING);
						$c["/$grep/$modifier"] = 1;
					}
					continue;
				}

				foreach ($matches as $line_number => $match) {
					if ( $orig_grep == "__" . $this->pre_grep ||
					(isset($this->pre_grep) && $this->pre_grep != "" && count($pre_matches) == 0) ||
					(count($pre_matches) > 0 && !any_within($pre_matches, $line_number+1, $this->lookahead_length)) ) {
						continue;
					}

					$result = new Result();
					$result->line_number = $line_number + 1;
					$result->filename = $this->filename;
					$result->severity = $yasca->get_adjusted_severity("Grep", $this->name, $this->severity);
					$result->category = $this->category;
					$result->category_link = $this->category_link;
					$result->description = $this->description; //$yasca->get_adjusted_description("Grep", $this->name, $this->description);
					$result->source = $match;
					$result->source_context = array_slice( $file_contents, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
					$result->plugin_name = $yasca->get_adjusted_alternate_name("Grep", $this->name, "Grep: " . $this->name);
					if ($this->fix !== "")
						$result->proposed_fix = eval($this->fix);

					$yasca->log_message("Found a result on line {$result->line_number} ({$this->name})", E_ALL);
					array_push($this->result_list, $result);
				}
			}
		}
	}

}
?>

