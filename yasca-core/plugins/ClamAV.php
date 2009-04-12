<?php

/**
 * The ClamAV Plugin uses ClamAV to discover backdoors, trojans and viruses in the source code.
 *
 * Original plugin by Josh Berry, 04/01/2009
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_ClamAV extends Plugin {
	public $valid_file_types = array();

	public $is_multi_target = true;

    public $installation_marker = true;		// This is ok because this one is multi-target and it will return if Linux and ClamAV not found

	private $executable = array('Windows' => "resources\\utility\\clamav\\clamscan.exe --no-summary -d resources\\utility\\clamav\\ -ri --detect-pua --no-mail --max-recursion=5 --max-dir-recursion=30 ",
                                'Linux'   => "clamscan --no-summary -ri --detect-pua --no-mail --max-recursion=5 --max-dir-recursion=30 ");
   
   /**
	* Executes ClamAV on the directory
	*/
	function execute() {
		static $alreadyExecuted;
		if ($alreadyExecuted == 1) return;
		$alreadyExecuted = 1;

		$yasca =& Yasca::getInstance();
		$dir = $yasca->options['dir'];      
		$result_list = array();

		if (getSystemOS() == "Windows") {
			$yasca->log_message("Forking external process (ClamAV)...", E_USER_WARNING);
			exec( $this->executable[getSystemOS()] . " " . escapeshellarg($dir),  $result_list);
			$yasca->log_message("External process completed...", E_USER_WARNING);
		} else if (getSystemOS() == "Linux") {
            if (preg_match("/no clamscan in/", `which clamscan`)) {
			    $yasca->log_message("ClamAV not detected. Please install and ensure that 'clamscan' is on the system path.", E_USER_WARNING);
                return;
            }
			$yasca->log_message("Forking external process (ClamAV)...", E_USER_WARNING);
			exec( $this->executable[getSystemOS()] . " " . escapeshellarg($dir),  $result_list);
			$yasca->log_message("External process completed...", E_USER_WARNING);
		}

		if ($yasca->options['debug'])
			$yasca->log_message("ClamAV returned: " . implode("\r\n", $result_list), E_ALL);
            
		// Now check each message
		foreach($result_list as $result) {
			if (preg_match("/^((?!clamscan\.exe).)*$/i", $result) && preg_match("/FOUND/i", $result)) {
				$matches = split(":", $result);
				$filename = $matches[0] . $matches[1];
				$message = $matches[2];
            
				$result = new Result();
				$result->line_number = 0;
				$result->filename = $filename;
				$result->plugin_name = "Virus/Trojan Found";
				$result->severity = 1;
				$result->category = "Virus/Trojan Found";
				$result->source = $message;
				$result->is_source_code = false;
				$result->source_context = "";
				$result->description = "A virus, backdoor, trojan or rootkit was found in the source or in a source file";
				array_push($this->result_list, $result);
      	  	}
		}  
	}
}
?>

