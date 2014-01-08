ScholasticGrading
=================

Copyright 2014 Jeffrey Gill, licensed under the terms of the GNU General
Public License, version 2 or later.

This is an extension for [MediaWiki](www.mediawiki.org) that allows course
instructors to assign grades to students and allows students to view their
grades. It creates a special page, *Special:Grades*.

This project is maintained at
https://github.com/CWRUChielLab/ScholasticGrading.git

Installation
------------

You will need to have shell access to your wiki to install this extension.

Download and install the source into `extensions/ScholasticGrading`:

    cd extensions
    git clone https://github.com/CWRUChielLab/ScholasticGrading.git

Add the following code to your `LocalSettings.php` file:

    require_once("$IP/extensions/ScholasticGrading/ScholasticGrading.php");

Run the wiki maintenance script. This will modify your wiki database by adding
tables needed for this extension.

    php maintenance/update.php

Navigate to *Special:Version* on your wiki to verify that the extension is
successfully installed. You should see a new entry in the list of Special pages
called "Grades".

Usage
-----

TODO

