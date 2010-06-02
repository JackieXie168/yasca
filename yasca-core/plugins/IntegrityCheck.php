<?php

/**
 * The IntegrityCheck Plugin uses IntegrityCheck to discover potential vulnerabilities in .class files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_IntegrityCheck extends Plugin {
    public $valid_file_types = array();

    public $is_multi_target = true;

    public $installation_marker = true;
    
    function execute() {
        if (getSystemOS() !== 'Windows') return;        // Only execute on Windows
        
        static $alreadyExecuted;
        if ($alreadyExecuted == 1) return;
        $alreadyExecuted = 1;
        
        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];

		$referenceFile = (isset($yasca->options['parameter']['IntegrityCheck_ReferenceFile']) ?
						  $yasca->options['parameter']['IntegrityCheck_ReferenceFile'] :
						  "./IntegrityCheck_Reference.ser");

		if (file_exists($referenceFile)) {
			$file_list = unserialize(file_get_contents($referenceFile));
		}

		if (isset($yasca->options['parameter']['IntegrityCheck_Initialize']) &&
            $yasca->options['parameter']['IntegrityCheck_Initialize'] == 'true') {
			foreach (dir_recursive($dir) as $file) {
				$file_list[$file] = sha1_file($file, false);
			}
			file_put_contents($referenceFile, serialize($file_list));
		}
		if (isset($file_list)) {
			foreach (dir_recursive($dir) as $file) {
				if ($file_list[$file] !== sha1_file($file, false)) {
					$message = "File contents were changed.";

 	                $result = new Result();
    	            $result->line_number = 0;
        	        $result->filename = $file;
            	    $result->plugin_name = $yasca->get_adjusted_alternate_name("IntegrityCheck", $message, $message);
                	$result->severity = $yasca->get_adjusted_severity("IntegrityCheck", $message, 5);
	                $result->category = "IntegrityCheck Finding";
    	            $result->category_link = "";
        	        $result->source = $yasca->get_adjusted_description("IntegrityCheck", $message, $message);
            	    $result->is_source_code = false;
                	$result->source_context = "";
	                array_push($this->result_list, $result);
				}
			}
		} else {
			$yasca->log_message("IntegrityCheck not initialized. Run with \"-d \"IntegrityCheck_Initialize=true\"\" to create baseline.", E_USER_WARNING);
		}
    }
}
?>
