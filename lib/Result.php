<?php
/**
 * Result Class
 *
 * This struct holds result information for a particular issue found. There will be
 * one Result object created for each such issue. 
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.0
 * @package Yasca
 */
class Result {
	public $filename;
	public $severity = 5;
	public $category = "General";			// default value
	public $category_link;
	public $plugin_name;
	public $is_source_code = true;		// format the message as source code?	
	public $source;
	public $source_context;
	public $proposed_fix;
	public $line_number = 0;
	public $description = "";			// description (long, html) of the item
	public $custom = array(); 			// for any other custom variables that a Plugin wants
}
?>