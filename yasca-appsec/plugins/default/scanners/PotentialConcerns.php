<?php

/**
 * This attachment attempts to flag locations in code that should
 * be inspected manually.
 *
 * This plugin includes a lot of code from the grep plugin.
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_PotentialConcerns extends Plugin {
    public $valid_file_types = array();
    public $name;
    public $file_type;
    public $grep = array();
    public $pre_grep;                   /* Pre Lookahead Grep - must exist within $lookahead before a $grep */
    public $lookahead_length = 10;      /* Index of pre_grep to grep must be within $lookahead_length       */
    public $fix;
    public $category;
    public $category_link;
    public $severity = 5;
    public $description;
    public $preprocess;
    public $is_multi_target = true;
    
    protected static $union_valid_file_types;
    protected static $concerns_dir = 'plugins/default/scanners/concerns/';
    public static $findings = array(array());

    public $installation_marker = true;

    protected static $CACHE_ID = 'Plugin_PotentialConcerns.potential_concerns,Potential Concerns';
    
    /**
     * Re-initializes the variables back to their original state.
     */
    protected function initialize() {
        $this->name = "";
        $this->valid_file_types = array();
        $this->grep = array();
        $this->pre_grep = "";
        $this->lookahead_length = 10;
        $this->fix = "";
        $this->category = "";
        $this->category_link = "";
        $this->severity = 5;

        unset($this->description);
        unset($this->tags);
        unset($this->preprocess);
    }

    protected function concerns_list() {
    	$concerns = array();

		if ($handle = opendir(self::$concerns_dir)) {
		    while (false !== ($file = readdir($handle))) {
		    	if ($file == "." || $file == "..") continue;
				if (is_file(self::$concerns_dir . $file)) {
				    array_push($concerns, self::$concerns_dir . $file);
				}
		    }
	
		    closedir($handle);
		}

		return $concerns;
    }
    
    protected function have_finding(&$haystack, $file, $description) {
		for ($i = 0; $i < count($haystack); $i++) {
		    if (!isset($haystack[$i]['file'])) continue;
	
		    if 	($haystack[$i]['file'] == $file &&
			 $haystack[$i]['description'] == $description) {
			return $i;	     
		    }
		}
	
		return -1;
    }

    function execute() {
    	
        $grep_plugin_list = array();
		$yasca =& Yasca::getInstance();

        foreach ($this->concerns_list() as $grep_plugin) {
            if (endsWith($grep_plugin, ".grep") ) {                
                array_push($grep_plugin_list, $grep_plugin);
            }
        }
        // Capture all of the valid file types
        if (!isset(Plugin_PotentialConcerns::$union_valid_file_types)) {
            $yasca->log_message("Loading all scannable file types for the Grep plugin.", E_ALL);
                
            $union_valid_file_types = array();
            foreach ($grep_plugin_list as $grep_plugin) {
                $grep_content = explode("\n", file_get_contents($grep_plugin));
                $this->initialize();        // clear out $this parameters
                // Parse out the .grep files
                for ($i=0; $i<count($grep_content); $i++) {
                    $grep = trim($grep_content[$i]);
                    $matches = array();
                    if (preg_match('/\s*file_type\s*=\s*(.*)/i', $grep, $matches)) $valid_file_types = explode(",", $matches[1]);
                }
                $union_valid_file_types = array_merge($union_valid_file_types, (isset($valid_file_types) ? $valid_file_types : $this->valid_file_types));                   
            }
            $union_valid_file_types = array_values(array_unique($union_valid_file_types));          
            Plugin_PotentialConcerns::$union_valid_file_types = $union_valid_file_types;
        }
        
        // check for valid file type usage
        if (!Plugin::check_in_filetype($this->filename, Plugin_PotentialConcerns::$union_valid_file_types)) {
            return;
        }
        
    	    // Perform UTF Conversion, if necessary
            if (is_array($this->file_contents)) {
                $this->file_contents = explode("\n", utf16_to_utf8(implode("\n", $this->file_contents)));
            } else {
                $this->file_contents = utf16_to_utf8($this->file_contents);
            }
        
        foreach ($grep_plugin_list as $grep_plugin) {
            $yasca->log_message("Using Grep [$grep_plugin] to scan [$this->filename]", E_ALL);

            if (!$yasca->cache->contains($grep_plugin)) {
                $yasca->cache->put_file($grep_plugin);
            }
            $grep_content = $yasca->cache->get_file($grep_plugin);

            $this->initialize();        // clear out $this parameterss
        
            // Parse out the .grep files        
            for ($i=0; $i<count($grep_content); $i++) {
                $grep = trim($grep_content[$i]);
                $matches = array();
                if (preg_match('/^\s*name\s*=\s*(.*)/i', $grep, $matches)) $this->name = $matches[1];
                elseif (preg_match('/^\s*file_type\s*=\s*(.*)/i', $grep, $matches)) $this->valid_file_types = explode(",", $matches[1]);
                elseif (preg_match('/^\s*grep\s*=\s*(.*)/i', $grep, $matches)) array_push($this->grep, $matches[1]);
                elseif (preg_match('/^\s*pre_grep\s*=\s*(.*)/i', $grep, $matches)) $this->pre_grep = $matches[1];
                elseif (preg_match('/^\s*category\s*=\s*(.*)/i', $grep, $matches)) $this->category = $matches[1];
                elseif (preg_match('/^\s*preprocess\s*=\s*(.*)/i', $grep, $matches)) $this->preprocess = trim($matches[1]);
                elseif (preg_match('/^\s*lookahead_length\s*=\s*(.*)/i', $grep, $matches)) $this->lookahead_length = $matches[1];
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
            if (!Plugin::check_in_filetype($this->filename, $this->valid_file_types)) {
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
                $this->grep = array_reverse($this->grep);
                array_push($this->grep, "__" . $this->pre_grep);
                $this->grep = array_reverse($this->grep);
            }

            $pre_matches = array();         // holds line numbers of pre_grep matches



            if (isset($this->preprocess) && $yasca->options["debug"]) {
                $yasca->log_message("Before pre-processing, file contents are: \n" . implode("\n", $this->file_contents), E_ALL);
            }
            $file_contents = (isset($this->preprocess) ? @call_user_func($this->preprocess, $this->file_contents) : $this->file_contents);
            if (isset($this->preprocess) && $yasca->options["debug"]) {
                $yasca->log_message("After pre-processing with {$this->preprocess} for $grep_plugin, file contents are: \n" . implode("\n", $file_contents), E_ALL);
            }

            foreach ($this->grep as $grep) {
                $orig_grep = $grep;
                // Massage the grep to work below
                preg_match("/\/(.*)\/([imsxUX]*)?/", $grep, $matches);
                $modifier = isset($matches[2]) ? $matches[2] : "";
                $grep = isset($matches[1]) ? $matches[1] : $grep;

                // Scan entire file at once
                $matches = array();

                $orig_error_level = error_reporting(0);

                $matches = preg_grep('/' . $grep . '/' . $modifier, $file_contents);

                if (preg_match("/^__(.*)/", $orig_grep)) {          // for pre_grep
                	error_reporting($orig_error_level);
                    $pre_matches = array_keys($matches);
                    continue;
                }

                error_reporting($orig_error_level);

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

		    if (($i = $this->have_finding(self::$findings, $this->filename, 
			    $this->description)) == -1) {
			$i = count(self::$findings);
			self::$findings[$i]['file'] = $this->filename;
			self::$findings[$i]['description'] = $this->description;
			self::$findings[$i]['lines'] = sprintf("%d", $line_number+1);		    
		    } else {
			self::$findings[$i]['lines'] .= ", " . sprintf("%d", $line_number+1);	
		    }
                }
            }
		}

        $yasca->general_cache[Plugin_PotentialConcerns::$CACHE_ID] = $this->findings_to_string();
        $yasca->add_attachment(Plugin_PotentialConcerns::$CACHE_ID);
    }

    protected static function findings_to_string() {
		$report = '';
		for ($i = 0; $i < count(self::$findings); $i++) {
		    if (!isset(self::$findings[$i]['file'])) continue;
	
		    $fileLink = sprintf("<a title=\"%s\" target=\"_blank\" href=\"file://%s\" source_code_link=\"true\">%s</a>",
			self::$findings[$i]['file'], self::$findings[$i]['file'], basename(self::$findings[$i]['file'])); 
	
		    $report .= sprintf("%s %s %s<br/><br/>\n",  $fileLink,
			self::$findings[$i]['lines'], self::$findings[$i]['description']);
		}
	
		return $report;
    }
}
?>

