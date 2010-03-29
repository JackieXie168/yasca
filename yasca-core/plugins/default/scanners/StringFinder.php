<?php

/**
 * This class looks for all strings located in the source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_StringFinder extends Plugin {
    public $valid_file_types = array();
    
	public function Plugin_StringFinder($filename, &$file_contents){
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
    
    protected static $CACHE_ID = 'Plugin_StringFinder.string_list,Unique Strings';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        $string_list = isset($yasca->general_cache[self::$CACHE_ID]) ? 
                             $yasca->general_cache[self::$CACHE_ID] : 
                             array();
        
        $matches = preg_grep('/\"([^\"]+)\"/', $this->file_contents);
        foreach ($matches as $match) {
            preg_match('/\"([^\"]+)\"/', $match, $submatches);
            $str = $submatches[1];
            if (preg_match('/[^\x20-\x7F]/', $str)) continue;
            $str = htmlentities($str);
            if (in_array($str, $string_list)) continue;
            array_push($string_list, $str);
        }
        sort($string_list);
        
        $yasca->general_cache[self::$CACHE_ID] = $string_list;
        $yasca->add_attachment(self::$CACHE_ID);
    }
}
?>