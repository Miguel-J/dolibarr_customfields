<?php
/* Copyright (C) 2011-2013   Stephen Larroque <lrq3000@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/customfields/class/customfields.class.php
 *      \ingroup    customfields
 *      \brief      Core class file for the CustomFields module, all critical functions reside here
 *		\author		Stephen Larroque
 */

// Include JSON library for PHP <= 5.2 (used to define json_decode() and json_encode() for PHP4)
if( !function_exists('json_decode') or !function_exists('json_encode') ) include_once(dirname(__FILE__).'/ext/JSON.php');
// Include PHP4 object model compatibility library (TODO: may not work! Please check before, eg: will probably need to declare every var that is used, because this is currently not the case (but how to declare all SQL fields???))
include_once(dirname(__FILE__).'/ext/php4compat.php');

// Loading the translation class if it's not yet loaded (or with another name) - DO NOT EDIT!
if (! is_object($langs))
{
    include_once(DOL_DOCUMENT_ROOT."/core/class/translate.class.php");
    $langs=new Translate(dirname(__FILE__).'/../langs/',$conf);
}

// Put here all includes required by your class file
$langs->load('customfields@customfields'); // customfields standard language support
$langs->load('customfields-user@customfields'); // customfields language support for user's values (like enum, fields names, etc..)

function customfields_cmp_obj($a, $b)
{
    if (isset($a->extra->position)) {
        $apos = $a->extra->position;
    } elseif (isset($a->ordinal_position)) {
        $apos = $a->ordinal_position;
    } else {
        return 0;
    }
    if (isset($b->extra->position)) {
        $bpos = $b->extra->position;
    } elseif (isset($b->ordinal_position)) {
        $bpos = $b->ordinal_position;
    } else {
        return 0;
    }
    // In case we have a draw (same position)
    if ($apos == $bpos) {
        // Give the advantage to extra options before ordinal_position
        if (isset($a->extra->position) and !isset($b->extra->position)) {
            return +1;
        } elseif (!isset($a->extra->position) and isset($b->extra->position)) {
            return -1;
        // if either both are extra options or both are ordinal_position, then it's really a draw
        } else {
            return 0;
        }
    }
    // In case there's a difference, we return first the lesser one (logic...)
    return ($apos > $bpos) ? +1 : -1;
}

/**
 *      \class      customfields
 *      \brief      Core class for the CustomFields module, all critical functions reside here
 */
class CustomFields extends compatClass4 // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='customfields';			//!< Id that identify managed objects
	//var $table_element='customfields';	//!< Name of table without prefix where object is stored
        var $dbtype; // type of the database, will be used to use the right function to issue sql requests

	var $varprefix = 'cf_'; // prefix that will be prepended to the variables names for accessing the fields values

	var $id;


    /**
     *      Constructor
     *      @param      DB      Database handler
     *      @param      currentmodule        	Current module (facture/propal/etc.)
     */
    function __construct($db, $currentmodule)
    {
        // Include the config file (only used for $varprefix at this moment, so this class is pretty much self contained and independent - except for triggers and translation, but these are NOT necessary for CustomFields management, only for printing fields more nicely and for logs)
        include(dirname(__FILE__).'/../conf/conf_customfields.lib.php');

	$this->db = $db;
	$this->module = $currentmodule;
	$this->moduletable = MAIN_DB_PREFIX.$this->module."_customfields";
        $this->extratable = MAIN_DB_PREFIX."customfields_extraoptions";
        $this->dbtype = $db->type; // or $conf->db->type

	if (!empty($fieldsprefix)) $this->varprefix = $fieldsprefix;

	return 1;
    }


	// ============ FIELDS RECORDS MANAGEMENT ===========/

	//--------------- Lib Functions --------------------------

	/**
	 *	Similar to mysql_real_escape() but can be reversed and there's no need to be connected to the db
	 */
	function escape($str)
        {
		$search=array("\\","\0","\n","\r","\x1a","'",'"');
		$replace=array("\\\\","\\0","\\n","\\r","\Z","\'",'\"');
		return str_replace($search,$replace,$str);
        }

	/**
	 *	Reverse msql_real_escape() or the function above
	 *	UNUSED
	 */
	function reverse_escape($str)
	{
		$search=array("\\\\","\\0","\\n","\\r","\Z","\'",'\"');
		$replace=array("\\","\0","\n","\r","\x1a","'",'"');
		return str_replace($search,$replace,$str);
	}

        /**
         *      Return an object with only lowercase column_names (otherwise, on some OSes like Unix, mysql functions may return uppercase or mixed case column_name)
         *      Note: similar to mysql_fetch_object, but always return lowercase column_name for every items
         *      @param      $res        Mysql/Mysqli/PGsql/SQlite/MSsql resource
         *      @return       $obj         Object containing one row
         */
        function fetch_object($res, $class_name=null, $params=null) {
            // get the record as an object
            if ($this->dbtype === 'mysql') {
                if (isset($class_name) and isset($params)) {
                    $row = mysql_fetch_object($res, $class_name, $params);
                } elseif (isset($class_name)) {
                    $row = mysql_fetch_object($res, $class_name);
                } else {
                    $row = mysql_fetch_object($res);
                }
            } elseif($this->dbtype === 'mysqli') {
                if (isset($class_name) and isset($params)) {
                    $row = mysqli_fetch_object($res, $class_name, $params);
                } elseif (isset($class_name)) {
                    $row = mysqli_fetch_object($res, $class_name);
                } else {
                    $row = mysqli_fetch_object($res);
                }
            } elseif($this->dbtype === 'mssql') {
                $row = mssql_fetch_object($res);
            } elseif($this->dbtype === 'sqlite') {
                $row = $res->fetch(PDO::FETCH_OBJ);
            } elseif($this->dbtype === 'pgsql') {
                if (isset($class_name) and isset($params)) {
                    $row = pg_fetch_object($res, null, $class_name, $params);
                } elseif (isset($class_name)) {
                    $row = pg_fetch_object($res, null, $class_name);
                } else {
                    $row = pg_fetch_object($res);
                }
            }
            //$row = $this->db->fetch_object($res); // get the record as an object [DEPRECATED]
            $obj = array_change_key_case((array)$row, CASE_LOWER); // change column_name case to lowercase
            $obj = (object)$obj; // cast back as an object
            return $obj; // return the object
        }

	//--------------- Main Functions ---------------------

	/**
	 *      Fetch a record (or all records) from the database (meaning an instance of the custom fields, the values if you prefer)
	 *      @param	   id				id of the record to find (NOT customfields rowid but fk_moduleid, which is the same as the module's rowid) - can be left empty if you want to fetch all the records
	 *      @param      notrigger	    0=launch triggers after, 1=disable triggers
	 *      @return     int/null/obj/obj[]        	<0 if KO, null if no record is found, a record if only one is found, an array of records if OK
	 */
	function fetch($id=null, $notrigger=0)
	{
		// Get all the columns (custom fields), primary field included (that's why there's the true)
		$fields = $this->fetchAllFieldsStruct(true);

		// Forging the SQL statement - we set all the column_name to fetch (because Dolibarr wants to avoid SELECT *, so we must name the columns we fetch)
		foreach ($fields as $field) {
			$keys[] = $field->column_name;
		}
		$sqlfields = implode(',',$keys);

		$sql = "SELECT ".$sqlfields." FROM ".$this->moduletable;

                if (is_array($id)) {

                    $sql .= " WHERE fk_".$this->module."=".implode(' or fk_'.$this->module.'=', $id);
                } elseif ($id > 0) { // if we supplied an id, we fetch only this one record
		    $sql .= " WHERE fk_".$this->module."=".$id." LIMIT 1";
		}

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHRECORD';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		// Filling the record object
		if ($resql < 0) { // if there's an error
			return $resql; // we return the error code
		} else { // else we fill the record
			$num = $this->db->num_rows($resql); // number of results returned (number of records)
			// Several records returned = array() of objects (also if an array of $id was submitted, the user probably expects an array to be returned)
			if ($num > 1 or ($num == 1 and is_array($id))) {
				// Find the primary field (so that we can set the record's id)
				$prifield = $this->fetchPrimaryField($this->moduletable);

				$record = array();
				for ($i=0;$i < $num;$i++) {
					$obj = $this->fetch_object($resql);
					$obj->id = $obj->$prifield; // set the record's id
					$record[$obj->id] = $obj; // add the record to our records' array
				}
				$this->records = $record; // and we as well store the records as a property of the CustomFields class

                                // Workaround: on some systems, num_rows will return 2 when in fact there's only 1 row. Here we fix that by counting the number of elements in the final array: if only one, then we return only first element of the array to be compliant with the paradigm: one record = one value returned
                                if ( !is_array($id) and count($record) == 1) $record = reset($record);

			// Only one record returned = one object
			} elseif ($num == 1) {
				$record = $this->fetch_object($resql);

				// If we get only 1 result and $id is not set, this means that we are not looking for a particular record, we are fetching all records but we find only one. In this case, we must find the id by ourselves.
				if (!isset($id)) {
					$prifield = $this->fetchPrimaryField($this->moduletable); // find the primary field of the table
					$id = $record->$prifield; // set the id
				}

				$record->id = $id; // set the record's id
				$this->id = $id;

			// No record returned = null
			} else {
				$record = null;
			}
			$this->db->free($resql);

			// Return the field(s) or null
			return $record;
		}

	}

	/**	Fetch all the records from the database for the current module
	 *	there's no argument, and it's just an alias for fetch()
	 *	@return	int/null/obj[]		<0 if KO, null if no record found, an array of records if OK (even if only one record is found)
	 */
	function fetchAll($notrigger=0) {
		$records = $this->fetch(null, $notrigger);
		if ( !(is_array($records) or is_null($records) or is_integer($records)) ) { $records = array($records); } // we convert to an array if we've got only one field, and if it's not an error or null, functions relying on this one expect to get an array if OK
		return $records;
	}

	/**
	 *      Fetch any record in the database from any table (not just customfields)
	 *      @param	columns		one or several columns (separated by commas) to fetch
	 *      @param	table		table where to fetch from
	 *      @param	where		where clause (format: column='value'). Can put several where clause separated by AND or OR
	 *      @param	orderby	order by clause
	 *      @return     int or object or array of objects         	<0 if KO, object if one record found, array of objects if several records found
	 */
	function fetchAny($columns, $table, $where='', $orderby='', $limitby='', $notrigger=0)
	{

		$sql = "SELECT ".$columns." FROM ".$table;
		if (!empty($where)) {
			$sql.= " WHERE ".$where;
		}
		if (!empty($orderby)) {
			$sql.= " ORDER BY ".$orderby;
		}
		if (!empty($limitby)) {
			$sql.= " LIMIT ".$limitby;
		}

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHANY';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		// Filling the record object
		if ($resql < 0) { // if there's no error
			return $resql; // we return the error code
		} else { // else we fill the record
			$num = $this->db->num_rows($resql); // number of results returned (number of records)
			// Several records returned = array() of objects
			if ($num > 1) {
				$record = array();
				for ($i=0;$i < $num;$i++) {
					$record[] = $this->fetch_object($resql);
				}
			// Only one record returned = one object
			} elseif ($num == 1) {
				$record = $this->fetch_object($resql);
			// No record returned = null
			} else {
				$record = null;
			}
			$this->db->free($resql);

			// Return the record(s) or null
			return $record;
		}

	}


	/**
	 *      Insert/update a record in the database (meaning an instance of the custom fields, the values if you prefer)
	 *      @param	   object				Object containing all the form inputs to be processed to the database (so it mus contain the custom fields)
	 *      @param      notrigger	    0=launch triggers after, 1=disable triggers
	 *      @return     int         	<0 if KO, >0 if OK
	 */
	function create($object, $notrigger=0)
	{
		// Get all the columns (custom fields)
		$fields = $this->fetchAllFieldsStruct();

		if (empty($fields)) return null;

		// Forging the SQL statement
		$sqlfields = array();
                $sqlvalues = array();
		foreach ($fields as $field) {
			$key = $this->varprefix.$field->column_name;
			if (!isset($object->$key)) {
				$key = $field->column_name;
			}

            $object->$key = urldecode($object->$key); // New in Dolibarr 3.4.x: all data is url encoded, must decode before storing into db

			//We need to fetch the correct value when we update a date field
			if($field->data_type == 'date') {
                // Fetch day, month and year
                if (isset($object->{$key.'day'}) and isset($object->{$key.'month'}) and isset($object->{$key.'year'})) { // for date fields, Dolibarr will produce 3 more associated POST fields: myfielddate, myfieldmonth and myfieldyear
                    $dateday = trim($object->{$key.'day'});
                    $datemonth = trim($object->{$key.'month'});
                    $dateyear = trim($object->{$key.'year'});
                } else { // else if they are not submitted (or if they weren't assigned inside $object), we try to split the date into 3 values
                    list($dateday, $datemonth, $dateyear) = explode('/',$object->$key);
                }
                // Format the correct timestamp from the date for the database
                $object->$key = $this->db->idate(dol_mktime(0, 0, 0, $datemonth, $dateday, $dateyear));
           }

			if ($object->$key) { // Only insert/update this field if it was submitted
                                // Note: we separate fields and values because depending on whether we UPDATE or INSERT the record, the format is not the same (INSERT: values and fields are separated, UPDATE: both are submitted at the same place)
                                array_push($sqlfields, $field->column_name);
                                array_push($sqlvalues, "'".$this->escape($object->$key)."'"); // escape and single-quote values (even if they are not strings, the database will automatically correct that depending on the column_type)
			}
		}

                // we add the object id (filtered by fetchAllFieldsStruct)
                array_push($sqlfields, "fk_".$this->module);
                array_push($sqlvalues, $object->id);

                // fetch the record (to check whether it already exists or not)
		$result = $this->fetch($object->id);

		if (!empty($result) and count($result) > 0) { // if the record already exists for this facture id, we update it
                        // Compact and format all the fields and values in the correct sql syntax (eg: field='value')
                        $sqlfieldsandvalues = array();
                        for($i=0;$i<count($sqlfields);$i++) {
                            array_push($sqlfieldsandvalues, $sqlfields[$i].'='.$sqlvalues[$i]);
                        }
			$sql = "UPDATE ".$this->moduletable." SET ".implode(',', $sqlfieldsandvalues)." WHERE fk_".$this->module."=".$object->id;
		} else { // else we insert a new record
			$sql = "INSERT INTO ".$this->moduletable." (".implode(',',$sqlfields).") VALUES (".implode(',',$sqlvalues).")";
		}

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_CREATEUPDATERECORD';
		}

		// Executing the SQL statement
		$rtncode = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		$this->id = $this->db->last_insert_id($this->moduletable);

		return $rtncode;
	}


	/**
	 *      Insert/update a record in the database (meaning an instance of the custom fields, the values if you prefer)
	 *      @param	   object				Object containing all the form inputs to be processed to the database (so it mus contain the custom fields)
	 *      @param      notrigger	    0=launch triggers after, 1=disable triggers
	 *      @return     int         	<0 if KO, >0 if OK
	 */
	function update($object, $notrigger=0)
	{
		return $this->create($object,$notrigger);
	}

	/**
	 *      Delete a record in the database (meaning an instance of the custom fields, the values if you prefer)
	 *      @param	   id				id of the record to find (NOT rowid but fk_moduleid)
	 *      @param      notrigger	    0=launch triggers after, 1=disable triggers
	 *      @return     int         	<0 if KO, >0 if OK
	 */
	function delete($id, $notrigger=0)
	{
		// Forging the SQL statement
		$sql = "DELETE FROM ".$this->moduletable." WHERE fk_".$this->module."=".$id;

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_DELETERECORD';
		}

		// Executing the SQL statement
		$rtncode = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		$this->id = $id;

		return $rtncode;
	}


	/**
	 *      Insert a record in the database from a clone, by duplicating an existing record (meaning an instance of the custom fields, the values if you prefer)
	 *      @param	   id				ID of the object to clone
	 *      @param	cloneid			ID of the new cloned object
	 *      @param      notrigger	    0=launch triggers after, 1=disable triggers
	 *      @return     int         	<0 if KO, >0 if OK
	 */
	function createFromClone($id, $cloneid, $notrigger=0)
	{
		// Get all the columns (custom fields)
		//$fields = $this->fetchAllFieldsStruct();

		$object = $this->fetch($id);

		$object->id = $cloneid; // Affecting the new id
                $object->rowid = $cloneid; // Affecting the new id

		$rtncode = $this->create($object); // creating the new record

		return $rtncode;
	}


	// ============ FIELDS COLUMNS CONFIGURATION ===========/

	// ------------ Lib functions ---------------/

	/**
	*	Extract the size or value (if type is enum) from the column_type of the database field
	*  @param $column_type
	*  @return $size_or_value
	*/
       function getFieldSizeOrValue($column_type) {
	    preg_match('/[(]([^)]+)[)]/', $column_type, $matches);
	    return $matches[1];
       }

	/*	Execute a unique SQL statement, add it to the logfile and add an event trigger (or not)
         *	Note: just like mysql_query(), we can only issue one sql statement per call. It should be possible to issue multiple queries at once with an explode(';', $sqlqueries) but it would imply security issues with the semicolon, and would require a specific escape function.
         *	Note2: another way to issue multiple sql statement is to pass flag 65536 as mysql_connect's 5 parameter (client_flags), but it still raises the same security concerns.
	 *
	 *
	 *	@return -1 if error, object of the request if OK
	 */
	function executeSQL($sql, $eventname, $trigger=null) { // if $trigger is null, no trigger will be produced, else it will produce a trigger with the provided name
		// Executing the SQL statement
		dol_syslog(get_class($this)."::".$eventname." sql=".$sql, LOG_DEBUG); // Adding an event to the log
		$resql=$this->db->query($sql); // Issuing the sql statement to the db

		if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); } // Checking for errors

		// Managing trigger (if there's no error)
		if (! $error) {
			$id = $this->db->last_insert_id($this->moduletable);

			if (!empty($trigger)) {
				global $user, $langs, $conf; // required vars for the trigger
				//// Call triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers($trigger,$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error)  {
			foreach($this->errors as $errmsg) {
				dol_syslog(get_class($this)."::".$eventname." ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error; // error code : we return -1 multiplied by the number of errors (so if we have 5 errors we will get -5 as a return code)
		} else {
			$this->db->commit();
			return $resql;
		}
	}

	/*	Forge the sql command for createCustomFields and updateCustomFields (creation and update of a field's definition)
	*
	*
	*	@return $sql containing the forged sql statement
	*/
	function forgeSQLCustomField($fieldname, $type, $size, $nulloption, $defaultvalue = null, $customtype = null, $customdef, $id = null) {

		// Forging the SQL statement
		$sql = '';
		if (!empty($id)) { // if a field id was supplied, we forge an update sql statement, else we forge an add field sql statement
			$field = $this->fetchFieldStruct($id); // fetch the field by id (ordinal_position) so we can get the field name
			if ($fieldname != $field->column_name) { // if the name of the field changed, then we use the CHANGE keyword to rename the field and apply other statements
				$sql = "ALTER TABLE ".$this->moduletable." CHANGE ".$field->column_name." ".$fieldname." ";
			} else {
				$sql = "ALTER TABLE ".$this->moduletable." MODIFY ".$field->column_name." "; // else we just modify the field (no renaming with MODIFY keyword)
			}
		} else {
			$sql = "ALTER TABLE ".$this->moduletable." ADD ".$fieldname." ";
		}
		/*
		if (trim($size) == '') {
			$size = 0; // the default value for infinity is 0 (eg: text(0) equals unlimited text field)
		}*/
		if ($type == 'other' and !empty($customtype)) {
			$sql .= ' '.$customtype;
		} else {
			$sql .= ' '.$type;
		}
		if (!empty($size)) {
			$sql .= '('.$size.')'; // NOTE: $size can contain enum values too ! And some types (eg: text, boolean) do not need any size!
		} else {
			if ($type == 'varchar') $sql.= '(256)'; // One special case for the varchar : we define a specific default value of 256 chars (this is the only exception, non generic instruction in the  whole class! But it enhance a lot the ease of users who may forget to set a value)
		}
		if ($nulloption) {
			$sql .= ' null';
		} else {
			$sql .= ' not null';
		}
		if (!empty($defaultvalue)) {
			$defaultvalue = "'$defaultvalue'"; // we always quote the default value, for int the DBMS will automatically convert the string to an int value
			$sql .= ' default '.$defaultvalue;
		}
		if (!empty($customdef)) {
			$sql .= ' '.$customdef;
		}
		// Closing the SQL statement
		$sql .= ';';

		return $sql;
	}


	// ------------ Fields actions for management functions ---------------/

	/**
	 *      Initialize the customfields for this module (create the required table)
	 *
	 *
	 *	@return -1 if KO, 1 if OK
	 */
	function initCustomFields($notrigger = 0) {

		$reftable = MAIN_DB_PREFIX.$this->module; // the main module's table, we just add the dolibarr's prefix for db tables
		$prifield = $this->fetchPrimaryField($reftable); // we fetch the name of primary column of this module's table

		// Forging the SQL statement
		$sql = "CREATE TABLE ".$this->moduletable."(
		rowid                int(11) NOT NULL AUTO_INCREMENT,
		fk_".$this->module."       int(11) NOT NULL, -- id of the associated invoice/document
		PRIMARY KEY (rowid),
		KEY fk_".$this->module." (fk_".$this->module."),
		CONSTRAINT fk_".$this->module." FOREIGN KEY (fk_".$this->module.") REFERENCES ".$reftable." (".$prifield.") ON DELETE CASCADE ON UPDATE CASCADE
		) AUTO_INCREMENT=1 ;";

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_INITTABLE';
		}

		// Executing the SQL statement
		$rtncode = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		// Good or bad returncode ?
		if ($rtncode < 0) {
			return $rtncode; // bad
		} else {
			return 1; // good
		}
	}

	/**
	 *      Initialize the extraoptions table for CustomFields (for _ALL_ modules) - which is used to store extra options that cannot be stored inside a relational model database.
	 *
	 *
	 *	@return -1 if KO, 1 if OK
	 */
	function initExtraTable($notrigger = 0) {
		// Forging the SQL statement
		$sql = "CREATE TABLE ".$this->extratable."(
                table_name varchar(64),
		column_name varchar(64), -- we need to use the column_name because the ordinal_position is automatically rearranged for all columns when a field is deleted, and we can't know it (unless we put a trigger or a foreign keys, but the goal here is to not rely on referential integrity because we want to be able to simulate it), thus it's better to use column_name, but be careful with the size limit!
                extraoptions blob, -- better use a blob than a text, because: 1- text is deprecated in a lot of DBMS; 2- blob has no encoding, so that it won't interfer with the JSON encoding when using UTF-8 characters
                PRIMARY KEY (table_name, column_name)
		);";

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = 'EXTRATABLE_CUSTOMFIELD_INITTABLE';
		}

		// Executing the SQL statement
		$rtncode = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		// Good or bad returncode ?
		if ($rtncode < 0) {
			return $rtncode; // bad
		} else {
			return 1; // good
		}
	}

        /**
	 *      Check if the extra options (where all extra options that cannot be stored in a relational database) table exists
	 *
	 *	@return	< 0 if KO, false if does not exist, true if it does
	 *
	 */
	function probeTableExtra($notrigger = 0) {
            return $this->probeTable($this->extratable, $notrigger);
        }

	/**
	 *      Check if the table exists
	 *
	 *	@return	< 0 if KO, false if it doesn't exist, true if it does
	 *
	 */
	function probeTable($table=null, $notrigger = 0) {

            if (!isset($table)) $table = $this->moduletable;

            // Forging the SQL statement
            $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE='BASE TABLE'
            AND TABLE_SCHEMA='".$this->db->database_name."'
            AND TABLE_NAME='".$table."';";

            // Trigger or not?
            if ($notrigger) {
                    $trigger = null;
            } else {
                    $trigger = strtoupper($this->module).'_CUSTOMFIELD_PROBETABLE';
            }

            // Executing the SQL statement
            $resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

            // Forging the result
            if ($resql < 0) { // if an error happened when executing the sql command, we return -1
                    return $resql;
            } else { // else we check the result
                    if ($this->db->num_rows($resql) > 0) { // if there is a result, then we return true (the table exists)
                            return true;
                    } else { // else it doesn't
                            return false;
                    }
            }
	}

	/**
	*    Fetch the field sql definition for a particular field or for all fields from the database (not the records! See fetch and fetchAll to fetch records) and return it as an array or as a single object, and populate the CustomFields class $fields property
	*    @param    id          			id of the field (ordinal_position of the sql column) OR string column_name of the field
	*    @param    nohide				defines if the system fields (primary field and foreign key) must be hidden in the fetched results
	*    @return     int/null/obj/obj[]         <0 if KO, null if no field found, one field object if only one field could be found, an array of fields objects if OK
	*/
       function fetchFieldStruct($id=null, $nohide=false, $notrigger=0) {

		// Forging the SQL statement
		$whereaddendum = '';
                $whereextraaddendum = '';
		if (isset($id)) {
			if (is_numeric($id) and $id > 0) { // if we supplied an id, we fetch only this one record
                            $whereaddendum .= " AND c.ordinal_position = ".$id;
			} elseif (is_string($id) and !empty($id)) {
                            $whereaddendum .= " AND c.column_name = '".$id."'";
			}
		}

		if (!$nohide) {
			$whereaddendum .= " AND c.column_name != 'rowid' AND c.column_name != 'fk_".$this->module."'";
		}

		$sql = "SELECT c.ordinal_position,c.column_name,c.column_default,c.is_nullable,c.data_type,c.column_type,c.character_maximum_length,
		k.referenced_table_name, k.referenced_column_name, k.constraint_name,
                s.index_name,
                e.extraoptions
		FROM information_schema.COLUMNS as c
		LEFT JOIN information_schema.key_column_usage as k
		ON (k.column_name=c.column_name AND k.table_name=c.table_name AND k.table_schema=c.table_schema)
                LEFT JOIN information_schema.statistics as s
                ON (s.column_name=c.column_name AND s.table_name=c.table_name AND s.table_schema=c.table_schema)
                LEFT JOIN ".$this->extratable." as e
                ON (e.table_name=c.table_name AND e.column_name=c.column_name)
		WHERE c.table_schema = '".$this->db->database_name."' AND c.table_name = '".$this->moduletable."' ".$whereaddendum."
		ORDER BY c.ordinal_position;"; // We filter the reserved columns so that the user  cannot alter them, even by mistake and we get only the specified field by id

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHFIELD';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql, __FUNCTION__.'_CustomFields', $trigger);

		// Filling the field object
		if ($resql < 0) { // if there's no error
			return $resql; // we return the error code

		} else { // else we fill the field
			$num = $this->db->num_rows($resql); // number of lines returned as a result to our sql statement

                        if (!isset($this->fields)) $this->fields = new stdClass(); // initializing the cache object explicitly if empty (to avoid php > 5.3 warnings)

			// -- Several fields columns returned = array() of field objects
			if ($num > 1) {
				$field = array();
                                // Fetch every row from the request (there is no database access at this stage, we only get the result from our already processed query)
				for ($i=0;$i < $num;$i++) {
					$obj = $this->fetch_object($resql); // we retrieve the data line
					$obj->size = $this->getFieldSizeOrValue($obj->column_type); // add the real size of the field (character_maximum_length is not reliable for that goal)
					$obj->id = $obj->ordinal_position; // set the id (ordinal position in the database's table)
					$field[$obj->id] = $obj; // we store the field object in an array

                                        // unserialize extra options with json
                                        if ($obj->extraoptions) $obj->extra = json_decode($obj->extraoptions);

                                        // SQL compatibility mode: if the DBMS does not support foreign keys and referential integrity checks, we use the extra options to store and fetch the infos about the constrained field
                                        if (isset($obj->extra->referenced_table_name) and empty($obj->referenced_table_name)) $obj->referenced_table_name = $obj->extra->referenced_table_name;
                                        if (isset($obj->extra->referenced_column_name) and empty($obj->referenced_column_name)) $obj->referenced_column_name = $obj->extra->referenced_column_name;

                                        // we store the field object in an array
                                        $field[$obj->id] = $obj;
				}

                                // Sort fields by their position
                                usort($field, 'customfields_cmp_obj');

                                // Store in cache (in CustomFields object)
                                foreach ($field as $obj) {
                                    $column_name = $obj->column_name; // we get the column name of the field
                                    $this->fields->$column_name = $obj; // and we as well store the field as a property of the CustomFields class (caching for quicker access next time)
                                }

                                // Workaround: on some systems, num_rows will return 2 when in fact there's only 1 row. Here we fix that by counting the number of elements in the final array: if only one, then we return only first element of the array to be compliant with the paradigm: one record = one value returned
                                if (count($field) == 1) $field = reset($field);

			// -- Only one field returned = one field object
			} elseif ($num == 1) {
				$field = $this->fetch_object($resql);

				$field->size = $this->getFieldSizeOrValue($field->column_type); // add the real size of the field (character_maximum_length is not reliable for that goal)
				$field->id = $field->ordinal_position; // set the id (ordinal position in the database's table)

                                // unserialize extra options with json
                                if ($field->extraoptions) $field->extra = json_decode($field->extraoptions);

                                // SQL compatibility mode: if the DBMS does not support foreign keys and referential integrity checks, we use the extra options to store and fetch the infos about the constrained field
                                if (isset($field->extra->referenced_table_name) and empty($field->referenced_table_name)) $field->referenced_table_name = $field->extra->referenced_table_name;
                                if (isset($field->extra->referenced_column_name) and empty($field->referenced_column_name)) $field->referenced_column_name = $field->extra->referenced_column_name;

                                // Store in cache (in CustomFields object)
				$column_name = $field->column_name; // we get the column name of the field
				$this->fields->$column_name = $field; // and we as well store the field as a property of the CustomFields class

			// -- No field returned = null
			} else {
				$field = null;
			}

			$this->db->free($resql); // free last request (sparing a bit of memory)

			// Return the field
			return $field;
		}
       }

       /**
	*    Fetch ALL the fields sql definitions from the database (not the records! See fetch and fetchAll to fetch records)
	*    @param     nohide	defines if the system fields (primary field and foreign key) must be hidden in the fetched results
	*    @return     int/null/obj[]         <0 if KO, null if no field found, an array of fields objects if OK (even if only one field is found)
	*/
       function fetchAllFieldsStruct($nohide=false, $notrigger=0) {
		$fields = $this->fetchFieldStruct(null, $nohide, $notrigger);
		if ( !(is_array($fields) or is_null($fields) or is_integer($fields)) ) { $fields = array($fields); } // we convert to an array if we've got only one field, functions relying on this one expect to get an array if OK
		return $fields;
       }

	/**	Fetch constraints and foreign keys
	 *	@return <0 if KO, constraints[] array of constrained fields if OK
	 *	== UNUSED ==
	 *
	 */
       function fetchConstraints($notrigger = 0) {

		// Forging the SQL statement
		$sql = "SELECT
				CONCAT(table_name, '.', column_name) as 'foreign key',
				CONCAT(referenced_table_name, '.', referenced_column_name) as 'references',
				table_name, column_name, referenced_table_name, referenced_column_name
				FROM information_schema.key_column_usage
				WHERE referenced_table_name is not null
					AND table_schema = '".$this->db->database_name."'
					AND table_name = '".$this->moduletable."';";

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHCONSTRAINTS';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields', $trigger);

		// Filling in all the fetched fields into an array of fields objects
		if ($resql < 0) { // if there's an error in the SQL
			return $resql; // return the error code
		} else {
			$constraints = null;
			if ($this->db->num_rows($resql) > 0) {
				$num = $this->db->num_rows($resql);
				for ($i=0;$i < $num;$i++) {
					$obj = $this->fetch_object($resql); // we retrieve the data line
					$name = $obj->column_name;
					$constaints->$name = $obj; // we store the field object in an array
				}
			}
			$this->db->free($resql);

			return $constaints; // we return an array of constraints objects
		}
       }

       /**
	*    Load the sql informations about a field from the database
	*    @param	    table		table where to search in
	*    @param     name      column_name to search
	*    @return     obj         <-1 if KO, field if OK
	*/
       function fetchReferencedField($table='', $name='', $notrigger = 0) {

		if (!empty($table)) {
			$sqltable = $table;
		} else {
			$sqltable = $this->moduletable;
		}
		if (!empty($name)) {
			$sqlname = $name;
		} else {
			$sqlname = $this->fetchPrimaryField($sqltable); // if no referenced column name defined, we get the name of the primary field of the referenced table
		}
		// Forging the SQL statement
		$sql = "SELECT c.ordinal_position,c.column_name,c.column_default,c.is_nullable,c.data_type,c.column_type,c.character_maximum_length,
		k.referenced_table_name, k.referenced_column_name, k.constraint_name,
                s.index_name
		FROM information_schema.COLUMNS as c
		LEFT JOIN information_schema.key_column_usage as k
		ON (k.column_name=c.column_name AND k.table_name=c.table_name AND k.table_schema=c.table_schema)
                LEFT JOIN information_schema.statistics as s
                ON (s.column_name=c.column_name AND s.table_name=c.table_name AND s.table_schema=c.table_schema)
		WHERE c.table_schema ='".$this->db->database_name."' AND c.table_name = '".$sqltable."' AND c.column_name = '".$sqlname."'
		ORDER BY c.ordinal_position;";

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHREFFIELD';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		// Filling the field object
		if ($resql < 0) { // if there's no error
			return $resql; // we return the error code

		} else { // else we fill the field
			$obj = null;
			if ($this->db->num_rows($resql) > 0) {
			    $obj = $this->fetch_object($resql);

			    $obj->size = $this->getFieldSizeOrValue($obj->column_type); // add the real size of the field (character_maximum_length is not reliable for that goal)
			}
			$this->db->free($resql);

			// Return the field
			return $obj;
		}
       }

	/**
	 *	Check in the database if a field column name exists in a table
	 *	@param	$table	table name
	 *	@param	$name	column name
	 *	@return	false if KO, true if OK
	 */
	function checkIfIdenticalFieldExistsInRefTable($table, $name) {
		$fieldref = $this->fetchReferencedField($table, $name);
		if (isset($fieldref)) {
			if ( !($fieldref <= 0)) { // Special feature : if the customfield has a column name similar to one in the linked table, then we show the values of this field instead
				return true;
			 } else {
				return false;
			 }
		} else {
			return false;
		}
	}

       /**
	*    Load the records from a specified table and for the specified column name (plus another field with a column name identical to the $field->column_name)
	*    @param	   field		the constrained field (which contains a non-null referenced_column_name property)
	*    @return     obj[]         <-1 if KO, array of records if OK
	*/
       function fetchReferencedValuesList($field, $notrigger = 0) {

		// -- Forging the sql statement

		// First field : the referenced one
		$sqlfields = $field->referenced_column_name;
		$orderby = $field->referenced_column_name; // by default we order by this field (generally rowid)
		// Second field (if it exists) : one that has the same name as the customfield
		$realrefcolumn=explode('_', $field->column_name); // we take only the first part of the column name, before _ char (so you can name fkid_mylabel and it will look for the foreign fkid column)
		if ( $this->checkIfIdenticalFieldExistsInRefTable($field->referenced_table_name, $realrefcolumn[0]) ) { // Special feature : if the customfield has a column name similar to one in the linked table, then we show the values of this field instead
			$sqlfields.=','.$realrefcolumn[0];
			$orderby = $realrefcolumn[0]; // if a second field is found, we order by this one (eg: a list of name is better ordered alphabetically)
		}

		$sql = 'SELECT '.$sqlfields.' FROM '.$field->referenced_table_name.' ORDER BY '.$orderby.';';

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHREFVALUES';
		}

		// -- Executing the sql statement (fetching the referenced list)
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

		// -- Filling in all the fetched fields into an array of records objects
		if ($resql < 0) { // if there's an error in the SQL
			return $resql; // return the error code
		} else {
			$refarray = array();
			if ($this->db->num_rows($resql) > 0) {
				$num = $this->db->num_rows($resql);
				for ($i=0;$i < $num;$i++) {
					$obj = $this->fetch_object($resql); // we retrieve the data line
					$refarray[] = $obj; // we store the field object in an array
				}
			}
			$this->db->free($resql);

			return $refarray; // we return an array of records objects (for at least one field, maybe two because of the "special feature" - see above)
		}
	}

        /**
	*    Load a list of all the tables from dolibarr database
	*    @return     obj[]         <-1 if KO, array of tables if OK
	*/
       function fetchAllTables($notrigger = 0) {

		// Forging the SQL statement
		$sql = "SHOW TABLES;";

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHALLTABLES';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields', $trigger);

		// Filling in all the fetched fields into an array of fields objects
		if ($resql < 0) { // if there's an error in the SQL
			return $resql; // return the error code
		} else {
			$tables = array();
			if ($this->db->num_rows($resql) > 0) {
				$num = $this->db->num_rows($resql);
				for ($i=0;$i < $num;$i++) {
					$obj = $this->db->fetch_array($resql);
					$tables[$obj[0]] = $obj[0]; // we store the first row (the column that contains all the table names)
				}
			}
			$this->db->free($resql);

			return $tables; // we return an array of tables
		}
       }

        /**
	*    Find the column that is the primary key of a table
	*    @param      id          id object
	*    @return     int or string         <-1 if KO, name of primary column if OK
	*/
       function fetchPrimaryField($table, $notrigger = 0) {

		// Forging the SQL statement
		$sql = "SELECT column_name
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = '".$this->db->database_name."' AND TABLE_NAME = '".$table."' AND COLUMN_KEY = 'PRI';";

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHPRIMARYFIELD';
		}

		// Executing the SQL statement
		$resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields', $trigger);

		// Filling in all the fetched fields into an array of fields objects
		if ($resql < 0) { // if there's an error in the SQL
			return $resql; // return the error code
		} else {
			$tables = array();
			if ($this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_array($resql);
			}
			$this->db->free($resql);

			return $obj[0]; // we return the string value of the column name of the primary field
		}
       }

	/*	Delete a custom field (and the associated foreign key and index if necessary)
	*	@param 	id	id of the customfield (ordinal position in sql database)
	*
	*	@return	< 0 if KO, > 0 if OK
	*/
	function deleteCustomField($id, $notrigger = 0) {
		// Get the column_name
                if (empty($id)) {
                    $this->errors[] = 'Empty value';
                    $this->error .= 'Empty value';
                    return -1;
                } elseif (is_numeric($id)) { // if it's an id (ordinal_position), we must fetch the column_name from db
                    // Fetch the customfield object (so that we get all required informations to proceed to deletion : column_name, index and foreign key constraints if any)
                    $field = $this->fetchFieldStruct($id);
                    // Get the column name from the id
                    $fieldname = $field->column_name;
                } else { // else it's already a column_name
                    $fieldname = $id;
                }

		// Delete the associated constraint if exists
		$this->deleteConstraint($id);

		// Forging the SQL statement
		$sql = "ALTER TABLE ".$this->moduletable." DROP COLUMN ".$fieldname;

		// Trigger or not?
		if ($notrigger) {
			$trigger = null;
		} else {
			$trigger = strtoupper($this->module).'_CUSTOMFIELD_DELETEFIELD';
		}

		// Executing the SQL statement
		$rtncode = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

                // Delete the extra options record associated with this field
                $rtncode2 = 1;
                if ($rtncode >= 0) {
                    $sqle = "DELETE FROM ".$this->extratable." WHERE table_name='".$this->moduletable."' AND column_name='".$fieldname."';";
                    $rtncode2 = $this->executeSQL($sqle,__FUNCTION__.'_CustomFields',$trigger);
                }

		return min($rtncode, $rtncode2);
	}

	/**	Delete a constraint for a customfield
	 *	@param 	id	id of the customfield (ordinal position in sql database)
	 *
	 *	@return	-1 if KO, 1 if OK
	 */
	function deleteConstraint($id) {
		$rtncode1 = 1;
		$rtncode2 = 1;

		// Fetch customfield's informations
		$field = $this->fetchFieldStruct($id);

		// Delete the associated constraint if exists
		if (!empty($field->constraint_name)) {
			$sql = "ALTER TABLE ".$this->moduletable." DROP FOREIGN KEY ".$field->constraint_name;
			$rtncode1 = $this->executeSQL($sql,'deleteCustomFieldConstraint_CustomFields',null); // we need to execute this sql statement prior to any other one, because if we want to delete the column, we need first to delete the foreign key (this cannot be done with a single sql statement, you will get an error)
		}
		// Delete the associated index if exists
		if (!empty($field->index_name)) {
			$sql = "ALTER TABLE ".$this->moduletable." DROP INDEX ".$field->index_name;
			$rtncode2 = $this->executeSQL($sql,'deleteCustomFieldIndex_CustomFields',null); // same as above for the constraint
		}

		// Return code : -1 error or 1 OK
		if ($rtncode1 < 0 or $rtncode2 < 0) {
			return -1;
		} else {
			return 1;
		}
	}

	/**	Create a field (column in the customfields table) (will update the field if it does not exists)
	 *	@param	fieldname	name of the custom field (column name)
	 *	@param	type		sql type of the custom field (column type)
	 *	@param	size		bits size of the custom field type (data type)
	 *	@param	nulloption	accepts null values?
	 *	@param	defaultvalue	default value for this field? (null by default)
	 *	@param	constraint	name of the table linked by foreign key (the referenced_table_name)
	 *	@param	customtype	custom sql definition for the type (replaces type and size or just type parameter depending if the size is supplied in the def, ie: int(11) )
	 *	@param	customdef	custom sql definition that will be appended to the definition generated automatically (so you can add sql parameters the author didn't foreseen)
	 *	@param	customsql	custom sql statement that will be executed after the creation/update of the custom field (so that you can make complex statements)
	 *	@param	fieldid		id of the field to update (ordinal position). Leave this null to create the custom field, supply it if you want to update (or just use updateCustomField which is a simpler alias)
	 *	@param        extra               object which properties will be stored as extra options (can be anything). Extra options already stored in the database will be kept, unless a new value for a property is explicitly defined, in which case the option in the database is overwritten (you can set a property to null to erase it)
	 *	@param	notrigger	do not activate triggers?
	 *
	 *	@return -1 if KO, 1 if OK
	 */
	function addCustomField($fieldname, $type, $size, $nulloption, $defaultvalue = null, $constraint = null, $customtype = null, $customdef = null, $customsql = null, $fieldid = null, $extra = null, $notrigger = 0) {

            // Cleaning input vars
            $defaultvalue = $this->db->escape(trim($defaultvalue));
            //$size = $this->db->escape(trim($size)); // NOTE: $size can contain enum values too !
            //$customtype = $this->db->escape(trim($customtype));
            //$customdef = $this->db->escape(trim($customdef));
            //$customsql = $this->db->escape(trim($customsql));

            if (!empty($fieldid)) {
                $mode = "update";
            } else {
                $mode = "add";
            }

            // Delete the associated constraint if exists (the function will check if a constraint exists, if true then it will be deleted)
            if (!empty($fieldid)) {
                $this->deleteConstraint($fieldid);
            }

            // Automatically get the type of the field from constraint
            if (!empty($constraint)) {
                $prfieldname = $this->fetchPrimaryField($constraint);
                $prfield = $this->fetchReferencedField($constraint,$prfieldname);

                $type = $prfield->data_type;
                $nulloption = $prfield->is_nullable;
                $size = $prfield->size;
            }

            // Forging the SQL statement
            $sql = $this->forgeSQLCustomField($fieldname, $type, $size, $nulloption, $defaultvalue, $customtype, $customdef, $fieldid);

            // Trigger or not?
            if ($notrigger) {
                $trigger = null;
            } else {
                $trigger = strtoupper($this->module).'_CUSTOMFIELD_'.strtoupper($mode).'FIELD';
            }

            // Executing the SQL statement
            $rtncode1 = $this->executeSQL($sql, $mode.'CustomField_CustomFields',$trigger);

            // Executing the constraint linking if the field is a constrained field
            $rtncodec = 1;
            if (!empty($constraint)) {
                $sqlconstraint = 'ALTER TABLE '.$this->moduletable.' ADD CONSTRAINT fk_'.$fieldname.' FOREIGN KEY ('.$fieldname.') REFERENCES '.$constraint.'('.$prfield->column_name.');';
                $rtncodec = $this->executeSQL($sqlconstraint, $mode.'ConstraintCustomField_CustomFields',$trigger);

                // Mirroring constraint in the extra options (compatibility mode for MyIsam, without foreign keys constrained field will still work!)
                if (!isset($extra) or !is_object($extra)) $extra = new stdClass();
                $extra->referenced_table_name = $constraint;
                $extra->referenced_column_name = $prifield->column_name;
            } else {
                // If there is no constraint, we remove the constraints in extra options (the deletion of foreign keys are done above)
                if (!isset($extra) or !is_object($extra)) $extra = new stdClass();
                $extra->referenced_table_name = null; // we don't unset because we want the variable to stay, but null
                $extra->referenced_column_name = null;
            }

            // Executing the custom sql request if defined
            $rtncode2 = 1;
            if (!empty($customsql)) {
                $rtncode2 = $this->executeSQL($customsql, $mode.'CustomSQLCustomField_CustomFields',$trigger);
            }

            // Executing the update/creation (upsert) of the extra options in the extra table
            $rtncode3 = 1; // final return code for this part
            // Upsert extra options only if the custom field was successfully created/updated (else we shouldn't modify the extra table anyway, kind of a rollback)
            if ($rtncode1 >= 0) {
                if (!isset($extra) or !is_object($extra)) $extra = new stdClass(); // necessary to have a properly formatted $extra (an object) prior to call the setExtra() function
                if ($mode == 'update') {
                    // update mode, we have an id and we update the extra options (or even the column name) for this field
                    $rtncode3 = $this->setExtra($fieldid, $extra, $fieldname);
                } else {
                    // else we are in create mode: we just have a column_name and create an all-new entry in the extra options table
                    $rtncode3 = $this->setExtra($fieldname, $extra, $fieldname);
                }
            }

            // Return code : -1 error or 1 OK
            if (min($rtncode1, $rtncode2, $rtncodec, $rtncode3) < 0) { // If there was at least one error, we return -1
                return -1;
            } else { // Else everything's OK
                return 1;
            }
	}

	/*	Update a customfield's definition (will create the field if it does not exists)
	*	@param	fieldid	id of the field to edit (the ordinal position)
	*	@param	for the rest, see addCustomField
	*
	*	@return -1 if KO, 1 if OK
	*/
	function updateCustomField($fieldid, $fieldname, $type, $size, $nulloption, $defaultvalue, $constraint = null, $customtype = null, $customdef = null, $customsql = null, $extra = null, $notrigger = 0) {
	    return $this->addCustomField($fieldname, $type, $size, $nulloption, $defaultvalue, $constraint, $customtype, $customdef, $customsql, $fieldid, $extra, $notrigger);
	}

        /** Set the extra options of one custom field
         *  TODO: function to do it in batch for several custom fields at once
         *
         *  @param  int/string  $fieldid ordinal_position or column_name of the field to modify
         *  @param  object  $extra  object with properties that should be saved as extra options (just use a stdClass() object and append any property you want)
         *  @param  string  $newfieldname (optional)    internal variable used to update the field in addCustomField(). You should not be caring about this.
         *
         *  @return < 0 if KO, > 0 if OK
         *
         */
        function setExtra($fieldid, $extra, $newfieldname=null, $notrigger = 0) {
            // Quit if we don't have the required variables in the required format
            if (!isset($fieldid) or !is_object($extra)) {
                $this->addError('setExtra: fieldid or extra not in the appropriate format.');
                return -1;
            }

            // Fetch the customfield's structure
            $field = $this->fetchFieldStruct($fieldid);

            // Merge two objects: the already existent extra options for this field (from the db), plus the extra options given in parameters of this function
            if (isset($field->extra)) {
                $fullextra = (object) array_merge((array) $field->extra, (array) $extra); // note: user provided $extra options overrides the ones already stored for the field in case when there are identical keys in both
            } else {
                $fullextra = $extra;
            }
            // JSON encode + reversable escape for special characters (such as single quote, else it won't work with SQL!)
            $fullextraoptions = $this->escape(json_encode($fullextra));

            // Upsert (update + insert)
            // create mode: in case there is no previous field record, we just take the $newfieldname
            /*
            if (isset($field->column_name)) {
                $oldfieldname = $field->column_name;
            } else {
                $oldfieldname = $newfieldname;
            }
            */
            $oldfieldname = $field->column_name;
            // update mode with column_name changing: in case a $newfieldname is supplied, we will change the column_name (internal usage for addCustomField() function).
            if (!empty($newfieldname)) {
                $fieldname = $newfieldname;
            } else {
                $fieldname = $oldfieldname;
            }

            // Cross-DBMS implementation of Upsert, see for more infos http://en.wikipedia.org/wiki/Upsert
            $sqle1 = "UPDATE ".$this->extratable." SET column_name='".$fieldname."', extraoptions='".$fullextraoptions."' WHERE table_name='".$this->moduletable."' AND column_name='".$oldfieldname."';";
            /* DOESN'T WORK! this SHOULD work, but it doesn't because MySQL put a lock on the composite primary keys, and it then produces an error that shouldn't happen. There exist other solutions, but none of them are standard.
            $sqle2 = "INSERT INTO ".$this->extratable." (table_name, column_name, extraoptions)
                            SELECT '".$this->moduletable."', '".$fieldname."', '".$fullextraoptions."'
                            FROM ".$this->extratable."
                            WHERE NOT EXISTS (SELECT 1 FROM ".$this->extratable." WHERE table_name='".$this->moduletable."' AND column_name='".$fieldname."');"; // TODO: bug: the record will only be inserted (eg: in the case the field was created before the extraoptions table was created with an older release of this module) if there is at least ONE record in the extra table, else the SELECT returns nothing at all! Fix to make this sql works everytime? But how to do that with every possible DBMS without using DUAL (since it's not standard)?
            */
            $sqle2 = "INSERT INTO ".$this->extratable." (table_name, column_name, extraoptions)
                            VALUES ('".$this->moduletable."', '".$fieldname."', '".$fullextraoptions."')";

            // Trigger or not?
            if ($notrigger) {
                    $trigger = null;
            } else {
                    $trigger = strtoupper($this->module).'_'.$fieldid.'_CUSTOMFIELD_SETEXTRA';
            }

            // Execute the upsert of the extra options record
            // update first
            $rtncode = $this->executeSQL($sqle1, __FUNCTION__.'CustomFields', $trigger);

            // insert after (this will fail if the extra options record already exists anyway, because we have a composite primary key for table_name + column_name, so there can be no duplicates)
            $this->db->query($sqle2); // Note: this WILL produce an error in case the record already exists, but we don't care (because we have no workaround thank's to MySQL...)

            // Return the error code
            return $rtncode;
        }


	// ============ FIELDS PRINTING FUNCTIONS ===========/

	/**
	 *     Return HTML string to put an input field into a page
	 *     @param      field             Field object
	 *     @param      currentvalue           Current value of the parameter (will be filled in the value attribute of the HTML field)
	 *     @param      moreparam       To add more parametes on html input tag
	 *     @return       out			An html string ready to be printed
	 */
	function showInputField($field,$currentvalue=null,$moreparam='') {
		global $conf, $langs;

		$key=$field->column_name;
		$label=$langs->trans($key);
		$type=$field->data_type;
		if ($field->column_type == 'tinyint(1)') { $type = 'boolean'; }
		$size=$this->character_maximum_length;
		if (empty($currentvalue)) { $currentvalue = $field->column_default;}

		if ($type == 'date') {
		    $showsize=10;
		} elseif ($type == 'datetime') {
		    $showsize=19;
		} elseif ($type == 'int') {
		    $showsize=10;
		} else {
		    $showsize=round($size);
		    if ($showsize > 48) $showsize=48; // max show size limited to 48
		}

		$out = ''; // var containing the html output
		// Constrained field
		if (!empty($field->referenced_column_name)) {

			/*
			$tables = $this->fetchAllTables();
			$tables = array_merge(array('' => $langs->trans('None')), $tables); // Adding a none choice (to avoid choosing a constraint or just to delete one)
			$html=new Form($this->db);
			$out.=$html->selectarray($this->varprefix.$key,$tables,$field->referenced_table_name);
			*/

			// -- Fetch the records (list of values)
			$refarray = $this->fetchReferencedValuesList($field);

			// -- Print the list

			// Special feature : if the customfield has a column name similar to one in the linked table, then we show the values of this field instead
			$key1 = $field->referenced_column_name;
			if (count((array)$refarray[0]) > 1) {
				$realrefcolumn=explode('_', $field->column_name); // we take only the first part of the column name, before _ char (so you can name fkid_mylabel and it will look for the foreign fkid column)
				$key2 = $realrefcolumn[0];
			} else {
				$key2 = $field->referenced_column_name;
			}

			$out.='<select name="'.$this->varprefix.$key.'">';
			$out.='<option value=""></option>'; // Empty option
			foreach ($refarray as $ref) {
				if ($ref->$key1 == $currentvalue) {
					$selected = 'selected="selected"';
				} else {
					$selected = '';
				}
				$out.='<option value="'.$ref->$key1.'" '.$selected.'>'.$ref->$key2.'</option>';
			}
			$out.='</select>';

		// Normal non-constrained fields
		} else {
			if ($type == 'varchar') {
				$out.='<input type="text" name="'.$this->varprefix.$key.'" size="'.$showsize.'" maxlength="'.$size.'" value="'.$currentvalue.'"'.($moreparam?$moreparam:'').'>';
			} elseif ($type == 'text') {
				require_once(DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php");
                $randid = '_rand'.uniqid($key.rand(1,10000)); // Important to make sure that this field gets an unique ID, else the Javascript widget won't be able to locate the correct field if multiple fields have the same id (which is not correct anyway in X/HTML)
                $doleditor=new DolEditor($this->varprefix.$key.$randid,$currentvalue,'',200,'dolibarr_notes','In',false,false,$conf->fckeditor->enabled,5,100);
                $out.=$doleditor->Create(1);
                $out = str_replace('name="'.$this->varprefix.$key.$randid.'"', 'name="'.$this->varprefix.$key.'"', $out); // Finally, replace the name by removing the random id part (because we need the name to be exactly the same as the field's name so that we can detect it and save it in customfields_printforms.lib.php)
			} elseif ($type == 'date') {
				//$out.=' (YYYY-MM-DD)';
				$html=new Form($db);
                $randid = '_rand'.uniqid($key.rand(1,10000)); // Important to make sure that this field gets an unique ID, else the Javascript widget won't be able to locate the correct field if multiple fields have the same id (which is not correct anyway in X/HTML)
                $out.=$html->select_date($currentvalue,$this->varprefix.$key.$randid,0,0,1,$this->varprefix.$key.$randid,1,1,1);
                $out = str_replace('name="'.$this->varprefix.$key.$randid.'"', 'name="'.$this->varprefix.$key.'"', $out); // Finally, replace the name by removing the random id part (because we need the name to be exactly the same as the field's name so that we can detect it and save it in customfields_printforms.lib.php)
			} elseif ($type == 'datetime') {
				//$out.=' (YYYY-MM-DD HH:MM:SS)';
				if (empty($currentvalue)) { $currentvalue = 'YYYY-MM-DD HH:MM:SS'; }
				$out.='<input type="text" name="'.$this->varprefix.$key.'" size="'.$showsize.'" maxlength="'.$size.'" value="'.$currentvalue.'"'.($moreparam?$moreparam:'').'>';
			} elseif ($type == 'enum') {
				$out.='<select name="'.$this->varprefix.$key.'">';
				// cleaning out the enum values and exploding them into an array
				$values = trim($field->size);
				$values = str_replace("'", "", $values); // stripping single quotes
				$values = str_replace('"', "", $values); // stripping double quotes
				$values = explode(',', $values); // values of an enum are stored at the same place as the size of the other types. We explode them into an array (easier to walk and process)
				foreach ($values as $value) {
					if ($value == $currentvalue) {
						$selected = 'selected="selected"';
					} else {
						$selected = '';
					}
					$out.='<option value="'.$value.'" '.$selected.'>'.$langs->trans($value).'</option>';
				}
				$out.='</select>';
			} elseif ($type == 'boolean') {
				$out.='<select name="'.$this->varprefix.$key.'">';
				$out.='<option value="1" '.($currentvalue=='1'?'selected="selected"':'').'>'.$langs->trans("True").'</option>';
				$out.='<option value="false" '.($currentvalue=='false'?'selected="selected"':'').'>'.$langs->trans("False").'</option>';
				$out.='</select>';

			// Any other field
			} else { // for all other types (custom types and other undefined), we use a basic text input
				$out.='<input type="text" name="'.$this->varprefix.$key.'" size="'.$showsize.'" maxlength="'.$size.'" value="'.$currentvalue.'"'.($moreparam?$moreparam:'').'>';
			}
		}

	    return $out;
	}

	/**
	 *	Draw an input form (same as showInputField but produce a full form with an edit button and an action)
	 *	@param	$field	field object
	 *	@param	$currentvalue	current value of the field (will be set in the value attribute of the HTML input field)
	 *	@param	$page	URL of the page that will process the action (by default, the same page)
	 *	@param	$moreparam	More parameters
	 *	@return	$out			An html form ready to be printed
	 */
	function showInputForm($field, $currentvalue=null, $page=null, $moreparam='') {
		global $langs;

		$out = '';

		if (empty($page)) { $page = $_SERVER["PHP_SELF"]; }
		$name = $this->varprefix.$field->column_name;
		$out.='<form method="post" action="'.$page.'" name="form_'.$name.'">';
		$out.='<input type="hidden" name="action" value="set_'.$name.'">';
		$out.='<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		$out.='<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
		$out.='<tr><td>';
		$out.=$this->showInputField($field, $currentvalue, $moreparam);
		$out.='</td>';
		$out.='<td align="left"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
		$out.='</tr></table></form>';

		return $out;
	}

	/**
	 *     Return HTML string to print a record's data
	 *     @param	field	field object
	 *     @param      	value           Value to show
	 *     @param	outputlangs		the language to use to find the right translation
	 *     @param      	moreparam       To add more parametes on html input tags
	 *     @return	html				An html string ready to be printed (without input fields, just html text)
	 */
	function printField($field, $value, $outputlangs='', $moreparam='') {
		if ($outputlangs == '') {
			global $langs;
			$outputlangs = $langs;
		}

		$out = '';
		if (isset($value)) {
			// Constrained field
			if (!empty($field->referenced_column_name) and !empty($value)) {
				// Special feature : if the customfield has a column name similar to one in the linked table, then we show the values of this field instead
				$realrefcolumn=explode('_', $field->column_name); // we take only the first part of the column name, before _ char (so you can name fkid_mylabel and it will look for the foreign fkid column)
				if ( $this->checkIfIdenticalFieldExistsInRefTable($field->referenced_table_name, $realrefcolumn[0]) ) {
					// Constructing the sql statement
					$column = $realrefcolumn[0];
					$table = $field->referenced_table_name;
					$where = $field->referenced_column_name.'='.$value;

					// Fetching the record
					$record = $this->fetchAny($column, $table, $where);

					// Outputting the value
					$out.= $record->$column;
				// Else we just print out the value of the field
				} else {
					$out.=$value;
				}
			// Normal non-constrained field
			} else {
				// type enum (select box or yes/no box)
				if ($field->data_type == 'enum') {
					$out.=$outputlangs->trans($value);
				// type true/false
				} elseif ($field->column_type == 'tinyint(1)') {
					if ($value == '1') {
						$out.=$outputlangs->trans('True');
					} else {
						$out.=$outputlangs->trans('False');
					}
				// every other type
				} else {
					$out.=$value;
				}
			}
		}
		return $out;
	}

	/**
	 *     Return a non-HTML, simple text string ready to be printed into a PDF with the FPDF class or in ODT documents
	 *     @param	field	field object
	 *     @param      	value           Value to show
	 *	@param	outputlangs	for multilingual support
	 *     @param     	moreparam       To add more parameters on html input tags
	 *     @return	string				A text string ready to be printed (without input fields and without html entities, just simple text)
	 */
	function printFieldPDF($field, $value, $outputlangs='', $moreparam='') {
		$value=$this->printField($field, $value, $outputlangs, $moreparam);

		// Cleaning the html characters if the field contained some
		$value = preg_replace('/<br\s*\/?>/i', "", $value); // replace <br> into line breaks \n - fckeditor already outputs line returns, so we just remove the <br>
		$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8'); // replace all html characters into text ones (accents, quotes, etc.) and directly into UTF8

		return $value;
	}

	/**
	 *	Simplify the printing of the value of a field by accepting a string field name instead of an object
	 *	@param	fieldname	string field name of the field to print
	 *	@param	value		value to show (current value of the field)
	 *	@param	outputlangs	for multilingual support
	 *	@param	moreparam	to add more parameters on html input tags
	 *	@return	html		An html string ready to be printed
	 */
	function simpleprintField($fieldname, $value, $outputlangs='', $moreparam='') {
		if (!is_string($fieldname)) {
			return -1;
		} else {
                        $fieldname = $this->stripPrefix($fieldname); // strip the prefix if detected at the beginning

			if (!isset($this->fields->$fieldname)) {
				$field = $this->fetchFieldStruct($fieldname, true);
			} else {
				$field = $this->fields->$fieldname;
			}
			return $this->printField($field, $value, $outputlangs, $moreparam);
		}
	}

	/**
	 *	Same as simpleprintField but for PDF (without html entities)
	 *	@param	fieldname	string field name of the field to print
	 *	@param	value		value to show (current value of the field)
	 *	@param	outputlangs	for multilingual support
	 *	@param	moreparam	to add more parameters on html input tags
	 *     @return	string				A text string ready to be printed (without input fields and without html entities, just simple text)
	 */
	function simpleprintFieldPDF($fieldname, $value, $outputlangs='', $moreparam='') {
		if (!is_string($fieldname)) {
			return -1;
		} else {
                        $fieldname = $this->stripPrefix($fieldname); // strip the prefix if detected at the beginning

			if (!isset($this->fields->$fieldname)) {
				$field = $this->fetchFieldStruct($fieldname, true);
			} else {
				$field = $this->fields->$fieldname;
			}
			return $this->printFieldPDF($field, $value, $outputlangs, $moreparam);
		}
	}

	/**
	 *	Take a field name and returns the right label for the field, either with the prefix or without. If none is found, we return the normal field name.
	 *	@param	fieldname	 a field name
	 *	@param	outputlangs	the language to use to show the right translation of the label
	 *	@return	string		a label for the field
	 *
	 */
	function findLabel($fieldname, $outputlangs = '') {
		if ($outputlangs == '') {
			global $langs;
			$outputlangs = $langs;
		}

                $fieldname = $this->stripPrefix($fieldname); // strip the prefix if detected at the beginning

		if ($outputlangs->trans($this->varprefix.$fieldname) != $this->varprefix.$fieldname) { // if we find a label for a code in the format : cf_something
		    return $outputlangs->trans($this->varprefix.$fieldname);
		} elseif ($outputlangs->trans($fieldname) != $fieldname) { // if we find a label for a code in the format : something
		    return $outputlangs->trans($fieldname);
		} else { // if no label could be found, we return the field name
		    return $fieldname;
		}
	}

	function findLabelPDF($fieldname, $outputlangs = '') {
		$fieldname = $this->findLabel($fieldname, $outputlangs); // or use transnoentities()?

		// Cleaning the html characters if the field contained some
		$fieldname = preg_replace('/<br\s*\/?>/i', "", $fieldname); // replace <br> into line breaks \n - fckeditor already outputs line returns, so we just remove the <br>
		$fieldname = html_entity_decode($fieldname, ENT_QUOTES, 'UTF-8'); // replace all html characters into text ones (accents, quotes, etc.) and directly into UTF8

		return $fieldname;
	}

        // Function to strip CustomField's prefix (varprefix and fields_prefix).
        // It is mainly used as a way to easily detect both 'cf_myfield' and 'myfield' and translate them the same way.
        function stripPrefix($fieldname) {
            preg_match('/^'.addslashes($this->varprefix).'/', $fieldname, $matchs); // detect with regex if the prefix is prepended at the beginning of the field's name
            if (count($matchs) > 0) $fieldname = substr($fieldname, strlen($this->varprefix)); // strip the prefix if prefix detected

            return $fieldname;
        }

        /** Add an error in the array + automatically format them in a single nice imploded string
         *
         * @param   string/array  $errormsg   error message to add (can be an array or a single string)
         *
         * @return  true
         *
         */
        function addError($errormsg) {
            // Stack error message(s) in the local array
            if (is_array($errormsg)) {
                array_push($this->errors, $errormsg);
            } else {
                $this->errors[] = $errormsg;
            }

            // Refresh the concatenated string of all errors
            $this->error = implode(";\n", $this->errors);

            return true;
        }

        /** Easy function to print the errors encountered by CustomFields (if any)
         *
         *  @param  string  $error  string error message to print, null = customfield's errors will be printed
         *
         *  @return     bool        true if an error was printed, false if nothing was printed
         */
        function printErrors($error=null) {
            // either take an input error message, or use customfield's saved errors
            if (!empty($error)) {
                $mesg = $error;
            } else {
                //$this->error = implode(";\n", $this->errors);
                $mesg = $this->error;
            }

            // If there is/are errors
            if (!empty($mesg)) {
                // Print error messages
                if (function_exists('setEventMessage')) {
                    setEventMessage($mesg, 'errors'); // New way since Dolibarr v3.3
                } elseif (function_exists('dol_htmloutput_errors')) {
                    dol_htmloutput_errors($mesg); // Old way to print error messages
                } else {
                    print('<pre>');
                    print($mesg); // if no other error printing function was found, we just print out the errors with a basic html formatting
                    print('</pre>');
                }

                return true;
            } else {
                return false;
            }
        }

}
?>
