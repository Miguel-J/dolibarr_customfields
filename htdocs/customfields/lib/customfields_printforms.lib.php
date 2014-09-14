<?php
/* Copyright (C) 2011-2014   Stephen Larroque <lrq3000@gmail.com>
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
 *	\file       htdocs/customfields/lib/customfields_printforms.lib.php
 *	\brief      Printing library for the customfields module, very generic and useful (but no core database managing functions, they are in customfields.class.php)
 *	\ingroup    customfields
 */

/**
 *  Return array head with list of tabs to view object informations
 *  @param
 *  modulesarray         list of modules (format: array(modulename1, modulename2, etc..))
 *  currentmodule       modulename of the currently active module
 *  @return     void
 */
function customfields_admin_prepare_head($modulesarray, $currentmodule = null)
{
    global $langs, $conf, $user;

    include(dirname(__FILE__).'/../conf/conf_customfields.lib.php');
    include_once(dirname(__FILE__).'/../conf/conf_customfields_func.lib.php');

    $h = 0;
    $head = array();
    $currentmoduleindex = 0;

    // Get all customfields tables (= supported modules)
    $modarr = array_values_recursive('table_element', $modulesarray);
    $modarr = array_merge(array_flip(array_flip($modarr))); // double array_flip() + array_merge() = array_unique but is way faster

    // Preparing the tabs
    foreach ($modarr as $modulename) {
        if ($currentmodule == $modulename) { $currentmoduleindex = $h;} // detecting the index of the current tab
        $head[$h][0] = $_SERVER["PHP_SELF"].'?module='.$modulename;
        $head[$h][1] = $langs->trans($modulename);
        $head[$h][2] = 'general';
        $h++;
    }

    /*
     // detecting the index of the current tab
     // almost identical to the code above , but this one is less logical since we here detect the index in the $modulesarray when we need the index in $h array. Concretely, we get the same result in the end, but this is not the right method here.
    if (in_array($currentmodule, $modulesarray['table_element'])) {
        $currentmoduleindex = array_search($currentmodule, $modulesarray['table_element']);
    } else {
        $currentmoduleindex = 0;
    }
    */

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,null,$head,$h,'customfields_admin');
    complete_head_from_modules($conf,$langs,null,$head,$h,'customfields_admin', 'remove');
    $head = dol_fiche_head($head, $active="$currentmoduleindex", $title='', $notab=0, $picto=''); // draw the tabs

    return $head;
}

/**
 *      Print the customfields at the creation form of any table based module
 *      Description: show create form: all customfields are editable at once (fields can either have empty values which is default, or fetch the values of a record in the database if this form is used to edit instead of creating)
 *      @param      $currentmodule      the current module we are in (facture, propal, etc.)
 *      @param      $parameters             various parameters of the calling module (usually passed by the hookmanager)
 *      @param      $action                         action string name (passed by hookmanager) - UNUSED
 *      @param      $id                             id of the record (normally we are here to create it, but in some cases like copy or edit product lines or others, the id may already exists, so we fetch and show it)
 *      @return     void        returns nothing because this is a procedure : it just does what we want (print a field)
 */
function customfields_print_creation_form($currentmodule, $object = null, $parameters = null, $action = null, $id = null) {
    global $db, $langs;

    // Init and main vars
    include_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'); // to check if an error happened
    include_once(dirname(__FILE__).'/../class/customfields.class.php');
    if (file_exists(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php')) include_once(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php'); // to allow user's function overloading (eg: at printing, at edition, etc..)
    $customfields = new CustomFields($db, $currentmodule);

    if ($customfields->probeTable()) { // ... and if the table for this module exists, we show the custom fields
        // Fetch custom fields' database structures
        $fields = $customfields->fetchAllFieldsStruct();
        // quit if there's no custom field at all
        if(empty($fields)) return;
        // fetching the record - the values of the customfields for this id (if it exists)
        if (isset($id)) $datas = $customfields->fetch($id);
        // Check if we are converting from an origin object
        $origin = GETPOST('origin');
        if ( (!empty($object->origin) and strcmp($object->origin, $object->table_element) ) // check that the object is created using an origin object and that it is different from this object (else it would be cloning and not a conversion)
                 or (!empty($origin) and strcmp($origin, $object->table_element) ) // if the origin is in GET/POST instead of inside an attribute of the object, we check that too
           ) {
            $conversion = true;
        } else {
            $conversion = false;
        }
        
        // Disambiguate $_POST duplicated names
        // Fix the "forgetting" of typed values in products lines custom fields when an error occurs (because in fact the custom fields values are remembered, but the ones from the predefined products, not freeline products, thus there is a conflict and POST does not retain both values when two input fields have the exact same name)
        $post_noconflict = array();
        if (!empty($_POST)) {
            foreach (explode('&', file_get_contents('php://input')) as $keyValuePair) { // Use this way to access all fields, even the ones with the same name (important for products lines where fields are duplicated for both free products and predefined products) // TODO: find a better way to manage this edge case (same form for both free and predefined products, thus the same name fields for two customfields since they are duplicated. Fix directly the Dolibarr code by implementing two different forms?)
                list($key, $value) = explode('=', $keyValuePair);
                if (!isset($post_noconflict[$key]) and isset($value)) { // Appending only: only add the property to the object if this property is not already defined
                    $post_noconflict[$key] = urldecode($value); // don't forget to urldecode since values are urlencoded in php://input, which is not the case with $_POST!
                } elseif (empty($post_noconflict[$key]) and !empty($value)) {
                    $post_noconflict[$key] = urldecode($value);
                }
            }
        }

        // Print each field
        foreach ($fields as $field) {
            $name = strtolower($field->column_name); // the name of the customfield (which is the property of the record)

            //== Preparing special features
            $fieldrequired = '';
            $fieldrecopyhelper = '';
            //-- Required fields
            if ($field->extra->required and empty($field->extra->noteditable)) $fieldrequired = ' class="fieldrequired"';
            //-- Recopy on conversion notice
            if ( $field->extra->recopy and $conversion ) { // check that the field has the recopy option enabled and that we are converting here from an origin object
                $fieldrecopyhelper = '<br /><em>('.$langs->trans('RecopyOnConversion').' '.$langs->trans('Enabled').')</em>';
                $fieldrequired = ''; // disable required fields since it will be recopied
            }

            //== Print the custom field's label
            print '<tr><td'.$fieldrequired.'>'.$customfields->findLabel($name).$fieldrecopyhelper.'</td>';
            //-- Output the right colspan for the table
            if(isset($parameters->context)) {
                print '<td '.$parameters->context.'>';
            } else {
                print '<td colspan="2">';
            }
            //-- Recopy on conversion notice
            if ( $field->extra->recopy and $conversion ) print $langs->trans('RecopyCanBeEmptyHelper').'<br />';

            //== Print the custom field's value
            //-- Prepare the custom field's value
            $value = ''; // by default the value of this property is empty
            if ($post_noconflict['action'] != 'addline' or // Restore in any case if it's a creation page, and not a products line add line (because creation page willl anyway switch to another page if there's no error). TODO: remove this workaround when Dolibarr willl fix the creation pages of core modules (where errors are not stored in session but only locally in the page, like projects or provider invoice)
            !empty($object->error) or (isset($_SESSION['dol_events']['errors']) and count($_SESSION['dol_events']['errors']) > 0) ) {
                $postvalue = $post_noconflict[$customfields->varprefix.$name]; // Remember last input if there was an error
            }
            if ( !empty ($postvalue) ) {
                $value = $postvalue;
            } elseif (isset($datas->$name)) {
                // Default values from database record
                $value = $datas->$name; // if the property exists (the record is not empty), then we fill in this value
            }

            //-- Calling custom user's functions or print the custom field's value if none is found
            $customfunc_create = 'customfields_field_create_'.$currentmodule.'_'.$field->column_name;
            $customfunc_createfull = 'customfields_field_createfull_'.$currentmodule.'_'.$field->column_name;

            if (function_exists($customfunc_createfull)) { // a full function just does everything, CF just stop processing the field here
                $customfunc_createfull($currentmodule, $object, $parameters, $action, $id, $customfields, $field, $name, $value);
            } else {
                if (function_exists($customfunc_create)) { // here the function may modify any parameter it wants (by referencing the values with a pointer like &$values), and then CF will continue to process the printing of the HTML field with these modified variables
                    $customfunc_create($currentmodule, $object, $parameters, $action, $id, $customfields, $field, $name, $value);
                }
                if (!$field->extra->noteditable) { // security: if the field is not editable, we don't show it
                    print $customfields->ShowInputField($field, $value);
                } // TODO: else, print value for products lines if field is not editable (is this even possible? since the field may not yet be saved in db. Maybe just allow for customviewfull func)
            }
            print '</td></tr>';
        }
    }
}

/**
 *      Print the customfields at the main datasheet form of any table based module (with editable fields)
 *      Description: show datasheet form: either the customfields aren't edited and we just show their values and an edit button, either we are editing ONE customfield at a time
 *      @param      currentmodule      the current module we are in (facture, propal, etc.)
 *      @param      object                     the object containing the required informations (if we are in facture's module, it will be the facture object, if we are in propal it will be the propal object etc..)
 *      @param      parameters          various parameters from the originating module (usually passed by hookmanager)
 *      @param      action                     action name string (usually passed by hookmanager, but also preprocessed by CustomField's action_customfields.class.php)
 *      @param      user                        current user object, containing all his datas (mainly used to manage rights - to check if he's authorized to edit anything in this module)
 *      @param      idvar                       the name of the POST or GET variable containing the id of the object
 *      @param      rights                     path to the rights defining authorization for this module (in the form: $user->rights->something->create)
 *      @return     void        returns nothing because this is a procedure : it just does what we want
 */
function customfields_print_datasheet_form($currentmodule, $object, $parameters, $action, $user, $idvar = 'id', $rights = null) {
    global $db, $langs, $conf;

    // Init and main vars
    include_once(dirname(__FILE__).'/../class/customfields.class.php');
    if (file_exists(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php')) include_once(dirname(__FILE__).'/../fields/customfields_fields_extend.lib.php'); // to allow user's function overloading (eg: at printing, at edition, etc..)
    include_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'); // for images img_edit()
    $customfields = new CustomFields($db, $currentmodule);

    if ($customfields->probeTable()) { // ... and if the table for this module exists, we show the custom fields
        //print '<table class="border" width="100%">';

        if (!empty($object->rowid)) {
            $objid = $object->rowid;
        } else {
            $objid = $object->id;
        }
        if (empty($objid)) return; // if the object's id is empty, it's probably an error (eg: when creating a new task, project_update trigger will be called because of an internal call to edit a project to append the new task, but then this trigger is not silented and will call this function, which will have absolutely nothing to do.)

        // == Fetching customfields
        $fields = $customfields->fetchAllFieldsStruct(); // fetching the customfields list
        $datas = $customfields->fetch($objid); // fetching the record - the values of the customfields for this id (if it exists)
        if (empty($datas)) $datas = new stdClass(); // avoid php > 5.3 warnings if there is not yet any customfields for this record
        $datas->id = $objid; // in case the record does not yet exist for this id, we at least set the id property of the datas object (useful for the update later on)

        // == Checking rights
        // checking the user's rights for edition for all current module's customfields
        if (!empty($rights)) { // if a list of rights have been specified, we check the rights for creation/edition for each one
            $rightok = true;
            if (!is_array($rights)) { $rights = array($rights); } // convert to an arrray if we were supplied a string
            // for each right, we will check if it exists, and if true, if the current user has ALL the required right (if one necessary right isn't possessed, then the user will be refused edition)
            foreach ($rights as $moduleright) {
                //print("isset: ".isset($moduleright)." val: $moduleright\n"); // debugline
                eval("\$misset = isset($moduleright);"); // assign to $misset the boolean value if $moduleright exists
                if (!$misset) { // if the specified right does NOT exist or is not set (either because the user does NOT have it which makes Dolibarr NOT specify the right, or either this means the dev implementing CF has specified a right that does not exist)
                    $rightok = false; // no good, no access
                    break;
                } else { // if the right exists
                    eval("\$mval = $moduleright;"); // assign the value of the right pointed by $moduleright
                    //print('mval: '.$mval); // debugline
                    if (!$mval) { // and if the current user does NOT possess it (set to false, but in practice Dolibarr does not set unpermitted rights to false, but rather it doesn't set them at all)
                        $rightok = false; // then the user does not meet the necessary privileges to edit this customfield, then we skip
                        break;
                    }
                }
            }
        } else { // else by default we just check for the current module (in the hope the current module has the same name in the rights array... eg: product module is produit in the rights property...)
            $rightok = $user->rights->$currentmodule->creer;
        }

        // == Printing/Editing custom fields
        if (isset($fields)) { // only if at least one customfield exists (there's a special case where a record may exist because there existed customfields, but all customfields were deleted, and thus the records still exist with a rowid and fk_module columns set, but with nothing else. In this case, we skip.)

            $_POST_lower = array_change_key_case($_POST, CASE_LOWER);
            foreach ($fields as $field) { // for each customfields, we will print/save the edits

                // == Default values from database record
                $name = strtolower($field->column_name); // the name of the customfield (which is the property of the record)
                $value = ''; // by default the value of this property is empty
                if (isset($datas->$name)) { $value = $datas->$name; } // if the property exists (the record is not empty), then we fill in this value

                // == Save the edits
                if (strtolower($action)==strtolower('set_'.$customfields->varprefix.$name) and isset($_POST_lower[$customfields->varprefix.$name]) // if we edited the value
                    and $rightok // and the user has the required privileges to edit the field
                    and !$field->extra->noteditable // and we CAN edit this field
                    ) {

                    // Forging the new record
                    $newrecord = new stdClass(); // initializing the cache object explicitly if empty (to avoid php > 5.3 warnings)
                    $newrecord->$name = $_POST_lower[$customfields->varprefix.$name]; // we create a new record object with the field and the id
                    $newrecord->id = $objid;

                    /* UPDATE CUSTOMFIELD DIRECTLY
                            // Note: the generic fill is necessary for some fields like date fields, where 3 more fields are created (day, month, year) to hold values separately, and this is needed to correctly save the field into the database
                            foreach ($_POST as $key=>$value) { // Generic way to fill all the fields to the object (particularly useful for triggers and customfields) - NECESSARY to get the fields' values
                                if (!isset($newrecord->$key)) { // Appending only: only add the property to the object if this property is not only defined
                                    $newrecord->$key = $value;
                                }
                            }
                            $customfields->update($newrecord); // update or create the record in the database (will check automatically) - this does the same as the trigger below, but the trigger is more consistent with the rest (we need to use triggers for creation)
                            */
                    // Insert/update the record into the database by trigger
                    include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                    $interface=new Interfaces($db);
                    $newrecord->currentmodule = $currentmodule; // very important to pass the module as a property of the object
                    $result=$interface->run_triggers('CUSTOMFIELDS_MODIFY',$newrecord,$user,$langs,$conf);

                    // Updating the loaded record object
                    // deprecated, see below
                    // $datas->$name = $_POST[$customfields->varprefix.$name]; // we update the loaded record to the new value so that it gets printed asap
                    //$value = $datas->$name;
                    // Reloading the field from the database (we need to fetch from the database because there can be some not null fields with default values, and if we are creating the record, these will be filled it, and we have no way to know it when updating the database, so we need to fetch the record again)
                    $datas = $customfields->fetch($objid); // fetching the record - the values of the customfields for this id (if it exists)
                    $value = $datas->$name;
                }

                // == Print the record
                print '<tr><td>';
                // print the customfield's label
                print $customfields->findLabel($name);
                // print the edit button only if authorized
                if (!($action == 'editcustomfields' && strtolower(GETPOST('field')) == $name) && !(isset($objet->brouillon) and $object->brouillon == false) && $rightok && !$field->extra->noteditable) print '<span align="right"><a href="'.$_SERVER["PHP_SELF"].'?'.$idvar.'='.$objid.'&amp;action=editcustomfields&amp;field='.$field->column_name.'">'.img_edit("default",1).'</a></td>';
                print '</td>';
                if (isset($parameters->colspan)) { // sometimes the colspan is provided in $parameters, we use it if available
                    print '<td '.$parameters->colspan.'>';
                } else { // if not, by default it's generally a colspan=3
                    print '<td colspan="3">';
                }
                // print the editing form...
                if ($action == 'editcustomfields' && strtolower(GETPOST('field')) == $name && $rightok) {

                    // Calling custom user's functions
                    $customfunc_edit = 'customfields_field_editview_'.$currentmodule.'_'.$field->column_name;
                    $customfunc_editviewfull = 'customfields_field_editviewfull_'.$currentmodule.'_'.$field->column_name;

                    if (function_exists($customfunc_editviewfull)) {
                        $customfunc_editviewfull($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value);
                    } else {
                        if (function_exists($customfunc_edit)) $customfunc_edit($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value);
                        // Print the customfield edit form
                        print $customfields->showInputForm($objid, $field, $value, $idvar, $_SERVER["PHP_SELF"].'?'.$idvar.'='.$objid); // note: we also submit the ID as a GET variable, so that the user can just refresh the page and it will correctly show the right page (else the URL will be something like 'fiche.php' instead of 'fiche.php?id=xx')
                    }
                } else { // ... or print the customfield's value
                    // Calling custom user's functions
                    $customfunc_view = 'customfields_field_view_'.$currentmodule.'_'.$field->column_name;
                    $customfunc_viewfull = 'customfields_field_viewfull_'.$currentmodule.'_'.$field->column_name;

                    if (function_exists($customfunc_viewfull)) { // a full function just does everything, CF just stop processing the field here
                        $customfunc_viewfull($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value);
                    } else {
                        // here the function may modify any parameter it wants (by referencing the values with a pointer like &$values), and then CF will continue to process the printing of the HTML field with these modified variables
                        if (function_exists($customfunc_view)) {
                            $customfunc_view($currentmodule, $object, $parameters, $action, $user, $idvar, $rightok, $customfields, $field, $name, $value);
                        } elseif (empty($value) and $field->extra->noteditable) { // if the field has no value and is not editable (and no overloading function took care of managing the field's value), we show the SQL default value
                            $value = $field->column_default;
                        }
                        // Finally, print the field's value
                        print $customfields->printField($field, $value);
                    }
                }
                print '</td></tr>';
            }
        }

        //print '</table><br>';
    }
}

?>
