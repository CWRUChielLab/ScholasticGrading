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
    'url'               => 'https://github.com/CWRUChielLab/ScholasticGrading',
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
$wgResourceModules['ext.ScholasticGrading.SpecialGrades'] = array(
    'localBasePath' => dirname(__FILE__),
    'styles' => 'modules/ext.ScholasticGrading.SpecialGrades.css',
    'scripts' => 'modules/ext.ScholasticGrading.SpecialGrades.js',
    'dependencies' => array('jquery.ui.datepicker', 'jquery.ui.tabs'),
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

        $wgExtNewTables[] = array( 'scholasticgrading_adjustment',
            dirname(__FILE__) . '/sql/scholasticgrading_adjustment.sql');

        $wgExtNewTables[] = array( 'scholasticgrading_group',
            dirname(__FILE__) . '/sql/scholasticgrading_group.sql');

        $wgExtNewTables[] = array( 'scholasticgrading_groupuser',
            dirname(__FILE__) . '/sql/scholasticgrading_groupuser.sql');

        $wgExtNewTables[] = array( 'scholasticgrading_groupassignment',
            dirname(__FILE__) . '/sql/scholasticgrading_groupassignment.sql');

    } else {
        // >= 1.17 support

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_assignment',
            dirname(__FILE__) . '/sql/scholasticgrading_assignment.sql', true ) );

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_evaluation',
            dirname(__FILE__) . '/sql/scholasticgrading_evaluation.sql', true ) );

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_adjustment',
            dirname(__FILE__) . '/sql/scholasticgrading_adjustment.sql', true ) );

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_group',
            dirname(__FILE__) . '/sql/scholasticgrading_group.sql', true ) );

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_groupuser',
            dirname(__FILE__) . '/sql/scholasticgrading_groupuser.sql', true ) );

        $updater->addExtensionUpdate( array( 'addTable', 'scholasticgrading_groupassignment',
            dirname(__FILE__) . '/sql/scholasticgrading_groupassignment.sql', true ) );

    }

    return true;
}
