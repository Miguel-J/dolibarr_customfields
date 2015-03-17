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
 *      \file             htdocs/customfields/core/substitutions/functions_customfields.lib.php
 *      \ingroup    customfields
 *      \brief          Substition function for the ODT templating (render accessible all the customfields variable)
 *      \author      Stephen Larroque
 */

/** 		Function called to complete the substitution array (before generating on ODT, or a personalized email)
 * 		functions xxx_completesubstitutionarray are called by make_substitutions() if file
 * 		is inside directory htdocs/core/mymodule/substitutions/
 *
 *		@param	array		$substitutionarray	Array with substitution key=>val
 *		@param	Translate	$langs			Output langs
 *		@param	Object		$object			Object to use to get values
 * 		@return	void					The entry parameter $substitutionarray is modified
 */

// Function to fill the substitution array with all $object fields (so any field passed by a form should be substituted thank's to this function)
// Note: this is NOT necessary for CustomFields, this is only an addon for other devs (since CF is meant to be used by devs, this might ease their burden).
function generic_tag_filling(&$substitutionarray, $object) {
    // Generically add each property of the $object into the substitution array
    foreach ($object as $key=>$value) {
        if (!is_object($value) and !is_resource($value) and !isset($substitutionarray['object_'.$key])) { // only add the property if it is not already defined, and is not an object nor a resource (so it's either a string or a number or an array)
            $substitutionarray['object_'.$key] = $value;
        }
    }
}

// CustomFields substitution function for all ODT templates (also theoretically works for emails and sms, untested)
// Note: before, $object conveyed any field's data, customfields included, but since Dolibarr v3.2.0 beta, this is not the case anymore, so we need to fetch the customfields datas manually.
// Note2: this function is also automatically called for Dolibarr emails, which is okay but it is also called in the emails config page (?), so then a condition checks that the $object var exists and is not empty, in this case it will just quit
// Note3: about caching: this function also caches, because Dolibarr calls it twice for every ODT generation, and it's useless to fetch twice the customfields. This is an optional optimization, but it reduces the performance overload by 2.
function customfields_completesubstitutionarray(&$substitutionarray,$langs,$object,$parameters) {
    global $conf, $db;
    static  $cfcache; // cache for the customfields (their data won't change between two successive calls of this function)

    if (!isset($cfcache)) $cfcache = new stdClass(); // initializing the object explicitly

    // Init parameters
    $parameters = (array)$parameters; // convert the $parameters because sometimes an object is returned instead of an array

    if (empty($object)) return; // check that this function is called with an $object for substitution in ODTs (else it may be called in the email config page and create a bug)

    // OPTIONAL : Add generic support for any $object property
    generic_tag_filling($substitutionarray, $object); // must be done before so that we can replace specific values after

    // Adding customfields properties of the $object
    // CustomFields
    if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
        // If the cache exists, we use it, else we fetch the customfields datas and cache it
        if (!isset($cfcache->{$object->table_element})) {
            include_once(dirname(__FILE__).'/../../lib/customfields_aux.lib.php');
            $customfields = customfields_fill_object($object, null, $langs, null, true); // fetch all customfields and fill them inside $object->customfields ($customfields will then only contain the returned instance of CustomFields object, with customfields sql structure in database - it is unused here, but just used as an example)
            customfields_fill_object($object, null, $langs, 'raw', null); // fetch all customfields and their RAW value inside $object->customfields->raw (very useful for ODT conditionals substitutions). We already have one $customfields object, so we don't need another one.
            $cfcache->{$object->table_element} = $object->customfields; // cache the customfields datas - note: we store customfields per module, so that if this function is called twice for two different modules, the cache will also work
        } else {
            $object->customfields = $cfcache->{$object->table_element}; // load the cache
        }

        if (empty($object->customfields)) return; // if there's no custom field defined for this module, we gracefully exit

        // -- Begin to populate the substitution array with customfields data
        foreach ($object->customfields as $key=>$value) { // One field at a time

            if ($key == 'lines') continue; // skip if it's the lines subobject (this is not a custom field but the list of lines custom fields!)
            if ($key == 'raw') continue; // if it's the 'raw' subarray, we skip

            if (!is_scalar($value)) { // If the value is not a scalar (so it cannot just be printed out because it's a complex object or array or resource), then we replace the value with an error to signal to the user that there's a problem
                $substitutionarray[$key] = 'ErrorNotAScalar';
                $substitutionarray[$key.'_raw'] = 'ErrorNotAScalar';
            } else { // Else the value is a scalar, we can print it out
                // Add this customfield's data to the substitution array (will automatically be replaced inside the ODT, eg: {cf_user} becomes 'John Doe')
                $substitutionarray[$key] = $value; // adding this value to an odt variable (format: {cf_customfield} by default if varprefix = default 'cf_' )
                $substitutionarray[$key.'_raw'] = $value;
                if (isset($object->customfields->raw)) $substitutionarray[$key.'_raw'] = $object->customfields->raw->$key; // adding the raw value in the _raw variable (format: {cf_customfield_raw})
            }
        }
    }

}

// CustomFields substitution for lines variables for all ODT templates
// Note: about caching: this function is called for every line, so to avoid performance overload, we use a cache. Also, the cache is per module, so if this function is called twice in a row for two different modules, the appropriate cache for each one will be reused.

/* Summary of it works and was implemented
 - load customfields_fill_object_lines and cache (else it will be recalled for each product's line...)
 - for each call, we get a $line->rowid, so we choose $object->customfields->lines->{$line->rowid}
 - substitute values just like in the above function
*/
function customfields_completesubstitutionarray_lines(&$substitutionarray,$langs,$object,$parameters) {
    global $conf, $db;
    static  $cflinescache; // cache for the lines' customfields (their data won't change between two successive calls of this function)

    if (!isset($cflinescache)) $cflinescache = new stdClass(); // initializing the object explicitly

    // Init parameters
    $parameters = (array)$parameters; // convert the $parameters because sometimes an object is returned instead of an array

    // For some versions of Dolibarr, the $parameters is an array which can contain more than the line object, thus we just extract the line object
    if(isset($parameters['line'])) {
        $line = (object)$parameters['line'];
    // In some older versions of Dolibarr, the $parameters directly IS the line object we want
    //} elseif (is_object($parameters) and get_class($parameters) == 'FactureLigne') {
    } else {
        $line = (object)$parameters;
    }

    if (empty($object) or empty($line)) return; // check that this function is called with an $object and $line for substitution in ODTs (else it may be called in the email config page and create a bug)

    if ($conf->global->MAIN_MODULE_CUSTOMFIELDS) { // if the customfields module is activated...
        // If the cache exists, we use it, else we fetch the lines' customfields datas and cache it
        if (!isset($cflinescache->{$object->table_element})) {
            include_once(dirname(__FILE__).'/../../lib/customfields_aux.lib.php');
            $customfields = customfields_fill_object_lines($object, null, $langs, null, true); // fetch all customfields and fill them inside $object->customfields ($customfields will then only contain the returned instance of CustomFields object, with customfields sql structure in database - it is unused here, but just used as an example)
            customfields_fill_object_lines($object, null, $langs, 'raw', null); // fetch all customfields and their RAW value inside $object->customfields->raw (very useful for ODT conditionals substitutions). We already have one $customfields object, so we don't need another one.
            $cflinescache->{$object->table_element} = $object->customfields->lines; // cache the lines' customfields datas - note: we store customfields per module, so that if this function is called twice for two different modules, the cache will also work
            //$cflinescache->{$object->table_element}->customfields = $customfields;
        } else {
            $object->customfields->lines = $cflinescache->{$object->table_element}; // load the cache
            //$customfields = $cflinescache->{$object->table_element}->customfields;
        }

        if (empty($object->customfields->lines->{$line->rowid})) return; // if there's no custom field defined for this module AND line, we gracefully exit

        // -- Begin to populate the substitution array with customfields data
        foreach ($object->customfields->lines->{$line->rowid} as $key=>$value) { // One field at a time, relative to the current line being processed

            if ($key == 'raw') continue; // if it's the 'raw' subarray, we skip

            if (!is_scalar($value)) { // If the value is not a scalar (so it cannot just be printed out because it's a complex object or array or resource), then we replace the value with an error to signal to the user that there's a problem
                $substitutionarray[$key] = 'ErrorNotAScalar';
                $substitutionarray[$key.'_raw'] = 'ErrorNotAScalar';
            } else { // Else the value is a scalar, we can print it out
                // Add this customfield's data to the substitution array (will automatically be replaced inside the ODT, eg: {cf_user} becomes 'John Doe')
                $substitutionarray[$key] = $value; // adding this value to an odt variable (format: {cf_customfield} by default if varprefix = default 'cf_' )
                $substitutionarray[$key.'_raw'] = $object->customfields->lines->raw->{$line->rowid}->$key; // adding the raw value in the _raw variable (format: {cf_customfield_raw})
            }

        }
    }
}
