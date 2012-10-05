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
        } else {
            if(!strcmp($k, $needle)) {
                $result[] = $v;
                //array_push($result, $v);
            }
        }
    }

    return $result;
}

/* REQUIRES PHP 5.3 - works the same and is nicer
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
            if (is_array($v)) {
                $result = array_merge($result, array_extract_recursive($needle, $v)); // search for subarrays
                if (isset($v[$key]) and !strcmp($v[$key], $value)) { // check that the searched key exists and that the value corresponds (if true, we have a match for this exact pair of key/value)
                    $result[] = $v;
                    // array_push($result, $v); // overhead of function calling, better use $result[] = $v;
                    break; // since the subarray is at least pushed once, we don't want to push it twice because it also contains another $needle pattern, so just break
                }
            }
        }
    }

    return $result;
}

/* REQUIRES PHP 5.3 - works the same and is nicer (but not really recursive!)
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