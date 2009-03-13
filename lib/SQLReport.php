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
	    $yasca =& Yasca::getInstance();	    
	    
	    try {
		$this->dbh = new PDO('sqlite:yasca.db', 'x', 'x');
	    } catch(PDOException $e) {
		$yasca->log_message("Error creating database connection: " . $e->getMessage(), E_USER_ERROR);
		$this->dbh = false;
		return;
	    }

/*
	    $rows = $this->dbh->query("select 1 from target");
	    if (!is_array($rows) || count($rows) == 0) {

		// Create database structure if needed
		foreach (file("resources/db.sql") as $sql) {
		    $dbh->exec($sql);
		}
	    }
*/
	}

	/**
	 * Executes a SQLLiteReport, to the output file $options['output'] or ./results.db
	 */ 
	function execute() {
		if (!isset($this->dbh)) $this->openDatabase();
		if (!$this->dbh) {
		    $yasca->log_message("Aborting creation of SQLReport.", E_USER_ERROR);
		    return;
		}
		$yasca =& Yasca::getInstance();
		$sth = false;

		$target_dir = $this->options['dir'];
		$username = getenv("USERNAME");

		$sth = $this->dbh->prepare("insert into target (location, scan_dt, scan_by) values (?,  date('now'), ?)");
		$sth->bindParam(1, $target_dir);
		$sth->bindParam(2, $username);
		$sth->execute();

		$target_id = $this->dbh->lastInsertId();

		$this->dbh->beginTransaction();
		foreach ($this->results as $result) {
			if (!$this->is_severity_sufficient($result->severity))
			    continue;
			$description_id = $this->get_description_id($result->description);
			$category_id = $this->get_category_id($result->category, $result->category_link);
			$is_source_code = $result->is_source_code ? "Y" : "N";
			$source_context = $result->source_context;
			$source_context = implode("\n", $source_context);

			$sth = $this->dbh->prepare("insert into finding (target_id, category_id, severity, filename, line_number, description_id, source_line, source_context, active_fl) values (:target_id, :category_id, :severity, :filename, :line_number, :description_id, :source_line, :source_context, 'Y')");

			$sth->bindParam(":target_id", $target_id, PDO::PARAM_INT);
			$sth->bindParam(":category_id", $category_id, PDO::PARAM_INT);
			$sth->bindParam(":severity", $result->severity, PDO::PARAM_INT);
			$sth->bindParam(":filename", $result->filename);
			$sth->bindParam(":line_number", $result->line_number, PDO::PARAM_INT);
			$sth->bindParam(":description_id", $description_id, PDO::PARAM_INT);
			$sth->bindParam(":source_line", $result->source);
			$sth->bindParam(":source_context", $source_context);

			$sth->execute();			
		}

		$this->dbh->commit();

		$this->dbh = null;
	}

	function get_description_id($description) {
		$sth = $this->dbh->prepare("select description_id from description where description=?");
		$sth->bindParam(1, $description);
		$sth->execute();
	
		$rs = $sth->fetch(PDO::FETCH_OBJ);
		if ($rs == false) {
		    $sth = $this->dbh->prepare("insert into description (description) values (?)");
		    $sth->bindParam(1, $description);
		    $sth->execute();
		    return $this->dbh->lastInsertId();
		} else {
		    return $rs->description_id;
		}
	}

	function get_category_id($name, $url) {
		$sth = $this->dbh->prepare("select category_id from category where name=? and url = ?");
		$sth->bindParam(1, $name);
		$sth->bindParam(2, $url);
		$sth->execute();

		$rs = $sth->fetch(PDO::FETCH_OBJ);
		if ($rs == false) {
		    $sth = $this->dbh->prepare("insert into category (name, url) values (?, ?)");
		    $sth->bindParam(1, $name);
		    $sth->bindParam(2, $url);
		    $sth->execute();
		    return $this->dbh->lastInsertId();
		} else {
		    return $rs->category_id;
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