<?php
/* Copyright (C) 2011-2013 Stephen Larroque  <lrq3000@gmail.com>
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
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/customfields/lib/customfields_printforms.lib.php
 *	\brief      Printing library for the customfields module, very generic and useful (but no core database managing functions, they are in customfields.class.php)
 *	\ingroup    customfields
 *	\version    $Id: customfields_printforms.lib.php, v1.2.9
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

    $h = 0;
    $head = array();
    $currentmoduleindex = 0;

    // preparing the tabs
    foreach ($modulesarray as $module) {
        if ($currentmodule == $module) { $currentmoduleindex = $h;} // detecting the index of the current tab
        $head[$h][0] = $_SERVER["PHP_SELF"].'?module='.$module;
        $head[$h][1] = $langs->trans($module);
        $head[$h][2] = 'general';
        $h++;
    }

    /*
     // detecting the index of the current tab
     // almost identical to the code above , but this one is less logical since we here detect the index in the $modulesarray when we need the index in $h array. Concretely, we get the same result in the end, but this is not the right method here.
    if (in_array($currentmodule, $modulesarray)) {
        $currentmoduleindex = array_search($currentmodule, $modulesarray);
    } else {
        $currentmoduleindex = 0;
    }
    */

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    //complete_head_from_modules($conf,$langs,$object,$head,$h,'customfields_admin');
    dol_fiche_head($head, $active="$currentmoduleindex", $title='', $notab=0, $picto=''); // draw the tabs

    return $head;
}

/**
 *      Print the customfields at the creation form of any table based module
 *      @param      $currentmodule      the current module we are in (facture, propal, etc.)
 *      @return     void        returns nothing because this is a procedure : it just does what we want
 */
function customfields_print_creation_form($currentmodule, $id = null) {
    global $db, $langs;

    // Init and main vars
    include_once(dirname(__FILE__).'/../class/customfields.class.php');
    $customfields = new CustomFields($db, $currentmodule);

    if ($customfields->probeTable()) { // ... and if the table for this module exists, we show the custom fields
        // Fetch custom fields' database structures
        $fields = $customfields->fetchAllFieldsStruct();
        // quit if there's no custom field at all
        if(empty($fields)) return;
        // fetching the record - the values of the customfields for this id (if it exists)
        if (isset($id)) $datas = $customfields->fetch($id); // fetching the record - the values of the customfields for this id (if it exists)
        foreach ($fields as $field) {
            $name = $field->column_name;
            print '<tr><td>'.$customfields->findLabel($name).'</td><td colspan="2">';
            $value = ''; // by default the value of this property is empty
            $name = $field->column_name; // the name of the customfield (which is the property of the record)
            $postvalue = GETPOST($customfields->varprefix.$name);
            if ( !empty ($postvalue) ) {
                $value = $postvalue;
            } elseif (isset($datas->$name)) {
                // Default values from database record
                $value = $datas->$name; // if the property exists (the record is not empty), then we fill in this value
            }
            print $customfields->ShowInputField($field, $value);
            print '</td></tr>';
        }
    }
}

/**
 *      Print the customfields at the main form of any table based module (with editable fields)
 *      @param      currentmodule      the current module we are in (facture, propal, etc.)
 *      @param      idvar                       the name of the POST or GET variable containing the id of the object
 *      @param      object                     the object containing the required informations (if we are in facture's module, it will be the facture object, if we are in propal it will be the propal object etc..)
 *      @return     void        returns nothing because this is a procedure : it just does what we want
 */
function customfields_print_datasheet_form($currentmodule, $object, $action, $user, $idvar = 'id', $rights = null) {
    global $db, $langs, $conf;

    // Init and main vars
    include_once(dirname(__FILE__).'/../class/customfields.class.php');
    include_once(DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php'); // for images img_edit()
    $customfields = new CustomFields($db, $currentmodule);

    if ($customfields->probeTable()) { // ... and if the table for this module exists, we show the custom fields
        //print '<table class="border" width="100%">';

        if (!empty($object->rowid)) {
            $objid = $object->rowid;
        } else {
            $objid = $object->id;
        }

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
                if (!isset($user->rights->$moduleright->creer)) { // if the specified right does NOT exist or is not set (either because the user does NOT have it which makes Dolibarr NOT specify the right, or either this means the dev implementing CF has specified a right that does not exist)
                    $rightok = false; // no good, no access
                    break;
                } else { // if the right exists
                    if (!$user->rights->$moduleright->creer) { // and if the current user does NOT possess it (set to false, but in practice Dolibarr does not set unpermitted rights to false, but rather it doesn't set them at all)
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
                   ) {

                    // Forging the new record
                    $newrecord = new stdClass(); // initializing the record object explicitly if empty (to avoid php > 5.3 warnings)
                    $newrecord->$name = $_POST_lower[$customfields->varprefix.$name]; // we create a new record object with the field and the id
                    $newrecord->id = $objid;

                    // Insert/update the record into the database by trigger
                    //$customfields->update($newrecord); // update or create the record in the database (will check automatically) - this does the same as the trigger below, but the trigger is more consistent with the rest (we need to use triggers for creation)
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
                print $customfields->findLabel($name);
                // print the edit button only if authorized
                if (!($action == 'editcustomfields' && strtolower(GETPOST('field')) == $name) && !(isset($objet->brouillon) and $object->brouillon == false) && $rightok) print '<span align="right"><a href="'.$_SERVER["PHP_SELF"].'?'.$idvar.'='.$objid.'&amp;action=editcustomfields&amp;field='.$field->column_name.'">'.img_edit("default",1).'</a></td>';
                print '</td>';
                print '<td colspan="3">';
                // print the editing form...
                if ($action == 'editcustomfields' && strtolower(GETPOST('field')) == $name) {
                    print $customfields->showInputForm($field, $value, $_SERVER["PHP_SELF"].'?'.$idvar.'='.$objid);
                } else { // ... or print the field's value
                    print $customfields->printField($field, $value);
                }
                print '</td></tr>';
            }

        }

        //print '</table><br>';
    }
}

?>
