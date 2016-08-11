ScholasticGrading
=================

Copyright 2016 Jeffrey Gill, licensed under the terms of the GNU General
Public License, version 2 or later.

This is an extension for [MediaWiki](http://www.mediawiki.org) that allows
course graders to assign grades to students and allows students to view their
grades. It creates a special page, *Special:Grades*.

This project is maintained at
https://github.com/CWRUChielLab/ScholasticGrading.git

Installation
------------

You will need to have shell access to your wiki to install this extension.

Download and install the source code into `extensions/ScholasticGrading`:

    cd extensions
    git clone https://github.com/CWRUChielLab/ScholasticGrading.git

Add the following code to your `LocalSettings.php` file:

    require_once("$IP/extensions/ScholasticGrading/ScholasticGrading.php");
    $wgGroupPermissions['grader']['editgrades'] = true;

Run the wiki maintenance script. This will modify your wiki database by adding
tables needed for this extension.

    php maintenance/update.php

Navigate to *Special:Version* on your wiki to verify that the extension is
successfully installed. You should also see a new entry in the list of Special
pages called "Grades".

Usage
-----

### Graders

For the purposes of this documentation, a grader is anyone with the `editgrades`
right. If you followed the installation instructions above, there is a new user
group called "Graders" with this right. (User group membership is managed from
the "User rights management" Special page, *Special:UserRights*.)

When you visit *Special:Grades* using a grader account, you will see several
links:

* Manage groups
* Manage assignments
* View all user scores
* View grade log

If student groups and assignments have already been created, you will also see
tables listing students and assignments.

#### Groups

ScholasticGrading includes its own system of user groups for organizing
students. These student groups are distinct from the user group system included
with MediaWiki for managing user rights, and the two should not be confused.

Groups are used for attaching assignments to sets of users. You must create at
least one group. If you are hosting more than one course on your wiki, you
should create at least one group for each course. If the list of required
assignments, or their point values, is different between subsets of the students
in a single course (e.g., your course includes undergraduate and graduate
students, and graduate students are required to complete additional
assignments), you should create a group for each subset.

To create a group, click "Manage groups" from *Special:Grades*. Enter a title
for the group (e.g., "All students") and press "Apply changes". Group titles are
not visible to non-graders. Titles are not required to be unique, but this is
highly recommended, since groups with identical titles cannot be easily
distinguished.

To add or remove users from an existing group (a group must be created before
users can be added to it), click "Manage groups" again. The second table
contains a listing of all the wiki users, alphabetized by user name, with
columns for each group. Check or uncheck the appropriate boxes and press "Apply
changes" to add or remove users from groups. Generally, graders should not be
added to any groups.

Groups can be disabled by unchecking the "Enabled" box. Disabled groups are not
visible outside of the "Manage groups" interface, and the system will ignore
them in all other contexts. This means you can disable a group instead of
deleting it or removing each member from it, allowing you to easily reverse this
action in the future.

Groups can be deleted by pressing the delete button in the corresponding table
row. Deleting a group eliminates all user memberships to that group and any
assignment attachments to that group. Assignments and student scores are
unaffected, even if the assignments were attached exclusively to the deleted
group. In this case, the scores would cease to be visible to both students and
graders, but they would be restored if the assignments were later attached to
another enabled group.

#### Assignments

Assignments have a title, a date, and a point value. When an assignment is
attached to a group, all users that are members of the group will see it among
their list of assignments, and graders will be able to assign scores for the
assignment to those users.

To create an assignment, click "Manage assignments" from *Special:Grades*.
Click "Add another assignment" and specify a date, a title, and a point value in
the last row of the table. Assignment titles are not required to be unique, and
assignments with identical titles can be readily distinguished if they have
different dates. The date field may be left blank. Assignment values may be
integers or decimals. Attach the assignment to groups by checking the
appropriate boxes. Finally, press "Apply changes".

Assignments are sorted in the table first by date (dateless assignments are
listed at the end) then by title. After you create an assignment, it will be
sorted with the others and will not appear at the bottom of the table where you
first entered its information.

Assignments can be disabled by unchecking the "Enabled" box. Disabled
assignments are not visible outside the "Manage assignments" interface, and the
system will ignore them in all other contexts. This means you can disable an
assignment instead of deleting it, allowing you to easily reverse this action in
the future. When an assignment is disabled, existing student scores for the
assignment are preserved, and they will be visible again to students and
graders if the assignment is reenabled.

Assignments can be deleted by pressing the delete button in the corresponding
table row. Deleting an assignment destroys all existing student scores for that
assignment and should be used with caution.

#### Scores

TODO

### Non-Grader Users

Students should visit *Special:Grades* to view their scores. 

TODO

