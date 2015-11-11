<?php
/* Copyright (C) 2012-2015   Stephen Larroque <lrq3000@gmail.com>
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
 *	\file       htdocs/productseasyaccess/conf/conf_pea.lib.php
 *	\ingroup    others
 *	\brief          Contains all the configurable variables to expand the functionnalities of ProductsEasyAccess
 */

$peaversion = '1.3.1'; // version of this module, useful for other modules to discriminate the version you are using (may be useful in case of newer features that are necessary for other modules to properly run)

// **** EXPANSION VARIABLES ****
// Here you can edit the values to expand the functionnalities of ProductsEasyAccess

// PEA custom thumbnails settings
// Here you can allow PEA to create its own thumbnails instead of just using Dolibarr's generated thumbnails
$peaThumbMaxWidth = 50; // Maximum width of the image, use 0 to disable pea thumbnails, -1 for auto size (will not be a limit), and > 0 for a size limit
$peaThumbMaxHeight = 50; // Same here for height
$peaThumbExtName = '_peathumbnail'; // thumbnail extension to differenciate from Dolibarr's own thumbnails

$peaBarcode = false; // Enable the barcode image substitution in ODT documents?

?>