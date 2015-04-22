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
 * Description: manage some ajax functions to add automatic completion and functionalities to the admin panel.
 *
 */

// need to include main Dolibarr file since this script is the root calling script (there's no php parent, the only parent is the javascript call, thus we have to reload any necessary library)
// this is needed here to access the $db object, representing the database credentials
$res=0;
if (! $res && file_exists(dirname(__FILE__)."/../main.inc.php")) $res=@include(dirname(__FILE__)."/../main.inc.php");			// for root directory
if (! $res && file_exists(dirname(__FILE__)."/../../main.inc.php")) $res=@include(dirname(__FILE__)."/../../main.inc.php");		// for level1 directory ("custom" directory)
if (! $res && file_exists(dirname(__FILE__)."/../../../main.inc.php")) $res=@include(dirname(__FILE__)."/../../../main.inc.php");	// for level2 directory
if (! $res) die("Include of main fails");

// Security check
if (!$user->admin) die();

// Loading the translation class if it's not yet loaded (or with another name) - DO NOT EDIT!
if (! is_object($langs))
{
    include_once(DOL_DOCUMENT_ROOT."/core/class/translate.class.php");
    $langs=new Translate(dirname(__FILE__).'/../langs/',$conf);
}

$langs->load('customfields@customfields'); // customfields standard language support
$langs->load('customfields-user@customfields'); // customfields language support for user's values (like enum, fields names, etc..)

require(dirname(__FILE__).'/../conf/conf_customfields.lib.php');


// == Cascade autocomplete
// Check that this script is called with ajax (in this case, the current calling field will be specified)
if (!empty($_GET) and !empty($_GET['customfields_ajax_current_field'])) {
    // -- General pre-processing
    // Init result var
    $result = array();
    $result["alert"] = array();

    // Sanitize a bit GET data (data should still be checked and sanitized depending on data type if using a custom cascade function or strip_tags() if you want to avoid XSS injection)
    $_GET_sanitized = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING); // sanitize against XSS
    //$_GET_sanitized = array_map(array($db, 'escape'), $_GET_sanitized); // sanitize against SQL injection - TODO: ERROR: it can't handle arrays, thus checkboxes kill this! We have to make a function array_map_recursive


    // == Cascade autocomplete
    if (!empty($_GET_sanitized['cascade']) and !empty($_GET_sanitized['cascade_parent_field']) and !empty($_GET_sanitized['customfields_ajax_current_field']) and $_GET_sanitized['customfields_ajax_current_field'] == 'cascade_parent_field') {
        global $db; // load database object from main.inc.php

        $name = $_GET_sanitized['customfields_ajax_current_field']; // current calling field's name (which will modify other fields)
        if (isset($_GET_sanitized[$name])) { // check that the calling field also submitted a value, else we can't do anything to modify other fields if this one isn't set!

            // -- Init useful vars
            $currentmodule = $_GET_sanitized['customfields_ajax_current_module']; // current module, must be supplied by AJAX because this php script is called without any context
            $name = $_GET_sanitized['customfields_ajax_current_field']; // name of the current html field (sanitized, that's why we restore it)
            $current_value = $_GET_sanitized[$name]; // current value for the calling html field
            $linked_table = $_GET_sanitized['constraint'];
            $parent_field_name = $_GET_sanitized['cascade_parent_field'];
            if (empty($linked_table)) $result["alert"][] = "Can't cascade automatically if this field has no constraint! Please either select a constraint or implement a custom cascade.";

            // -- Load CustomFields object
            include_once(dirname(__FILE__).'/../class/customfields.class.php');
            $customfields = new CustomFields($db, $currentmodule);

            // -- Load target field's structure to fetch its linked table if it has a constraint
            $parent_field = $customfields->fetchFieldStruct($parent_field_name);
            // If target field is not constrained at all, drop here
            if (empty($parent_field->referenced_table_name) ) {
                $result["alert"][] = "Target field is not constrained. Please either set a constraint on the target field first before enabling the cascading, or use your own custom cascade function.";
            // Else, target field is constrained
            } else {
                // Fetch the name of the linked table
                $parent_linked_table = $parent_field->referenced_table_name;
                // Fetch any constraint between the current field's linked table and parent/target's linked table
                $constraints = $customfields->fetchConstraints($linked_table, $parent_linked_table);
                // If there's no constraint between those two table, drop here
                if (empty($constraints)) {
                    $result["alert"][] = "Can't find a relationship (foreign key) between the two field's constraints! You will need to manually set the CascadeJoinFrom and, if necessary, CascadeJoinOn fields.";
                // Else, there is a constraint relationship between the two tables, we autocomplete the relevant fields
                } else {
                    $result['cascade_parent_join_from'] = $constraints[0]->column_name;
                    $result['cascade_parent_join_on'] = $constraints[0]->referenced_column_name;
                }
            }
        }
    }


    // -- Post-processing
    // Extract the options keys so that we can send them in a Javascript array (ie: not associative) so that in Javascript we can use this array of keys to print the options in the correct order (else Javascript may reorder an associative array = object in any order, usually in ascending order of the key, here the id in referenced primary column)
    /*
    foreach($result as $fname=>$attributes) { // for each field to modify via AJAX
        if (isset($attributes["options"]) and !isset($attributes["options_keys"])) { // if options is set for this field, and options_keys isn't already specified, we create options_keys to specify the order to print the options
            $result[$fname]["options_keys"] = array_keys($attributes["options"]); // just extract the keys of the options in the same order as they were pushed to the php array
        }
    }
    */

    // Return the result to the AJAX client-side by json encoding then printing it
    print_r(json_encode($result));
    die(); // stop processing

// == AutoUpdate Check Version
} elseif (!empty($_GET) and !empty($_GET['cf_autoupdate_check_version'])) {
    // Init result var
    $result = array();
    $url = 'http://www.customfields.org/autoupdate/LATESTVER.txt';

    // Init CURL and correct options
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_TIMEOUT, 20);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true); // follow on redirection
    curl_setopt($c, CURLOPT_URL, $url);

    // Fetch the remote document
    $data = curl_exec($c);
    if ($cfdebug) $result['raw'] = $data;

    // If there's a CURL error
    if (curl_error($c)) {
        $result['html'] = 'Can\'t access the remote update website. Please check your CURL settings. CURL error: <br />'.curl_error($c);
        curl_close($c);
    // Else, no CURL error
    } else {
        // Get the status code
        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $result['status'] = $status;
        curl_close($c);

        // HTTP error, the remote document is unreachable
        if ($status !== 200) {
            $result['html'] = $langs->trans('AutoUpdateCheckingUnreachable');
        // Else everything's ok! The document is reachable.
        } else { // if ($status == 404 or $status == 403 or $status == 520)
            // If there's a newer version, notify user
            if (version_compare($data, $cfversion, '>')) { // use version_compare and NOT just a string comparison like '3.3.9' > '3.3.10' which will be true because of string comparison, which we do not want!
                $result['html'] = 'A new release version '.$data.' is available (<a href="'.fullpageurl().'?cf_autoupdate_check_changelog=true">see changelog here</a>). You can freely <a href="https://github.com/lrq3000/dolibarr_customfields" target="_blank">download it from the git repository</a>. Don\'t forget to backup your database before updating!';
            // Else we've got the latest version!
            } else {
                $result['html'] = $langs->trans('AutoUpdateCheckingOK');
            }
        }
    }

    // Send back the result via JSON encoded array to the AJAX caller
    print_r(json_encode($result));
    die(); // stop processing here

// AutoUpdate Show Changelog
} elseif (!empty($_GET) and !empty($_GET['cf_autoupdate_check_changelog'])) {
    // Init result var
    $result = array();
    $url = 'http://www.customfields.org/autoupdate/CHANGELOG.txt';

    // Init CURL and correct options
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_TIMEOUT, 20);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($c, CURLOPT_URL, $url);

    // Fetch the remote document
    $data = curl_exec($c);
    if ($cfdebug) $result['raw'] = $data;

    // If there's a CURL error
    if (curl_error($c)) {
        print('Can\'t access the remote update website. Please check your CURL settings. CURL error: <br />'.curl_error($c));
        curl_close($c);
    // Else, no CURL error
    } else {
        // Get the status code
        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        // HTTP error, the remote document is unreachable
        if ($status !== 200) {
            print($langs->trans('AutoUpdateCheckingUnreachable'));
        // Else everything's ok! The document is reachable.
        } else { // if ($status == 404 or $status == 403 or $status == 520)
            $cl_path = dirname(__FILE__).'/../CHANGELOG.txt';
            // If local changelog doesn't exist, then print the new changelog directly
            if (!file_exists($cl_path)) {
                print(nl2br($data));
            // Else if the local changelog exists, we will show the difference between the local old changelog and the remote new one
            } else {
                // Load the local changelog
                $f = fopen($cl_path, "r") or die("Unable to open local CHANGELOG file to compute the diff! (".$cl_path.")");
                $fold = fread($f,filesize($cl_path));
                fclose($f);
                // Load the new changelog
                $fnew = $data;

                // Load the Diff library
                include_once(dirname(__FILE__).'/../lib/3rdparty/finediff/finediff.php');

                // Ensure correct encoding to handle potential accentuation
                $fold = mb_convert_encoding($fold, 'HTML-ENTITIES', 'UTF-8');
                $fnew = mb_convert_encoding($fnew, 'HTML-ENTITIES', 'UTF-8');

                // Compute the diff
                $diff_opcodes = FineDiff::getDiffOpcodes($fold, $fnew, FineDiff::$characterGranularity);
                $rendered_diff = FineDiff::renderDiffToHTMLFromOpcodes($fold, $diff_opcodes);

                // Pretty print the HTML diff (with CSS colors and stuffs)
                prettyPrintDiffHTML('CHANGELOG DIFF - CustomFields Pro', $rendered_diff);
            }
        }
    }

    die(); // stop processing here
}

function fullpageurl() {
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["SCRIPT_NAME"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"];
    }
    return $pageURL;
}

// Pretty print a HTML diff computed by the finediff library
function prettyPrintDiffHTML($title, $difftext) {
print <<<EOF
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<style type="text/css">
body {margin:0;border:0;padding:0;font:11pt sans-serif}
body > h1 {margin:0 0 0.5em 0;font:2em sans-serif;background-color:#def}
body > div {padding:2px}
p {margin-top:0}
ins {color:green;background:#dfd;text-decoration:none}
del {color:red;background:#fdd;text-decoration:none}
#params {margin:1em 0;font: 14px sans-serif}
.panecontainer > p {margin:0;border:1px solid #bcd;border-bottom:none;padding:1px 3px;background:#def;font:14px sans-serif}
.panecontainer > p + div {margin:0;padding:2px 0 2px 2px;border:1px solid #bcd;border-top:none}
.pane {margin:0;padding:0;border:0;width:100%;min-height:20em;overflow:auto;font:12px monospace}
#htmldiff {color:gray}
#htmldiff.onlyDeletions ins {display:none}
#htmldiff.onlyInsertions del {display:none}
</style>
<title>
EOF;
print($title);
print <<<EOF
</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
<div class="panecontainer" style="width:99%">
<div id="htmldiff" class="pane" style="white-space:pre-wrap">
EOF;
print($difftext);
print <<<EOF
</div>
</div>
</body>
</html>
EOF;
}

?>
