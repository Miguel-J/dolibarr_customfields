<?php
/* Copyright (C) 2011-2015   Stephen Larroque <lrq3000@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * at your option any later version.
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
 *      \author     Stephen Larroque
 */

/* DEV NOTES FOR PROFILING FUNCTIONS
  To profile functions calls and classes instanciations easily, you can use the following code:

  $then = microtime(true); // do not forget the parameter set to true! (else you will get inconsistent and even negative times)
  func(); // replace this by the function you want to profile
  $now = microtime(true);
  echo sprintf("Elapsed:  %f", $now-$then);

  Also you can use these two commands to profile memory usage:
  getmemoryusage()
  getpeakmemory_usage()

  One last advice: most of the time spent by functions are generally in SQL querying the database, so the better place to start optimizing would be by optimizing SQL queries first, then the number of calls to functions that do SQL queries (caching etc...), but the author tried his best to really optimize all these aspects (but one can always do better ;) ).
*/

// Include JSON library for PHP <= 5.2 (used to define json_decode() and json_encode() for PHP4)
if( !function_exists('json_decode') or !function_exists('json_encode') ) include_once(dirname(__FILE__).'/ext/JSON.php');
// Include PHP4 object model compatibility library (TODO: may not work! Please check before, eg: will probably need to declare every var that is used, because this is currently not the case (but how to declare all SQL fields???))
include_once(dirname(__FILE__).'/ext/php4compat.php');

// include main Dolibarr file in case it's not already done by caller script
$res=0;
if (! $res && file_exists(dirname(__FILE__)."/../main.inc.php")) $res=@include_once(dirname(__FILE__)."/../main.inc.php");			// for root directory
if (! $res && file_exists(dirname(__FILE__)."/../../main.inc.php")) $res=@include_once(dirname(__FILE__)."/../../main.inc.php");		// for level1 directory ("custom" directory)
if (! $res && file_exists(dirname(__FILE__)."/../../../main.inc.php")) $res=@include_once(dirname(__FILE__)."/../../../main.inc.php");	// for level2 directory
if (! $res) die("Include of main fails");

// include config aux processing functions (and PHP4 compatibility functions like array_replace_recursive)
include_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php');

// Loading the translation class if it's not yet loaded (or with another name) - DO NOT EDIT!
if (!isset($langs) or !is_object($langs))
{
    include_once(DOL_DOCUMENT_ROOT."/core/class/translate.class.php");
    $langs=new Translate(dirname(__FILE__).'/../langs/',$conf);
}

$langs->load('customfields@customfields'); // customfields standard language support
$langs->load('customfields-user@customfields'); // customfields language support for user's values (like enum, fields names, etc..)


// Auxiliary function to sort custom fields by either their extra option position, either by their column's ordinal_position
function customfields_cmp_obj($a, $b)
{
    if (isset($a->extra['position'])) {
        $apos = $a->extra['position'];
    } elseif (isset($a->ordinal_position)) {
        $apos = $a->ordinal_position;
    } else {
        return 0;
    }
    if (isset($b->extra['position'])) {
        $bpos = $b->extra['position'];
    } elseif (isset($b->ordinal_position)) {
        $bpos = $b->ordinal_position;
    } else {
        return 0;
    }
    // In case we have a draw (same position)
    if ($apos == $bpos) {
        // Give the advantage to extra options before ordinal_position
        if (isset($a->extra['position']) and !isset($b->extra['position'])) {
            return +1;
        } elseif (!isset($a->extra['position']) and isset($b->extra['position'])) {
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
    var $db;                            //!< To store db handler
    var $error;                         //!< To return error code (or message)
    var $errors=array();                //!< To return several error codes (or messages)
    //var $element='customfields';          //!< Id that identify managed objects
    //var $table_element='customfields';    //!< Name of table without prefix where object is stored
    var $dbtype; // type of the database, will be used to use the right function to issue sql requests

    var $varprefix = 'cf_'; // prefix that will be prepended to the variables names for accessing the fields values
    var $svsdelimiter = '_'; // separator for Smart Value Substitution for Constrained Fields (a constrained field will try to find similar column names in the referenced table, and you can specify several column names when using this separator)

    var $debug = false; // print useful debug statements?

    var $id;


    /**
     *      Constructor
     *      @param      DB      Database handler
     *      @param      currentmodule           Current module (facture/propal/etc.)
     */
    function __construct($db, $currentmodule)
    {
        // Include the config file (only necessary to get a few config variables at this moment, so this class is pretty much self contained and independent - except for triggers and translation, but these are NOT necessary for CustomFields management, only for printing fields more nicely and for logs)
        include(dirname(__FILE__).'/../conf/conf_customfields.lib.php');

        $this->db = $db;
        $this->module = $currentmodule;
        $this->moduletable = MAIN_DB_PREFIX.$this->module."_customfields";
        $this->extratable = MAIN_DB_PREFIX."customfields_extraoptions";
        $this->dbtype = $db->type; // or $conf->db->type

        if (!empty($fieldsprefix)) $this->varprefix = $fieldsprefix;
        if (!empty($svsdelimiter)) $this->svsdelimiter = $svsdelimiter;

        if (!empty($constraint_links)) $this->constraint_links = $constraint_links;

        if (!empty($cfdebug)) $this->debug = true;

        return 1;
    }


    // ============ FIELDS RECORDS MANAGEMENT ===========/

    //--------------- Lib Functions --------------------------

    /**
     *  Similar to mysql_real_escape() but can be reversed and there's no need to be connected to the db
     */
    function escape($str)
    {
        $search=array("\\","\0","\n","\r","\x1a","'",'"');
        $replace=array("\\\\","\\0","\\n","\\r","\Z","\'",'\"');
        return str_replace($search,$replace,$str);
    }

    /**
     *  Reverse msql_real_escape() or the function above
     *  UNUSED
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
     *      @param     id                  id of the record to find, ie the id of the Dolibarr's object (NOT customfields rowid but customfields fk_moduleid, which is the same as the module object's rowid) OR an array of id OR can be left empty if you want to fetch all the records
     *      @param     table             null/string - null to fetch the customfields structure for the current module, or can be set to any table if you want to get the structure of another table's fields
     *      @param     notrigger       0=launch triggers after, 1=disable triggers
     *      @return     int/null/obj/obj[]          <0 if KO, null if no record is found, if OK: a record if only one is found, or an array of records otherwise (special case: if $id is an array of ids, then even if only one record is found, an array of records will always be returned)
     */
    function fetch($id=null, $table=null, $notrigger=0)
    {
        /* DEPRECATED: not needed anymore, Dolibarr now accepts SELECT * (before the star wasn't tolerated)
          // Get all the columns (custom fields), primary field included (that's why there's the true)
          //$fields = $this->fetchAllFieldsStruct(true);

          // Forging the SQL statement - we set all the column_name to fetch (because Dolibarr wants to avoid SELECT *, so we must name the columns we fetch)
          foreach ($fields as $field) {
              $keys[] = $field->column_name;
          }
          $sqlfields = implode(',',$keys);
          */

        if (empty($table)) $table = $this->moduletable;
        $sql = "SELECT * FROM ".$table;

        //if (is_array($id) and count($id) == 1) $id = reset($id); // If there is only one id in the array, we extract the first value from the array

        if (is_array($id)) {
            $id = filter_var_array($id, FILTER_SANITIZE_NUMBER_INT); // sanitize by type checking (prevent XSS injection and most other stuffs)
            $id = array_map(array($this->db, 'escape'), $id); // sanitize for SQL injection
            $sql .= " WHERE fk_".$this->module."=".implode(' or fk_'.$this->module.'=', $id);
        } elseif ($id > 0) { // if we supplied an id, we fetch only this one record
            $id = $this->db->escape(filter_var($id, FILTER_SANITIZE_NUMBER_INT)); // sanitize...
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
        if (is_int($resql) and $resql < 0) { // if there's an error
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

    /** Fetch all the records from the database for the current module
     *  there's no argument (except $table), because it's just an alias for fetch() without giving an $id
     *  @param     table             null/string - null to fetch the customfields structure for the current module, or can be set to any table if you want to get the structure of another table's fields
     *  @return int/null/obj[]      <0 if KO, null if no record found, an array of records if OK (even if only one record is found)
     */
    function fetchAll($table=null, $notrigger=0) {
        $records = $this->fetch(null, $table, $notrigger);
        if ( !(is_array($records) or is_null($records) or is_integer($records)) ) { $records = array($records); } // we convert to an array if we've got only one field, and if it's not an error or null, functions relying on this one expect to get an array if OK
        return $records;
    }

    /**
     *      Fetch any record in the database from any table (not just customfields)
     *      @param  columns     one or several columns (separated by commas) to fetch
     *      @param  table       table where to fetch from
     *      @param  where       where clause (format: column='value'). Can put several where clause separated by AND or OR
     *      @param  orderby order by clause
     *      @return     int or array of objects             <0 if KO, array of objects if one or several records found (for more consistency, even if only one record is found, an array is returned)
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
        if (is_int($resql) and $resql < 0) { // if there's no error
            return $resql; // we return the error code
        } else { // else we fill the record
            $num = $this->db->num_rows($resql); // number of results returned (number of records)
            // Several records returned = array() of objects
            if ($num >= 1) {
                $record = array();
                for ($i=0;$i < $num;$i++) {
                    $record[] = $this->fetch_object($resql);
                }
            // Only one record returned = one object
            /*
                } elseif ($num == 1) {
                    $record = $this->fetch_object($resql);
                */
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
     *      @param     object               Object containing all the form inputs to be processed to the database (so it must contain the custom fields)
     *      @param      notrigger       0=launch triggers after, 1=disable triggers
     *      @return     int             <0 if KO, >0 if OK
     */
    function create($object, $notrigger=0)
    {
        // Get all the columns (custom fields)
        $fields = $this->fetchAllFieldsStruct();

        if (empty($fields)) return null; // if the customfields table for this module exists but there's no field at all, we just quit

        // Forging the SQL statement
        $sqlfields = array();
        $sqlvalues = array();
        foreach ($fields as $field) {
            // Get the right key to access the field in the $object
            $key = $this->varprefix.$field->column_name; // either it's in the format 'cf_myfield' (with prefix)
            if (!isset($object->$key)) { // either it's in the format 'myfield' (without prefix)
                $key = $field->column_name;
            }

            if (isset($object->$key) and // Only insert/update this field if it was submitted...
                !(strlen($object->$key) == 0 and strtolower($field->is_nullable) != 'yes')) { // ... and if the value is null (totally empty), we insert/update the field only if the field accepts null values (this is a special feature of CustomFields, because SQL accepts null values even for non-nullable sql fields by setting an empty value - either '' or 0 - here CustomFields will keep the old value)
                // Note: we separate fields and values because depending on whether we UPDATE or INSERT the record, the format is not the same (INSERT: values and fields are separated, UPDATE: both are submitted at the same place)

                // Insert field's column_name
                $sqlfields[] = $field->column_name;

                // Insert field's value
                if (strlen($object->$key) == 0) {
                    $sqlvalues[] = 'NULL'; // special case: if the value supplied is an empty string (even 0 returns 1 length), then we put this as NULL without quotes (else, it will supply a field with '' which can be rejected even on nullable sql fields)
               } else {
                    // For numbers types, also accept commas just like dots to separate the decimals
                    if ($field->data_type == 'float' or $field->data_type == 'double' or $field->data_type == 'decimal' or $field->data_type == 'numeric') {
                        $object->$key = str_replace(',', '.', $object->$key);
                    // We need to fetch the correct value when we update a date field
                    } elseif ($field->data_type == 'date') {
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

                   } elseif ($field->data_type == 'datetime') {
                        // Fetch day, month and year
                        if (isset($object->{$key.'min'}) and isset($object->{$key.'hour'}) and isset($object->{$key.'day'}) and isset($object->{$key.'month'}) and isset($object->{$key.'year'})) { // for date fields, Dolibarr will produce 3 more associated POST fields: myfielddate, myfieldmonth and myfieldyear
                            $datemin = trim($object->{$key.'min'});
                            $datehour = trim($object->{$key.'hour'});
                            $dateday = trim($object->{$key.'day'});
                            $datemonth = trim($object->{$key.'month'});
                            $dateyear = trim($object->{$key.'year'});
                        } else { // else if they are not submitted (or if they weren't assigned inside $object), we try to split the date into 3 values. But the hour/minute isn't stored in the same field anyway...
                            list($dateday, $datemonth, $dateyear) = explode('/',$object->$key);
                            $datemin = 0;
                            $datehour = 0;
                        }
                        // Format the correct timestamp from the date for the database
                        $object->$key = $this->db->idate(dol_mktime($datehour, $datemin, 0, $datemonth, $dateday, $dateyear));
                    }

                    $sqlvalues[] = "'".$this->escape($object->$key)."'"; // escape and single-quote values (even if they are not strings, the database will automatically correct that depending on the column_type)
                }
            }
        }

        // we add the object id (filtered by fetchAllFieldsStruct)
        if (!empty($object->rowid)) {
            $objid = $object->rowid;
        } else {
            $objid = $object->id;
        }
        $sqlfields[] = "fk_".$this->module;
        $sqlvalues[] = $objid;

        // fetch the record (to check whether it already exists or not)
        $result = $this->fetch($objid);

        if (!empty($result) and count($result) > 0) { // if the record already exists for this facture id, we update it
            // Compact and format all the fields and values in the correct sql syntax (eg: field='value')
            $sqlfieldsandvalues = array();
            for($i=0;$i<count($sqlfields);$i++) {
                $sqlfieldsandvalues[] = $sqlfields[$i].'='.$sqlvalues[$i];
            }
            $sql = "UPDATE ".$this->moduletable." SET ".implode(',', $sqlfieldsandvalues)." WHERE fk_".$this->module."=".$objid;
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
     *      @param     object               Object containing all the form inputs to be processed to the database (so it mus contain the custom fields)
     *      @param      notrigger       0=launch triggers after, 1=disable triggers
     *      @return     int             <0 if KO, >0 if OK
     */
    function update($object, $notrigger=0)
    {
        return $this->create($object,$notrigger);
    }

    /**
     *      Delete a record in the database (meaning an instance of the custom fields, the values if you prefer)
     *      @param     id               id of the record to find (NOT rowid but fk_moduleid)
     *      @param      notrigger       0=launch triggers after, 1=disable triggers
     *      @return     int             <0 if KO, >0 if OK
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
     *      @param     id               ID of the object to clone
     *      @param  cloneid         ID of the new cloned object
     *      @param      notrigger       0=launch triggers after, 1=disable triggers
     *      @return     int             <0 if KO, >0 if OK
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
    *   Extract the size or value (if type is enum) from the column_type of the database field
    *  @param $column_type
    *  @return $size_or_value
    */
   function getFieldSizeOrValue($column_type) {
        preg_match('/[(]([^)]+)[)]/', $column_type, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        } else {
            return 0;
        }
   }

    /*  Execute a unique SQL statement, add it to the logfile and add an event trigger (or not)
         *  Note: just like mysql_query(), we can only issue one sql statement per call. It should be possible to issue multiple queries at once with an explode(';', $sqlqueries) but it would imply security issues with the semicolon, and would require a specific escape function.
         *  Note2: another way to issue multiple sql statement is to pass flag 65536 as mysql_connect's 5 parameter (client_flags), but it still raises the same security concerns.
     *
     *
     *  @return -1 if error, object of the request if OK
     */
    function executeSQL($sql, $eventname, $trigger=null) { // if $trigger is null, no trigger will be produced, else it will produce a trigger with the provided name
        $error = 0;

        // Executing the SQL statement
        dol_syslog(get_class($this)."::".$eventname." sql=".$sql, LOG_DEBUG); // Adding an event to the log
        $resql=$this->db->query($sql); // Issuing the sql statement to the db

        // Checking for errors and add error message
        if (! $resql) {
            $error++;
            $this->addError($this->db->lasterror());
            if ($this->debug) $this->addError('SQL request: '.$sql);
        }

        // Managing trigger (if there's no error)
        if (! $error) {
            //$id = $this->db->last_insert_id($this->moduletable);

            if (!empty($trigger)) {
                global $user, $langs, $conf; // required vars for the trigger
                //// Call triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                //if (version_compare(DOL_VERSION, '3.7.0', '>=')) {
                    //$result=$this->call_trigger($trigger,$user); // new way to call triggers starting from Dolibarr v3.7.0
                //} else {
                $result=$interface->run_triggers($trigger,$this,$user,$langs,$conf); // old way to call triggers for Dolibarr < v3.7.0
                //}
                if ($result < 0) { $error++; $this->addError('Error returned by a trigger after executeSQL():'); $this->addError($interface->errors); }
                //// End call triggers
            }
        }

        // Commit or rollback
        if ($error)  {
            foreach($this->errors as $errmsg) {
                dol_syslog(get_class($this)."::".$eventname." ".$errmsg, LOG_ERR);
                //$this->error.=($this->error?', '.$errmsg:$errmsg);
            }
            $this->printErrors();
            $this->db->rollback();
            return -1*$error; // error code : we return -1 multiplied by the number of errors (so if we have 5 errors we will get -5 as a return code)
        } else {
            $this->db->commit();
            return $resql;
        }
    }

    /* Execute multiple SQL queries (no security check!)
    * idea from: http://www.dev-explorer.com/articles/multiple-mysql-queries
    * WARNING: use with caution
    *
    * *** UNUSED ***
    */
    function executeMultiSQL($sql, $eventname, $trigger=null) {
        $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $sql);
        $rtncodes = array();
        foreach ($queries as $query){
            if (strlen(trim($query)) > 0) $rtncodes[] = $this->executeSQL($query, $eventname, $trigger);
        }
        return min($rtncodes); // return the worst error code
    }

    /*  Forge the sql command for addCustomFields and updateCustomFields (creation and update of a field's definition)
    *
    *
    *   @return string $sql which contain the forged sql statement
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
     *  @return -1 if KO, 1 if OK
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
     *  @return -1 if KO, 1 if OK
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
     *      Check if the table exists
     *      IMPORTANT: this check IS NEVER done automatically, thus it's your responsibility to probeTable() before trying to fetch() or fetchFieldStruct() or fetchAllFieldsStruct(), else you will encounter an error! This is done on purpose to avoid issuing the query when it's not required.
     *
     *  @return < 0 if KO, false if it doesn't exist, true if it does
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
        if (is_int($resql) and $resql < 0) { // if an error happened when executing the sql command, we return -1
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
     *      Check if the extra options (where all extra options that cannot be stored in a relational database) table exists
     *
     *  @return < 0 if KO, false if does not exist, true if it does
     *
     */
    function probeTableExtra($notrigger = 0) {
        return $this->probeTable($this->extratable, $notrigger);
    }

    /**
    *    Fetch the field sql definition for a particular field or for all fields from the database (not the records! See fetch and fetchAll to fetch records) and return it as an array or as a single object, and populate the CustomFields class $fields property (or any other path specified in $cachepath)
    *    Also update a local cache of fields structure to accelerate further accesses.
    *    Note: fields structure are by default cached, and you can potentially cache any table's fields structure. Anyway, this function won't use the cache (only update it) because this function can also be called when the database structure has changed and we want to fetch the new structure. BUT it still caches the retrieved fields structure, so it can then be called elsewhere in the code (eg: simplePrintField() and checkIfFieldExists() )
    *    @param    $id                      int/string - id of the field (ordinal_position of the sql column) OR string column_name of the field
    *    @param    $nohide          false/true - defines if the two system fields (primary field and foreign key) must be hidden in the fetched results - only works for CustomFields's tables (since it will look for rowid and fk_something, it's NOT dynamic!)
    *    @param    $table                           null/string - null to fetch the customfields structure for the current module, or can be set to any table if you want to get the structure of another table's fields
    *    @param    $cachepath                null/false/string - enable caching of the fields structure inside this instance of CustomFields object. false: disable caching; null (default): default path ($this->fields->custom_field_column_name); string: a path can be specified to replace the default cache path (mainly used when you fetch the structure of another table than CustomFields and to avoid overwriting CustomFields structures cache) (eg: $this->$table_name->anything, and your field's structure will be accessible with $this->$table_name->anything->custom_field_column_name)
    *    @param    $notrigger                   false/true - enable (default) or disable the activation of a trigger when this function is called
    *    @return     int/null/obj/obj[]         <0 if KO, null if no field found, one field object if only one field could be found, an array of fields objects if OK
    */
   function fetchFieldStruct($id=null, $nohide=false, $table=null, $cachepath=null, $notrigger=0) {

        // Forging the SQL statement
        $whereaddendum = '';
        $whereaddendumid = '';
        $whereextra = '';
        $whereextraaddendum = '';
        // If an ID is set, we will fetch only this column, else if empty we will fetch all columns from the table
        if (isset($id)) {
            // If the ID is numeric, we search the field with the ordinal position (id of the column in the SQL meta database) - !is_string is not sufficient here, we must check with is_numeric AND if it's above 0
            if (is_numeric($id) and $id > 0) { // if we supplied an id, we fetch only this one record
                $id = $this->db->escape(filter_var($id,FILTER_SANITIZE_NUMBER_INT)); // sanitize by type checking + sql escaping
                $whereaddendumid .= " AND ordinal_position = ".$id; // ordinal_position does NOT exist in statistics table, so we must avoid putting this in the where clause for that join (see below in the $sql request)
            // Else if it's a string, we search for a column name
            } elseif (is_string($id) and !empty($id)) {
                $id = $this->stripPrefix($id); // strip the prefix if detected at the beginning
                $id = $this->db->escape(filter_var($id,FILTER_SANITIZE_STRING)); // sanitize by type checking + sql escaping
                $whereaddendum .= " AND column_name = '".$id."'";
                $whereextraaddendum .= " AND column_name = '".$id."'"; // Not necessary for the SQL request to be performed, but if available, it will accelerate a bit the result
            }
        }

        if (!$nohide) { // We filter the reserved columns so that the user  cannot alter them, even by mistake and we get only the specified field by id (unless it is specified that we need them, generally used internally in this class)
            $whereaddendum .= " AND column_name != 'rowid' AND column_name != 'fk_".$this->module."'";
        }

        // Forging Where
        $where = "table_schema = '".$this->db->database_name."'";
        if (empty($table)) {
            $where .= " AND table_name = '".$this->moduletable."'";
            $whereextra = "table_name = '".$this->moduletable."'";
        } else {
            $where .= " AND table_name = '".$table."'";
            $whereextra = "table_name = '".$table."'";
        }
        $where .= " ".$whereaddendum;

        // Forging SQL Request
        // Description: we fetch the SQL structure (but NOT the values) of the custom fields (information_schema.COLUMNS) along with foreign key and table if the field is constrained (information_schema.key_column_usage) and constraint index (information_schema.statistics).
        // These datas are then used to properly print the field and manage them (eg: date field will be show as a date and with a calendar pickup to edit the date, etc...).
        // Optimisation note: To optimize the query and limit the number of returned lines, we need to use a WHERE clause for every table BEFORE the join (if you do it after, the time to process the request will increase by 50x, so BEWARE!). Normally this shouldn't happen (the query optimizer should automatically do it), but with MyISAM and InnoDB it does...
        // Outer join note: we need an outer join because most fields won't be constrained, and so they will not even appear in key_column and statistics tables, so we need an OUTER JOIN to keep columns table rows even if in the other tables the result is null.
        // Alternative note: alternatively, it should be possible to break this request into 3 requests, because we can detect fields that are linked to a foreign key by looking at the column_key = 'mul', if that's the case we can then issue another sql request for constrained fields. But I find this SQL query quick enough, and I'm not sure if this alternative way would work faster.
        // About id note: the column_name is the same for all information_schema tables, but ordinal_position is not the same between .columns and .key_column_usage, and it simply doesn't exist for statistics, hence why we create a specific $whereaddendumid variable to handle this special case.
        // TODO? NOT IN takes less resources than LEFT JOIN. A commenter in that article mentions using NOT EXISTS is best. Ref: http://blog.sqlauthority.com/2008/04/22/sql-server-better-performance-left-join-or-not-in/
        // TODO? LEFT OUTER JOIN can also be rewritten with UNION operator (but would this really be faster? In my tests, not necessarily significantly better, while it makes the query a lot heavier and unreadable...)
        $sql = "SELECT c.ordinal_position,c.column_name,c.column_default,c.is_nullable,c.data_type,c.column_type,c.character_maximum_length,
        k.referenced_table_name, k.referenced_column_name, k.constraint_name,
                s.index_name,
                e.extraoptions
        FROM (SELECT * FROM information_schema.columns
                  WHERE ".$where.$whereaddendumid.") as c
        LEFT OUTER JOIN (SELECT * FROM information_schema.key_column_usage
                  WHERE ".$where.") as k USING(table_schema, table_name, column_name)
                LEFT OUTER JOIN (SELECT * FROM information_schema.statistics
                  WHERE ".$where.") as s USING(table_schema, table_name, column_name)
                LEFT OUTER JOIN (SELECT * FROM ".$this->extratable."
                  WHERE ".$whereextra.$whereextraaddendum.") as e ON(e.table_name=c.table_name AND e.column_name=c.column_name)
        ORDER BY c.ordinal_position;";

        // Trigger or not?
        if ($notrigger) {
            $trigger = null;
        } else {
            $trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHFIELD';
        }

        // Executing the SQL statement
        $resql = $this->executeSQL($sql, __FUNCTION__.'_CustomFields', $trigger);

        // Filling the field object
        if (is_int($resql) and $resql < 0) { // if there's no error
            return $resql; // we return the error code

        } else { // else we fill the field
            $num = $this->db->num_rows($resql); // number of lines returned as a result to our sql statement

            // Preparing the cache
            if (isset($cachepath) and $cachepath and strlen($cachepath) > 0) { // if $cachepath is defined (custom cache path)
                eval("\$cpath = &$cachepath;"); // if a cachepath is supplied, we use it (by making a reference to the path)
            } elseif (!isset($cachepath)) { // if $cachepath is null (default path)
                $cpath = &$this->fields; // else we just use the default cache path ($this->fields)
                $cachepath = true;
            } // else if $cachepath is false, then do not store the cache

            if (!isset($cpath)) $cpath = new stdClass(); // initializing the cache object explicitly if empty (to avoid php > 5.3 warnings)

            // -- Several fields columns returned = array() of field objects
            if ($num > 1) {
                $field = array();
                                // Fetch every row from the request (there is no database access at this stage, we only get the result from our already processed query)
                for ($i=0;$i < $num;$i++) {
                    $obj = $this->fetch_object($resql); // we retrieve the data as an object
                    $obj->size = $this->getFieldSizeOrValue($obj->column_type); // add the real size of the field (character_maximum_length is not reliable for that goal, so we get it from the column_type)
                    $obj->id = $obj->ordinal_position; // set the id (ordinal position in the database's table)

                    // unserialize extra options with json
                    if ($obj->extraoptions) $obj->extra = json_decode($obj->extraoptions, true);

                    // SQL compatibility mode: if the DBMS does not support foreign keys and referential integrity checks, we use the extra options to store and fetch the infos about the constrained field
                    if (isset($obj->extra['referenced_table_name']) and empty($obj->referenced_table_name)) $obj->referenced_table_name = $obj->extra['referenced_table_name'];
                    if (isset($obj->extra['referenced_column_name']) and empty($obj->referenced_column_name)) $obj->referenced_column_name = $obj->extra['referenced_column_name'];

                    // we store the field object in an array
                    $field[$obj->id] = $obj;
                }

                // Sort fields by their position
                usort($field, 'customfields_cmp_obj');

                // Store in cache if enabled
                if ($cachepath) { // if cachepath is not false
                    foreach ($field as $obj) {
                        $column_name = $obj->column_name; // we get the column name of the field
                        $cpath->$column_name = $obj; // and we as well store the field as a property of the CustomFields class (caching for quicker access next time)
                    }
                }

                // Workaround: on some systems, num_rows will return 2 when in fact there's only 1 row. Here we fix that by counting the number of elements in the final array: if only one, then we return only first element of the array to be compliant with the paradigm: one record = one value returned
                if (count($field) == 1) $field = reset($field);

            // -- Only one field returned = one field object
            } elseif ($num == 1) {
                $field = $this->fetch_object($resql);

                $field->size = $this->getFieldSizeOrValue($field->column_type); // add the real size of the field (character_maximum_length is not reliable for that goal)
                $field->id = $field->ordinal_position; // set the id (ordinal position in the database's table)

                // unserialize extra options with json
                if ($field->extraoptions) $field->extra = json_decode($field->extraoptions, true);

                // SQL compatibility mode: if the DBMS does not support foreign keys and referential integrity checks, we use the extra options to store and fetch the infos about the constrained field
                if (isset($field->extra['referenced_table_name']) and empty($field->referenced_table_name)) $field->referenced_table_name = $field->extra['referenced_table_name'];
                if (isset($field->extra['referenced_column_name']) and empty($field->referenced_column_name)) $field->referenced_column_name = $field->extra['referenced_column_name'];

                // Store in cache if enabled
                if ($cachepath) { // if cachepath is not false
                    $column_name = $field->column_name; // we get the column name of the field
                    $cpath->$column_name = $field; // and we as well store the field as a property of the CustomFields class (caching for quicker access next time)
                }

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
    *    Note: this is mainly an alias for fetchFieldStruct but that always return an array (ease the processing since you don't have to care about if only one object is returned or multiple, you just do a foreach loop).
    *    @param    $nohide          false/true - defines if the two system fields (primary field and foreign key) must be hidden in the fetched results
    *    @param    $table                           null/string - null to fetch the customfields structure for the current module, or can be set to any table if you want to get the structure of another table's fields
    *    @param    $cachepath                null/false/string - enable caching of the fields structure inside this instance of CustomFields object. false: disable caching; null (default): default path ($this->fields->custom_field_column_name); string: a path can be specified to replace the default cache path (mainly used when you fetch the structure of another table than CustomFields and to avoid overwriting CustomFields structures cache) (eg: $this->$table_name->anything, and your field's structure will be accessible with $this->$table_name->anything->custom_field_column_name)
    *    @param    $notrigger                   false/true - enable (default) or disable the activation of a trigger when this function is called
    *    @return     int/null/obj[]                 <0 if KO, null if no field found, an array of fields objects if OK (even if only one field is found)
    */
    function fetchAllFieldsStruct($nohide=false, $table=null, $cachepath=null, $notrigger=0) {
        $fields = $this->fetchFieldStruct(null, $nohide, $table, $cachepath, $notrigger);
        if ( !(is_array($fields) or is_null($fields) or is_integer($fields)) ) { $fields = array($fields); } // we convert to an array if we've got only one field, functions relying on this one expect to get an array if OK
        return $fields;
    }

    /** Fetch constraints and foreign keys
     *    Description: this method fetch the constraints and foreign keys. By default, constraints will be fetched for the current module's table, but if specified, constraints can be fetched for any table, and even between two tables (to check if two tables have any relationship).
     *  @return      <0 if KO, obj[]     array of constrained fields (objects) if OK
     *
     */
    function fetchConstraints($table=null, $linked_table=null, $notrigger = 0) {

        // Build where clauses
        $where_add = array();
        if (empty($table)) {
            $where_add[] = "table_name = '".$this->moduletable."'"; // by default, fetch constraints for this module
        } else{
            $where_add[] = "table_name = '".$this->db->escape(filter_var($table, FILTER_SANITIZE_STRING))."'"; // else fetch for any field specified
        }
        if (!empty($linked_table)) { // if specified, fetch constraints between two tables
            $where_add[] = "referenced_table_name = '".$this->db->escape(filter_var($linked_table, FILTER_SANITIZE_STRING))."'";
        }

        // Prepare where statement
        $where = '';
        if (count($linked_table) > 0) {
            $where = ' AND '.implode(' AND ', $where_add);
        }

        // Forging the SQL statement
        $sql = "SELECT
                CONCAT(table_name, '.', column_name) as 'foreign key',
                CONCAT(referenced_table_name, '.', referenced_column_name) as 'references',
                table_name, column_name, referenced_table_name, referenced_column_name
                FROM information_schema.key_column_usage
                WHERE referenced_table_name is not null
                    AND table_schema = '".$this->db->database_name."'".$where.";";

        // Trigger or not?
        if ($notrigger) {
            $trigger = null;
        } else {
            $trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHCONSTRAINTS';
        }

        // Executing the SQL statement
        $resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields', $trigger);

        // Filling in all the fetched fields into an array of fields objects
        if (is_int($resql) and $resql < 0) { // if there's an error in the SQL
            return $resql; // return the error code
        } else {
            $constraints = array();
            if ($this->db->num_rows($resql) > 0) {
                $num = $this->db->num_rows($resql);
                for ($i=0;$i < $num;$i++) {
                    $obj = $this->fetch_object($resql); // we retrieve the data line
                    //$name = $obj->column_name;
                    $constaints[] = $obj; // we store the field object in an array
                }
            }
            $this->db->free($resql);

            return $constaints; // we return an array of constraints objects
        }
    }

    /**
     *  Check in the database if a field column name exists in a table
     *  @param  $reftable   table name
     *  @param  $refname    column name
     *  @param        $name           optional, used to specify where to cache (this should be the name of the current constrained field) - only useful if $caching is enabled
     *  @param        $caching       if checking a constrained field, you can specify to cache all fields of the referenced table to accelerate further accesses
     *  @return false if KO, true if OK
     */
    function checkIfFieldExists($refname, $reftable=null, $name=null, $caching=false) {
        // If caching is disabled, we simply fetch the referenced field
        if (!$caching) {
            $fieldref = $this->fetchFieldStruct($refname, true, $reftable, false);
        // Else if caching is enabled...
        } else {
            // ...we check if the referenced were already accessed and cached, and if that's not the case...
            if (!isset($name) or !isset($this->referenced_fields->$reftable)) {
                // ...we just fetch the whole referenced table once and cache all its fields for next time (so that next time this table is accessed by whatever customfield, it will directly access the cache instead of the database)
                $fieldref = $this->fetchAllFieldsStruct(true, $reftable, '$this->referenced_fields->'.$reftable);
            } // Now the referenced table and all its fields structures is cached in any case

            // If the searched field's name exists in the cache of the table, ...
            if (isset($this->referenced_fields->$reftable->$refname)) {
                // ... then it's ok
                $fieldref = $this->referenced_fields->$reftable->$refname;
            } else {
                // else it doesn't exists in the table (since the cache is up-to-date, if the field cannot be found in the cache then it doesn't exist at all)
                $fieldref = null;
            }
        }

        // Return code
        if (isset($fieldref) and !($fieldref <= 0)) { // check that a field was found (returned value not null), and that it's not an error code (int <= 0)
            return true; // if ok then return true
        } else {
            return false; // else it's KO
        }
    }

    function smartValueSubstitutionOnly($field) {
        // -- Smart Value Substitution for constrained fields
        // Description: break the customfield's name depending on a separator (by default '_'), and then check if each part corresponds to a field in the referenced table (if yes, we will show these fields as the value, instead of the rowid/primary field)

        $sqlfields = array(); // will contain all the sql fields in the end
        $realrefcolumns=explode($this->svsdelimiter, $field->column_name); // split the customfield's name into several parts depending on the separator (by default '_', eg: if you name the customfield 'fkid_mylabel', it will look for the foreign 'fkid' column)
        // Check if each part has a corresponding column in the referenced table, or not
        foreach ($realrefcolumns as $refcol) {
            if ( $this->checkIfFieldExists($refcol, $field->referenced_table_name, $field->column_name, true) ) { // Check...
                $sqlfields[] = $refcol; // If it has a corresponding column, then we push this column into the sql fields array that we keep for later usages
            }
        }

        // -- Forging the sql statement

        // List of fields to fetch...
        if (count($sqlfields) > 1) { // ... if several corresponding foreign fields were found, we must concatenate all these fields in one to process them nicely (functions relying on this one expect one [rowid] or two fields [rowid, $field->column_name] at most, not more)
            $concat = 'CONCAT('.implode(",' ',", $sqlfields).') as '.$field->column_name;
            $sqlfields = array($concat);
        } elseif (count($sqlfields) == 1) { // ... else if there is only one corresponding foreign field, we still have to make an alias to get coherent (so that the second field can still be accessed with the customfield's name: $field->column_name)
            $sqlfields[0] .= ' as '.$field->column_name;
        } else { // ... else, no corresponding foreign field was found, the array is empty, but to be consistent we will still create a duplicate of the rowid but aliased with the custom field name, so that we get coherent result by returning the raw value (rowid/primary field's value) as a value for this custom field.
            $sqlfields[] = $field->referenced_column_name.' as '.$field->column_name;
        }

        return $sqlfields;
    }

   /**
    *    Load linked records of a constrainted field
    *    The linked records are fetched from the linked table referenced by this constrainted field, fetching the primary column/rowid values to be used as keys + one or several fields values concatenated to be printed as values in a html select
    *    Also contain Smart Value Substitution for constrained fields (the feature that depending on the name, it will check similar columns names in the referenced table to fetch one or several fields values and concatenate them to then print them as values instead of just the rowid)
    *    Also manages Remote Fields Access (fetching remote fields from the referenced table).
    *
    *    @param    field        the constrained field as an object with the field's structure (which contains a non-null referenced_column_name property)
    *    @param    id            fetch the record(s) having this rowid (if null: fetch all records - string/integer: fetch only one record - array of integers: fetch only those records)
    *    @param    where    additional where constraint (must NOT contain the WHERE sql statement, just the conditions you want, like 'somecolumn=1')
    *    @param    allcolumns    return all linked columns or just the necessary ones? (aka Remote fields access) (if false/null = fetch only column rowid and smart value substitution defined columns - true = fetch all linked columns + smart value substitution defined columns - string = just add one column to the result - array = fetch svs defined columns + columns in the array)
    *    @return     obj[]         <-1 if KO, array of records if OK (with 1st value = rowid/primary column, and maybe 2nd value = smart value substitution from other fields in the same table)
    */
    function fetchReferencedValuesList($field, $id=null, $where=null, $allcolumns=false, $notrigger = 0) {

        if (empty($field->referenced_table_name)) return null; // return null if there's no referenced table

        // -- Smart Value Substitution for constrained fields
        // Description: break the customfield's name depending on a separator (by default '_'), and then check if each part corresponds to a field in the referenced table (if yes, we will show these fields as the value, instead of the rowid/primary field)

        $sqlfields = array(); // will contain all the sql fields in the end
        $realrefcolumns=explode($this->svsdelimiter, $field->column_name); // split the customfield's name into several parts depending on the separator (by default '_', eg: if you name the customfield 'fkid_mylabel', it will look for the foreign 'fkid' column)
        // Check if each part has a corresponding column in the referenced table, or not
        foreach ($realrefcolumns as $refcol) {
            if ( $this->checkIfFieldExists($refcol, $field->referenced_table_name, $field->column_name, true) ) { // Check...
                $sqlfields[] = $refcol; // If it has a corresponding column, then we push this column into the sql fields array that we keep for later usages
            }
        }

        // -- Forging the sql statement

        // List of fields to fetch...
        if (count($sqlfields) > 1) { // ... if several corresponding foreign fields were found, we must concatenate all these fields in one to process them nicely (functions relying on this one expect one [rowid] or two fields [rowid, $field->column_name] at most, not more)
            $concat = 'CONCAT('.implode(",' ',", $sqlfields).') as '.$field->column_name;
            $sqlfields = array($concat);
        } elseif (count($sqlfields) == 1) { // ... else if there is only one corresponding foreign field, we still have to make an alias to get coherent (so that the second field can still be accessed with the customfield's name: $field->column_name)
            $sqlfields[0] .= ' as '.$field->column_name;
        } else { // ... else, no corresponding foreign field was found, the array is empty, but to be consistent we will still create a duplicate of the rowid but aliased with the custom field name, so that we get coherent result by returning the raw value (rowid/primary field's value) as a value for this custom field.
            $sqlfields[] = $field->referenced_column_name.' as '.$field->column_name;
        }

        // Adding the rowid/primary field, this is ALWAYS needed as this is the key that will be used when selecting a value (this id is unique, names and other values can make collisions)
        array_unshift($sqlfields, $field->referenced_column_name); // Adding it at the first position in the array, this is important

        // Order by...
        // Note: this must be done BEFORE doing the processing for $allcolumns, else we may have two sql fields (rowid + wildcard) and thus set orderby the referenced_column_name when in fact there's no sql field with this name!
        if (count($sqlfields) > 1) { // ... the smart substituted value if a corresponding foreign field was found...
            $orderby = $field->column_name; // (eg: a list of name is better ordered alphabetically)
        } else { // ... else, there's only the rowid/primary field ...
            $orderby = $field->referenced_column_name; // by default we order by this field (generally rowid)
        }

        // Fetch all columns from referenced table, or just the one needed to do the Smart Value Substitution?
        if (is_bool($allcolumns) and $allcolumns == true) {
            $sqlfields[] = $field->referenced_table_name.'.*'; // Wildcard * to select all columns must always be put first in the list of columns to select in a sql request, else it will produce a bug! Or an alternative is to specify the table's name (eg: llx_table.*) as done here.
        } elseif (is_array($allcolumns)) {
            array_merge($sqlfields, $allcolumns);
        } elseif (is_string($allcolumns)) {
            $sqlfields[] = $allcolumns;
        }

        // Where
        $where_sql = '';
        if (!empty($id) or !empty($where) or !empty($field->extra['constraint_where'])) {
            $where_arr = array();
            if (!empty($id)) {
                $id = $this->db->escape(filter_var($id, FILTER_SANITIZE_NUMBER_INT));
                $where_arr[] = $field->referenced_column_name.'='.$id;
            }
            if (!empty($where)) $where_arr[] = $where;
            if (!empty($field->extra['constraint_where'])) $where_arr[] = $field->extra['constraint_where'];

            if (count($where_arr) > 0) {
                $where_sql = ' WHERE '.implode(' AND ', $where_arr);
            }
        }


        // Final touch, crafting the final sql request
        $sql = 'SELECT '.implode(',', $sqlfields).' FROM '.$field->referenced_table_name.$where_sql.' ORDER BY '.$orderby.';';


        // Trigger or not?
        if ($notrigger) {
            $trigger = null;
        } else {
            $trigger = strtoupper($this->module).'_CUSTOMFIELD_FETCHREFVALUES';
        }

        // -- Executing the sql statement (fetching the referenced list)
        $resql = $this->executeSQL($sql,__FUNCTION__.'_CustomFields',$trigger);

        // -- Filling in all the fetched fields into an array of records objects
        if (is_int($resql) and $resql < 0) { // if there's an error in the SQL
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
    *    Convert the linked (referenced) records to an array(rowid=>value) so that we can easily generate an array of options
    *    Also contain Smart Value Substitution for constrained fields (the feature that depending on the name, it will check similar columns names in the referenced table to fetch one or several fields values and concatenate them to then print them as values instead of just the rowid)
    *
    *    @param    field                        the constrained field as an object with the field's structure (which contains a non-null referenced_column_name property), usually fetched using fetchFieldStruct() or fetchAllFieldsStruct()
    *    @param    linked_records       referenced records of the constrained field, fetched via fetchReferencedValuesList()
    *    @return     array[]                   array of the form array(rowid=>value) where value is the svs value if available, or the rowid if not
    */
    function convertReferencedRecordsToArray($field, $linked_records) {
        if (empty($field->referenced_column_name) or empty($linked_records) or !is_array($linked_records)) return null;
        // Special feature : smart value substitution for constrained fields : if the customfield has a column name similar to one (or several) in the linked table, then we show the values of this field instead
        $refkey = $field->referenced_column_name; // main key is the rowid (or whatever is called the primary field for the referenced table) - this allows to avoid confusion when selecting values (since this key necessarily has a unique value, since it's a SQL primary field)
        // Second key: value that will be shown
        if (count((array)$linked_records[0]) > 1) { // Either we substitute based on the name of the customfield (eg: name field, or a composition of fields like name_firstname)
            $refval = $field->column_name; // Smart Value Substitution (SVS): use the svs value (will be stored in a field called by the customfield's name, because we name it like this in fetchReferencedValuesList() ).
        } else { // Either we just show the first key (ie: rowid/primary field, which is an integer) as the value (no svs value)
            $refval = $field->referenced_column_name;
        }
        // Extract the rowid key and value and then return the new array
        return array_reassociate($refkey, $refval, $linked_records);
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
        if (is_int($resql) and $resql < 0) { // if there's an error in the SQL
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
    function fetchPrimaryField($table, $fallback=true, $notrigger = 0) {

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
        if (is_int($resql) and $resql < 0) { // if there's an error in the SQL
            $errmsg = $this->db->lasterror();
            $this->error.=($this->error?', '.$errmsg:$errmsg);
            return $resql; // return the error code
        } else {
            $tables = array();
            // If there is at least one result, we fetch the first one (in case there are multiple primary keys, but this is theoretically impossible with mysql, but is possible in sql standard)
            if ($this->db->num_rows($resql) > 0) {
                $obj = $this->db->fetch_array($resql);
                $prifield = $obj[0]; // get the column_name of the first field (normally there should be only one ayway)
                // else, no primary field could be found (but there wasn't any sql error, so probably the table has no primary field set, this happens on Views for example)
            } elseif ($fallback) {
                $fields = $this->fetchFieldStruct(null, true, $table, false);
                // if a field could be found in the table structure, we fetch the first one
                if (!empty($fields)) {
                    $arr = (array)$fields; // convert to an array so that we can access the first field (impossible with objects without knowing the name of the property)
                    $firstfield=reset($arr); // access the first field
                    $prifield=$firstfield->column_name; // store the column_name of the first field
                // if there is no field at all in this table, then we return an error
                } else {
                    $prifield = -1;
                }
            } else {
                $prifield = -1;
            }
            $this->db->free($resql);

            if (!is_string($prifield) and $prifield < 0) {
                $errmsg = "No primary field could be found in the specified table $table!";
                $this->error.=($this->error?', '.$errmsg:$errmsg);
            }

            return $prifield; // we return the string value of the column name of the primary field
        }
    }

    /*  Delete a custom field (and the associated foreign key and index if necessary)
    *   @param  id  id of the customfield (ordinal_position or column_nume in sql database)
    *
    *   @return < 0 if KO, > 0 if OK
    */
    function deleteCustomField($id, $notrigger = 0) {
        // Get the column_name
        if (empty($id)) {
            $this->addError('Empty value');
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

    /** Delete a constraint for a customfield
     *  @param  id  id of the customfield (ordinal position in sql database)
     *
     *  @return -1 if KO, 1 if OK
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

    /** Create a field (column in the customfields table) (will update the field if it does not exists)
     *  @param  fieldname   name of the custom field (column name)
     *  @param  type        sql type of the custom field (column type)
     *  @param  size        bits size of the custom field type (data type)
     *  @param  nulloption  accepts null values?
     *  @param  defaultvalue    default value for this field? (null by default)
     *  @param  constraint  name of the table linked by foreign key (the referenced_table_name)
     *  @param  customtype  custom sql definition for the type (replaces type and size or just type parameter depending if the size is supplied in the def, ie: int(11) )
     *  @param  customdef   custom sql definition that will be appended to the definition generated automatically (so you can add sql parameters the author didn't foreseen)
     *  @param  customsql   custom sql statement that will be executed after the creation/update of the custom field (so that you can make complex statements)
     *  @param  fieldid     id of the field to update (ordinal position). Leave this null to create the custom field, supply it if you want to update (or just use updateCustomField which is a simpler alias)
     *  @param  extra      associative array which properties will be stored as extra options (can be anything). Extra options already stored in the database will be kept, unless a new value for a property is explicitly defined, in which case the option in the database is overwritten (you can set a property to null to erase it, but do not use unset($extra['key']] because the previous value will be kept!)
     *  @param  notrigger   do not activate triggers?
     *
     *  @return -1 if KO, 1 if OK
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
            $prifieldname = $this->fetchPrimaryField($constraint);
            $prifield = $this->fetchFieldStruct($prifieldname, true, $constraint);

            $type = $prifield->data_type;
            //$nulloption = $prifield->is_nullable; // commenting this allows to create constrained fields that accepts null values
            $size = $prifield->size;
        }

        $fieldname = strtolower($fieldname); // force the field name (sql column name) to be lowercase to avoid errors on some platforms

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
            $sqlconstraint = 'ALTER TABLE '.$this->moduletable.' ADD CONSTRAINT fk_'.$fieldname.' FOREIGN KEY ('.$fieldname.') REFERENCES '.$constraint.'('.$prifield->column_name.');';
            $rtncodec = $this->executeSQL($sqlconstraint, $mode.'ConstraintCustomField_CustomFields',$trigger);

            // Mirroring constraint in the extra options (compatibility mode for MyIsam, without foreign keys constrained field will still work!)
            if (!isset($extra) or !is_array($extra)) $extra = array();
            $extra['referenced_table_name'] = $constraint;
            $extra['referenced_column_name'] = $prifield->column_name;
        } else {
            // If there is no constraint, we remove the constraints in extra options (the deletion of foreign keys are done above)
            if (!isset($extra) or !is_array($extra)) $extra = array();
            $extra['referenced_table_name'] = null; // we don't unset because we want the variable to stay, but null
            $extra['referenced_column_name'] = null;
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
            if (!isset($extra) or !is_array($extra)) $extra = array(); // necessary to have a properly formatted $extra (an array) prior to call the setExtra() function
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

    /*  Update a customfield's definition (will create the field if it does not exists)
    *   @param  fieldid id of the field to edit (the ordinal position)
    *   @param  for the rest, see addCustomField
    *
    *   @return -1 if KO, 1 if OK
    */
    function updateCustomField($fieldid, $fieldname, $type, $size, $nulloption, $defaultvalue, $constraint = null, $customtype = null, $customdef = null, $customsql = null, $extra = null, $notrigger = 0) {
        return $this->addCustomField($fieldname, $type, $size, $nulloption, $defaultvalue, $constraint, $customtype, $customdef, $customsql, $fieldid, $extra, $notrigger);
    }

    /** Set the extra options of one custom field and store it in database
     *  Description: you can use the extra options method to set ANY property you want to a custom field, just pass it as an object, whose properties will be saved as-is in the database and retrieved whenever you will call fetchFieldStruct().
     *  TODO: function to do it in batch for several custom fields at once
     *
     *  @param  int/string/obj  $fieldid ordinal_position or column_name of the field to modify or directly the field object
     *  @param  array  $extra  associative arrays with properties that should be saved as extra options, NOTE that $extra will be appended on $field->extra (thus it will only modify the options you specify in argument $extra, other previous options will be preserved)
     *  @param  string  $newfieldname (optional)    internal variable used to update the field in addCustomField() (when we change the field's name, we also relocate the extra options this way). You should not be caring about this.
     *  @param  bool    $replace    by default, supplied $extra options are appended to previous options, but if $replace=true, then previous options will be deleted altogether and replaced by the supplied $extra.
     *
     *  @return < 0 if KO, > 0 if OK
     *
     */
    function setExtra($fieldid, $extra, $newfieldname=null, $replace=false, $notrigger = 0) {
        // Quit if we don't have the required variables in the required format
        if (!isset($fieldid) or !is_array($extra)) {
            if (!isset($fieldid)) $this->addError('setExtra: $fieldid not in the appropriate format. $fieldid should either be an integer, a string or a field object.');
            if (!is_array($extra)) $this->addError('setExtra: $extra not in the appropriate format. $extra should be an associative array.');
            return -1;
        }

        // Fetch the customfield's structure if not available
        if (!is_object($fieldid)) {
            $field = $this->fetchFieldStruct($fieldid);
        } else {
            $field = $fieldid;
        }

        // Sanitize the inputted $extra by replacing double-quotes by single quotes (eg: to support strings with quotes in extra options)
        // DEPRECATED: not needed! Json_encode already escapes strings for each extra parameters, and then $this->escape() will escape the json quotes so that SQL does not produce an error.
        //foreach ($extra as $k=>$v) {
            //$extra[$k] = str_replace('"', "'", $v);
        //}
        //$extra = array_map(array($this, 'escape'), $extra); // sanitize for SQL injection - doesn't work because we already escape below, thus strings aren't restituted correctly

        // Append new $extra options on previous options in $field->extra, by merge the two objects: the already existing extra options for this field (from the db), plus the extra options given in parameters of this function
        if (isset($field->extra) and !$replace) {
            $fullextra = array_replace_recursive((array) $field->extra, (array) $extra); // note: user provided $extra options have precedence by putting them last in array_merge(): they override the ones already stored for the field in case when there are identical keys in both
        } else {
            $fullextra = $extra; // no previous extra options, we create extra from user's input
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
     *     @param      currentvalue           null/string/array - Current value of the parameter (will be filled in the value attribute of the HTML field) - null: default column value; string: value will be selected and printed; array (format: array[i][0] = id, array[i][1] = value): will force the function to print a select box with the given array of ids/values
     *     @param      moreparam       To add more parametes on html input tag
     *     @return       out            An html string ready to be printed
     */
    function showInputField($field,$currentvalue=null,$moreparam='', $ajax_php_callback='') {
        global $conf, $langs;

        // Init vars
        $key=$field->column_name;
        $name=$this->varprefix.$key;
        $id=$this->varprefix.$key;
        $label=$langs->trans($key);
        $type=$field->data_type;
        if ($field->column_type == 'tinyint(1)') { $type = 'boolean'; }
        $size=$this->character_maximum_length;
        if ( !is_array($currentvalue) and (!isset($currentvalue) or !strlen($currentvalue)) ) { $currentvalue = $field->column_default;}

        if ($type == 'int') {
            $showsize=10;
        } else {
            $showsize=round($size);
            if ($showsize > 48) $showsize=48; // max show size limited to 48
        }

        $out = ''; // var containing the html output

        // == Managing cases of special printing
        // 1st special case: custom array of ids/values crafted from a custom sql request (see customfields_fields_extend.lib.php)
        if (is_array($currentvalue)) {
            $out.='<select class="flat" name="'.$name.'"'.($moreparam?$moreparam:'').'>';
            foreach ($currentvalue as $record) {
                $id = $record['id'];
                $value = $record['value'];
                if (isset($record['selected']) and $record['selected'] = true) {
                    $selected = 'selected="selected"';
                } else {
                    $selected = '';
                }
                $out.='<option value="'.$id.'" '.$selected.'>'.$langs->trans($value).'</option>';
            }
            $out.='</select>';
        // 2nd special case: Constrained field
        } elseif (!empty($field->referenced_column_name)) {

            /*
                $tables = $this->fetchAllTables();
                $tables = array_merge(array('' => $langs->trans('None')), $tables); // Adding a none choice (to avoid choosing a constraint or just to delete one)
                $html=new Form($this->db);
                $out.=$html->selectarray($name,$tables,$field->referenced_table_name);
                */

            // Automatic cascading fields management (used mainly when editing data sheet, for the create form it's managed via ajax)
            $cascade_join_on = '';
            if (!empty($field->extra['cascade']) and !empty($field->extra['cascade_parent_value'])) {
                // Get parent field if provided, else fetch it from db
                if (!empty($field->extra['cascade_parent_field'])) {
                    if (is_object($field->extra['cascade_parent_field'])) {
                        $parent_field = $field->extra['cascade_parent_field'];
                    } elseif (!empty($field->extra['cascade_parent_field_obj']) and is_object($field->extra['cascade_parent_field_obj'])) {
                        $parent_field = $field->extra['cascade_parent_field_obj'];
                    } else {
                        $parent_field = $this->fetchFieldStruct($field->extra['cascade_parent_field']);
                    }
                }

                // Get parent field's current value, this MUST be provided, since we have no other way to know that
                $parent_current_value = '';
                if (!empty($field->extra['cascade_parent_value'])) {
                    $parent_current_value = $field->extra['cascade_parent_value'];
                }

                // Fetch the join on value if the join from field is not the table's rowid (eg: llx_c_departements is linked with llx_c_regions via departements.fk_region=regions.code_region fields, not the rowid)
                // Note: This is kind of a standard SQL Join but wrapped via PHP, but of course we could do that directly in a single SQL request. Here we chose to do it via PHP to reuse a maximum of code to be flexible (so that one field doesn't need to know about what does the other field, they just share one value).
                $join_on_value = $parent_current_value;
                if (!empty($field->extra['cascade_parent_join_on'])) { // If the parent column we should join on is specified (and thus different from rowid)
                    $parent_field_record = $this->fetchReferencedValuesList($parent_field, $parent_current_value, null, $field->extra['cascade_parent_join_on']); // fetch the value for the column we have to join on
                    if (!empty($parent_field_record) and isset($parent_field_record[0]->{$field->extra['cascade_parent_join_on']})) { // if this column is available, fetch the value, which we will use for the where below
                        $join_on_value = $parent_field_record[0]->{$field->extra['cascade_parent_join_on']};
                    }
                }

                $cascade_join_on = $field->extra['cascade_parent_join_from'].'='.$join_on_value;
            }

            // -- Fetch the linked records (list of values)
            $refrecords = $this->fetchReferencedValuesList($field, null, $cascade_join_on); // We can also constrain the resulting options on the parent field's value in case the current field is the child of a cascade
            $refarray = $this->convertReferencedRecordsToArray($field, $refrecords); // Convert the linked records to an array(rowid=>value) so that we can easily generate an array of options

            // -- Print the list
            // Printing a select with all the values and keys
            $out.='<select class="flat" name="'.$name.'" id="'.$id.'"'.($moreparam?$moreparam:'').'>';
            if (strtolower($field->is_nullable) == 'yes') $out.='<option value=""></option>'; // Empty option if null is allowed for this field
            if (count($refarray) > 0) {
                foreach ($refarray as $key=>$value) {
                    if ($key == $currentvalue) {
                        $selected = 'selected="selected"';
                    } else {
                        $selected = '';
                    }
                    $out.='<option value="'.$key.'" '.$selected.'>'.$value.'</option>';
                }
            }
            $out.='</select>';

        // 3rd case: Normal non-constrained fields
        } else {
            if ($type == 'varchar') {
                $out.='<input type="text" name="'.$name.'" id="'.$id.'" size="'.$showsize.'" maxlength="'.$size.'" value="'.$currentvalue.'"'.($moreparam?$moreparam:'').'>';
            } elseif ($type == 'text' and !empty($field->extra['nohtml'])) {
                $out.='<textarea name="'.$name.'" id="'.$id.'" size="'.$showsize.'" maxlength="'.$size.'"'.($moreparam?$moreparam:'').'>'.$currentvalue.'</textarea>';
            } elseif ($type == 'text') {
                require_once(DOL_DOCUMENT_ROOT."/core/class/doleditor.class.php");
                $randid = '_rand'.uniqid($key.rand(1,10000)); // Important to make sure that this field gets an unique ID, else the Javascript widget won't be able to locate the correct field if multiple fields have the same id (which is not correct anyway in X/HTML)
                $doleditor=new DolEditor($name.$randid,$currentvalue,'',200,'dolibarr_notes','In',false,false,$conf->fckeditor->enabled,5,100);
                $out.=$doleditor->Create(1);
                $out = str_replace('name="'.$name.$randid.'"', 'name="'.$name.'"'.($moreparam?$moreparam:''), $out); // Finally, replace the name by removing the random id part (because we need the name to be exactly the same as the field's name so that we can detect it and save it in customfields_printforms.lib.php)
                $id.=$randid; // append random unique id
            } elseif ($type == 'date') {
                //$out.=' (YYYY-MM-DD)';
                $html=new Form($this->db);
                $randid = '_rand'.uniqid($key.rand(1,10000)); // Important to make sure that this field gets an unique ID, else the Javascript widget won't be able to locate the correct field if multiple fields have the same id (which is not correct anyway in X/HTML)
                $out.=$html->select_date($currentvalue,$name.$randid,0,0,1,$name.$randid,1,1,1); // TODO: fix $currentvalue when it is in format day/month/year (when an error happens and we want to remember the field, for example in products lines)
                $out = str_replace('name="'.$name.$randid, ($moreparam?$moreparam:'').'name="'.$name, $out); // Finally, replace the name by removing the random id part (because we need the name to be exactly the same as the field's name so that we can detect it and save it in customfields_printforms.lib.php). We replace all occurrences, meaning that we also fix the names of the day, month and year fields that are created automatically in addition of our field (useful to correctly separate these fields because in the final field we can't know which is the day or month, which change according to the locale, eg: english month is first, french day is first).
                $id.=$randid; // append random unique id
            } elseif ($type == 'datetime') {
                //$out.=' (YYYY-MM-DD HH:MM:SS)';
                if (empty($currentvalue)) { $currentvalue = 'YYYY-MM-DD HH:MM'; }
                //$out.='<input type="text" name="'.$name.'" size="'.$showsize.'" maxlength="'.$size.'" value="'.$currentvalue.'"'.($moreparam?$moreparam:'').'>';
                $html=new Form($this->db);
                $randid = '_rand'.uniqid($key.rand(1,10000)); // Important to make sure that this field gets an unique ID, else the Javascript widget won't be able to locate the correct field if multiple fields have the same id (which is not correct anyway in X/HTML)
                $out.=$html->select_date($currentvalue,$name.$randid,1,1,1,$name.$randid,1,1,1);
                $out = str_replace('name="'.$name.$randid, ($moreparam?$moreparam:'').'name="'.$name, $out); // Finally, replace the name by removing the random id part (because we need the name to be exactly the same as the field's name so that we can detect it and save it in customfields_printforms.lib.php). We replace all occurrences, meaning that we also fix the names of the day, month and year fields that are created automatically in addition of our field (useful to correctly separate these fields because in the final field we can't know which is the day or month, which change according to the locale, eg: english month is first, french day is first).
                $out = str_replace('name="'.$name.'hour"', 'name="'.$name.'hour" id="'.$name.$randid.'hour"', $out); // Add the random id to the hour and minutes selectors (because Dolibarr doesn't generate an ID for them, only a name attribute, which is weird because all other inputs have both ids and names).
                $out = str_replace('name="'.$name.'min"', 'name="'.$name.'min" id="'.$name.$randid.'min"', $out);
                //$out = preg_replace('#this.form.elements\[([^]]+)\]#i', 'document.getElementById(\1)', $out);
                $id.=$randid; // append random unique id
            } elseif ($type == 'enum') {
                $out.='<select class="flat" name="'.$name.'" id="'.$id.'"'.($moreparam?$moreparam:'').'>';
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
                $out.='<select name="'.$name.'" id="'.$id.'"'.($moreparam?$moreparam:'').'>';
                $out.='<option value="1" '.($currentvalue=='1'?'selected="selected"':'').'>'.$langs->trans("True").'</option>';
                $out.='<option value="false" '.($currentvalue=='0'?'selected="selected"':'').'>'.$langs->trans("False").'</option>';
                $out.='</select>';

            // Any other field
            } else { // for all other types (custom types and other undefined), we use a basic text input
                $out.='<input type="text" name="'.$id.'" id="'.$name.'" size="'.$showsize.'" maxlength="'.$size.'" value="'.$currentvalue.'"'.($moreparam?$moreparam:'').'>';
            }
        }

        if (!empty($ajax_php_callback)) $out .= "\n".$this->showInputFieldAjax($id, $ajax_php_callback);

        return $out;
    }

    /**
     *     Return AJAX jQuery script to append in HTML to make a custom field AJAX-call back to a php function
     *     This is a jQuery wrapper to easily send the form's inputs to a php script, and then receive data that can be fed back onto the form.
     *     This allows to make cascaded dropdown lists for example.
     *     It expects returned data to be either an array (eg: array("cf_myfield"=>"myvalue")) to change the current value of one or multiple fields (key is the name of the field to change, and the value is the new current value to set), or a recursive array (eg: array( "cf_myfield" => array("value"=>"myvalue", "options"=>array(1=>"option1", 2=>"option2"), "html"=>"<input type...>" ) ) ), each key being the field to change associated to a sub-array containing either "value" to change current value, "options" to change the options of a select input type, or "html" to change the html of this input altogether (these options are not exclusive, you can use as many as you want for one same field, eg: setting the options and current value at the same time).
     *     @param      id                           HTML id of the field to which we will attach the AJAX function
     *     @param      php_callback_url   Relative URL from the root of Dolibarr's htdocs folder, pointing to the php script to which to send the field's data, and from which to receive data to change form's inputs via jQuery. The PHP script should print its result in encoded json (eg: print(json_encode($result)); ).
     *     @param      on_func                 Javascript/jQuery event on which to attach the AJAX call for this field.
     *     @param      request_type         HTML form request type: "post" (if the AJAX will tamper the database or if you want a bit more security) or "get"
     *     @return       out                        An html string containing jQuery Javascript ready to be printed
     */
    function showInputFieldAjax($id, $php_callback_url, $on_func="change", $request_type="post") {
        $php_callback = dol_buildpath($php_callback_url, 1); // relative path works correctly here, but if you ever need to use an absolute path (because script is not called correctly), then use dol_buildpath type 1 for relative and construct the DOL_URL_ROOT by yourself because you need to include the port (which is not included by default in DOL_URL_ROOT).

        $out = '<script type="text/javascript">
$(document).ready(function(){ // when document is ready to be shown
    $("#'.$id.'").on("'.$on_func.'", function() {
        // get the parent form
        var form = $($(this)[0].form);
        // serialize the data in the form
        var serializedData = form.serializeArray();
        serializedData.push({name: "customfields_ajax_current_module", value: "'.$this->module.'"});
        serializedData.push({name: "customfields_ajax_current_field", value: $(this).attr("name")});

        '.($this->debug?'alert(JSON.stringify(serializedData, null, 4));':'').'

        // fire off the request
        //alert("'.$php_callback.'"); // to check if the php callback is correctly called (path is correct), uncomment this line
        request = $.ajax({
            url: "'.$php_callback.'",
            type: "'.$request_type.'",
            data: serializedData
        });

        // callback handler that will be called on success
        request.done(function (data, textStatus, jqXHR){
            // log a message to the console
            '.($this->debug?'console.log("Customfields: Ajax data received");':'').'
            '.($this->debug?'alert(data);':'').'
            if ($.trim(data)) { // check if returned data is not empty, else we wont do anything
                dataArr = JSON.parse(data);
                if (!jQuery.isEmptyObject(dataArr)) { // check if data is empty again
                    jQuery.each(dataArr, function (key,attributes) { // for each field (each key = one field that we have to update)
                        //var field = form.find("#"+key);
                        var field = form.find("*[name="+key+"]"); // prefer set custom fields by name instead of id because id don\'t necessarily correspond to the customfield\'s internal name, whereas the name is ensured to correspond (else datasheet editing/saving in customfields_printforms.lib.php would not work).
                        if (jQuery.type(attributes) === "object" || jQuery.type(attributes) === "array") { // the value is an object/array where value and/or html data are specified (each key represent an attribute to replace for this field)
                            // Substituting whole HTML
                            if (attributes["html"] != undefined) {
                                field.html(attributes["html"]);
                            }
                            // Replace only options using an array of keys/values
                            if (attributes["options"] != undefined && (jQuery.type(attributes["options"]) === "object" || jQuery.type(attributes["options"]) === "array") ) {
                                if (field.is("select") || (field.is("input") && (field.type === "radio" || field.type === "checkbox"))) {
                                    var out = "";
                                    if (attributes["options_keys"] != undefined) { // if keys order is available, use that
                                        jQuery.each(attributes["options_keys"], function (optKey, optVal) { // iterate over options keys to keep the order
                                            out = out+"<option value=\""+optVal+"\">"+attributes["options"][optVal]+"</option>";
                                            //out = out+"<option value=\""+optKey+"\">"+optVal+"</option>"; // this is not correct because, even if it works, it wont respect the keys order (because in Javascript, php associative arrays get converted to objects, and objects have no intrinsic order, its up to the browser implementation so it may be ordered in some browser while not on others).
                                        });
                                    } else { // else we will print in whatever scrambled order Javascript does...
                                        jQuery.each(attributes["options"], function (optKey, optVal) { // iterate over options keys to keep the order
                                            out = out+"<option value=\""+optKey+"\">"+optVal+"</option>";
                                        });
                                    }
                                    field.html(out);
                                } else {
                                    '.($this->debug?'console.error("Customfields: Ajax error: i don\'t know how to manage options for the field "+key+" of type "+field.tagName);':'').'
                                }
                            }
                            // Select the current value
                            if (attributes["value"] != undefined) {
                                field.val(attributes["value"]);
                            }
                            // Alert (eg, to explicitly tell the user that something is wrong in the form)
                            if (attributes["alert"] != undefined) {
                                alert(attributes["alert"]);
                            }
                            // Change the field\'s attributes
                            if (attributes["attr"] != undefined && jQuery.type(attributes["attr"]) === "object") {
                                jQuery.each(attributes["attr"], function (optKey, optVal) { // iterate over options keys to keep the order
                                    field.attr(optKey, optVal);
                                });
                            }
                            // Change the field\'s CSS
                            if (attributes["css"] != undefined && jQuery.type(attributes["css"]) === "object") {
                                field.css(attributes["css"]);
                            }
                        } else { // simple string, the value was directly returned as-is
                            field.val(attributes);
                        }
                    });
                    // Alert (eg, to explicitly tell the user that something is wrong in the form)
                    if (dataArr["alert"] != undefined) {
                        if (jQuery.type(dataArr["alert"]) === "object" || jQuery.type(dataArr["alert"]) === "array") { // Array of alert messages, we will walk through the array and show them all
                            jQuery.each(dataArr["alert"], function (key,value) {
                                alert(value);
                            });
                        } else {
                            alert(dataArr["alert"]);
                        }
                    }
                }
            }
        });

        // callback handler that will be called on failure
        request.fail(function (jqXHR, textStatus, errorThrown){
            // log the error to the console
            '.($this->debug?'console.error("Customfields: Ajax error occured: "+textStatus, errorThrown);':'').'
        });
    });
});
</script>';

        return $out;
    }

    /**
     *  Draw an input form (same as showInputField but produce a full form with an edit button and an action)
     *  @param        $id                         id of the object in database (generally stored in GETPOST('id') or GETPOST('rowid'))
     *  @param  $field                 field object
     *  @param  $currentvalue     current value of the field (will be set in the value attribute of the HTML input field)
     *  @param        $idvar                   name of the variable that holds the id for this module (generally 'id', but can be 'rowid', 'facid', 'socid', etc..)
     *  @param  $page                  URL of the page that will process the action (by default, the same page)
     *  @param  $moreparam      More parameters
     *  @return $out                      An html form ready to be printed
     */
    function showInputForm($id, $field, $currentvalue=null, $idvar='id', $page=null, $moreparam='', $ajax='') {
        global $langs;

        $out = '';

        if (empty($page)) { $page = $_SERVER["PHP_SELF"]; }
        $name = $this->varprefix.$field->column_name;
        $out.='<form method="post" action="'.$page.'" name="form_'.$name.'">';
        $out.='<input type="hidden" name="action" value="set_'.$name.'">'; // action (just a 'set_mycustomfield')
        $out.='<input type="hidden" name="'.$idvar.'" value="'.$id.'">'; // Pass on the ID (necessary to correctly reload the page and to save the new value)
        $out.='<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">'; // Newtoken is needed, it's generated by Dolibarr, and is needed so that the form is accepted
        $out.='<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
        $out.='<tr><td>';
        $out.=$this->showInputField($field, $currentvalue, $moreparam, $ajax);
        $out.='</td>';
        $out.='<td align="left"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
        $out.='</tr></table></form>';

        return $out;
    }

    /**
     *     Return HTML string to print a record's data
     *     @param   field   field object
     *     @param       value           Value to show
     *     @param   outputlangs     the language to use to find the right translation
     *     @param       moreparam       To add more parametes on html input tags
     *     @return  html                An html string ready to be printed (without input fields, just html text)
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
                // Fetching the record
                // Special feature SmartValueSubstitution is managed inside fetchReferencedValuesList : if the customfield has one or several column names similar to one in the linked table, then we show the values of this (these) field(s) instead
                $record = $this->fetchReferencedValuesList($field, $value);
                // Outputting the smart value substituted value if available, else we just print the rowid
                if (!empty($record) and isset($record[0]->{$field->column_name})) {
                    $out.= $record[0]->{$field->column_name}; // in the format $record->$column_name where $column_name is the current customfield's name (generally it's stored in $field->column_name)
                // Else we just print out the value of the field (rowid)
                } else {
                    $out.=$value;
                }
                // Automatic link generation: if the linked table is a Dolibarr module (specified in customfields config file), then we will automatically create a link to the object's datasheet
                $ctable_no_prefix = $this->stripPrefix($field->referenced_table_name, MAIN_DB_PREFIX); // first remove the database prefix to get only the table's name
                if (isset($this->constraint_links[$ctable_no_prefix])) { // then check if this table is a Dolibarr module
                    $constraint_url = dol_buildpath($this->constraint_links[$ctable_no_prefix], 1); // create a relative url from root /
                    $out = '<a href="'.$constraint_url.$value.'">'.$out.'</a>'; // enclose the printed value inside an HTML link
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
                // type textraw (TextArea with no html)
                } elseif ($field->column_type == 'text' and !empty($field->extra['nohtml'])) {
                    $out.=str_replace("\n", '<br />', $value); 
                } elseif ($field->column_type == 'date') { // Note that even without using dol_print_date, CustomFields already formats the date and datetime fields in database in a readable format (universal time, eg: 2015-01-15)
                    $out.=dol_print_date($value, 'day', 'gmt');
                } elseif ($field->column_type == 'datetime') { // Note that even without using dol_print_date, CustomFields already formats the date and datetime fields in database in a readable format (universal time, eg: 2015-01-15)
                    $out.=dol_print_date($value, 'dayhour', 'gmt');
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
     *     @param   field   field object
     *     @param       value           Value to show
     *  @param  outputlangs for multilingual support
     *     @param       moreparam       To add more parameters on html input tags
     *     @return  string              A text string ready to be printed (without input fields and without html entities, just simple text)
     */
    function printFieldPDF($field, $value, $outputlangs='', $moreparam='') {
        $value=$this->printField($field, $value, $outputlangs, $moreparam);

        // Cleaning the html characters if the field contained some
        $value = preg_replace('/<br\s*\/?>/i', "", $value); // replace <br> into line breaks \n - fckeditor already outputs line returns, so we just remove the <br>
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8'); // replace all html characters into text ones (accents, quotes, etc.) and directly into UTF8

        return $value;
    }

    /**
     *  Simplify the printing of the value of a field by accepting a string field name instead of an object
     *  Note: this function caches fields structure and then reuse it automatically to reduce overload when calling multiple times this function (actually it is pretty efficient for CPU and memory!).
     *  @param  fieldname   string field name of the field to print
     *  @param  value       value to show (current value of the field)
     *  @param  outputlangs for multilingual support
     *  @param  moreparam   to add more parameters on html input tags
     *  @return html        An html string ready to be printed
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
     *  Same as simpleprintField but for PDF (without html entities)
     *  Note: this function caches fields structure and then reuse it automatically to reduce overload when calling multiple times this function (actually it is pretty efficient for CPU and memory!).
     *  @param  fieldname   string field name of the field to print
     *  @param  value       value to show (current value of the field)
     *  @param  outputlangs for multilingual support
     *  @param  moreparam   to add more parameters on html input tags
     *     @return  string              A text string ready to be printed (without input fields and without html entities, just simple text)
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
     *  Take a field name and returns the right label for the field, either with the prefix or without. If none is found, we return the normal field name.
     *  @param  fieldname    a field name
     *  @param  outputlangs the language to use to show the right translation of the label
     *  @return string      a label for the field
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

    // Function to strip CustomField's prefix (varprefix and fields_prefix) or any other prefix if specified.
    // It is mainly used as a way to easily detect both 'cf_myfield' and 'myfield' and translate them the same way.
    function stripPrefix($fieldname, $prefix=null) {
        if (empty($prefix)) $prefix = $this->varprefix;
        preg_match('/^'.addslashes($prefix).'/', $fieldname, $matchs); // detect with regex if the prefix is prepended at the beginning of the field's name
        if (count($matchs) > 0) $fieldname = substr($fieldname, strlen($prefix)); // strip the prefix if prefix detected

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
        } elseif (!empty($this->error)) {
            $mesg = $this->error;
        } elseif (!empty($this->errors)) { // in case we filled $this->errors array directly (bypassing $this->addError())
            $mesg = implode(";\n", $this->errors);
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
