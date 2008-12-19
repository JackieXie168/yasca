<?php

include_once("lib/Report.php");

/**
 * XMLReport Class
 *
 * This class renders scan results as XML.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.0
 * @package Yasca
 */
class XMLReport extends Report {
	/**
	 * The default extension used for reports of this type.
	 */
	var $default_extension = "xml";
	
	/**
	 * Executes an XMLReport, with output going to $options['output']
	 */ 
	function execute() {
		if (!$handle = $this->create_output_handle()) return;
		
		fwrite($handle, $this->get_preamble());
		fwrite($handle, "<YascaXMLResults>\r\n");
		$num_results_written = 0;
		foreach ($this->results as $result) {
			if (!$this->is_severity_sufficient($result->severity))
				continue;
				
			$filename = $result->filename;
			$filename = preg_replace("/" . addslashes($this->options['dir']) . "/", "", $filename, 1);
			$filename = str_replace("\\", "/", $filename);
						
			$last_slash = strripos($filename, "/");
			$split_filename = substr($filename, $last_slash);
			
			$result->source_context = implode("\r\n", $result->source_context);
			
			fwrite($handle,
				"<result>\r\n" .
				" <plugin_name><![CDATA[" . $result->plugin_name . "]]></plugin_name>\r\n" .
				" <filename><![CDATA[" . $result->filename . "]]></filename>\r\n" .
				" <category><![CDATA[" . $result->category . "]]></category>\r\n" .
				" <category_link><![CDATA[" . $result->category_link . "]]></category_link>\r\n" .
				" <severity><![CDATA[" . $result->severity . "]]></severity>\r\n" .
				" <severity_description><![CDATA[" . $this->get_severity_description($result->severity) . "]]></severity_description>\r\n" .
				" <source><![CDATA[" . $result->source . "]]></source>\r\n" .
				" <line_number><![CDATA[" . $result->line_number . "]]></line_number>\r\n" .
				" <source_context><![CDATA[" . $result->source_context . "]]></source_context>\r\n" .
				" <custom><![CDATA[" . $result->custom . "]]></custom>\r\n" .
				"</result>\r\n");
			++$num_results_written;
		}
		fwrite($handle, "</YascaXMLResults>\r\n");
		fwrite($handle, $this->get_postamble());
	    fclose($handle);
	}
	
	function get_preamble() {
		return <<<EOT
<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE YascaXMLResults [
	<!ELEMENT YascaXMLResults (result+)>
	<!ELEMENT result (plugin_name, filename, category, category_link, severity, severity_description, source, line_number, source_context, custom?)>
	<!ELEMENT plugin_name (#PCDATA)>
	<!ELEMENT filename (#PCDATA)>
	<!ELEMENT category (#PCDATA)>
	<!ELEMENT category_link (#PCDATA)>	
	<!ELEMENT severity (#PCDATA)>
	<!ELEMENT severity_description (#PCDATA)>
	<!ELEMENT source (#PCDATA)>
	<!ELEMENT line_number (#PCDATA)>
	<!ELEMENT source_context (#PCDATA)>
	<!ELEMENT custom (#PCDATA)>
]>
EOT;
	}
		
	function get_postamble() {
		return "";
	}
	
	
	function array2json($arr) {
	    $parts = array();
	    $is_list = false;
	    if (!isset($arr) || !is_array($arr)) {
	    	$arr = array();
	    }
	
	    //Find out if the given array is a numerical array
	    $keys = array_keys($arr);
	    $max_length = count($arr)-1;
	    if(($keys[0] == 0) and ($keys[$max_length] == $max_length)) {//See if the first key is 0 and last key is length - 1
	        $is_list = true;
	        for($i=0; $i<count($keys); $i++) { //See if each key correspondes to its position
	            if($i != $keys[$i]) { //A key fails at position check.
	                $is_list = false; //It is an associative array.
	                break;
	            }
	        }
	    }
	
	    foreach($arr as $key=>$value) {
	        if(is_array($value)) { //Custom handling for arrays
	            if($is_list) $parts[] = array2json($value); /* :RECURSION: */
	            else $parts[] = '"' . $key . '":' . array2json($value); /* :RECURSION: */
	        } else {
	            $str = '';
	            if(!$is_list) $str = '"' . $key . '":';
	
	            //Custom handling for multiple data types
	            if(is_numeric($value)) $str .= $value; //Numbers
	            elseif($value === false) $str .= 'false'; //The booleans
	            elseif($value === true) $str .= 'true';
	            else $str .= '"' . addslashes($value) . '"'; //All other things
	            // :TODO: Is there any more datatype we should be in the lookout for? (Object?)
	
	            $parts[] = $str;
	        }
	    }
	    $json = implode(',',$parts);
	    
	    if($is_list) return '[' . $json . ']';//Return numerical JSON
	    return '{' . $json . '}';//Return associative JSON
	} 
}



?>
