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
 *	\file       htdocs/customfields/conf/conf_customfields_func.lib.php
 *	\brief      Configuration parsing library (mainly used to add multidimensional arrays crawling). Should be used to parse conf_customfields.lib.php arrays.
 *	\ingroup    customfields
 */


// **** CONFIG PROCESSING FUNCTIONS ****

/**
 * Get all values associated with the specified key/needle in a multidimensional array
 * eg: array_values_recursive('context', $modulesarray);
 *
 * @param mixed $needle string (key to search)
 * @param array $haystack
 * @return null|array
 */
function array_values_recursive($needle, $haystack){
    if (!isset($haystack)) return null;

    $result = array();
    foreach($haystack as $k=>$v) {
        if (is_array($v)) {
            $result = array_merge($result, array_values_recursive($needle, $v));
        } elseif (is_object($v)) {
            $result = array_merge($result, array_values_recursive($needle, (array)$v));
        } else {
            if(!strcmp($k, $needle)) {
                $result[] = $v;
                //array_push($result, $v);
            }
        }
    }

    return $result;
}

/* REQUIRES PHP 5.3 because of anonymous function - works the same and is nicer
function array_values_recursive($needle, array $haystack){
    $val = array();
    array_walk_recursive($haystack,
        function($v, $k) use($needle, &$val){
            if($k == $needle) array_push($val, $v);
        }
    );
    return $val;
}
*/


/**
 * Return arrays where the following pair of keys/values can be found
 * eg: array_extract_recursive(array('table_element'=>'facture'), $modulesarray);
 *
 * @param array $needle (keys/values)
 * @param array $haystack
 * @return null|array  (always return a multidimensional array: one big array containing every array detected as containing the requested key, even if only one array is returned)
 */
function array_extract_recursive($needle, $haystack){
    if (!isset($haystack)) return null;

    $result = array();
    foreach($haystack as $k=>$v) { // explore the haystack array
        foreach ($needle as $key=>$value) { // foreach pair of key/value (search pattern)
            if (is_object($v)) {
                $v = (array)$v;
            }
            if (is_array($v)) {
                $result = array_merge($result, array_extract_recursive($needle, $v)); // search for subarrays
                if (isset($v[$key]) and is_string($v[$key]) and !strcmp($v[$key], $value)) { // check that the searched key exists and that the value corresponds (if true, we have a match for this exact pair of key/value)
                    $result[] = $v;
                    // array_push($result, $v); // overhead of function calling, better use $result[] = $v;
                    break; // since the subarray is at least pushed once, we don't want to push it twice because it also contains another $needle pattern, so just break
                }
            }
        }
    }

    return $result;
}

/* REQUIRES PHP 5.3 because of anonymous function - works the same and is nicer (but not really recursive!)
function array_extract_recursive(array $needle, array $haystack){
    $val = array();
    array_walk($haystack,
        function($v, $k) use($needle, &$val){
            if (is_array($v)) {
                foreach ($needle as $key=>$value) {
                    if (isset($v[$key]) and $v[$key] == $value) {
                        array_push($val, $v);
                        break; // since the subarray is at least pushed once, we don't want to push it twice because it also contains another $needle pattern, so just break
                    }
                }
            }
        }
    );

    return $val;
}
*/


/**
 * Transforms an array into another array where values from the original array are reassociated together to form the new array
 * eg: array_reassociate('rowid', 'column_name', $linked_records);
 *
 * @param string $target_key             Array's key of the values to use as a key for the new array
 * @param string|array $target_value Array's key(s) of the value(s) to use as values for the new array. Can be either a string for one value, or an array of strings for multiple values.
 * @param array $haystack                Array to transform
 * @return null|array                            Returns an array as specified
 */
function array_reassociate($target_key, $target_value, $haystack){
    if (!isset($haystack)) return null;
    if (is_object($target_value)) $target_value = (array)$target_value;

    $result = array();
    foreach($haystack as $k=>$v) { // explore the haystack array
        if (is_object($v)) $v = (array)$v;
        if (isset($v[$target_key])) { // if the target key exists in this sub-array, we extract it
            $key = $v[$target_key]; // extract the key
            if (is_array($target_value)) { // if we want to extract multiple values, $target_value is an array and thus we extract each value and assign it in an array under the $target_key
                $result[$key] = array();
                foreach($target_value as $tv) { // for each target value, store it under the target_key inside an array
                    $result[$key][$tv] = $v[$tv];
                }
            } else { // else we extract just one value and assign it under the target key
                $result[$key] = $v[$target_value];
            }
        }
    }

    return $result;
}


/**
 * Similar to array_unshift (add a value at the beginning of an array) but for associative arrays (preserving the keys)
 *
 * @param array $arr        Array to modify
 * @param string $key       Key to assign to the new value
 * @param string $val        New value to assign in the array
 * @return null|array          Returns an associative array with the new key/value pair added at the beginning
 */
function array_unshift_assoc(&$arr, $key, $val)
{
    $arr = array_reverse($arr, true);
    $arr[$key] = $val;
    return array_reverse($arr, true);
}

// Compatibility with PHP 4: provides the array_replace_recursive function that is only available from PHP 5.3
if (!function_exists('array_replace_recursive')) {
    function array_replace_recursive($array, $array1) {
        function recurse($array, $array1) {
            foreach ($array1 as $key => $value) {
                // create new key in $array, if it is empty or not an array
                if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
                    $array[$key] = array();
                }

                // overwrite the value in the base array
                if (is_array($value)) {
                    $value = recurse($array[$key], $value);
                }
                $array[$key] = $value;
            }
            return $array;
        }

        // handle the arguments, merge one by one
        $args = func_get_args();
        $array = $args[0];
        if (!is_array($array)) {
            return $array;
        }
        for ($i = 1; $i < count($args); $i++) {
            if (is_array($args[$i])) {
                $array = recurse($array, $args[$i]);
            }
        }
        return $array;
    }
}



/**
 * PHP variable variables with object's property or array value
 * Courtesy of Sam-Mauris Yong
 * Enhanced by lrq3000 to support recursive access of subproperties
 * This function is mainly used as a safer replacement of eval().
 *
 * @param array/object   $obj     Object or array to traverse
 * @param string             $path   A string containing the path of the property to fetch the value (eg: client->rowid will be translated as $obj->client->rowid, and [2]['foo'] will be translated as $obj[2]['foo'] )
 * @return null|any          Returns the field's value or null if a key in the path isn't set, thus you can do a isset(varvar($obj, 'my->path')) to check whether this variable is defined or not.
 */
function varvar($obj, $path){
    // Accessing object subproperties
    if(strpos($path,'->') !== false){
        $parts = explode('->',$path);
        $ret = $obj;
        foreach($parts as $part) {
            if (!isset($ret->$part)) return null; // return null if some part of the path isn't set
            $ret = $ret->$part;
        }
        return $ret;
    // Arrays
    }elseif(strpos($path,'[') !== false && strpos($path,']') !== false){
        $parts = explode('[',$path);
        //global ${$parts[0]};
        $ret = $obj;
        foreach($parts as $part) {
            $key = substr($part,0,strlen($part)-1);
            if (!isset($ret[$key])) return null; // return null if some part of the path isn't set
            $ret = $ret[$key];
        }
        return $ret;
    // Just a string
    }else{
        if (is_object($obj) and isset($obj->$path)) {
            return $obj->$path; // $obj->${$path}
        } elseif (is_array($obj) and isset($obj[$path])) {
            return $obj[$path];
        } else {
            return null;
        }
    }
}

?>