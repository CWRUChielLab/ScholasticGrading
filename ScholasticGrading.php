<?php

/**
 * ScholasticGrading extension for MediaWiki
 *
 * @package ScholasticGrading
 * @author Jeffrey Gill <jeffrey.p.gill@gmail.com>
 */


# Protect against web entry
if(!defined('MEDIAWIKI')) {
    echo <<<EOT
To install the ScholasticGrading extension, put the following line in LocalSettings.php:
require_once("\$IP/extensions/ScholasticGrading/ScholasticGrading.php");\n
EOT;
    exit(1);
}


# List the extension credits on the Special:Version page
$wgExtensionCredits['specialpage'][] = array(
    'path'              => __FILE__,
    'name'              => 'ScholasticGrading',
    'author'            => array('Jeffrey Gill'),
    'url'               => 'https://github.com/jpg18/ScholasticGrading',
    'descriptionmsg'    => 'scholasticgrading-desc',
    'version'           => 0.0,
);


# Register classes and system messages
$wgAutoloadClasses['SpecialGrades'] = dirname(__FILE__) . '/SpecialGrades.php';
$wgExtensionMessagesFiles['ScholasticGrading'] = dirname(__FILE__) . '/ScholasticGrading.i18n.php';
$wgExtensionMessagesFiles['ScholasticGradingAlias'] = dirname(__FILE__) . '/ScholasticGrading.alias.php';


# Create the special page Special:Grades
$wgSpecialPages['Grades'] = 'SpecialGrades';
$wgSpecialPageGroups['Grades'] = 'scholastic';


# User right to create, modify, and delete grades; given to Instructors by default
$wgAvailableRights[] = 'editgrades';
$wgGroupPermissions['instructor']['editgrades'] = true;


# Create the log Special:Log/grades
$wgLogTypes[] = 'grades';
$wgLogNames['grades'] = 'log-name-grades';
$wgLogHeaders['grades'] = 'log-description-grades';
$wgLogRestrictions['grades'] = 'editgrades';
$wgLogActionsHandlers['grades/*'] = 'LogFormatter';


# Register JavaScript and CSS resources
$wgResourceModules['ext.ScholasticGrading.assignment-date'] = array(
    'localBasePath' => dirname(__FILE__),
    'scripts' => 'modules/ext.ScholasticGrading.assignment-date.js',
    'dependencies' => 'jquery.ui.datepicker',
);
$wgResourceModules['ext.ScholasticGrading.evaluation-date'] = array(
    'localBasePath' => dirname(__FILE__),
    'scripts' => 'modules/ext.ScholasticGrading.evaluation-date.js',
    'dependencies' => 'jquery.ui.datepicker',
);
$wgResourceModules['ext.ScholasticGrading.vertical-text'] = array(
    'localBasePath' => dirname(__FILE__),
    'styles' => 'modules/ext.ScholasticGrading.vertical-text.css',
);


# Create database tables; triggered when maintenance/update.php is run
$wgHooks['LoadExtensionSchemaUpdates'][] = 'scholasticGradingSchemaUpdate';

function scholasticGradingSchemaUpdate ( $updater = null ) {
    if ( $updater === null ) {
        // <= 1.16 support
        global $wgExtNewTables, $wgExtModifiedFields;

        $wgExtNewTables[] = array( 'scholasticgrading_assignment',
            dirname(__FILE__) . '/sql/scholasticgrading_assignment.sql');

        $wgExtNewTables[] = array( 'scholasticgrading_evaluation',
            dirname(__FILE__) . '/sql/scholasticgrading_evaluation.sql');

    } else {
        // >= 1.17 support

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_assignment',
            dirname(__FILE__) . '/sql/scholasticgrading_assignment.sql', true ) );

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_evaluation',
            dirname(__FILE__) . '/sql/scholasticgrading_evaluation.sql', true ) );

    }

    return true;
}
