<?php
/* Copyright (C) 2012   Stephen Larroque <lrq3000@gmail.com>
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
 *      \file       /yourmodule/class/sqlwrapper.class.php
 *      \ingroup    sqlwrapper
 *      \brief          Auxiliary class for easier and more robust SQL queries (it works on top of the Dolibarr SQL API) - this is an excerpt from the CustomFields class, but is totally independent.
 *      \author     Stephen Larroque
 */

include_once(dirname(__FILE__).'/ext/php4compat.php'); // compatibility with PHP4

/**
 *      \class      sqlwrapper
 *      \brief      Auxiliary class for easier and more robust SQL queries (it works on top of the Dolibarr SQL API)
 */
class SQLWrapper extends compatClass4 // extends CommonObject
{

    /**
     *      Constructor
     *      @param      db      Database handler
     */
    function __construct($db) {
        $this->db = $db;
    }

    /**
    *      Return an object with only lowercase column_names (otherwise, on some OSes like Unix, mysql functions may return uppercase or mixed case column_name)
    *      Note: similar to mysql_fetch_object, but always return lowercase column_name for every items
    *      @param      $res        Mysql/Mysqli/PGsql/SQlite/MSsql resource
    *      @return       $obj         Object containing one row
    */
    function fetch_object($res, $class_name=null, $params=null) {
        $row = $this->db->fetch_object($res); // get the record as an object
        $obj = array_change_key_case((array)$row, CASE_LOWER); // change column_name case to lowercase
        $obj = (object)$obj; // cast back as an object
        return $obj; // return the object
    }

    /**
    *      Fetch any record in the database from any table (not just customfields)
    *      @param	columns		one or several columns (separated by commas) to fetch
    *      @param	table		table where to fetch from
    *      @param	where		where clause (format: column='value'). Can put several where clause separated by AND or OR
    *      @param	orderby	order by clause
    *      @return     int or array of objects         	<0 if KO, array of objects if one or several records found (for more consistency, even if only one record is found, an array is returned)
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
                   $trigger = strtoupper($this->module).'_SQLWRAPPER_FETCHANY';
           }

           // Executing the SQL statement
           $resql = $this->executeSQL($sql,__FUNCTION__,$trigger);

           // Filling the record object
           if ($resql < 0) { // if there's no error
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
                $trigger = strtoupper($this->module).'_SQLWRAPPER_FETCHPRIMARYFIELD';
        }

        // Executing the SQL statement
        $resql = $this->executeSQL($sql,__FUNCTION__, $trigger);

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
}
?>