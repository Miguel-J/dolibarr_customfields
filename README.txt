=================================================
*               CUSTOMFIELDS MODULE             *
*           by Stephen Larroque (lrq3000)       *
*                  version 3.5.13                *
*             release date 2015/11/11           *
=================================================

===== DESCRIPTION =====

This module will enable the user to create custom fields for the supported modules. You can choose the datatype, the size, the label(s), the possible values, the value by default, and even constraints (links to other tables) and custom sql definitions and custom sql statements!

CustomFields has been made with the intention of being as portable, flexible, modular and reusable as possible, so that it can be adapted to any Dolibarr's module, and to (almost) any user's need (even if something isn't implemented, you can most probably just use a custom sql statement, the rest will be managed automatically, even with custom statements!).

===== DOWNLOAD =====

The latest version can always be found on the github:
https://github.com/lrq3000/dolibarr_customfields

===== CONTACT =====

This module was created by Stephen Larroque for the Dolibarr ERP/CRM.

You can either contact the author by mail <lrq3000 at gmail dot com> or on the github above or on the Dolibarr's forum (french or english):
French: http://www.dolibarr.fr/forum/511-creation-dun-nouveau-module/28793-release-customfields-module-champs-personnalises
English: http://www.dolibarr.org/forum/511-creation-of-a-new-module/18877-release-customfields-module

Note: NO SUPPORT will be provided by email. Please post on the forum or on GitHub issues.

===== DOCUMENTATION =====

A full manual user guide can be found on the online Dolibarr's wiki:
http://wiki.dolibarr.org/index.php/Module_CustomFields
Mirror: https://htmlpreview.github.io/?https://raw.githubusercontent.com/lrq3000/dolibarr_customfields/pro/htdocs/customfields/docs/Module%20CustomFields%20-%20Dolibarr%20Wiki.html

For developpers wanting to extend CustomFields or implementers wanting to use CustomFields to its fullest potential, a full description of the API can be found here:
http://wiki.dolibarr.org/index.php/Module_CustomFields_Dev
Mirror: https://htmlpreview.github.io/?https://raw.githubusercontent.com/lrq3000/dolibarr_customfields/pro/htdocs/customfields/docs/Module%20CustomFields%20Dev%20-%20Dolibarr%20Wiki.html

For concrete examples of CustomFields implementations, you can find a list of tutorials here:
http://wiki.dolibarr.org/index.php/Module_CustomFields_Cases
Mirror: https://htmlpreview.github.io/?https://raw.githubusercontent.com/lrq3000/dolibarr_customfields/pro/htdocs/customfields/docs/Module%20CustomFields%20Cases%20-%20Dolibarr%20Wiki.html

===== INSTALL =====

Just as any Dolibarr's module, just unzip the contents of this package inside your dolibarr's folder (you should be asked to overwrite some files if done right).

You can also place CustomFields in the htdocs/custom/ folder, it should work correctly but if you encounter weird issues (like ajax cascading not working), you should try to place CustomFields directly in the root htdocs/ folder instead of htdocs/custom/.

===== SPECIAL THANK'S =====
- Thank's to Laurent Destailleur for supporting my work and to help me get used to the Dolibarr system.
- Thank's to Remy Younes for his patch for datetime support and bugfixes.
- Thank's to my wonderful fiancee for supporting me in everything I undertake.
- Thank's to everyone who supported the continued development of CustomFields.
- Finally, thank's to you for using CustomFields, I hope it will fulfill your needs.
