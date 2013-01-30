<?php

/**
 * ScholasticGrading extension for MediaWiki
 *
 * @file
 * @ingroup Extensions
 *
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
$wgLogActionsHandlers['grades/*'] = 'LogFormatter';


# Create database tables; triggered when maintenance/update.php is run
$wgHooks['LoadExtensionSchemaUpdates'][] = 'scholasticGradesSchemaUpdate';

function scholasticGradesSchemaUpdate ( $updater = null ) {
    if ( $updater === null ) {
        // <= 1.16 support
        global $wgExtNewTables, $wgExtModifiedFields;

        $wgExtNewTables[] = array(
            'test',
            dirname(__FILE__) . '/sql/test.sql'
        );

    } else {
        // >= 1.17 support

        $updater->addExtensionUpdate(array('addTable',
            'test',
            dirname(__FILE__) . '/sql/test.sql', true));

    }

    return true;
}
