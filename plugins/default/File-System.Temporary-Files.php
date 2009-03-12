<?php

/**
 * This class looks for temporary files.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_file_system_temporary_files extends Plugin {
	public $valid_file_types = array();	// match everything
	
	public $pattern_list = array(
                                "*.tmp",
                                "*.temp",
                                "*dummy*",
                                "*.old",
                                "*.bak",
                                "*.save",
                                "*.backup",
                                "*.orig",
                                "*.000",
                                "*.copy",
                                "Copy of*",
                                "_*",
                                "vssver.scc",   /* Visual SourceSafe */
                                "thumbs.db",    /* Explorer Thumbnails */
                                "*.psd",        /* Photoshop */ 
                                "hco.log",      /* CA Harvest */
                                "harvest.sig",  /* CA Harvest */
                                "*.svn-base",   /* SVN */
                                "all-wcprops",  /* SVN */
                                ".project",     /* Eclipse */
                                ".classpath",   /* Eclipse */
                                ".gitignore"    /* Git */
                                );

	function execute() {		
		$filename = basename($this->filename);
		foreach ($this->pattern_list as $pattern) {
			if (fnmatch($pattern, $filename)) {
				$result = new Result();
				$result->severity = 3;
				$result->category = "Potentially Sensitive Data Under Web Root";
				$result->category_link = "http://www.owasp.org/index.php/Sensitive_Data_Under_Web_Root";
				$result->source = "This type of file is usually not used in production.";
				$result->is_source_code = false;
				array_push($this->result_list, $result);
			}
		}
		
		
	}
}
?>
