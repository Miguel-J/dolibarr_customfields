<?php

/**
 * PHP4 Object Model Compatibility
 * -------------------------------
 *
 * Author: David Grudl
 * Version: 2005-08-24
 *
 * Web: http://www.davidgrudl.com
 *
 * Copyright (c) 2005, David Grudl <david@grudl.com>
 * Licensed under GPL
 */

if (!class_exists('compatClass4', false)) {
    if (PHP_VERSION < 5) {


        class compatClass4 {


            // new PHP5 constructor
            function __construct()
            {
            }


            // old PHP4 constructor
            function compatClass4()
            {
                // generate references
                foreach ($this as $key => $foo)
                    // into object (garbage collector friendly method)
                    $this->__HIDDEN__[] = & $this->$key;
                    // or into global space (better when using print_r, var_dump)
                    // $GLOBALS['__HIDDEN__'][] = & $this->$key;

                // call php5 constructor
                $args = func_get_args();
                call_user_func_array(array(&$this, '__construct'), $args);
            }

        }

        // clone simulation (must be hidden behind PHP5 parser)
        eval('
        function clone($obj)
        {
            unset($obj->__HIDDEN__);
            foreach ($obj as $key => $value) {

                // reference to new variable
                $obj->$key = & $value;

                // and generate reference - into object or global space, see constructor compatClass4()
                $obj->__HIDDEN__[] = & $value;
                // $GLOBALS[\'__HIDDEN__\'][] = & $value;

                unset($value);
            }

            if (is_callable(array(&$obj, \'__clone\'))) $obj->__clone();

            return $obj;
        }
        ');


    } else {

        class compatClass4 {}

    }
}

?>