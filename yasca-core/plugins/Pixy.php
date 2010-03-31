<?php
/**
 * The Pixy Plugin uses Pixy to discover potential vulnerabilities in PHP 4 files.
 * Uses Pixy 3.0.3
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Pixy extends Plugin {
    public $valid_file_types = array("php", "php4");

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\pixy\\pixy.bat",
                               'Linux'   => "%SA_HOME%resources/utility/pixy/pixy");
    
    //@todo Pixy is the #2 slowest plugin because it causes a full instantiation of the java JVM for each and every file, rather than once.
    public $is_multi_target = false;
    
    /** Shortcuts the Java check because Pixy is not multi-target */
    protected static $minimum_java_exists = null;

    public $installation_marker = "pixy";
    
    /**
     * Executes the Pixy function. This calls out to pixy.bat which then calls Java, but
     * process output comes back here.
     */
    function execute() {
    	// added because Pixy is a one-at-a-time plugin
        if (isset(self::$minimum_java_exists) && !self::$minimum_java_exists) return;
        
        $yasca =& Yasca::getInstance();
        
        if (!isset(self::$minimum_java_exists)){
        	self::$minimum_java_exists = $this->check_for_java(1.5);
	        if (!self::$minimum_java_exists) {
	            $yasca->log_message("The Pixy Plugin requires JRE 1.5 or later.", E_USER_WARNING);
	            return;
	        }
        }
	
        $pixy_results = array();
        
        $executable = $this->executable[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);

        exec( $executable . " " . escapeshellarg($this->filename) . " 2>&1", $pixy_results);
               
        if ($yasca->options['debug']) 
            $yasca->log_message("Pixy returned: " . implode("\r\n", $pixy_results), E_ALL);
    
        $rule = "";
        $category_link = "#";
        
        foreach($pixy_results as $line) {
            if (preg_match('/^XSS Analysis BEGIN/i', $line)) { 
            	$rule = "Cross-Site Scripting"; 
            	$category_link = "http://www.owasp.org/index.php/Cross_Site_Scripting"; 
            } elseif (preg_match('/^SQL Analysis BEGIN/i', $line)) {
            	$rule = "SQL Injection";
            	$category_link = "http://www.owasp.org/index.php/SQL_Injection"; 
            } elseif (preg_match('/^File Analysis BEGIN/i', $line)) {
            	$rule = "File-Related Vulnerability";
            	$category_link = "#";
            }

            if ($rule == "") continue;
            
            if (preg_match('/^\-\d*(.*?):(\d+)$/', $line, $results)) {
                $vFilename = str_replace("\\", "/", trim($results[1]));
                //if (!file_exists($vFilename)) continue;
                $vFilename = trim($results[1]);

                $vLine = $results[2];
                $priority = 1;

                $result = new Result();
                $result->line_number = $vLine;
                $result->filename = $vFilename;
                $result->category = $rule;
                $result->category_link = $category_link;
                $result->is_source_code = true;
                $result->plugin_name = $yasca->get_adjusted_alternate_name("Pixy", $rule, $rule);
                $result->severity = $yasca->get_adjusted_severity("Pixy", $rule, $priority);

                $result->source = array_slice( file($vFilename), $result->line_number-1, 1 );
                $result->source = $result->source[0];
                $result->description = $yasca->get_adjusted_description("Pixy", $rule, "<p>description</p><h4>Example:</h4><pre class=\"fixedwidth\">example</pre>");

				$result->source_context = array_slice( file($vFilename), max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
	
				if (!in_array($result, $this->result_list)) {
				    array_push($this->result_list, $result);
				}
		    }
		}
    }
}
?>
