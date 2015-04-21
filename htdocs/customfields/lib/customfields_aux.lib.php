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
 *	\file       htdocs/customfields/lib/customfields_aux.lib.php
 *	\brief      Functions to simplify the use of the CustomFields main class. This library is a Facade design pattern.
 *	\description    Here are stored functions that use the CustomFields main class but can't be implemented inside (eg: because of instanciating a specific CustomFields object for a specific module, or because we need multiple CustomFields objects at once, etc.)
 *	\ingroup    customfields
 */

/**
 *  Fill a specified object with customfields of a specified second object (can be the same or another module's object, this allows to use customfields from other modules)
 *  NOTE: most of the functionnalities here cannot be implemented directly inside the CustomFields class because they require to instanciate several different CustomFields objects (one for each module that we want to request), and it's impossible in PHP to instanciate a class from within itself.
 *  Also, this function will automatically apply Smart Value Substitution and Recursive Remote Fields Access for constrained fields (fetching referenced fields from remote tables, and recursively if the referenced table itself contains a constrained field).
 *  @param $object              Object          to object (object where customfields will be stored)
 *  @param $fromobject     Object          from object (needs to at least contain 2 fields: table_element (module's name) and id (or $idvar, which contain the record's id you want to fetch)) - you can also create a dummy $fromobject with these only two fields to use this function
 *  @param $prefix               string         store customfields in a subobject (eg: $prefix = 'mycategory' will store customfield cf_myfield inside $object->customfields->mycategory->cf_myfield)
 *  @param $pdfformat      null/false/true      beautify the customfields values? (null = no beautify nor translation; false = beautify and translate; true = translation and pdf beautify with html entities encoding)
 *  @param $linemode        false/true             false=process object's customfields; true=process object's lines' customfields (you should not touch this parameter, use customfields_fill_object_lines() instead)
 *
 *  @return  null/int(-1)/CustomFields object       either null if there's no customfields found, either -1 if an error happened (table_element or id missing in $fromobject), either original $object populated and return a $customfields object generated from $fromobject (which allows to get the geometry of the customfields table that served to fill the $object, useful to translate label with findLabel() or findLabelPDF() )
 *
 *  Note: values are returned formatted and translated (by default normal, or PDF wise or not formatted if specified), but labels (keys) are NOT returned formatted (not translated by default because a field should always be accessible by a base name, whatever the translation is). You can always translate them by using $langs->load('customfields-user@customfields'); $key=array_keys(get_object_vars($object->customfields)); $langs->trans($key[xxx]);
 */
function customfields_fill_object(&$object,$fromobject = null, $outputlangs = null, $prefix = null,$pdfformat = false,$linemode=false) {
    global $conf, $db;

    if (!is_object($object)) $object = new stdClass();

    if (!isset($fromobject)) $fromobject = $object; // by default, $fromobject is the same as $object

    // -- Include necessary files
    include_once(dirname(__FILE__).'/../class/customfields.class.php'); // to fetch customfields database
    include(dirname(__FILE__).'/../conf/conf_customfields.lib.php'); // to detect the current module's parameters
    include_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php'); // to parse module's parameters (from config)

    // -- Find the current record's id
    // set id variable if specified in $modulesarray (by default = 'rowid' or 'id', but can be 'socid', and other fancy stuffs)
    $idvar = 'rowid';
    $tmpmod = array_extract_recursive(array('table_element'=>$fromobject->table_element), $modulesarray); // Extract the subarray containing the found context
    if (isset($tmpmod[0]['idvar'])) $idvar = $tmpmod[0]['idvar'];
    // fetch the id (might be stored in an $idvar field or simply in a standard 'id' field)
    if (isset($fromobject->$idvar)) {
        $id = $fromobject->$idvar;
    } elseif (isset($fromobject->rowid)) {
        $id = $fromobject->rowid;
    } else {
        $id = $fromobject->id;
    }

    if (!$linemode) { // object customfields
        if (!isset($fromobject->table_element) or !isset($id)) return -1; // we need at least the table_element and an id in $fromobject. If one or both is missing, we quit with an error
    } else { // object's lines' customfields
        if (!isset($fromobject->table_element_line) or !isset($id)) return -1; // we need at least the table_element_line and an id in $fromobject. If one or both is missing, we quit with an error
    }

    // -- Fetch customfields data
    // Loading a CustomFields object tailored for the current module
    if (!$linemode) {
        $customfields = new CustomFields($db, $fromobject->table_element);
    } else {
        $customfields = new CustomFields($db, $fromobject->table_element_line);
    }

    if (!$customfields->probeTable()) return null; // if the customfields table does not exist (CF not configured for this module), then we simply exit

    // Fetch the customfields (columns names)
    $columns = $customfields->fetchAllFieldsStruct();

    if (!$columns) return null; // return nothing if there's not even at least one customfield configured in the database

    // Fetch the current record (saved data, what we want to print in the end)
    if (!$linemode) { // object mode, we just have one record to fetch
        $record = $customfields->fetch($id);
        $lines = array($record); // just to trick the foreach loop to work once
    } else { // lines mode, we have several records (one for each line) to fetch
        // Fetch the ids of every (product) lines for this object (because we only have the object's id, we need the lines' ids linked to this object)
        $prifield = $customfields->fetchPrimaryField(MAIN_DB_PREFIX.$fromobject->table_element_line); // fetch the column name of the primary row for the lines table
        $linesids = $customfields->fetchAny($prifield, MAIN_DB_PREFIX.$fromobject->table_element_line, $fromobject->fk_element.'='.$id); // fetch the lines' ids linked to this object's id
        // Preparing the lines' ids in an array
        $lids = array();
        if (empty($linesids)) return null;
        foreach($linesids as $lineid) {
            $lids[] = $lineid->$prifield;
        }
        // Fetch the (customfields) records of all the lines for this object
        $lines = $customfields->fetch($lids);
    }

    $fkname = 'fk_'.$customfields->module; // foreign key column name in CustomFields's table, storing the id of the (product) line for this object. We need it in order to store lines' datas into a subproperty of $object->customfields->lines->$lineid (we use the line id so that's it's easy to get the relevant line with just the id afterwards)

    if (empty($lines)) return null;

    // For every $lines, we process one $record (which is a product line if $linemode is enabled) and store it in $object
    foreach($lines as $record) {

        // -- Begin to populate the substitution array with customfields data
        foreach ($columns as $field) { // One field at a time
            // Formatting the data
            $name = $customfields->varprefix.$field->column_name; // name of the property (name of the customfield column, eg: cf_user)
            //$translatedname = $customfields->findLabelPDF($field->column_name, $outputlangs); // UNUSED. Label of the customfield (as it was translated in customfields-user.lang, eg: cf_user might become 'My User')
            $value = '';
            if (isset($record->{$field->column_name})) $value = $record->{$field->column_name}; // unformatted value (eg: cf_user = 2 = user id), we need it in order to print a beautified value (eg: ids replaced by strings) and to make the link for constraints
            if (!isset($pdfformat)) { // no formatting
                $fmvalue = $value;
            } elseif ($pdfformat) {
                $fmvalue = $customfields->printFieldPDF($field, $value, $outputlangs); // PDF formatted and translated value (cleaned and properly formatted, eg: cf_user value = 'John Doe') of the customfield
            } else {
                $fmvalue = $customfields->printField($field, $value, $outputlangs); // translated value
            }

            // Add this customfield's record's datas to the $object
            if (!isset($object->customfields) or !is_object($object->customfields)) $object->customfields = new stdClass();
            if (!$linemode) {
                // if in object mode, we simply store the datas in $object->customfields
                if ($prefix) { // prepending the prefix whether it was specified or not
                    if (!is_object($object->customfields->$prefix)) $object->customfields->$prefix = new stdClass();
                    $object->customfields->$prefix->$name = $fmvalue; // adding this value to a sub-property of $object to avoid any conflict (default: $object->customfields->cf_myfield)
                } else {
                    $object->customfields->$name = $fmvalue;
                }
            } else {
                if (!isset($object->customfields->lines) or !is_object($object->customfields->lines)) $object->customfields->lines = new stdClass();
                // else we are in lines mode, we store the datas in $object->customfields->lineid (so that the customfields are easily accessible knowing just the line's id)
                if ($prefix) { // prepending the prefix whether it was specified or not
                    if (!is_object($object->customfields->lines->$prefix->{$record->$fkname})) {
                        $object->customfields->lines->$prefix = new stdClass();
                        $object->customfields->lines->$prefix->{$record->$fkname} = new stdClass();
                    }
                    $object->customfields->lines->$prefix->{$record->$fkname}->$name = $fmvalue; // adding this value to a sub-property of $object to avoid any conflict (default: $object->customfields->lines->$lineid->cf_myfield)
                } else {
                    if (!isset($object->customfields->lines->{$record->$fkname}) or !is_object($object->customfields->lines->{$record->$fkname})) $object->customfields->lines->{$record->$fkname} = new stdClass();
                    $object->customfields->lines->{$record->$fkname}->$name = $fmvalue;
                }
            }

            //print("name: $name - trname: $translatedname - field: {$field->column_name} - value: $fmvalue\n"); // debugline

            // Constraints substitution (easy remote fields access)
            // if the customfield has a constraint, we fetch all the datas from this constraint in the referenced table (so that related field to a customfield with constraints will also be substituable)
            if (!empty($field->referenced_table_name) and !empty($value)) { // and only if a value was selected, else it makes little sense to fetch the remote fields (except with AJAX Cascade call where we want to fetch all referenced records, but this is managed in the ajax wrapper lib without using this facade api).
                //$fkrecord = $customfields->fetchAny('*', $field->referenced_table_name, $field->referenced_column_name."='".$value."'"); // we fetch the record in the referenced table. Equivalent to the fetchReferencedValuesList command below using $id=$value and $allcolumns=true.
                //$fkrecord = $customfields->fetchReferencedValuesList($field, $value, null, true); // works correctly but is not recursive. Use fetchReferencedValuesRec() for a recursive fetching.
                $fkrecord = fetchReferencedValuesRec($customfields, $field, $value, $outputlangs, $pdfformat); // we fetch the record in the referenced table. This is equivalent to the fetchAny command above.

                if (!empty($fkrecord[0])) { // normally, this should never happen since a constrained customfield is always linked to a foreign record, but in case that happens, we skip to avoid errors...
                    foreach ($fkrecord[0] as $column_name => $value) { // for each foreign record, we add the value to an odt variable (format eg: base field: cf_user, constrants fields: cf_user_name, cf_user_firstname, etc..)
                        if (!$linemode) {
                            // Prepending the prefix if it was specified
                            if ($prefix) {
                                $object->customfields->$prefix->{$name.'_'.$column_name} = $value; // Saving the field's data in the object
                            } else {
                                $object->customfields->{$name.'_'.$column_name} = $value;
                            }
                        } else {
                            if ($prefix) { // prepending the prefix whether it was specified or not
                                $object->customfields->lines->$prefix->{$record->$fkname}->{$name.'_'.$column_name} = $value; // Saving the field's data in the object
                            } else {
                                $object->customfields->lines->{$record->$fkname}->{$name.'_'.$column_name} = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    return $customfields;
}


/*  Fill a specified object with customfields of a specified second object (can be the same or another module's object, this allows to use customfields from other modules)
 *  @param $object              Object          to object (object where customfields will be stored)
 *  @param $fromobject     Object          from object (needs to at least contain 2 fields: table_element (module's name) and id (or $idvar, which contain the record's id you want to fetch)) - you can also create a dummy $fromobject with these only two fields to use this function
 *  @param $prefix               string         store customfields in a subobject (eg: $prefix = 'mycategory' will store customfield cf_myfield inside $object->customfields->mycategory->cf_myfield)
 *  @param $pdfformat      null/false/true      beautify the customfields values? (null = no beautify nor translation; false = beautify and translate; true = translation and pdf beautify with html entities encoding)
 *  @param $linemode        false/true             false=process object's customfields; true=process object's lines' customfields (you should not touch this parameter, use customfields_fill_object_lines() instead)
 *
 *  @return  null/int(-1)/CustomFields object       either null if there's no customfields found, either -1 if an error happened (table_element or id missing in $fromobject), either original $object populated and return a $customfields object generated from $fromobject (which allows to get the geometry of the customfields table that served to fill the $object, useful to translate label with findLabel() or findLabelPDF() )
 */
/* Summary of how it worked and was implemented
 - fetch id object
 - fetch table_element_line relative to id -> return linesid
 - fetch lines related to linesid -> return lines records
 - foreach lines, process them and store them in $object->customfields->lines->rowid
*/
function customfields_fill_object_lines(&$object,$fromobject = null, $outputlangs = null, $prefix = null,$pdfformat = false) {
    return customfields_fill_object($object, $fromobject, $outputlangs, $prefix, $pdfformat, true);
}


/**
 *  Recopy custom fields either selectively on conversion, or all fields without discrimination on cloning
 *  Note: this function will automatically select the correct action depending on the from object and target object class.
 *  @param $object              Object          target object (object where customfields will be copied to)
 *  @param $fromobject     Object          source object (needs to at least contain 2 fields: table_element (module's name) and id (or $idvar, which contain the record's id you want to fetch)) - you can also create a dummy $fromobject with these only two fields to use this function
 *  @param $action2            String           type of action: 'cloning' or 'conversion'
 *  @param $store_object  Bool           store result in $object, or in database directly (default to database if false)
 *
 *  @return  Object[]   An array of two objects: $customfields and $customfields_lines, which are instances of the CustomFields class and can then be used to fetch the errors using $customfields->error.
 */
function customfields_clone_or_recopy($object, $fromobject, $action2 = null, $store_object = false) {
    global $db, $user;
    include_once(dirname(__FILE__).'/../class/customfields.class.php');

    // Deduce the current action if not specified
    if (empty($action2)) {
        // If the source object and target object are instances of the same class, we are cloning
        if ( !strcmp(get_class($object), get_class($fromobject)) ) {
            $action2 = 'cloning';
        // Else, the source object and target object are instances of different classes, we are converting
        } else {
            $action2 = 'conversion';
        }
    }

    //------------------------------------------------------------------------------------
    //== Clone or Recopy module's custom fields (not lines fields)
    // Load the CustomFields class for the target object (the current $object that is being created)
    $customfields = new CustomFields($db, $object->table_element);

    // Check that there is something to do (there is an origin object and there is a customfields table for the target object)
    if (isset($object->origin_id) && $customfields->probeTable()) {

        //-- Clone module's custom fields (not lines fields)
        if ($action2 === 'cloning') {
            // Saving the data (creating a record)
            $rtncode = $customfields->createFromClone($object->origin_id, $object->id);

        //-- Recopy module's custom fields (not lines fields)
        } elseif($action2 === 'conversion') {
            // Fetch the target customfields structures (columns names)
            $columns = $customfields->fetchAllFieldsStruct();

            // Fetch the origin custom fields
            if (isset($fromobject) and !empty($fromobject->table_element)) $origin_table_element = $fromobject->table_element; else $origin_table_element = $object->origin; // get the origin table_element (if not defined, we set the origin name as the table_element)
            $customfields_origin = new CustomFields($db, $origin_table_element);
            if (isset($object->origin_id)) {
                $originid = $object->origin_id;
            } elseif (isset($object->originid)) { // for backward compatibility
                $originid = $object->originid;
            } elseif (isset($fromobject->id)) {
                $originid = $fromobject->id;
            } elseif (isset($fromobject->rowid)) {
                $originid = $fromobject->rowid;
            }
            $records_origin = $customfields_origin->fetch($originid, null, 1); // fetch only the values, we don't need the structures of the origin custom fields

            // For each target custom field, we will check if it has to be recopied from another object. If true, we will copy over
            foreach ($columns as $field) {
                // Get the original field to recopy from (by default and if empty, we use the same field name as the target field)
                if (!empty($field->extra['recopy_field'])) $field_origin = $field->extra['recopy_field']; else $field_origin = $field->column_name;

                // Check if the target field has to be recopied, and if the field exists in the origin object (because we may want to copy a field that appears only in client orders but not in propales, etc.. So depending on the origin object that was converted to the target object, the origin field may or not exist and we must check that)
                if (!empty($field->extra['recopy']) and isset($records_origin->$field_origin)) {

                    // Store the recopied value ...
                    if ($store_object) { // ... into the $object
                        $object->{$customfields->varprefix.$field->column_name} = $records_origin->$field_origin;
                    } else { // ... or into database
                        // Forging the new record
                        $newrecord = new stdClass(); // initializing the object explicitly (to avoid php > 5.3 warnings)
                        $newrecord->{$field->column_name} = $records_origin->$field_origin; // we create a new record object with the field and the id
                        $newrecord->id = $object->id;

                        // Commit recopied value into the database
                        $customfields->update($newrecord, 1);

                        // Forcefully call all overloading functions on the target object, in case the overloading functions must refresh something like the total price
                        $overloading_params = array(
                                                            'action'=>GETPOST('action'),
                                                            'currentmodule'=>$customfields->module,
                                                            'customfields'=>$customfields,
                                                            'object'=>$object,
                                                            'parameters'=>array(),
                                                            'id'=>$line->rowid,
                                                            'field'=>$field,
                                                            'fieldname'=>strtolower($field->column_name),
                                                            'value'=>$newrecord->{$field->column_name},
                                                            'fields'=>$columns,
                                                            'user'=>$user,
                                                            'idvar'=>'',
                                                            'rightok'=>true,
                                                            );
                        $overloading_types = array('create', 'edit', 'save', 'aftersave');
                        call_overloading_function($overloading_types, $customfields->module, $field->column_name, $overloading_params);
                    }
                }
            }
        }
    }

    //-----------------------------------------------------------------------------------
    //== Clone or Recopy module's products lines' custom fields
    // TODO: there's currently no way to find the correlation between a target line record and an origin line record, because target lines are created totally independently (you can check in Dolibarr's code, it simply creates new records in a loop for each origin record, without keeping a track of the origin line id), thus we here simply suppose that since every product line is created sequentially in a loop, the first target product line matchs the first origin product line, and so on.

    // Create an empty object to return it
    $customfields_lines = new stdClass();

    // Proceed only if the object contains lines
    if (!empty($object->table_element_line)) {
        // Load the target products' lines
        $lines = $object->lines;
        // If the products' lines aren't loaded, we force loading them
        if (empty($lines) && method_exists($object,'fetch_lines'))  {
            // Load the products' lines in the object
            $lines = $object->fetch_lines();
            // Sometimes fetch_lines() returns the lines, sometimes it returns an int code error and the lines are directly loaded into the $object, so in this case we get $object->lines...
            if (!is_array($lines) and isset($object->lines)) $lines = $object->lines;
        }

        // Load the source products' lines
        $lines_origin = $fromobject->lines;
        // If the products' lines aren't loaded, we force loading them
        if (empty($lines_origin) && method_exists($fromobject,'fetch_lines'))  {
            // Load the products' lines in the object
            $lines_origin = $fromobject->fetch_lines();
            // Sometimes fetch_lines() returns the lines, sometimes it returns an int code error and the lines are directly loaded into the $object, so in this case we get $object->lines...
            if (!is_array($lines_origin) and isset($fromobject->lines)) $lines_origin = $fromobject->lines;
        }

        // Load the CustomFields class for the target object lines (the current $object that is being created)
        $customfields_lines = new CustomFields($db, $object->table_element_line);

        // Check that there is something to do (there is an origin object and there is a customfields table for the target object)
        if (!empty($lines) && !empty($lines_origin) && isset($fromobject->table_element_line) && $customfields_lines->probeTable()) {

            // Load the origin table_element_line
            $origin_table_element_line = $fromobject->table_element_line;

            // Load the CustomFields class for the source object lines
            $customfields_lines_origin = new CustomFields($db, $origin_table_element_line);

            // Fetching origin products lines ids
            $lids = array();
            foreach ($lines_origin as $line_origin) {
                $lids[] = $line_origin->rowid;
            }

            /* Alternative way to get the origin products lines ids
            if (isset($object->origin_id)) $originid = $object->origin_id; else $originid = $object->originid; // for backward compatibility

            // Fetch the ids of every (product) lines for this object (because we only have the object's id, we need the lines' ids linked to this object)
            $prifield = $customfields_lines_origin->fetchPrimaryField(MAIN_DB_PREFIX.$origin_table_element_line); // fetch the column name of the primary row for the lines table
            $linesids = $customfields->fetchAny($prifield, MAIN_DB_PREFIX.$origin_table_element_line, $fromobject->fk_element.'='.$originid); // fetch the lines' ids linked to this object's id
            // Preparing the lines' ids in an array
            $lids = array();
            if (!empty($linesids)) {
                foreach($linesids as $lineid) {
                    $lids[] = $lineid->$prifield;
                }
            }
            */

            if (!empty($lids)) { // Continue only if there is at least one product line

                // Fetch the values of the origin custom fields lines
                $records_origin = $customfields_lines_origin->fetch($lids, null, 1); // fetch only the values, we don't need the structures of the origin custom fields

                if (empty($records_origin)) return null; // stop if there's no custom fields for this module (thus there are no parent custom fields even if there is a parent object)

                //-- Match sequentially the origin custom fields with the origin products lines
                // First, we set the keys of the customfields records to the products lines rowid
                $tmp = array();
                foreach ($records_origin as $r) {
                    $tmp[$r->{'fk_'.$origin_table_element_line}] = $r;
                }
                $records_origin = $tmp;
                unset($tmp);

                // Then we match the customfields records to the sequential id of the products lines
                $tmp = array();
                foreach ($lines_origin as $id=>$line) {
                    $tmp[$id] = $records_origin[$line->rowid];
                }
                $records_origin = $tmp;
                unset($tmp);
                // At this point, we should have all our customfields ordered from 0 to N (where N is the number of lines), the same as origin products lines, and we HOPE that this sequential id order is the same as the target products lines (because this is how the target lines are created: sequentially from the origin lines)

                // Reset records keys (to begin from 0 instead of the line's rowid, since origin and target rowid can't match)
                //ksort($records_origin);
                //$records_origin = array_merge($records_origin);

                //-- Clone module's products lines' custom fields
                if ($action2 == 'cloning') {
                    // We clone all custom fields for each product's line
                    foreach ($lines as $id=>$line) {
                        // Saving the data (creating a record)
                        $rtncode = $customfields_lines->createFromClone($records_origin[$id]->{'fk_'.$origin_table_element_line}, $line->rowid);
                    }

                //-- Recopy module's products lines' custom fields
                } elseif ($action2 == 'conversion') {

                    // Fetch the target customfields structures (columns names)
                    $columns_lines = $customfields_lines->fetchAllFieldsStruct();

                    if (empty($columns_lines)) return null; // stop if there's no custom fields for this module (thus there are no parent custom fields even if there is a parent object)

                    // For each target custom field, we will check if it has to be recopied from another object. If true, we
                    foreach ($columns_lines as $field) {
                        // Get the original field to recopy from (by default and if empty, we use the same field name as the target field)
                        if (!empty($field->extra['recopy_field'])) $field_origin = $field->extra['recopy_field']; else $field_origin = $field->column_name;

                        // Check if the target field has to be recopied, and if the field exists in the origin object (because we may want to copy a field that appears only in client orders but not in propales, etc.. So depending on the origin object that was converted to the target object, the origin field may or not exist and we must check that)
                        if (!empty($field->extra['recopy'])) {
                            // We fill the recopy custom field for each product's line
                            foreach ($lines as $id=>$line) {

                                // If that the field exists in the origin line (the custom field may not exists in the origin module)
                                if (isset($records_origin[$id]->$field_origin)) {

                                    // Store the recopied value ...
                                    // ... in the $object
                                    if ($store_object) {
                                        $object->customfields->lines->{$line->rowid} = $records_origin[$id]->$field_origin;
                                    // ... or in the database
                                    } else {
                                        // Forging the new record
                                        $newrecord = new stdClass(); // initializing the object explicitly (to avoid php > 5.3 warnings)
                                        $newrecord->{$field->column_name} = $records_origin[$id]->$field_origin; // we create a new record object with the field and the id
                                        $newrecord->id = $line->rowid;

                                        // Recopy the field value (commit into the database)
                                        $customfields_lines->update($newrecord, 1);

                                        // Forcefully call all overloading functions on the target object, in case the overloading functions must refresh something like the total price
                                        $overloading_params = array(
                                                                            'action'=>GETPOST('action'),
                                                                            'currentmodule'=>$customfields_lines->module,
                                                                            'customfields'=>$customfields_lines,
                                                                            'object'=>$object,
                                                                            'parameters'=>array(),
                                                                            'id'=>$line->rowid,
                                                                            'field'=>$field,
                                                                            'fieldname'=>strtolower($field->column_name),
                                                                            'value'=>$newrecord->{$field->column_name},
                                                                            'fields'=>$columns_lines,
                                                                            'user'=>$user,
                                                                            'idvar'=>'',
                                                                            'rightok'=>true,
                                                                            );
                                        $overloading_types = array('create', 'edit', 'save', 'aftersave');
                                        call_overloading_function($overloading_types, $customfields_lines->module, $field->column_name, $overloading_params);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /* DEBUG
    print_r($lines);
    print_r($lines_origin);
    die();
    */

    // Return both generated customfields objects (useful to extract the errors)
    return array($customfields, $customfields_lines);
}

/*  Fetch referenced values of a constrained field recursively, aka Recursive Remote Fields Access (fetching referenced fields from remote tables, and recursively if the referenced table itself contains a constrained field).
*   This function relies on CustomFields->fetchReferencedValuesList(), and is in fact mostly a recursive implementation of this function.
 *  Also applies Smart Value Substitution.
 *  Note that for recursively fetched referenced fields, the name of these fields will be appended by all parents fields. For example, if a constrained field "cf_username" links to llx_user, and llx_user has a constrained field "cf_invoice" linked to llx_facture, then the final value will be stored inside $fkrecord[0]["cf_username_cf_invoice"].
 *
 *  @param $customfields Object     CustomFields class instance for the target field (necessary to know which table_element we are working one).
 *  @param $field               Object     Field object (from a call to CustomFields->fetchFieldStruct()).
 *  @param $id                   int            Rowid of the record we want to fetch (this is the rowid of the Dolibarr's module object, ie: object->rowid, NOT CustomFields record id).
 *  @param $pdfformat      null/false/true      beautify the customfields values? (null = no beautify nor translation; false = beautify and translate; true = translation and pdf beautify with html entities encoding)
 *  @param $recursive      false/true Recursively fetch the constrained field ? (by default true, this enable Recursive Remote Fields Access).
 *  @param $blacklist         array[]     private variable, this tracks the table_element (database tables) we already visited, to avoid an infinite recursion loop.
 *  @param $level              int             private variable, to track the recursion depth level.
 *
 *  @return  null/int(-1)/array       either null if there's nothing found, either -1 if an error happened, either an associative array $fkrecord where $fkrecord[0] contains each referenced field and its value.
 */
function fetchReferencedValuesRec($customfields, $field, $id, $outputlangs=null, $pdfformat=false, $recursive=true, $blacklist=null, $level=0) {
    global $db;

    // Include required libs
    include_once(dirname(__FILE__).'/../class/customfields.class.php'); // to fetch customfields database and referenced remote fields
    include_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php'); // to use auxiliary functions to merge arrays

    $fkrecord = null;

    // If the specified custom field has a constraint
    if (!empty($field->referenced_table_name) and !empty($id)) {

        // -- Fetch the referenced fields (the standard fields first, here there's no custom field yet) for the current constrained field.
        $fkrecord = $customfields->fetchReferencedValuesList($field, $id, null, true);

        /* DEBUG
        print_r($blacklist);
        if ($level > 0) {
            print('<pre>Level:'.$level);
            print_r($fkrecord);
            if ($fkrecord == -1) $customfields->printErrors();
            print('</pre>');
        }
        */

        // If there's no referenced field or if it's empty or the current constrained field has no value (thus it doesn't point to a particular referenced record yet), we quit (by returning an empty array so that recursivity works)
        if ($fkrecord === -1) return array();
        // Else, the referenced fields were fetched successfully.

        // Strip the table prefix to extract only the table_name
        $remote_cf_table = $customfields->stripPrefix($field->referenced_table_name, MAIN_DB_PREFIX);
        // Infinite recursion loop prevention: if the current table_element is in the $blacklist array (meaning we already visited this table in another recursion level), we stop this recursion.
        if (!empty($blacklist) and in_array($remote_cf_table, $blacklist, true)) return array();

        // -- Fetch the custom fields for the referenced table (eg: referenced table is "llx_user", we check if there is a table "llx_user_customfields" to fetch custom fields for the referenced table "llx_user")
        $customfields_remote = new CustomFields($db, $remote_cf_table); // Instanciate a CustomFields object for the referenced table
        // Check if the referenced table has a customfields table (CustomFields was initialized for the referenced table).
        if ($customfields_remote->probeTable()) {
            // Referenced table possess custom fields: fetch them for the current record rowid
            $cf_fkrecord = $customfields_remote->fetch($id);

            // If custom fields were created for the current record, we will extract them and merge them to the list of records (and maybe recursively fetch the constrained field if option is enabled)
            if (!empty($cf_fkrecord)) {
                // Add cf_ prefix to stay consistent and avoid collisions (Dolibarr's object fields with the same names as some custom fields, thus one of them will be overwritten).
                $cf_fkrecord_prefixed = addPrefixArr($cf_fkrecord, $customfields->varprefix);
                // Merge the two records: referenced table's fields with referenced table's custom fields (but give the precedence to the Dolibarr's object's record over the custom fields, to avoid replacing the id and stuff like that)
                $fkrecord[0] = (object)array_replace_recursive((array)$cf_fkrecord_prefixed, (array)$fkrecord[0]); // the last array is always given precedence over the first one (the second one will overwrite values from the first one)

                // -- Recursively fetch sub-referenced fields (current constrained field -> referenced fields -> referenced custom fields, with constrained fields -> subreferenced fields from referenced constrained fields -> subreferenced custom fields etc...)
                if ($recursive === true) {
                    // Get the structure and extra options of referenced custom fields
                    $r_fields = $customfields_remote->fetchAllFieldsStruct();

                    // For each remote custom field, we will check if it's a constrained field
                    foreach ($r_fields as $r_field) {
                        $r_value = $cf_fkrecord->{$r_field->column_name}; // value of the remote custom field. Will contain the id of a second referenced table if it's a constrained field.
                        $r_key = $customfields->varprefix.$r_field->column_name; // remote custom field key

                        // Update custom field's value with beautified formatting if enabled
                        if (!isset($pdfformat)) { // no formatting if $pdfformat == null
                            //$fkrecord[0]->$r_key = $r_value; // value is already set at this point, so we don't have to do nothing here.
                        } elseif ($pdfformat) { // PDF formatted and translated value (cleaned and properly formatted, eg: cf_user value = 'John Doe') of the customfield
                            $fkrecord[0]->$r_key = $customfields_remote->printFieldPDF($r_field, $r_value, $outputlangs);
                        } else { // if false, beautified and translated value (eg: universal date format is converted to Dolibarr locale format)
                            $fkrecord[0]->$r_key = $customfields_remote->printField($r_field, $r_value, $outputlangs);
                        }

                        // If this remote custom field is constrained (and there is a record id to point to), we recursively fetch the subreferenced fields
                        if (!empty($r_field->referenced_table_name) and !empty($r_value)) {
                            // Infinite recursion loop prevention: initialize and add current referenced table to the blacklist, to avoid infinite recursion
                            if (empty($blacklist)) $blacklist = array();
                            $blacklist[] = $remote_cf_table;

                            // Recursively fetch the subreferenced fields
                            $fkrecord_rec = fetchReferencedValuesRec($customfields_remote, $r_field, $r_value, $outputlangs, $pdfformat, $recursive, $blacklist, ++$level);

                            // If there's any subreferenced fields for this record, merge them with the list of records we will return
                            if (!empty($fkrecord_rec)) {
                                // Recursive Smart Value Substitution: update the constrained custom field with the substitued value (else the id will be shown, but since we recursively fetched the subreferenced fields, we can update here without any additional cost). Just be sure to conform to the prefixes we added.
                                $fkrecord[0]->$r_key = $fkrecord_rec[0]->{$r_field->column_name};
                                // Remove the Smart Value Substituted variable from the recursively subreferenced fields, because else it will be duplicated, and there can be collisions (eg: this referenced table has a custom field named "lastname" and the subreferenced  table also has a custom field named "lastname": we can't know which one is what, thus the rule we use here is that the svs substituted field is stored at the parent referenced table, not at the subreferenced table).
                                unset($fkrecord_rec[0]->{$r_field->column_name});

                                // Prefix each key with the current custom field's name to avoid any confusion and collision with other custom fields that may fetch from the same referenced table
                                $rec_prefix = $r_key.$customfields->svsdelimiter;
                                $fkrecord_rec[0] = addPrefixArr($fkrecord_rec[0], $rec_prefix);

                                // Merge the two records: the currently referenced record with the subreferenced record referenced by the current reference record's custom field (a bit complicated to explain isn't it?) (but give the precedence to the Dolibarr's object's record over the recursively fetched referenced fields)
                                $fkrecord[0] = (object)array_replace_recursive((array)$fkrecord_rec[0], (array)$fkrecord[0]);
                            }
                        }
                    }
                }
            }
        }
    }

    // Return the extracted referenced records (at level 0 this is the final record merged with all recursively extracted subrecords; else at other levels this array of records will be merged at the level above)
    return $fkrecord;
}

// Prefix each key with the referenced table's name to avoid any confusion
function addPrefixArr($array, $prefix) {
    $array2 = array();
    foreach ($array as $k=>$v) {
        $array2[$prefix.$k] = $v;
    }
    if (is_object($array)) $array2 = (object)$array2;
    return $array2;
}

// Easy function to call an overloading function
// $type can be either a string, the type of the overloading function to call, or an array of strings to call multiple overloading functions at once.
// $currentmodule is the module on which we will call the overloading function.
// $fieldname is the name of the field which overloading function we want to call.
// $params is an dictionary-type/hash-type array, containing all the necessary parameters to supply to the overloading functions that you want to call. Eg: $params = array('currentmodule'=>$currentmodule, 'object'=>$object, ...);
function call_overloading_function($type, $currentmodule, $fieldname, $params) {
    if (file_exists(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php')) {
        include_once(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php'); // to automatically trigger user's function overloading (ie: save and aftersave)
    } else { // else we've got nothing to do, return
        return;
    }

    $fieldname = strtolower($fieldname); // the name of the customfield (which is the property of the record)

    if (!strcmp($type, 'view') or (is_array($type) and in_array('view', $type))) {
        $customfunc_view = 'customfields_field_view_'.$currentmodule.'_'.$fieldname;
        $customfunc_viewfull = 'customfields_field_viewfull_'.$currentmodule.'_'.$fieldname;

        if (function_exists($customfunc_viewfull)) { // a full function just does everything, CF just stop processing the field here
            $customfunc_viewfull($params['currentmodule'], $params['object'], $params['parameters'], $params['action'], $params['user'], $params['idvar'], $params['rightok'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        } elseif (function_exists($customfunc_view)) { // here the function may modify any parameter it wants (by referencing the values with a pointer like &$values), and then CF will continue to process the printing of the HTML field with these modified variables
            $customfunc_view($params['currentmodule'], $params['object'], $params['parameters'], $params['action'], $params['user'], $params['idvar'], $params['rightok'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        }
    }
    if (!strcmp($type, 'create') or (is_array($type) and in_array('create', $type))) {
        $customfunc_create = 'customfields_field_create_'.$currentmodule.'_'.$fieldname;
        $customfunc_createfull = 'customfields_field_createfull_'.$currentmodule.'_'.$fieldname;

        if (function_exists($customfunc_createfull)) { // a full function just does everything, CF just stop processing the field here
            $customfunc_createfull($params['currentmodule'], $params['object'], $params['parameters'], $params['action'], $params['id'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        } elseif (function_exists($customfunc_create)) { // here the function may modify any parameter it wants (by referencing the values with a pointer like &$values), and then CF will continue to process the printing of the HTML field with these modified variables
            $customfunc_create($params['currentmodule'], $params['object'], $params['parameters'], $params['action'], $params['id'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        }
    }
    if (!strcmp($type, 'edit') or (is_array($type) and in_array('edit', $type))) {
        $customfunc_edit = 'customfields_field_editview_'.$currentmodule.'_'.$fieldname;
        $customfunc_editviewfull = 'customfields_field_editviewfull_'.$currentmodule.'_'.$fieldname;
        if (function_exists($customfunc_editviewfull)) { // use custom function if available, with full control (the user has to print himself, CustomFields won't do any post-processing)
            $customfunc_editviewfull($params['currentmodule'], $params['object'], $params['parameters'], $params['action'], $params['user'], $params['idvar'], $params['rightok'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        } elseif (function_exists($customfunc_edit)) { // use custom function if available, with partial control (the user can control the value and field but CustomFields will manage the post-processing and printing). Can also be used to manage manually a custom cascaded field's options.
            $customfunc_edit($params['currentmodule'], $params['object'], $params['parameters'], $params['action'], $params['user'], $params['idvar'], $params['rightok'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        }
    }
    if (!strcmp($type, 'save') or (is_array($type) and in_array('save', $type))) {
        $customfunc_save = 'customfields_field_save_'.$currentmodule.'_'.$fieldname;
        $customfunc_savefull = 'customfields_field_savefull_'.$currentmodule.'_'.$fieldname;
        if (function_exists($customfunc_savefull)) { // here one can STOP CustomFields processing, and one can then do the processing by oneself
            $customfunc_savefull($params['currentmodule'], $params['object'], $params['action'], $params['user'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        } elseif (function_exists($customfunc_save)) { // here the user can just modify the values and the field and then CustomFields will save them into the database
            $customfunc_save($params['currentmodule'], $params['object'], $params['action'], $params['user'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
        }
    }
    if (!strcmp($type, 'aftersave') or (is_array($type) and in_array('aftersave', $type))) {
        // Calling custom functions after submitting the data to the database
        $customfunc_aftersave = 'customfields_field_aftersave_'.$currentmodule.'_'.$fieldname;
        if (function_exists($customfunc_aftersave)) $customfunc_aftersave($params['currentmodule'], $params['object'], $params['action'], $params['user'], $params['customfields'], $params['field'], $params['fieldname'], $params['value'], $params['fields']);
    }
}


?>
