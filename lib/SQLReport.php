<?php

include_once("lib/Report.php");

/**
 * SQLLiteReport Class
 *
 * This class places all contents in a SQLLite Database.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.0
 * @package Yasca
 */
class SQLReport extends Report {
	/**
	 * The default extension used for reports of this type.
	 */
	public $default_extension = "html";

	/**
	 * Holds a reference to the SQL Database
	 */
	private $dbh;

	function openDatabase() {
	    $dsn = $yasca->options['dsn'];
	    $username = $yasca->options['username'];
	    $password = $yasca->options['password'];
	    
	    try {
		$this->dbh = new PDO('mysql:dbname=block863_yasca;host=block86.com', 'yasca', 'yasca');
	    } catch(PDOException $e) {
		$yasca->log_message("Error creating database connection: " . $e->getMessage());
		$this->dbh = false;
		return;
	    }

	    $rows = $dbh->query("select 1 from scan");
	    if (!is_array($rows) || count($rows) == 0)

		// Create database structure if needed
		foreach (file("resources/db.sql") as $sql) {
		    $dbh->exec($sql);
		}
	    }
	}

	private function setCategory($result) {
	    if (!$this->db) $this->openDatabase();
	    $category_id = $this->dbh->query("select category_id from category where name = '{$result->category}' and link = '{$result->category_link}'", true);
	    print_r($category_id);

	    if (!is_numeric($category_id)) {
		$this->db->queryExec("insert into category (name, link) values ('{$result->category}', '{$result->category_link}')");
		$category_id = $this->db->singleQuery("select category_id from category where name = '{$result->category}' and link = '{$result->category_link}'", true);
	    }
	    return $category_id;
	}

	private function setPlugin($result) {
	    if (!$this->db) $this->openDatabase();
	    $plugin_id = $this->db->singleQuery("select plugin_id from plugin where name = '{$result->plugin_name}'", true);
	    if (!is_numeric($plugin_id)) {
		$this->db->queryExec("insert into plugin (name) values ('{$result->plugin_name}')");
		$plugin_id = $this->db->singleQuery("select plugin_id from plugin where name = '{$result->plugin_name}'", true);
	    }
	    return $plugin_id;
	}


	/**
	 * Executes a SQLLiteReport, to the output file $options['output'] or ./results.db
	 */ 
	function execute() {
		if (!isset($this->dbh)) $this->openDatabase();

		$target_dir = $this->options['dir'];
		$options = print_r($this->options, true);

		$this->db->queryExec("insert into scan (target, scan_dt, options) values ('$target_dir', '2009-01-01', '$options')");
		$scan_id = $this->db->lastInsertRowid();

		foreach ($this->results as $result) {
			if (!$this->is_severity_sufficient($result->severity))
			    continue;
			$category_id = $this->setCategory($result);
			$plugin_id = $this->setPlugin($result);


			$filename = $result->filename;
			$line_number = $result->line_number;
			$is_source_code = $result->is_source_code ? "Y" : "N";

			$this->db->queryExec("insert into result (scan_id, plugin_id, category_id, filename, line_number, is_source_code) values ($scan_id, $plugin_id, $category_id, '$filename', $line_number, '$is_source_code')");
			
		}
		
	}
	
	function get_preamble() {
		return "";
	}
		
	function get_postamble() {
		return "";
	}	
}

?>
