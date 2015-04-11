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



/**** PART OF CUSTOMFIELDS MODULE FOR DOLIBARR
 * Description: here you can overload a few functions of CustomFields to do your own stuff, mainly the printing and the management of the fields.
 *
 * TODO: optimize queries by constructing only one query and issuing it at once. Currently, up to 4 queries can be issued! This allows for modularity, but this is slow...
 */

// need to include main Dolibarr file since this script is the root calling script (there's no php parent, the only parent is the javascript call, thus we have to reload any necessary library)
// this is needed here to access the $db object, representing the database credentials
$res=0;
if (! $res && file_exists(dirname(__FILE__)."/../main.inc.php")) $res=@include(dirname(__FILE__)."/../main.inc.php");			// for root directory
if (! $res && file_exists(dirname(__FILE__)."/../../main.inc.php")) $res=@include(dirname(__FILE__)."/../../main.inc.php");		// for level1 directory ("custom" directory)
if (! $res && file_exists(dirname(__FILE__)."/../../../main.inc.php")) $res=@include(dirname(__FILE__)."/../../../main.inc.php");	// for level2 directory
if (! $res) die("Include of main fails");

// Check that this script is called with ajax (in this case, the current calling field will be specified)
if(!empty($_POST) and isset($_POST['customfields_ajax_current_field'])){
    global $db; // load database object from main.inc.php

    $parent_name = $_POST['customfields_ajax_current_field']; // current calling field's name (which will modify other fields)
    if (isset($_POST[$parent_name])) { // check that the calling field also submitted a value, else we can't do anything to modify other fields if this one isn't set!

        // -- Sanitize a bit POST data (data should still be checked and sanitized depending on data type if using a custom cascade function or strip_tags() if you want to avoid XSS injection)
        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING); // sanitize against XSS
        $_POST_sanitized = array_map(array($db, 'escape'), $_POST); // sanitize against SQL injection

        // -- Init useful vars
        $currentmodule = $_POST_sanitized['customfields_ajax_current_module']; // current module, must be supplied by AJAX because this php script is called without any context
        $parent_name = $_POST_sanitized['customfields_ajax_current_field']; // name of the current field (sanitized, that's why we restore it)
        $current_value = $_POST_sanitized[$parent_name]; // current value for the calling field (ie: the parent field's value)

        // -- Load CustomFields object
        include_once(dirname(__FILE__).'/../class/customfields.class.php');
        $customfields = new CustomFields($db, $currentmodule);
        $parent_name = $customfields->stripPrefix($parent_name);

        // -- Fetch all field's structures and extra infos at once (spare a lot of SQL queries since one parent field can cascade on multiple children)
        $fields = $customfields->fetchAllFieldsStruct();
        $field = (object)reset(array_extract_recursive(array('column_name' => $parent_name), $fields));
        
        // Get all children's field structures and extra infos (from the fetchAllFieldsStruct() we did above to optimize our SQL queries)
        $cascade_children = array_extract_recursive(array('cascade_parent_field'=>$parent_name), $fields);

        // -- Manage cascaded dropdown list
        if (!empty($cascade_children)) { // if this field has any children on which to cascade on (this is how we check that cascade is enabled for a parent field, on children we can just check !empty($field->extra['cascade']))
            $result = array(); // result to return to AJAX
            $result_visibility = array(); // visibility arguments

            // Cascade on every children fields
            foreach ($fields as $child_field) {
                //$child_field = (object)reset(array_extract_recursive(array('column_name' => $child_field_name), $fields));
                
                // Init vars
                $child_field_name = $child_field->column_name;

                // Check that this field is indeed a child
                if (empty($child_field->extra['cascade']) or empty($child_field->extra['cascade_parent_field']) or strcmp($child_field->extra['cascade_parent_field'], $parent_name)) {
                    //print('Skipped: '.$parent_name.'=?'.$child_field->extra['cascade_parent_field'].'<'.$child_field_name."<br />\n");
                    continue;
                }

                // -- Custom AJAX callback management
                $full_func_called = false; // if full custom function was called, we won't do the automatic management
                $result_custom = array();
                if ($child_field->extra['cascade_custom']) {
                    // Search and load custom functions if available
                    if (file_exists(dirname(__FILE__).'/../fields/customfields_fields_ajax_custom.lib.php')) include_once(dirname(__FILE__).'/../fields/customfields_fields_ajax_custom.lib.php'); // to allow user's function overloading for ajax
                    $customfunc_ajax = 'customfields_field_ajax_'.$currentmodule.'_'.$child_field_name; // custom function to execute for this field only
                    $customfunc_ajax_full = 'customfields_field_ajaxfull_'.$currentmodule.'_'.$child_field_name; // custom function to execute for this field only, and which will manage everything (we won't print the result nor json_encode, thus it will have to do the whole job itself). Also POST data is not sanitized.
                    $customfunc_ajax_all = 'customfields_field_ajax_'.$currentmodule.'_all'; // custom function to execute for any field in this module
                    $customfunc_ajax_full_all = 'customfields_field_ajaxfull_'.$currentmodule.'_all'; // custom function to execute for any field in this module and with full control over the printing
                    // Full specific custom function (for this field): user can set the values and manage everything, we won't tamper the result
                    if (function_exists($customfunc_ajax_full)) {
                        $result_custom = array_replace_recursive($result, $customfunc_ajax_full($customfields, $currentmodule, $fields, $child_field, $child_field_name, $field, $parent_name, $current_value, $_POST_sanitized));
                        $full_func_called = true;
                    // Specific custom function (for this field): the user can do additional stuffs like changing HTML attributes (eg: field's visibility), and the cascading will still be automatically managed afterwards.
                    } elseif (function_exists($customfunc_ajax)) {
                        $result_custom = array_replace_recursive($result, $customfunc_ajax($customfields, $currentmodule, $fields, $child_field, $child_field_name, $field, $parent_name, $current_value, $_POST_sanitized)); // call the function and get back the result, we will do some post-processing after
                    // Generic custom function to apply to all fields of this module
                    } elseif (function_exists($customfunc_ajax_full_all)) {
                        $result_custom = array_replace_recursive($result, $customfunc_ajax_full_all($customfields, $currentmodule, $fields, $child_field, $child_field_name, $field, $parent_name, $current_value, $_POST_sanitized));
                        $full_func_called = true;
                    // Specific custom function (for all fields of this module)
                    } elseif (function_exists($customfunc_ajax_all)) { // Found a generic custom function to manage all fields for this module (can be used as a fallback: if a specific custom function for this field is found, it is preferred, else for other fields they will all be managed by this generic custom function).
                        $result_custom = array_replace_recursive($result, $customfunc_ajax_all($customfields, $currentmodule, $fields, $child_field, $child_field_name, $field, $parent_name, $current_value, $_POST_sanitized)); // call the function and get back the result, we will do some post-processing after
                    }
                }

                // -- Automatic cascading management
                // Manage automatically the cascading (simpler, no programming for user but less extensible)
                // Here we automatically update the options of a target field using the value of the calling field
                if (!$full_func_called) {
                    // Init vars
                    $current_value = filter_var($current_value, FILTER_SANITIZE_NUMBER_INT); // sanitize a bit, we know constrained fields only use integer ids (rowid type)
                    $join_on_value = $current_value; // value that will be used to kind-of join the two cascaded fields

                    // Fetch the join on value if the join from field is not the table's rowid (eg: llx_c_departements is linked with llx_c_regions via fk_region=code_region field, not the rowid)
                    // Note: This is kind of a standard SQL Join but wrapped via PHP, but of course we could do that directly in a single SQL request. Here we chose to do it via PHP to reuse a maximum of code to be flexible (so that one field doesn't need to know about what does the other field, they just share one value).
                    if (!empty($child_field->extra['cascade_parent_join_on'])) { // If join from is specified
                        $field_record = $customfields->fetchReferencedValuesList($field, $current_value, null, $child_field->extra['cascade_parent_join_on']); // fetch the value for the column we have to join on
                        if (!empty($field_record) and isset($field_record[0]->{$child_field->extra['cascade_parent_join_on']})) { // if this column is available, fetch the value, which we will use for the where below
                            $join_on_value = $field_record[0]->{$child_field->extra['cascade_parent_join_on']};
                        }
                    }

                    // Fetch the linked records (list of values) of the target field, which will be our options. We also constrain on the calling field's value, that's how we reduce the number of pertinent options available.
                    $linked_records = $customfields->fetchReferencedValuesList($child_field, null, $child_field->extra['cascade_parent_join_from'].'='.$join_on_value);
                    // Convert the linked records to an array(rowid=>value) so that we can easily generate an array of options
                    $linked_arr = $customfields->convertReferencedRecordsToArray($child_field, $linked_records);
                    if (empty($linked_arr)) $linked_arr = array();
                    if (strtolower($child_field->is_nullable) == 'yes') $linked_arr = array_unshift_assoc($linked_arr, '', ''); // Add default empty value if the field accepts null values. WARNING: should NOT do array_unshift_assoc($linked_arr, 0, '') because else constrained fields will supply a 0 value as a rowid key, instead of an empty value, which may violate a foreign key and prevent all custom fields (the whole customfields sql query) from being submitted!

                    // Store the result into "options" type of change (we will replace all options of the target constrained field by the one we just fetched and constrained)
                    $result[$customfields->varprefix.$child_field_name] = array('options'=>$linked_arr);
                    // Merge with custom function's returned result if available
                    if (!empty($result_custom)) $result = array_replace_recursive($result, $result_custom);
                }
                
                // -- Hidden field show on cascade management
                // Show the field if it was hidden and show_on_cascade is enabled for this field
                if ($child_field->extra['hide'] and $child_field->extra['show_on_cascade']) {
                    if (!empty($current_value)) {
                        $result_visibility[$customfields->varprefix.$child_field_name] = array('css' => array('display'=>'block'),
                                                                                                                'attr' => array('style'=>'display: block'));
                        $result_visibility[$customfields->varprefix.$child_field_name.'_label'] = array('css' => array('display'=>'block'),
                                                                                                                'attr' => array('style'=>'display: block'));
                    } else {
                        $result_visibility[$customfields->varprefix.$child_field_name] = array('css' => array('display'=>'none'),
                                                                                                            'attr' => array('style'=>'display: none'));
                        $result_visibility[$customfields->varprefix.$child_field_name.'_label'] = array('css' => array('display'=>'none'),
                                                                                                            'attr' => array('style'=>'display: none'));
                    }
                }
            }
            // End of the loop

            // -- Post-processing
            // Extract the options keys so that we can send them in a Javascript array (ie: not associative) so that in Javascript we can use this array of keys to print the options in the correct order (else Javascript may reorder an associative array = object in any order, usually in ascending order of the key, here the id in referenced primary column)
            foreach($result as $fname=>$attributes) { // for each field to modify via AJAX
                if (isset($attributes["options"]) and !isset($attributes["options_keys"])) { // if options is set for this field, and options_keys isn't already specified, we create options_keys to specify the order to print the options
                    $result[$fname]["options_keys"] = array_keys($attributes["options"]); // just extract the keys of the options in the same order as they were pushed to the php array
                }
            }
            // Merge options and visibility
            if (!empty($result_visibility)) {
                $result = array_replace_recursive((array) $result_visibility, (array) $result);
            }

            // Return the result to the AJAX client-side by json encoding then printing it
            print_r(json_encode($result));
        }
    }
}

?>