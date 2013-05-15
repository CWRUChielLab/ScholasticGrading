<?php

/**
 * Internationalization for ScholasticGrading
 *
 * @file
 * @ingroup Extensions
 */


$messages = array();


/** English
 * @author Jeffrey Gill
 */
$messages['en'] = array(
    # General messages
    'grades' => "Grades",
    'specialpages-group-scholastic' => "Scholastic tools",
    'scholasticgrading-desc' => "[[Special:Grades | Allows instructors to assign and students to review course grades]]",

    # User groups
    'group-instructor' => "Instructors",
    'group-instructor-member' => "{{GENDER:$1|instructor}}",
    'grouppage-instructor' => "{{ns:project}}:Instructors",

    # Rights
    'right-editgrades' => "Create, modify, and delete grades",
    'action-editgrades' => "edit grades",

    # Logs
    'log-name-grades' => "Grade log",
    'log-description-grades' => "Below is a list of the most recent grade changes made to [[Special:Grades]].",
    'logentry-grades-addAssignment' => "$1 {{GENDER:$2|added}} assignment \"$4\" ($5)",
    'logentry-grades-addEvaluation' => "$1 {{GENDER:$2|added}} an evaluation for \"$4\" ($5)",
    'logentry-grades-editAssignment' => "$1 {{GENDER:$2|updated}} assignment \"$4\" ($5)",
    'logentry-grades-editEvaluation' => "$1 {{GENDER:$2|updated}} evaluation for \"$4\" ($5)",
    #   $1: user name with links
    #   $2: user name
    #   $3: page title
    #   $4: param1
    #   $5: param2
);


/** Message documentation
 * @author Jeffrey Gill
 */
$messages['qqq'] = array(
    # General
    'grades' => "The name of the extension's entry in Special:SpecialPages and the title of the special page",
    'specialpages-group-scholastic' => "Category title in Special:SpecialPages",
    'scholasticgrading-desc' => "{{desc|name=ScholasticGrading|url=https://github.com/jpg18/ScholasticGrading}}",

    # User groups
    'group-instructor' => "{{doc-group|instructor}}",
    'group-instructor-member' => "{{doc-group|instructor|member",
    'grouppage-instructor' => "{{doc-group|instructor|page",

    # Rights
    'right-editgrades' => "{{doc-right|editgrades}}",
    'action-editgrades' => "{{doc-action|editgrades}}",

    # Logs
    'log-name-grades' => "Page title on Special:Log/grades",
    'log-description-grades' => "Description shown on Special:Log/grades",
    'logentry-grades-addAssignment' => "Appears on [[Special:Log/grades]] when a new assignment is created",
    'logentry-grades-addEvaluation' => "Appears on [[Special:Log/grades]] when a new evaluation is created",
    'logentry-grades-editAssignment' => "Appears on [[Special:Log/grades]] when an assignment is updated",
    'logentry-grades-editEvaluation' => "Appears on [[Special:Log/grades]] when an evaluation is updated",
);
