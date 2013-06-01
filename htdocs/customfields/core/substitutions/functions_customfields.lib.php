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
 *      \file       htdocs/core/modules/substitutions/functions_customfields.lib.php
 *      \ingroup    customfields
 *      \brief      Substition function for the ODT templating (render accessible all the customfields variable)
 *		\author		Stephen Larroque
 */

/** 		Function called to complete the substitution array (before generating on ODT, or a personalized email)
 * 		functions xxx_completesubstitutionarray are called by make_substitutions() if file
 * 		is inside directory htdocs/includes/modules/substitutions
 *
 *		@param	array		$substitutionarray	Array with substitution key=>val
 *		@param	Translate	$langs			Output langs
 *		@param	Object		$object			Object to use to get values
 * 		@return	void					The entry parameter $substitutionarray is modified
 */

function generic_tag_filling(&$substitutionarray, $object) {
   // Generically add each property of the $object into the substitution array
   foreach ($object as $key=>$value) {
      if (!is_object($value) and !is_resource($value) and !isset($substitutionarray['object_'.$key])) { // only add the property if it is not already defined, and is not an object nor a resource
         $substitutionarray['object_'.$key] = $value;
      }
   }
}

function customfields_completesubstitutionarray(&$substitutionarray,$outputlangs,$object) {
   global $conf,$db;

   if (empty($object)) return; // check that this function is called with an $object for substitution in ODTs (else it may be called for emails and create a bug)

   // OPTIONAL : Add generic support for any $object property
   generic_tag_filling($substitutionarray, $object); // must be done before so that we can replace specific values after

    // Adding customfields properties of the $object
    // CustomFields
    if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
            // Init and main vars
            include_once(dirname(__FILE__).'/../../class/customfields.class.php');
            $customfields = new CustomFields($db, $object->table_element);

            // Fetching custom fields records
            $fields = $customfields->fetch($object->id);

            $fieldstruct = $customfields->fetchAllFieldsStruct();
            if (!isset($fields)) return null;

            foreach ($fieldstruct as $field) {
                    $name = $customfields->varprefix.$field->column_name; // name of the property (this is one customfield)
                    $translatedname = $customfields->findLabelPDF($field->column_name, $outputlangs); // label of the customfield
                    $value = $customfields->printFieldPDF($field, $fields->{$field->column_name}, $outputlangs); // value (cleaned and properly formatted) of the customfield
                    $substitutionarray[$name] = $value; // adding this value to an odt variable (format: {cf_customfield} by default if varprefix is default)

                    // if the customfield has a constraint, we fetch all the datas from this constraint in the referenced table
                    if (!empty($field->referenced_table_name)) {
                            $record = $customfields->fetchAny('*', $field->referenced_table_name, $field->referenced_column_name."='".$fields->{$field->column_name}."'"); // we fetch the record in the referencd table

                            if (!empty($record)) {
                                    foreach ($record as $column_name => $value) { // for each record, we add the value to an odt variable
                                            $substitutionarray[$name.'_'.$column_name] = $value;
                                    }
                            }
                    }
            }
    }

    return 0;

}

/** Useless function necessary for old Dolibarr versions not to throw an error (because as soon as it sees this file, it will necessarily call to this function...)
 *
 */
function customfields_completesubstitutionarray_lines(&$substitutionarray,$outputlangs,$object,$line) {
    return;
}
