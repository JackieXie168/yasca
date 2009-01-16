<?php
/**
 * The Pixy Plugin uses Pixy to discover potential vulnerabilities in PHP 4 files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * Uses Pixy 3.0.3
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Pixy extends Plugin {
	public $valid_file_types = array("php", "php4");

	private $executable = array('Windows' => "resources\\utility\\pixy\\pixy.bat",
				    'Linux'   => "./resources/utility/pixy/pixy");
	/**
	 * This class is multi-target.
	 */
	public $is_multi_target = false;

	/**
	 * Executes the Pixy function. This calls out to pixy.bat which then calls Java, but
	 * process output comes back here.
	 */
	function execute() {
		$yasca =& Yasca::getInstance();
		
		if (!$this->check_for_java(1.5)) {
			$yasca->log_message("The PMD Plugin requires JRE 1.5 or later.", E_USER_WARNING);
			return;
		}
		
		$dir = $yasca->options['dir'];
		$pixy_results = array();
			
		exec( $this->executable[getSystemOS()] . " " . escapeshellarg($this->filename), $pixy_results);
			
		if ($yasca->options['debug']) 
			$yasca->log_message("Pixy returned: " . implode("\r\n", $pixy_results), E_ALL);
    
		$rule = "";
		
		for ($i=0; $i<count($pixy_results); $i++) {
		    $line = $pixy_results[$i];
		    if (preg_match('/^XSS Analysis BEGIN/i', $pixy_results[$i]))  $rule = "Cross-Site Scripting";
		    if (preg_match('/^SQL Analysis BEGIN/i', $pixy_results[$i]))  $rule = "SQL Injection";
		    if (preg_match('/^File Analysis BEGIN/i', $pixy_results[$i])) $rule = "File-Related Vulnerability";

		    if ($rule = "") continue;
		    
		    if (preg_match('/^\- (.*):(\d+)$/', $pixy_results[$i], $results)) {
			    $vFilename = str_replace("\\", "/", trim($results[1]));
			    if (!file_exists($vFilename)) continue;
			    
			    $vLine = $results[2];
			    $priority = 2;

			    $result = new Result();
			    $result->line_number = $vLine;
			    $result->filename = $vFilename;
			    $result->category = "Pixy: $rule";
			    $result->category_link = "";
			    $result->is_source_code = true;
			    $result->plugin_name = $yasca->get_adjusted_alternate_name("Pixy", $rule, $rule);
			    $result->severity = $yasca->get_adjusted_severity("Pixy", $rule, $priority);
				
			    $result->source = array_slice( file($vFilename), $result->line_number-1, 1 );
			    $result->source = $result->source[0];
			    $result->description = $yasca->get_adjusted_description("Pixy", $rule, "<p>description</p><h4>Example:</h4><pre class=\"fixedwidth\">example</pre>");

			    $result->source_context = array_slice( file($vFilename), max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
			    array_push($this->result_list, $result);
		    }
		}
	}
}
?>
