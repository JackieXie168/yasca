<?php

/**
 * This class looks for all URLs located in the source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_URLFinder extends Plugin {
    public $valid_file_types = array();
    
	public function Plugin_URLFinder($filename, &$file_contents){
		parent::Plugin($filename, $file_contents);
		// Handle this separately, since it's valid on all files EXCEPT those listed below
		// TODO White-list checking instead of blacklist checking. An 80 meg media file in the directory will crash yasca.
		if ($this->check_in_filetype($filename, array("jar", "zip", "dll", "war", "tar", "ear",
													  "jpg", "png", "gif", "exe", "bin", "lib",
													  "svn-base", "7z", "rar",
													  "mov", "wmv", "mp3"))) {
			$this->is_valid_filetype = false;
		}
	}
    
    protected static $CACHE_ID = 'Plugin_URLFinder.url_list,Unique URLs';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        $url_list = isset($yasca->general_cache[self::$CACHE_ID]) ? 
                          $yasca->general_cache[self::$CACHE_ID] : 
                          array();
        
        $matches = preg_grep('/([a-z0-9\-\_][a-z0-9\-\_\.]+\.com)[^a-z0-9]/i', $this->file_contents);
        foreach ($matches as $match) {
            preg_match('/([a-z0-9\-\_][a-z0-9\-\_\.]+\.com)[^a-z0-9]/i', $match, $submatches);
            $url = $submatches[1];                          // only have to worry about [1]
            if (preg_match('/package /', $url)) continue;
            if (preg_match('/import /', $url)) continue;
            if (preg_match('/^\s*\*/', $url)) continue;     // probably in a comment
    
            $value = "$url,$this->filename";
            if (in_array($value, $url_list)) continue;
            array_push($url_list, $value);
        }
        sort($url_list);
        
        $yasca->general_cache[self::$CACHE_ID] = $url_list;
        $yasca->add_attachment(self::$CACHE_ID);
        
        $matches = null;
        $url_list = null;
        
        static $already_added = false;
        if ($already_added) return;
        $already_added = true;
        
        $id = self::$CACHE_ID;
        $yasca->register_callback('post-scan', function () use ($id) {
        	$yasca =& Yasca::getInstance();
        	$url_list = $yasca->general_cache[$id];
	
	        $html = '<table style="width:95%;">';
	        $html .= '<th>URL</th><th>Filename</th>';
	        foreach ($url_list as $url) {
	            $url_array = explode(",", $url, 2);
	            $html .= "<tr><td>{$url_array[0]}</td><td><a target=\"_blank\" ";
	            $html .= "href=\"file://{$url_array[1]}\">{$url_array[1]}</a></td></tr>";
	        }
	        $html .= "</table>";
	        $yasca->general_cache[$id] = $html; 
   	 	});
    }
    
}
?>