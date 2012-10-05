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
 *	\file       htdocs/customfields/lib/customfields_aux.lib.php
 *	\brief      Functions to simplify the use of the CustomFields main class. This library is a Facade design pattern.
 *	\description    Here are stored functions that use the CustomFields main class but can't be implemented inside (eg: because of instanciating a specific CustomFields object for a specific module, or because we need multiple CustomFields objects at once, etc.)
 *	\ingroup    customfields
 */

/**
 *  Fill a specified object with customfields of a specified second object (can be the same or another module's object, this allows to use customfields from other modules)
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
        foreach($linesids as $lineid) {
            $lids[] = $lineid->$prifield;
        }
        // Fetch the (customfields) records of all the lines for this object
        $lines = $customfields->fetch($lids);
    }

    $fkname = 'fk_'.$customfields->module; // foreign key column name in CustomFields's table, storing the id of the (product) line for this object. We need it in order to store lines' datas into a subproperty of $object->customfields->lines->$lineid (we use the line id so that's it's easy to get the relevant line with just the id afterwards)

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
            if (!$linemode) {
                // if in object mode, we simply store the datas in $object->customfields
                if ($prefix) { // prepending the prefix whether it was specified or not
                    $object->customfields->$prefix->$name = $fmvalue; // adding this value to a sub-property of $object to avoid any conflict (default: $object->customfields->cf_myfield)
                } else {
                    $object->customfields->$name = $fmvalue;
                }
            } else {
                // else we are in lines mode, we store the datas in $object->customfields->lineid (so that the customfields are easily accessible knowing just the line's id)
                if ($prefix) { // prepending the prefix whether it was specified or not
                    $object->customfields->lines->$prefix->{$record->$fkname}->$name = $fmvalue; // adding this value to a sub-property of $object to avoid any conflict (default: $object->customfields->lines->$lineid->cf_myfield)
                } else {
                    $object->customfields->lines->{$record->$fkname}->$name = $fmvalue;
                }
            }

            //print("name: $name - trname: $translatedname - field: {$field->column_name} - value: $fmvalue\n"); // debugline

            // Constraints substitution
            // if the customfield has a constraint, we fetch all the datas from this constraint in the referenced table (so that related field to a customfield with constraints will also be substitutable)
            if (!empty($field->referenced_table_name)) {
                $fkrecord = $customfields->fetchAny('*', $field->referenced_table_name, $field->referenced_column_name."='".$value."'"); // we fetch the record in the referenced table

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

?>