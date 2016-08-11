<?php

/**
 * Implements Special:Grades
 *
 * @package ScholasticGrading
 * @author Jeffrey Gill <jeffrey.p.gill@gmail.com>
 */


class SpecialGrades extends SpecialPage {

    /**
     * Constructor for SpecialGrades
     *
     * Sets up the special page Special:Grades
     *
     */

    function __construct() {
        parent::__construct('Grades');
    } /* end constructor */


    /**
     * Main execution method
     *
     * This function is run automatically when the special page is loaded
     *
     * @param string|null $subPage optional action passed in URL as Special:Grades/subPage
     */

    function execute ( $subPage ) {

        $page = $this->getOutput();

        # Set the page title
        $this->setHeaders();

        # Load JavaScript and CSS resources
        $page->addModules('ext.ScholasticGrading.SpecialGrades');

        # Check whether database tables exist
        if ( !$this->allTablesExist() ) {
            return;
        }

        # Process requests
        $request = $this->getRequest();
        $action = $subPage ? $subPage : $request->getVal('action', $subPage);

        switch ( $action ) {

        case 'assignments':

            if ( $this->canModify(true) ) {
                $this->showAllAssignments();
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'groups':

            if ( $this->canModify(true) ) {
                $this->showAllGroups();
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'viewuserscores':

            if ( $this->canModify(true) ) {
                $page->addHTML(Html::rawElement('p', null,
                    Html::element('a', array('class' => 'sg-toggleunevaluated', 'href' => 'javascript:void(0);'), 'Hide unevaluated assignments')) . "\n");
                $this->showUserScores(
                    $request->getVal('user', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'viewalluserscores':

            if ( $this->canModify(true) ) {
                $page->addHTML(Html::rawElement('p', null,
                    Html::element('a', array('class' => 'sg-toggleuserscores', 'href' => 'javascript:void(0);'), 'Hide tables') . ' | ' .
                    Html::element('a', array('class' => 'sg-toggleunevaluated', 'href' => 'javascript:void(0);'), 'Hide unevaluated assignments')) . "\n");
                $this->showAllUserScores();
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'editassignment':

            if ( $this->canModify(true) ) {
                $this->showAssignmentForm(
                    $request->getVal('id', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'editevaluation':

            if ( $this->canModify(true) ) {
                $this->showEvaluationForm(
                    $request->getVal('user', false),
                    $request->getVal('assignment', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'editadjustment':

            if ( $this->canModify(true) ) {
                $this->showAdjustmentForm(
                    $request->getVal('id', false),
                    $request->getVal('user', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'editgroup':

            if ( $this->canModify(true) ) {
                $this->showGroupForm(
                    $request->getVal('id', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'edituserscores':

            if ( $this->canModify(true) ) {
                $this->showUserScoreForms(
                    $request->getVal('user', false)
                );
                $page->addHTML(Html::rawElement('p', null,
                    Linker::linkKnown($this->getTitle(), 'See student\'s view', array(),
                        array('action' => 'viewuserscores', 'user' => $request->getVal('user', false))) . '.') . "\n");
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'editassignmentscores':

            if ( $this->canModify(true) ) {
                $this->showAssignmentEvaluationForms(
                    $request->getVal('id', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'submit':

            if ( $this->canModify(true) ) {
                if ( $request->wasPosted() && $this->getUser()->matchEditToken($request->getVal('wpEditToken')) ) {
                    $this->submitForms();
                } else {
                    # Prevent cross-site request forgeries
                    $page->addWikiMsg('sessionfailure');
                }
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        default:

            if ( !$this->getUser()->isAnon() ) {

                # User is registered and logged in

                if ( $this->canModify(false) ) {
                    $page->addHTML(Html::rawElement('p', null,
                        Linker::linkKnown($this->getTitle(), 'Manage groups', array(),
                            array('action' => 'groups')) . ' | ' .
                        Linker::linkKnown($this->getTitle(), 'Manage assignments', array(),
                            array('action' => 'assignments')) . ' | ' .
                        Linker::linkKnown($this->getTitle(), 'View all user scores', array(),
                            array('action' => 'viewalluserscores')) . ' | ' .
                            Linker::linkKnown(Title::newFromText('Special:Log/grades'), 'View grade log')) . "\n");
                    $this->showGradeGrids();
                } else {
                    $page->addHTML(Html::rawElement('p', null,
                        Html::element('a', array('class' => 'sg-toggleunevaluated', 'href' => 'javascript:void(0);'), 'Hide unevaluated assignments')) . "\n");
                    $this->showUserScores(
                        $this->getUser()->getId()
                    );
                }

            } else {

                # The user is not logged in
                $page->addWikiText('Grades are only available to registered users. You must be logged in to see your grades.');

            }

            break;

        } /* end switch action */

    } /* end execute */


    /**
     * Check whether all database tables exist
     *
     * Determines whether the database tables used by the extension exist
     * and provides warnings if any do not.
     */

    function allTablesExist () {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        $allTablesExist = true;
        $tables = array(
            'scholasticgrading_assignment',
            'scholasticgrading_evaluation',
            'scholasticgrading_adjustment',
            'scholasticgrading_group',
            'scholasticgrading_groupuser',
            'scholasticgrading_groupassignment'
        );

        foreach ( $tables as $table ) {
            if ( !$dbr->tableExists($table) ) {
                $page->addHTML(Html::element('p', null, 'Database table ' . $table . ' does not exist.') . "\n");
                $allTablesExist = false;
            }
        }

        if ( !$allTablesExist )
            $page->addHTML(Html::element('p', null, 'Run maintenance/update.php.') . "\n");

        return $allTablesExist;

    } /* end allTablesExist */


    /**
     * Check grade editing permissions
     *
     * Determines whether the user has privileges to modify assignments and grades
     *
     * @param bool $printErrors whether to display error messages on the page
     * @return bool whether the user can modify assignments and grades
     */

    function canModify ( $printErrors = false ) {

        $page = $this->getOutput();

        if ( !$this->getUser()->isAllowed('editgrades') ) {

            # The user does not have the correct privileges
            if ( $printErrors ) {
                throw new PermissionsError('editgrades');
            }
            return false;

        } elseif ( wfReadOnly() ) {

            # The database is in read-only mode
            if ( $printErrors ) {
                $page->readOnlyPage();
            }
            return false;

        }

        return true;

    } /* end canModify */


    /**
     * Validate a title string
     *
     * Returns the string with leading and trailing
     * whitespace removed if non-whitespace characters
     * are present. Otherwise, returns false.
     *
     * @param string $test_title title string to validate
     * @return sting|bool the trimmed string or false
     */

    function validateTitle ( $test_title ) {

        if ( strlen(trim($test_title)) > 0 ) {
            return trim($test_title);
        } else {
            return false;
        }

    } /* end validateTitle */


    /**
     * Validate a date string
     *
     * Returns the date string if it has the form
     * YYYY-MM-DD or null if it is an empty string.
     * Otherwise, returns false.
     *
     * @param string $test_date date string to validate
     * @return sting|bool the date string or false
     */

    function validateDate ( $test_date ) {

        if ( trim($test_date) == '' )
            return null;

        $date = DateTime::createFromFormat('Y-m-d', trim($test_date));
        $date_errors = DateTime::getLastErrors();
        if ( $date_errors['warning_count'] + $date_errors['error_count'] > 0 ) {
            # Date is not of the form YYYY-MM-DD
            return false;
        } else {
            return $date->format('Y-m-d');
        }

    } /* end validateDate */


    /**
     * Compare two scores and sort by date then title
     *
     * @param array $score1 score array containing date and title keys
     * @param array $score2 score array containing date and title keys
     * @return int -1, 1, or 0 if $score1 should be sorted before, after, or is equivalent to $score2
     */

    function sortScores ( $score1, $score2 ) {

        if ( $score1['date'] === null && $score2['date'] === null ) {
            return strcmp($score1['title'], $score2['title']);
        } elseif ( $score1['date'] === null ) {
            return 1;
        } elseif ( $score2['date'] === null ) {
            return -1;
        } elseif ( strcmp($score1['date'], $score2['date']) ) {
            return strcmp($score1['date'], $score2['date']);
        } else {
            return strcmp($score1['title'], $score2['title']);
        }

    } /* end sortScores */


    /**
     * Set the page title depending on context
     *
     * Creates the title shown at the top of each page
     * and in the browser title bar. Title depends on
     * the interface in use.
     */

    function getDescription() {

        $request = $this->getRequest();
        $action = $request->getVal('action');

        if ( $request->getVal('title') == $this->getTitle() ) {

            # User is visiting this special page

            switch ( $action ) {

            case 'assignments':

                return 'Manage assignments';

                break;

            case 'groups':

                return 'Manage groups';

                break;

            case 'edituserscores':

                # Check whether user exists
                $dbr = wfGetDB(DB_SLAVE);
                $user = $dbr->selectRow('user', '*', array('user_id' => $request->getVal('user', false)));
                if ( $user ) {

                    # The user exists
                    return 'Edit scores for ' . $this->getUserDisplayName($user->user_id);

                } else {

                    # The user does not exist
                    return $this->msg('grades')->plain();

                }

                break;

            case 'editassignmentscores':

                # Check whether assignment exists
                $dbr = wfGetDB(DB_SLAVE);
                $assignment = $dbr->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $request->getVal('id', false)));
                if ( $assignment ) {

                    # The assignment exists
                    return 'Edit scores for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ')';

                } else {

                    # The assignment does not exist
                    return $this->msg('grades')->plain();

                }

                break;

            case 'viewuserscores':

                return 'Grades for ' . $this->getUserDisplayName($request->getVal('user', false));

                break;

            default:

                if ( !$this->getUser()->isAnon() ) {

                    # User is registered and logged in

                    if ( $this->canModify(false) ) {

                        # Instructor interface
                        return $this->msg('grades')->plain();

                    } else {

                        # Student interface
                        return 'Grades for ' . $this->getUserDisplayName(false);

                    }

                } else {

                    # The user is not logged in
                    return $this->msg('grades')->plain();

                }

                break;

            }

        } else {

            # User is viewing another page, such as Special:SpecialPages
            return $this->msg('grades')->plain();

        }

    } /* end getDescription */


    /**
     * Get the user display name
     *
     * If a valid user id is provided, returns the
     * user's real name with user name in parentheses,
     * or, if the user has no real name, returns the
     * user name instead. If false is provided, uses
     * the information of the user that executed the
     * page. Returns false if an invalid id is provided.
     *
     * @param int|bool user_id the user id
     * @return string|bool the user display name or false
     */

    function getUserDisplayName ( $user_id = false ) {

        if ( $user_id ) {

            # Use the provided user id to look up
            # the user in the database

            $dbr = wfGetDB(DB_SLAVE);

            # Check whether user exists
            $user = $dbr->selectRow('user', '*', array('user_id' => $user_id));
            if ( $user ) {

                # The user exists

                # Determine if the user has a real name
                if ( $user->user_real_name ) {

                    # Return the user real name with user name
                    return $user->user_real_name . ' (' . $user->user_name . ')';

                } else {

                    # Return user name only
                    return $user->user_name;

                }

            } else {

                # The user does not exist
                return false;

            }

        } else {

            # Use the user executing the page

            # Determine if the user has a real name
            if ( $this->getUser()->getRealName() ) {

                # Return the user real name with user name
                return $this->getUser()->getRealName() . ' (' . $this->getUser()->getName() . ')';

            } else {

                # Return user name only
                return $this->getUser()->getName();

            }

        }

    } /* end getUserDisplayName */


    /**
     * Process sets of assignment/evaluation/adjustment/group creation/modification/deletion requests
     *
     * Processes assignment, evaluation, adjustment, and group forms. Does not
     * directly modify the database. Instead, each set of assignment parameters
     * is sent to the writeAssignment function one at a time, each set of
     * evaluation parameters is sent to the writeEvaluation function one at a
     * time, each set of adjustment parameters is sent to the writeAdjustment
     * function one at a time, and each set of group parameters is sent to the
     * writeGroup function one at a time. The function first checks whether
     * a delete button was pressed for any assignment, evaluation, adjustment,
     * or group. If one was, all other requests are ignored, and the delete
     * request is sent to the appropriate database function.
     *
     * @return bool whether the database was changed or not
     */

    function submitForms () {

        $page = $this->getOutput();
        $request = $this->getRequest();

        $assignmentParams = $request->getArray('assignment-params');
        $evaluationParams = $request->getArray('evaluation-params');
        $adjustmentParams = $request->getArray('adjustment-params');
        $groupParams      = $request->getArray('group-params');

        if ( !$assignmentParams && !$evaluationParams && !$adjustmentParams && !$groupParams ) {

            # No parameters are available to be processed
            $page->addWikiText('Parameter arrays are missing or invalid.');
            return;
        
        }

        # If this is a submission of more than one item, turn off warnings
        $verboseFlag = true;
        if ( count($assignmentParams) + count($evaluationParams) + count($adjustmentParams) + count($groupParams) > 1  )
            $verboseFlag = false;

        # Fill in missing parameters and handle any delete requests

        if ( $assignmentParams ) {

            foreach ( $assignmentParams as $key => $assignment ) {

                # Store default values for missing parameters
                if ( !array_key_exists('assignment-id', $assignment) )
                    $assignmentParams[$key]['assignment-id'] = false;
                if ( !array_key_exists('assignment-title', $assignment) )
                    $assignmentParams[$key]['assignment-title'] = false;
                if ( !array_key_exists('assignment-value', $assignment) )
                    $assignmentParams[$key]['assignment-value'] = false;
                if ( !array_key_exists('assignment-enabled', $assignment) ) {
                    # Form posts omit checkboxes that are unchecked
                    $assignmentParams[$key]['assignment-enabled'] = false;
                }
                if ( !array_key_exists('assignment-date', $assignment) )
                    $assignmentParams[$key]['assignment-date'] = false;
                if ( !array_key_exists('assignment-group', $assignment) )
                    $assignmentParams[$key]['assignment-group'] = false;

                # Check whether a delete button was pressed
                if ( $request->getVal('delete-assignment-' . $key, false) ) {

                    $dbChanged = $this->writeAssignment(
                        $assignmentParams[$key]['assignment-id'],
                        $assignmentParams[$key]['assignment-title'],
                        $assignmentParams[$key]['assignment-value'],
                        $assignmentParams[$key]['assignment-enabled'],
                        $assignmentParams[$key]['assignment-date'],
                        $assignmentParams[$key]['assignment-group'],
                        true
                    );

                    if ( $dbChanged )
                        $page->addWikiText('All changes are logged at [[Special:Log/grades]].');

                    return;

                }

            }

        }

        if ( $evaluationParams ) {

            foreach ( $evaluationParams as $key => $evaluation ) {

                # Store default values for missing parameters
                if ( !array_key_exists('evaluation-user', $evaluation) )
                    $evaluationParams[$key]['evaluation-user'] = false;
                if ( !array_key_exists('evaluation-assignment', $evaluation) )
                    $evaluationParams[$key]['evaluation-assignment'] = false;
                if ( !array_key_exists('evaluation-score', $evaluation) )
                    $evaluationParams[$key]['evaluation-score'] = false;
                if ( !array_key_exists('evaluation-enabled', $evaluation) ) {
                    # Form posts omit checkboxes that are unchecked
                    $evaluationParams[$key]['evaluation-enabled'] = false;
                }
                if ( !array_key_exists('evaluation-date', $evaluation) )
                    $evaluationParams[$key]['evaluation-date'] = false;
                if ( !array_key_exists('evaluation-comment', $evaluation) )
                    $evaluationParams[$key]['evaluation-comment'] = false;
                    
                # Check whether a delete button was pressed
                if ( $request->getVal('delete-evaluation-' . $key, false) ) {

                    $dbChanged = $this->writeEvaluation(
                        $evaluationParams[$key]['evaluation-user'],
                        $evaluationParams[$key]['evaluation-assignment'],
                        $evaluationParams[$key]['evaluation-score'],
                        $evaluationParams[$key]['evaluation-enabled'],
                        $evaluationParams[$key]['evaluation-date'],
                        $evaluationParams[$key]['evaluation-comment'],
                        true
                    );

                    if ( $dbChanged )
                        $page->addWikiText('All changes are logged at [[Special:Log/grades]].');

                    return;

                }

            }

        }

        if ( $adjustmentParams ) {

            foreach ( $adjustmentParams as $key => $adjustment ) {

                # Store default values for missing parameters
                if ( !array_key_exists('adjustment-id', $adjustment) )
                    $adjustmentParams[$key]['adjustment-id'] = false;
                if ( !array_key_exists('adjustment-user', $adjustment) )
                    $adjustmentParams[$key]['adjustment-user'] = false;
                if ( !array_key_exists('adjustment-title', $adjustment) )
                    $adjustmentParams[$key]['adjustment-title'] = false;
                if ( !array_key_exists('adjustment-value', $adjustment) )
                    $adjustmentParams[$key]['adjustment-value'] = false;
                if ( !array_key_exists('adjustment-score', $adjustment) )
                    $adjustmentParams[$key]['adjustment-score'] = false;
                if ( !array_key_exists('adjustment-enabled', $adjustment) ) {
                    # Form posts omit checkboxes that are unchecked
                    $adjustmentParams[$key]['adjustment-enabled'] = false;
                }
                if ( !array_key_exists('adjustment-date', $adjustment) )
                    $adjustmentParams[$key]['adjustment-date'] = false;
                if ( !array_key_exists('adjustment-comment', $adjustment) )
                    $adjustmentParams[$key]['adjustment-comment'] = false;

                # Check whether a delete button was pressed
                if ( $request->getVal('delete-adjustment-' . $key, false) ) {

                    $dbChanged = $this->writeAdjustment(
                        $adjustmentParams[$key]['adjustment-id'],
                        $adjustmentParams[$key]['adjustment-user'],
                        $adjustmentParams[$key]['adjustment-title'],
                        $adjustmentParams[$key]['adjustment-value'],
                        $adjustmentParams[$key]['adjustment-score'],
                        $adjustmentParams[$key]['adjustment-enabled'],
                        $adjustmentParams[$key]['adjustment-date'],
                        $adjustmentParams[$key]['adjustment-comment'],
                        true
                    );

                    if ( $dbChanged )
                        $page->addWikiText('All changes are logged at [[Special:Log/grades]].');

                    return;

                }

            }

        }

        if ( $groupParams ) {

            foreach ( $groupParams as $key => $group ) {

                # Store default values for missing parameters
                if ( !array_key_exists('group-id', $group) )
                    $groupParams[$key]['group-id'] = false;
                if ( !array_key_exists('group-title', $group) )
                    $groupParams[$key]['group-title'] = false;
                if ( !array_key_exists('group-enabled', $group) ) {
                    # Form posts omit checkboxes that are unchecked
                    $groupParams[$key]['group-enabled'] = false;
                }
                if ( !array_key_exists('group-user', $group) )
                    $groupParams[$key]['group-user'] = false;

                # Check whether a delete button was pressed
                if ( $request->getVal('delete-group-' . $key, false) ) {

                    $dbChanged = $this->writeGroup(
                        $groupParams[$key]['group-id'],
                        $groupParams[$key]['group-title'],
                        $groupParams[$key]['group-enabled'],
                        $groupParams[$key]['group-user'],
                        true
                    );

                    if ( $dbChanged )
                        $page->addWikiText('All changes are logged at [[Special:Log/grades]].');

                    return;

                }

            }

        }

        # A delete button was not pressed, so write all changes
        $dbChangedAtLeastOnce = false;

        if ( $assignmentParams ) {

            # Make database changes for each assignment in assignment-params
            foreach ( $assignmentParams as $assignment ) {

                $dbChanged = $this->writeAssignment(
                    $assignment['assignment-id'],
                    $assignment['assignment-title'],
                    $assignment['assignment-value'],
                    $assignment['assignment-enabled'],
                    $assignment['assignment-date'],
                    $assignment['assignment-group'],
                    false,
                    $verboseFlag
                );
                $dbChangedAtLeastOnce = $dbChangedAtLeastOnce || $dbChanged;

            }

        }

        if ( $evaluationParams ) {

            # Make database changes for each evaluation in evaluation-params
            foreach ( $evaluationParams as $evaluation ) {
                
                $dbChanged = $this->writeEvaluation(
                    $evaluation['evaluation-user'],
                    $evaluation['evaluation-assignment'],
                    $evaluation['evaluation-score'],
                    $evaluation['evaluation-enabled'],
                    $evaluation['evaluation-date'],
                    $evaluation['evaluation-comment'],
                    false,
                    $verboseFlag
                );
                $dbChangedAtLeastOnce = $dbChangedAtLeastOnce || $dbChanged;

            }

        }

        if ( $adjustmentParams ) {

            # Make database changes for each adjustment in adjustment-params
            foreach ( $adjustmentParams as $adjustment ) {

                $dbChanged = $this->writeAdjustment(
                    $adjustment['adjustment-id'],
                    $adjustment['adjustment-user'],
                    $adjustment['adjustment-title'],
                    $adjustment['adjustment-value'],
                    $adjustment['adjustment-score'],
                    $adjustment['adjustment-enabled'],
                    $adjustment['adjustment-date'],
                    $adjustment['adjustment-comment'],
                    false,
                    $verboseFlag
                );
                $dbChangedAtLeastOnce = $dbChangedAtLeastOnce || $dbChanged;

            }

        }

        if ( $groupParams ) {

            # Make database changes for each group in group-params
            foreach ( $groupParams as $group ) {

                $dbChanged = $this->writeGroup(
                    $group['group-id'],
                    $group['group-title'],
                    $group['group-enabled'],
                    $group['group-user'],
                    false,
                    $verboseFlag
                );
                $dbChangedAtLeastOnce = $dbChangedAtLeastOnce || $dbChanged;

            }

        }

        if ( $dbChangedAtLeastOnce )
            $page->addWikiText('All changes are logged at [[Special:Log/grades]].');
        else
            $page->addWikiText('No changes were made.');

    } /* end submitForms */


    /**
     * Execute assignment creation/modification/deletion
     *
     * Creates, modifies, or deletes an assignment by directly
     * modifying the database. If the (id) key provided corresponds
     * to an existing assignment, the function will modify or delete
     * that assignment depending on whether the delete flag is set.
     * Otherwise, the function will create a new assignment.
     * Also handles group memberships. Parameters are initially
     * validated and sanitized.
     *
     * @param int|bool    $assignmentID the id of an assignment
     * @param int|bool    $assignmentTitle the title of an assignment
     * @param float|bool  $assignmentValue the value of an assignment
     * @param int|bool    $assignmentEnabled the enabled status of an assignment
     * @param string|bool $assignmentDate the date of an assignment
     * @param array|bool  $assignmentGroups the group memberships of an assignment
     * @param bool        $delete whether to delete the assignment or not
     * @param bool        $verbose whether to print warnings
     * @return bool       whether the database was changed or not
     */

    function writeAssignment ( $assignmentID = false, $assignmentTitle = false, $assignmentValue = false, $assignmentEnabled = false, $assignmentDate = false, $assignmentGroups = false, $delete = false, $verbose = true ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);
        $log = new LogPage('grades', false);

        # Validate/sanitize assignment parameters
        $assignmentID          = filter_var($assignmentID, FILTER_VALIDATE_INT);
        $assignmentTitle       = filter_var($assignmentTitle, FILTER_CALLBACK, array('options' => array($this, 'validateTitle')));
        $assignmentValue       = filter_var($assignmentValue, FILTER_VALIDATE_FLOAT);
        if ( !is_bool($assignmentEnabled) ) {
            $assignmentEnabled = filter_var($assignmentEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $assignmentDate        = filter_var($assignmentDate, FILTER_CALLBACK, array('options' => array($this, 'validateDate')));
        if ( $assignmentTitle === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid title for assignment (may not be empty).');
            return false;
        }
        if ( $assignmentValue === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid value for assignment (must be a float).');
            return false;
        }
        if ( !is_bool($assignmentEnabled) ) {
            if ( $verbose )
                $page->addWikiText('Invalid enabled status for assignment (must be a boolean).');
            return false;
        }
        if ( $assignmentDate === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid date for assignment (must have form YYYY-MM-DD).');
            return false;
        }
        if ( !is_array($assignmentGroups) ) {
            if ( $verbose )
                $page->addWikiText('Invalid group membership array for assignment (must be an array).');
            return false;
        }

        # Check whether assignment exists
        $assignment = $dbw->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $assignmentID));
        if ( $assignment ) {

            # The assignment exists

            if ( !$delete ) {

                # Edit the existing assignment
                $totalAffectedRows = 0;
                $dbw->update('scholasticgrading_assignment', array(
                    'sga_title'   => $assignmentTitle,
                    'sga_value'   => $assignmentValue,
                    'sga_enabled' => $assignmentEnabled,
                    'sga_date'    => $assignmentDate,
                ), array('sga_id' => $assignmentID));
                $totalAffectedRows += $dbw->affectedRows();

                # Create a new log entry
                if ( $dbw->affectedRows() > 0 ) {
                    $log->addEntry('editAssignment', $this->getTitle(),
                        'id=' . $assignmentID .
                        '; from ["' .
                            $assignment->sga_title . '", ' .
                            $assignment->sga_date . ', ' .
                            'value=' . (float)$assignment->sga_value . ', ' .
                            ($assignment->sga_enabled ? 'enabled' : 'disabled') .
                        '] to ["' .
                            $assignmentTitle . '", ' .
                            $assignmentDate . ', ' .
                            'value=' . (float)$assignmentValue . ', ' .
                            ($assignmentEnabled ? 'enabled' : 'disabled') .
                        ']', array());
                }

                # Edit the group memberships for the assignment and create log entries
                foreach ( $assignmentGroups as $groupID => $isMember ) {
                    $membership = $dbw->selectRow('scholasticgrading_groupassignment', '*', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignmentID));
                    $group = $dbw->selectRow('scholasticgrading_group', '*', array('sgg_id' => $groupID));
                    if ( $membership && !$isMember ) {
                        $dbw->delete('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignmentID));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteGroupAssignment', $this->getTitle(),
                                'assignment "' .
                                    $assignmentTitle . '" (' .
                                    $assignmentDate . ') [id=' .
                                    $assignmentID .
                                ']; group "' .
                                    $group->sgg_title . '" [id=' .
                                    $groupID .
                                ']', array());
                        }
                    } elseif ( !$membership && $isMember ) {
                        $dbw->insert('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignmentID));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('addGroupAssignment', $this->getTitle(),
                                'assignment "' .
                                    $assignmentTitle . '" (' .
                                    $assignmentDate . ') [id=' .
                                    $assignmentID .
                                ']; group "' .
                                    $group->sgg_title . '" [id=' .
                                    $groupID .
                                ']', array());
                        }
                    }
                }

                # Report success
                if ( $totalAffectedRows === 0 ) {

                    if ( $verbose )
                        $page->addWikiText('Database unchanged.');
                    return false;

                } else {

                    $page->addWikiText('\'\'\'Assignment "' . $assignmentTitle . '" (' . $assignmentDate . ') updated!\'\'\'');
                    return true;

                }

            } else {

                # Prepare to delete the existing assignment

                if ( !$request->getVal('confirm-delete') ) {

                    # Ask for confirmation of delete
                    $page->addWikiText('Are you sure you want to delete assignment "' . $assignment->sga_title . '" (' . $assignment->sga_date . ')?');

                    # List all evaluations that will be deleted with the assignment
                    $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_assignment_id' => $assignmentID));
                    if ( $evaluations->numRows() > 0 ) {
                        $content = '';
                        $content .= Html::element('p', null, 'Evaluations for the following users will be deleted:') . "\n";
                        $content .= Html::openElement('ul', null);
                        foreach ( $evaluations as $evaluation ) {
                            $user = $dbw->selectRow('user', '*', array('user_id' => $evaluation->sge_user_id));
                            $content .= Html::rawElement('li', null, $this->getUserDisplayName($user->user_id)) . "\n";
                        }
                        $content .= Html::closeElement('ul') . "\n";
                        $page->addHtml($content);
                    }

                    # Provide a delete button
                    $content = '';
                    $content .= Html::openElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        ));
                    $content .= Xml::submitButton('Delete assignment', array('name' => 'delete-assignment-0'));
                    $content .= Html::hidden('confirm-delete', true);
                    $content .= Html::hidden('assignment-params[0][assignment-id]',      $assignmentID);
                    $content .= Html::hidden('assignment-params[0][assignment-title]',   $assignmentTitle);
                    $content .= Html::hidden('assignment-params[0][assignment-value]',   $assignmentValue);
                    $content .= Html::hidden('assignment-params[0][assignment-enabled]', $assignmentEnabled ? 1 : 0);
                    $content .= Html::hidden('assignment-params[0][assignment-date]',    $assignmentDate);
                    foreach ( $assignmentGroups as $groupID => $isMember )
                        $content .= Html::hidden('assignment-params[0][assignment-group][' . $groupID . ']', $isMember ? 1 : 0);
                    $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
                    $content .= Html::closeElement('form') . "\n";

                    $page->addHtml($content);

                    return false;

                } else {

                    # Delete is confirmed
                    $totalAffectedRows = 0;

                    # Delete the evaluations for this assignment and create log entries
                    $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_assignment_id' => $assignmentID));
                    foreach ( $evaluations as $evaluation ) {
                        $dbw->delete('scholasticgrading_evaluation', array('sge_user_id' => $evaluation->sge_user_id, 'sge_assignment_id' => $assignmentID));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteEvaluation', $this->getTitle(),
                                'for user ' .
                                    $this->getUserDisplayName($evaluation->sge_user_id) .
                                    ' [id=' . $evaluation->sge_user_id .
                                '] for assignment "' .
                                    $assignment->sga_title .
                                    '" (' . $assignment->sga_date .
                                    ') [id=' . $assignmentID .
                                ']; was [' .
                                    $evaluation->sge_date . ', ' .
                                    'score=' . (float)$evaluation->sge_score . ', ' .
                                    ($evaluation->sge_enabled ? 'enabled' : 'disabled') . ', ' .
                                    '"' . $evaluation->sge_comment . '"' .
                                ']', array());
                        }
                    }

                    # Delete the group memberships for the assignment and create log entries
                    $groupassignments = $dbw->select('scholasticgrading_groupassignment', '*', array('sgga_assignment_id' => $assignmentID));

                    foreach ( $groupassignments as $groupassignment ) {
                        $group = $dbw->selectRow('scholasticgrading_group', '*', array('sgg_id' => $groupassignment->sgga_group_id));
                        $dbw->delete('scholasticgrading_groupassignment', array('sgga_group_id' => $group->sgg_id, 'sgga_assignment_id' => $assignmentID));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteGroupAssignment', $this->getTitle(),
                                'assignment "' .
                                    $assignment->sga_title . '" (' .
                                    $assignment->sga_date . ') [id=' .
                                    $assignmentID .
                                ']; group "' .
                                    $group->sgg_title . '" [id=' .
                                    $group->sgg_id .
                                ']', array());
                        }
                    }

                    # Delete the existing assignment
                    $dbw->delete('scholasticgrading_assignment', array('sga_id' => $assignmentID));
                    $totalAffectedRows += $dbw->affectedRows();

                    # Create a new log entry
                    if ( $dbw->affectedRows() > 0 ) {
                        $log->addEntry('deleteAssignment', $this->getTitle(),
                            'id=' . $assignmentID .
                            '; was ["' .
                                $assignment->sga_title . '", ' .
                                $assignment->sga_date . ', ' .
                                'value=' . (float)$assignment->sga_value . ', ' .
                                ($assignment->sga_enabled ? 'enabled' : 'disabled') .
                            ']', array());
                    }

                    # Report success
                    if ( $totalaffectedRows === 0 ) {

                        if ( $verbose )
                            $page->addWikiText('Database unchanged.');
                        return false;

                    } else {

                        $page->addWikiText('\'\'\'Assignment "' . $assignment->sga_title . '" (' . $assignment->sga_date . ') deleted!\'\'\'');
                        return true;

                    }

                }

            }

        } else {

            # The assignment does not exist

            # Create a new assignment
            $totalAffectedRows = 0;
            $dbw->insert('scholasticgrading_assignment', array(
                'sga_title'   => $assignmentTitle,
                'sga_value'   => $assignmentValue,
                'sga_enabled' => $assignmentEnabled,
                'sga_date'    => $assignmentDate,
            ));
            $totalAffectedRows += $dbw->affectedRows();

            # Attempt to get the id for the newly created assignment
            $maxAssignmentID = $dbw->selectRow('scholasticgrading_assignment', array('maxid' => 'MAX(sga_id)'), '')->maxid;
            $assignment = $dbw->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $maxAssignmentID));
            if ( !$assignment || $assignment->sga_title != $assignmentTitle || $assignment->sga_value != $assignmentValue || $assignment->sga_enabled != $assignmentEnabled || $assignment->sga_date != $assignmentDate ) {

                # The query result does not match the new assignment
                $page->addWikiText('Unable to retrieve id of new assignment. Groups were not assigned. Log entry was not written.');
                return false;

            }

            # Create a new log entry
            if ( $totalAffectedRows > 0 ) {
                $log->addEntry('addAssignment', $this->getTitle(),
                    'id=' . $assignment->sga_id .
                    '; is ["' .
                        $assignmentTitle . '", ' .
                        $assignmentDate . ', ' .
                        'value=' . (float)$assignmentValue . ', ' .
                        ($assignmentEnabled ? 'enabled' : 'disabled') .
                    ']', array());
            }

            # Create the group memberships for the assignment and create log entries
            foreach ( $assignmentGroups as $groupID => $isMember ) {

                if ( $isMember ) {

                    $group = $dbw->selectRow('scholasticgrading_group', '*', array('sgg_id' => $groupID));
                    $dbw->insert('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignment->sga_id));
                    $totalAffectedRows += $dbw->affectedRows();

                    if ( $dbw->affectedRows() > 0 ) {
                        $log->addEntry('addGroupAssignment', $this->getTitle(),
                            'assignment "' .
                                $assignmentTitle . '" (' .
                                $assignmentDate . ') [id=' .
                                $assignment->sga_id .
                            ']; group "' .
                                $group->sgg_title . '" [id=' .
                                $groupID .
                            ']', array());
                    }

                }

            }

            # Report success
            if ( $totalAffectedRows === 0 ) {

                if ( $verbose )
                    $page->addWikiText('Database unchanged.');
                return false;

            } else {

                $page->addWikiText('\'\'\'Assignment "' . $assignmentTitle . '" (' . $assignmentDate . ') added!\'\'\'');
                return true;

            }

        }

        return false;

    } /* end writeAssignment */


    /**
     * Execute an evaluation creation/modification/deletion
     *
     * Creates, modifies, or deletes an evaluation by directly
     * modifying the database. If the (user,assignment) key provided
     * corresponds to an existing evaluation, the function will
     * modify or delete that evaluation depending on whether the
     * delete flag is set. Otherwise, the function will create a new
     * evaluation as long as both the user and assignment exist.
     * Parameters are initially validated and sanitized.
     *
     * @param int|bool    $evaluationUser the user id of an evaluation
     * @param int|bool    $evaluationAssignment the assignment id of an evaluation
     * @param float|bool  $evaluationScore the score of an evaluation
     * @param int|bool    $evaluationEnabled the enabled status of an evaluation
     * @param string|bool $evaluationDate the date of an evaluation
     * @param string|bool $evaluationComment the comment of an evaluation
     * @param bool        $delete whether to delete the evaluation or not
     * @param bool        $verbose whether to print warnings
     * @return bool       whether the database was changed or not
     */

    function writeEvaluation ( $evaluationUser = false, $evaluationAssignment = false, $evaluationScore = false, $evaluationEnabled = false, $evaluationDate = false, $evaluationComment = false, $delete = false, $verbose = true ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);
        $log = new LogPage('grades', false);

        # Validate/sanitize evaluation parameters
        $evaluationUser        = filter_var($evaluationUser, FILTER_VALIDATE_INT);
        $evaluationAssignment  = filter_var($evaluationAssignment, FILTER_VALIDATE_INT);
        $evaluationScore       = filter_var($evaluationScore, FILTER_VALIDATE_FLOAT);
        if ( !is_bool($evaluationEnabled) ) {
            $evaluationEnabled = filter_var($evaluationEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $evaluationDate        = filter_var($evaluationDate, FILTER_CALLBACK, array('options' => array($this, 'validateDate')));
        $evaluationComment     = trim($evaluationComment);
        if ( $evaluationUser === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid user id for evaluation (must be an integer).');
            return false;
        }
        if ( $evaluationAssignment === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid assignment id for evaluation (must be an integer).');
            return false;
        }
        if ( $evaluationScore === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid score for evaluation (must be a float).');
            return false;
        }
        if ( !is_bool($evaluationEnabled) ) {
            if ( $verbose )
                $page->addWikiText('Invalid enabled status for evaluation (must be a boolean).');
            return false;
        }
        if ( $evaluationDate === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid date for evaluation (must have form YYYY-MM-DD).');
            return false;
        }

        # Check whether user and assignment exist
        $user = $dbw->selectRow('user', '*', array('user_id' => $evaluationUser));
        $assignment = $dbw->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment));
        if ( !$user || !$assignment ) {

            # Either the user or assignment does not exist
            $page->addWikiText('Either user (id=' . $evaluationUser . ') or assignment (id=' . $evaluationAssignment . ') does not exist.');
            return false;

        } else {

            # The user and assignment both exist

            # Check whether evaluation exists
            $evaluation = $dbw->selectRow('scholasticgrading_evaluation', '*', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));
            if ( $evaluation ) {

                # The evaluation exists

                if ( !$delete ) {

                    # Edit the existing evaluation
                    $dbw->update('scholasticgrading_evaluation', array(
                        'sge_score'   => $evaluationScore,
                        'sge_enabled' => $evaluationEnabled,
                        'sge_date'    => $evaluationDate,
                        'sge_comment' => $evaluationComment,
                    ), array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));

                    # Create a new log entry
                    if ( $dbw->affectedRows() > 0 ) {
                        $log->addEntry('editEvaluation', $this->getTitle(),
                            'for user ' .
                                $this->getUserDisplayName($evaluationUser) .
                                ' [id=' . $evaluationUser .
                            '] for assignment "' .
                                $assignment->sga_title .
                                '" (' . $assignment->sga_date .
                                ') [id=' . $evaluationAssignment .
                            ']; from [' .
                                $evaluation->sge_date . ', ' .
                                'score=' . (float)$evaluation->sge_score . ', ' .
                                ($evaluation->sge_enabled ? 'enabled' : 'disabled') . ', ' .
                                '"' . $evaluation->sge_comment . '"' .
                            '] to [' .
                                $evaluationDate . ', ' .
                                'score=' . (float)$evaluationScore . ', ' .
                                ($evaluationEnabled ? 'enabled' : 'disabled') . ', ' .
                                '"' . $evaluationComment . '"' .
                            ']', array());
                    }

                    # Report success
                    if ( $dbw->affectedRows() === 0 ) {

                        if ( $verbose )
                            $page->addWikiText('Database unchanged.');
                        return false;

                    } else {

                        $page->addHtml(Html::rawElement('p', null, Html::rawElement('b', null, 'Evaluation for ' .
                            Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                                array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                            Linker::linkKnown($this->getTitle(), '"' . $assignment->sga_title . '" (' . $assignment->sga_date . ')', array(),
                                array('action' => 'editassignmentscores', 'id' => $assignment->sga_id)) . ' updated!')) . "\n");
                        return true;

                    }

                } else {

                    # Prepare to delete the existing evaluation

                    if ( !$request->getVal('confirm-delete') ) {

                        # Ask for confirmation of delete
                        $page->addHtml(Html::rawElement('p', null, 'Are you sure you want to delete the evaluation for ' .
                            Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                                array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                            Linker::linkKnown($this->getTitle(), '"' . $assignment->sga_title . '" (' . $assignment->sga_date . ')', array(),
                                array('action' => 'editassignmentscores', 'id' => $assignment->sga_id)) . '?') . "\n");

                        # Provide a delete button
                        $page->addHtml(Html::rawElement('form',
                            array(
                                'method' => 'post',
                                'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                            ),
                            Xml::submitButton('Delete evaluation', array('name' => 'delete-evaluation-0')) .
                            Html::hidden('confirm-delete', true) .
                            Html::hidden('evaluation-params[0][evaluation-user]',       $evaluationUser) .
                            Html::hidden('evaluation-params[0][evaluation-assignment]', $evaluationAssignment) .
                            Html::hidden('evaluation-params[0][evaluation-score]',      $evaluationScore) .
                            Html::hidden('evaluation-params[0][evaluation-enabled]',    $evaluationEnabled ? 1 : 0) .
                            Html::hidden('evaluation-params[0][evaluation-date]',       $evaluationDate) .
                            Html::hidden('evaluation-params[0][evaluation-comment]',    $evaluationComment) .
                            Html::hidden('wpEditToken', $this->getUser()->getEditToken())
                        ));

                        return false;

                    } else {

                        # Delete is confirmed so delete the existing evaluation
                        $dbw->delete('scholasticgrading_evaluation', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));

                        # Create a new log entry
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteEvaluation', $this->getTitle(),
                                'for user ' .
                                    $this->getUserDisplayName($evaluationUser) .
                                    ' [id=' . $evaluationUser .
                                '] for assignment "' .
                                    $assignment->sga_title .
                                    '" (' . $assignment->sga_date .
                                    ') [id=' . $evaluationAssignment .
                                ']; was [' .
                                    $evaluation->sge_date . ', ' .
                                    'score=' . (float)$evaluation->sge_score . ', ' .
                                    ($evaluation->sge_enabled ? 'enabled' : 'disabled') . ', ' .
                                    '"' . $evaluation->sge_comment . '"' .
                                ']', array());
                        }

                        # Report success
                        if ( $dbw->affectedRows() === 0 ) {

                            if ( $verbose )
                                $page->addWikiText('Database unchanged.');
                            return false;

                        } else {

                            $page->addHtml(Html::rawElement('p', null, Html::rawElement('b', null, 'Evaluation for ' .
                                Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                                    array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                                Linker::linkKnown($this->getTitle(), '"' . $assignment->sga_title . '" (' . $assignment->sga_date . ')', array(),
                                    array('action' => 'editassignmentscores', 'id' => $assignment->sga_id)) . ' deleted!')) . "\n");
                            return true;

                        }

                    }

                }

            } else {

                # The evaluation does not exist

                # Create a new evaluation
                $dbw->insert('scholasticgrading_evaluation', array(
                    'sge_user_id'       => $evaluationUser,
                    'sge_assignment_id' => $evaluationAssignment,
                    'sge_score'         => $evaluationScore,
                    'sge_enabled'       => $evaluationEnabled,
                    'sge_date'          => $evaluationDate,
                    'sge_comment'       => $evaluationComment,
                ));

                # Create a new log entry
                if ( $dbw->affectedRows() > 0 ) {
                    $log->addEntry('addEvaluation', $this->getTitle(),
                        'for user ' .
                            $this->getUserDisplayName($evaluationUser) .
                            ' [id=' . $evaluationUser .
                        '] for assignment "' .
                            $assignment->sga_title .
                            '" (' . $assignment->sga_date .
                            ') [id=' . $evaluationAssignment .
                        ']; is [' .
                            $evaluationDate . ', ' .
                            'score=' . (float)$evaluationScore . ', ' .
                            ($evaluationEnabled ? 'enabled' : 'disabled') . ', ' .
                            '"' . $evaluationComment . '"' .
                        ']', array());
                }

                # Report success
                if ( $dbw->affectedRows() === 0 ) {

                    if ( $verbose )
                        $page->addWikiText('Database unchanged.');
                    return false;

                } else {

                    $page->addHtml(Html::rawElement('p', null, Html::rawElement('b', null, 'Evaluation for ' .
                        Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                            array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                        Linker::linkKnown($this->getTitle(), '"' . $assignment->sga_title . '" (' . $assignment->sga_date . ')', array(),
                            array('action' => 'editassignmentscores', 'id' => $assignment->sga_id)) . ' added!')) . "\n");
                    return true;

                }

            }

        }

        return false;

    } /* end writeEvaluation */


    /**
     * Execute an adjustment creation/modification/deletion
     *
     * Creates, modifies, or deletes an adjustment by directly
     * modifying the database. If the (id) key provided corresponds
     * to an existing adjustment, the function will modify or delete
     * that adjustment depending on whether the delete flag is set.
     * Otherwise, the function will create a new adjustment as long
     * as the user exists. Parameters are initially validated and
     * sanitized.
     *
     * @param int|bool    $adjustmentID the id of an adjustment
     * @param int|bool    $adjustmentUser the user id of an adjustment
     * @param int|bool    $adjustmentTitle the title of an adjustment
     * @param float|bool  $adjustmentValue the value of an adjustment
     * @param float|bool  $adjustmentScore the score of an adjustment
     * @param int|bool    $adjustmentEnabled the enabled status of an adjustment
     * @param string|bool $adjustmentDate the date of an adjustment
     * @param string|bool $adjustmentComment the comment of an adjustment
     * @param bool        $delete whether to delete the adjustment or not
     * @param bool        $verbose whether to print warnings
     * @return bool       whether the database was changed or not
     */

    function writeAdjustment ( $adjustmentID = false, $adjustmentUser = false, $adjustmentTitle = false, $adjustmentValue = false, $adjustmentScore = false, $adjustmentEnabled = false, $adjustmentDate = false, $adjustmentComment = false, $delete = false, $verbose = true ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);
        $log = new LogPage('grades', false);

        # Validate/sanitize adjustment parameters
        $adjustmentID          = filter_var($adjustmentID, FILTER_VALIDATE_INT);
        $adjustmentUser        = filter_var($adjustmentUser, FILTER_VALIDATE_INT);
        $adjustmentTitle       = filter_var($adjustmentTitle, FILTER_CALLBACK, array('options' => array($this, 'validateTitle')));
        $adjustmentValue       = filter_var($adjustmentValue, FILTER_VALIDATE_FLOAT);
        $adjustmentScore       = filter_var($adjustmentScore, FILTER_VALIDATE_FLOAT);
        if ( !is_bool($adjustmentEnabled) ) {
            $adjustmentEnabled = filter_var($adjustmentEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $adjustmentDate        = filter_var($adjustmentDate, FILTER_CALLBACK, array('options' => array($this, 'validateDate')));
        $adjustmentComment     = trim($adjustmentComment);
        if ( $adjustmentUser === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid user id for adjustment (must be an integer).');
            return false;
        }
        if ( $adjustmentTitle === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid title for adjustment (may not be empty).');
            return false;
        }
        if ( $adjustmentValue === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid value for adjustment (must be a float).');
            return false;
        }
        if ( $adjustmentScore === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid score for adjustment (must be a float).');
            return false;
        }
        if ( !is_bool($adjustmentEnabled) ) {
            if ( $verbose )
                $page->addWikiText('Invalid enabled status for adjustment (must be a boolean).');
            return false;
        }
        if ( $adjustmentDate === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid date for adjustment (must have form YYYY-MM-DD).');
            return false;
        }

        # Check whether user exist
        $user = $dbw->selectRow('user', '*', array('user_id' => $adjustmentUser));
        if ( !$user ) {

            # User does not exist
            $page->addWikiText('User (id=' . $adjustmentUser . ') does not exist.');
            return false;

        } else {

            # The user exists

            # Check whether adjustment exists
            $adjustment = $dbw->selectRow('scholasticgrading_adjustment', '*', array('sgadj_id' => $adjustmentID));
            if ( $adjustment ) {

                # The adjustment exists

                if ( !$delete ) {

                    # Edit the existing adjustment
                    $dbw->update('scholasticgrading_adjustment', array(
                        'sgadj_user_id' => $adjustmentUser,
                        'sgadj_title'   => $adjustmentTitle,
                        'sgadj_value'   => $adjustmentValue,
                        'sgadj_score'   => $adjustmentScore,
                        'sgadj_enabled' => $adjustmentEnabled,
                        'sgadj_date'    => $adjustmentDate,
                        'sgadj_comment' => $adjustmentComment,
                    ), array('sgadj_id' => $adjustmentID));

                    # Create a new log entry
                    if ( $dbw->affectedRows() > 0 ) {
                        $log->addEntry('editAdjustment', $this->getTitle(),
                            'id=' . $adjustmentID .
                            '; for user ' .
                                $this->getUserDisplayName($adjustmentUser) .
                                ' [id=' . $user->user_id .
                            ']; from ["' .
                                $adjustment->sgadj_title . '", ' .
                                $adjustment->sgadj_date . ', ' .
                                'value=' . (float)$adjustment->sgadj_value . ', ' .
                                'score=' . (float)$adjustment->sgadj_score . ', ' .
                                ($adjustment->sgadj_enabled ? 'enabled' : 'disabled') . ', ' .
                                '"' . $adjustment->sgadj_comment . '"' .
                            '] to ["' .
                                $adjustmentTitle . '", ' .
                                $adjustmentDate . ', ' .
                                'value=' . (float)$adjustmentValue . ', ' .
                                'score=' . (float)$adjustmentScore . ', ' .
                                ($adjustmentEnabled ? 'enabled' : 'disabled') . ', ' .
                                '"' . $adjustmentComment . '"' .
                            ']', array());
                    }

                    # Report success
                    if ( $dbw->affectedRows() === 0 ) {

                        if ( $verbose )
                            $page->addWikiText('Database unchanged.');
                        return false;

                    } else {

                        $page->addHtml(Html::rawElement('p', null, Html::rawElement('b', null, 'Point adjustment for ' .
                            Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                                array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                            Linker::linkKnown($this->getTitle(), '"' . $adjustmentTitle . '" (' . $adjustmentDate . ')', array(),
                                array('action' => 'editadjustment', 'id' => $adjustmentID)) . ' updated!')) . "\n");
                        return true;

                    }

                } else {

                    # Prepare to delete the existing adjustment

                    if ( !$request->getVal('confirm-delete') ) {

                        # Ask for confirmation of delete
                        $page->addHtml(Html::rawElement('p', null, 'Are you sure you want to delete the point adjustment for ' .
                            Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                                array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                            Linker::linkKnown($this->getTitle(), '"' . $adjustmentTitle . '" (' . $adjustmentDate . ')', array(),
                                array('action' => 'editadjustment', 'id' => $adjustmentID)) . '?') . "\n");

                        # Provide a delete button
                        $page->addHtml(Html::rawElement('form',
                            array(
                                'method' => 'post',
                                'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                            ),
                            Xml::submitButton('Delete adjustment', array('name' => 'delete-adjustment-0')) .
                            Html::hidden('confirm-delete', true) .
                            Html::hidden('adjustment-params[0][adjustment-id]',      $adjustmentID) .
                            Html::hidden('adjustment-params[0][adjustment-user]',    $adjustmentUser) .
                            Html::hidden('adjustment-params[0][adjustment-title]',   $adjustmentTitle) .
                            Html::hidden('adjustment-params[0][adjustment-value]',   $adjustmentValue) .
                            Html::hidden('adjustment-params[0][adjustment-score]',   $adjustmentScore) .
                            Html::hidden('adjustment-params[0][adjustment-enabled]', $adjustmentEnabled ? 1 : 0) .
                            Html::hidden('adjustment-params[0][adjustment-date]',    $adjustmentDate) .
                            Html::hidden('adjustment-params[0][adjustment-comment]', $adjustmentComment) .
                            Html::hidden('wpEditToken', $this->getUser()->getEditToken())
                        ));

                        return false;

                    } else {

                        # Delete is confirmed so delete the existing adjustment
                        $dbw->delete('scholasticgrading_adjustment', array('sgadj_id' => $adjustmentID));

                        # Create a new log entry
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteAdjustment', $this->getTitle(),
                                'id=' . $adjustmentID .
                                '; for user ' .
                                    $this->getUserDisplayName($adjustmentUser) .
                                    ' [id=' . $user->user_id .
                                ']; was ["' .
                                    $adjustment->sgadj_title . '", ' .
                                    $adjustment->sgadj_date . ', ' .
                                    'value=' . (float)$adjustment->sgadj_value . ', ' .
                                    'score=' . (float)$adjustment->sgadj_score . ', ' .
                                    ($adjustment->sgadj_enabled ? 'enabled' : 'disabled') . ', ' .
                                    '"' . $adjustment->sgadj_comment . '"' .
                                ']', array());
                        }

                        # Report success
                        if ( $dbw->affectedRows() === 0 ) {

                            if ( $verbose )
                                $page->addWikiText('Database unchanged.');
                            return false;

                        } else {

                            $page->addHtml(Html::rawElement('p', null, Html::rawElement('b', null, 'Point adjustment for ' .
                                Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                                    array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for "' . $adjustmentTitle . '" (' . $adjustmentDate . ') deleted!')) . "\n");
                            return true;

                        }

                    }

                }

            } else {

                # The adjustment does not exist

                # Create a new adjustment
                $totalAffectedRows = 0;
                $dbw->insert('scholasticgrading_adjustment', array(
                    'sgadj_user_id' => $adjustmentUser,
                    'sgadj_title'   => $adjustmentTitle,
                    'sgadj_value'   => $adjustmentValue,
                    'sgadj_score'   => $adjustmentScore,
                    'sgadj_enabled' => $adjustmentEnabled,
                    'sgadj_date'    => $adjustmentDate,
                    'sgadj_comment' => $adjustmentComment,
                ));
                $totalAffectedRows += $dbw->affectedRows();

                # Attempt to get the id for the newly created adjustment
                $maxAdjustmentID = $dbw->selectRow('scholasticgrading_adjustment', array('maxid' => 'MAX(sgadj_id)'), '')->maxid;
                $adjustment = $dbw->selectRow('scholasticgrading_adjustment', '*', array('sgadj_id' => $maxAdjustmentID));
                if ( !$adjustment || $adjustment->sgadj_user_id != $adjustmentUser || $adjustment->sgadj_title != $adjustmentTitle || $adjustment->sgadj_value != $adjustmentValue || $adjustment->sgadj_score != $adjustmentScore || $adjustment->sgadj_enabled != $adjustmentEnabled || $adjustment->sgadj_date != $adjustmentDate || $adjustment->sgadj_comment != $adjustmentComment ) {

                    # The query result does not match the new adjustment
                    $page->addWikiText('Unable to retrieve id of new adjustment. Log entry was not written.');
                    return false;

                }

                # Create a new log entry
                if ( $totalAffectedRows > 0 ) {
                    $log->addEntry('addAdjustment', $this->getTitle(),
                        'id=' . $adjustment->sgadj_id .
                        '; for user ' .
                            $this->getUserDisplayName($adjustmentUser) .
                            ' [id=' . $user->user_id .
                        ']; is ["' .
                            $adjustmentTitle . '", ' .
                            $adjustmentDate . ', ' .
                            'value=' . (float)$adjustmentValue . ', ' .
                            'score=' . (float)$adjustmentScore . ', ' .
                            ($adjustmentEnabled ? 'enabled' : 'disabled') . ', ' .
                            '"' . $adjustmentComment . '"' .
                        ']', array());
                }

                # Report success
                if ( $totalAffectedRows === 0 ) {

                    if ( $verbose )
                        $page->addWikiText('Database unchanged.');
                    return false;

                } else {

                    $page->addHtml(Html::rawElement('p', null, Html::rawElement('b', null, 'Point adjustment for ' .
                        Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                            array('action' => 'edituserscores', 'user' => $user->user_id)) . ' for ' .
                        Linker::linkKnown($this->getTitle(), '"' . $adjustmentTitle . '" (' . $adjustmentDate . ')', array(),
                            array('action' => 'editadjustment', 'id' => $adjustment->sgadj_id)) . ' added!')) . "\n");
                    return true;

                }

            }

        }

        return false;

    } /* end writeAdjustment */


    /**
     * Execute group creation/modification/deletion
     *
     * Creates, modifies, or deletes a group by directly modifying
     * the database. If the (id) key provided corresponds to an
     * existing group, the function will modify or delete that
     * group depending on whether the delete flag is set.
     * Otherwise, the function will create a new group.
     * Also handles user memberships. Parameters are initially
     * validated and sanitized.
     *
     * @param int|bool    $groupID the id of a group
     * @param int|bool    $groupTitle the title of a group
     * @param int|bool    $groupEnabled the enabled status of a group
     * @param array|bool  $groupUsers the user memberships of a group
     * @param bool        $delete whether to delete the group or not
     * @param bool        $verbose whether to print warnings
     * @return bool       whether the database was changed or not
     */

    function writeGroup ( $groupID = false, $groupTitle = false, $groupEnabled = false, $groupUsers = false, $delete = false, $verbose = true ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);
        $log = new LogPage('grades', false);

        # Validate/sanitize group parameters
        $groupID          = filter_var($groupID, FILTER_VALIDATE_INT);
        $groupTitle       = filter_var($groupTitle, FILTER_CALLBACK, array('options' => array($this, 'validateTitle')));
        if ( !is_bool($groupEnabled) ) {
            $groupEnabled = filter_var($groupEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ( $groupTitle === false ) {
            if ( $verbose )
                $page->addWikiText('Invalid title for group (may not be empty).');
            return false;
        }
        if ( !is_bool($groupEnabled) ) {
            if ( $verbose )
                $page->addWikiText('Invalid enabled status for group (must be a boolean).');
            return false;
        }
        if ( !is_array($groupUsers) ) {
            if ( $verbose )
                $page->addWikiText('Invalid user membership array for group (must be an array).');
            return false;
        }

        # Check whether group exists
        $group = $dbw->selectRow('scholasticgrading_group', '*', array('sgg_id' => $groupID));
        if ( $group ) {

            # The group exists

            if ( !$delete ) {

                # Edit the existing group
                $totalAffectedRows = 0;
                $dbw->update('scholasticgrading_group', array(
                    'sgg_title'   => $groupTitle,
                    'sgg_enabled' => $groupEnabled,
                ), array('sgg_id' => $groupID));
                $totalAffectedRows += $dbw->affectedRows();

                # Create a new log entry
                if ( $dbw->affectedRows() > 0 ) {
                    $log->addEntry('editGroup', $this->getTitle(),
                        'id=' . $groupID .
                        '; from ["' .
                            $group->sgg_title . '", ' .
                            ($group->sgg_enabled ? 'enabled' : 'disabled') .
                        '] to ["' .
                            $groupTitle . '", ' .
                            ($groupEnabled ? 'enabled' : 'disabled') .
                        ']', array());
                }

                # Edit the user memberships for the group and create log entries
                foreach ( $groupUsers as $userID => $isMember ) {
                    $membership = $dbw->selectRow('scholasticgrading_groupuser', '*', array('sggu_group_id' => $groupID, 'sggu_user_id' => $userID));
                    $user = $dbw->selectRow('user', '*', array('user_id' => $userID));
                    if ( $membership && !$isMember ) {
                        $dbw->delete('scholasticgrading_groupuser', array('sggu_group_id' => $groupID, 'sggu_user_id' => $userID));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteGroupUser', $this->getTitle(),
                                'user "' .
                                    $this->getUserDisplayName($userID) . ' [id=' .
                                    $userID .
                                ']; group "' .
                                    $groupTitle . '" [id=' .
                                    $groupID .
                                ']', array());
                        }
                    } elseif ( !$membership && $isMember ) {
                        $dbw->insert('scholasticgrading_groupuser', array('sggu_group_id' => $groupID, 'sggu_user_id' => $userID));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('addGroupUser', $this->getTitle(),
                                'user "' .
                                    $this->getUserDisplayName($userID) . ' [id=' .
                                    $userID .
                                ']; group "' .
                                    $groupTitle . '" [id=' .
                                    $groupID .
                                ']', array());
                        }
                    }
                }

                # Report success
                if ( $totalAffectedRows === 0 ) {

                    if ( $verbose )
                        $page->addWikiText('Database unchanged.');
                    return false;

                } else {

                    $page->addWikiText('\'\'\'Group "' . $groupTitle . '" updated!\'\'\'');
                    return true;

                }

            } else {

                # Prepare to delete the existing group

                if ( !$request->getVal('confirm-delete') ) {

                    # Ask for confirmation of delete
                    $page->addWikiText('Are you sure you want to delete group "' . $group->sgg_title . '"?');

                    # Provide a delete button
                    $content = '';
                    $content .= Html::openElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        )
                    );
                    $content .= Xml::submitButton('Delete group', array('name' => 'delete-group-0'));
                    $content .= Html::hidden('confirm-delete', true);
                    $content .= Html::hidden('group-params[0][group-id]',      $groupID);
                    $content .= Html::hidden('group-params[0][group-title]',   $groupTitle);
                    $content .= Html::hidden('group-params[0][group-enabled]', $groupEnabled ? 1 : 0);
                    foreach ( $groupUsers as $userID => $isMember )
                        $content .= Html::hidden('group-params[0][group-user][' . $userID . ']', $isMember ? 1 : 0);
                    $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
                    $content .= Html::closeElement('form') . "\n";

                    $page->addHtml($content);

                    return false;

                } else {

                    # Delete is confirmed
                    $totalAffectedRows = 0;

                    # Delete the assignment memberships for the group and create log entries
                    $groupassignments = $dbw->select('scholasticgrading_groupassignment', '*', array('sgga_group_id' => $groupID));

                    foreach ( $groupassignments as $groupassignment ) {
                        $assignment = $dbw->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $groupassignment->sgga_assignment_id));
                        $dbw->delete('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $groupassignment->sgga_assignment_id));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteGroupAssignment', $this->getTitle(),
                                'assignment "' .
                                    $assignment->sga_title . '" (' .
                                    $assignment->sga_date . ') [id=' .
                                    $assignment->sga_id .
                                ']; group "' .
                                    $group->sgg_title . '" [id=' .
                                    $group->sgg_id .
                                ']', array());
                        }
                    }

                    # Delete the user memberships for the group and create log entries
                    $groupusers = $dbw->select('scholasticgrading_groupuser', '*', array('sggu_group_id' => $groupID));

                    foreach ( $groupusers as $groupuser ) {
                        $user = $dbw->selectRow('user', '*', array('user_id' => $groupuser->sggu_user_id));
                        $dbw->delete('scholasticgrading_groupuser', array('sggu_group_id' => $groupID, 'sggu_user_id' => $groupuser->sggu_user_id));
                        $totalAffectedRows += $dbw->affectedRows();
                        if ( $dbw->affectedRows() > 0 ) {
                            $log->addEntry('deleteGroupUser', $this->getTitle(),
                                'user "' .
                                    $this->getUserDisplayName($user->user_id) . ' [id=' .
                                    $user->user_id .
                                ']; group "' .
                                    $group->sgg_title . '" [id=' .
                                    $group->sgg_id .
                                ']', array());
                        }
                    }

                    # Delete the existing group
                    $dbw->delete('scholasticgrading_group', array('sgg_id' => $groupID));
                    $totalAffectedRows += $dbw->affectedRows();

                    # Create a new log entry
                    if ( $dbw->affectedRows() > 0 ) {
                        $log->addEntry('deleteGroup', $this->getTitle(),
                            'id=' . $groupID .
                            '; was ["' .
                                $group->sgg_title . '", ' .
                                ($group->sgg_enabled ? 'enabled' : 'disabled') .
                            ']', array());
                    }

                    # Report success and create a new log entry
                    if ( $totalAffectedRows === 0 ) {

                        if ( $verbose )
                            $page->addWikiText('Database unchanged.');
                        return false;

                    } else {

                        $page->addWikiText('\'\'\'Group "' . $group->sgg_title . '" deleted!\'\'\'');
                        return true;

                    }

                }

            }

        } else {

            # The group does not exist

            # Create a new group
            $totalAffectedRows = 0;
            $dbw->insert('scholasticgrading_group', array(
                'sgg_title'   => $groupTitle,
                'sgg_enabled' => $groupEnabled,
            ));
            $totalAffectedRows += $dbw->affectedRows();

            # Attempt to get the id for the newly created group
            $maxGroupID = $dbw->selectRow('scholasticgrading_group', array('maxid' => 'MAX(sgg_id)'), '')->maxid;
            $group = $dbw->selectRow('scholasticgrading_group', '*', array('sgg_id' => $maxGroupID));
            if ( !$group || $group->sgg_title != $groupTitle || $group->sgg_enabled != $groupEnabled ) {

                # The query result does not match the new group
                $page->addWikiText('Unable to retrieve id of new group. Log entry was not written.');
                return false;

            }

            # User membership is not processed for new groups

            # Create a new log entry
            if ( $totalAffectedRows > 0 ) {
                $log->addEntry('addGroup', $this->getTitle(),
                    'id=' . $group->sgg_id .
                    '; is ["' .
                        $groupTitle . '", ' .
                        ($groupEnabled ? 'enabled' : 'disabled') .
                    ']', array());
            }

            # Report success
            if ( $totalAffectedRows === 0 ) {

                if ( $verbose )
                    $page->addWikiText('Database unchanged.');
                return false;

            } else {

                $page->addWikiText('\'\'\'Group "' . $groupTitle . '" added!\'\'\'');
                return true;

            }

        }

        return false;

    } /* end writeGroup */


    /**
     * Display the assignment creation/modification form
     *
     * Generates a form for creating a new assignment or editing an existing one.
     * If no assignment id is provided, the form will be prepared for assignment creation.
     * If a valid assignment id is provided, the form will be prepared for assignment modification.
     * If an invalid assignment id is provided, report an error.
     *
     * @param int|bool $id an optional assignment id
     */

    function showAssignmentForm ( $id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Set default parameters for creating a new assignment
        $fieldsetTitle = 'Create a new assignment';
        $buttons = Xml::submitButton('Create assignment', array('name' => 'create-assignment'));
        $assignmentIdDefault = false;
        $assignmentTitleDefault = '';
        $assignmentValueDefault = '';
        $assignmentEnabledDefault = true;
        $assignmentDateDefault = date('Y-m-d');

        if ( $id ) {

            # Check whether assignment exists
            $assignment = $dbr->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $id));
            if ( !$assignment ) {

                # The assignment does not exist
                $page->addWikiText('Assignment (id=' . $id . ') does not exist.');
                return;

            } else {

                # The assignment exists

                # Use its values as default parameters
                $fieldsetTitle = 'Edit an existing assignment';
                $buttons = Xml::submitButton('Apply changes', array('name' => 'modify-assignment')) .
                    Xml::submitButton('Delete assignment', array('name' => 'delete-assignment-0'));
                $assignmentIdDefault = $id;
                $assignmentTitleDefault = $assignment->sga_title;
                $assignmentValueDefault = (float)$assignment->sga_value;
                $assignmentEnabledDefault = $assignment->sga_enabled;
                $assignmentDateDefault = $assignment->sga_date;

            }

        }

        # Query for all groups
        $groups = $dbr->select('scholasticgrading_group', '*', '', __METHOD__,
            array('ORDER BY' => array('sgg_title')));

        # Create a checkbox for each group
        $groupchecks = '';
        foreach ( $groups as $group ) {

            $groupassignment = $dbr->selectRow('scholasticgrading_groupassignment', '*',
                array('sgga_group_id' => $group->sgg_id, 'sgga_assignment_id' => $assignmentIdDefault));
            if ( $groupassignment ) {

                # The assignment is a member of the group
                $groupchecks .= Html::rawElement('tr', null,
                    Html::rawElement('td', null, Xml::label($group->sgg_title . ':', 'assignment-group-' . $group->sgg_id)) .
                    Html::rawElement('td', null,
                        Html::hidden('assignment-params[0][assignment-group][' . $group->sgg_id . ']', 0) .
                        Xml::check('assignment-params[0][assignment-group][' . $group->sgg_id . ']', true, array('id' => 'assignment-group-' . $group->sgg_id))
                    )
                );

            } else {

                # The assignment is not a member of the group
                $groupchecks .= Html::rawElement('tr', null,
                    Html::rawElement('td', null, Xml::label($group->sgg_title . ':', 'assignment-group-' . $group->sgg_id)) .
                    Html::rawElement('td', null,
                        Html::hidden('assignment-params[0][assignment-group][' . $group->sgg_id . ']', 0) .
                        Xml::check('assignment-params[0][assignment-group][' . $group->sgg_id . ']', false, array('id' => 'assignment-group-' . $group->sgg_id))
                    )
                );

            }

        }

        # Build the assignment form
        $content = Xml::fieldset($fieldsetTitle,
            Html::rawElement('form',
                array(
                    'method' => 'post',
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                 ),
                 Html::rawElement('table', null,
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Title:', 'assignment-title')) .
                        Html::rawElement('td', null, Xml::input('assignment-params[0][assignment-title]', 20, $assignmentTitleDefault, array('id' => 'assignment-title')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Point value:', 'assignment-value')) .
                        Html::rawElement('td', null, Xml::input('assignment-params[0][assignment-value]', 20, $assignmentValueDefault, array('id' => 'assignment-value', 'autocomplete' => 'off')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'assignment-enabled')) .
                        Html::rawElement('td', null, Xml::check('assignment-params[0][assignment-enabled]', $assignmentEnabledDefault, array('id' => 'assignment-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'assignment-date')) .
                        Html::rawElement('td', null, Xml::input('assignment-params[0][assignment-date]', 20, $assignmentDateDefault, array('id' => 'assignment-date', 'autocomplete' => 'off', 'class' => 'sg-date-input')))
                    ) .
                    $groupchecks
                ) .
                $buttons .
                Html::hidden('assignment-params[0][assignment-id]', $assignmentIdDefault) .
                Html::hidden('wpEditToken', $this->getUser()->getEditToken())
            )
        );

        $page->addHTML($content);

    } /* end showAssignmentForm */


    /**
     * Display the evaluation creation/modification form
     *
     * Generates a form for creating a new evaluation or editing an existing one.
     * If the user id and assignment id are keys for an evaluation that does
     * not exist, the form will be prepared for evaluation creation.
     * If the user id and assignment id are keys for an existing evaluation,
     * the form will be prepared for evaluation modification.
     *
     * @param int|bool $user_id the user id of an evaluation
     * @param int|bool $assignment_id the assignment id of an evaluation
     */

    function showEvaluationForm ( $user_id = false, $assignment_id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Check whether user and assignment exist
        $user = $dbr->selectRow('user', '*', array('user_id' => $user_id));
        $assignment = $dbr->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $assignment_id));
        if ( !$user || !$assignment ) {

            # Either the user or assignment does not exist
            $page->addWikiText('Either user (id=' . $user_id . ') or assignment (id=' . $assignment_id . ') does not exist.');
            return;

        } else {

            # The user and assignment both exist

            # Check whether evaluation exists
            $evaluation = $dbr->selectRow('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment_id));
            if ( !$evaluation ) {

                # The evaluation does not exist
                # Set default parameters for creating a new evaluation
                $fieldsetTitle = 'Create a new evaluation';
                $buttons = Xml::submitButton('Create evaluation', array('name' => 'create-evaluation'));
                $evaluationUserIdDefault = $user_id;
                $evaluationAssignmentIdDefault = $assignment_id;
                $evaluationScoreDefault = '';
                $evaluationEnabledDefault = true;
                $evaluationDateDefault = $assignment->sga_date;
                $evaluationCommentDefault = '';

            } else {

                # The evaluation exists

                # Use its values as default parameters
                $fieldsetTitle = 'Edit an existing evaluation';
                $buttons = Xml::submitButton('Apply changes', array('name' => 'modify-evaluation')) .
                    Xml::submitButton('Delete evaluation', array('name' => 'delete-evaluation-0'));
                $evaluationUserIdDefault = $user_id;
                $evaluationAssignmentIdDefault = $assignment_id;
                $evaluationScoreDefault = (float)$evaluation->sge_score;
                $evaluationEnabledDefault = $evaluation->sge_enabled;
                $evaluationDateDefault = $evaluation->sge_date;
                $evaluationCommentDefault = $evaluation->sge_comment;

            }

        }

        # Build the evaluation form
        $content = Xml::fieldset($fieldsetTitle,
            Html::rawElement('form',
                array(
                    'method' => 'post',
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                ),
                Html::rawElement('table', null,
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('User:', 'evaluation-user')) .
                        Html::rawElement('td', null, $this->getUserDisplayName($user->user_id))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Assignment:', 'evaluation-assignment')) .
                        Html::rawElement('td', null, $assignment->sga_title . ' (' . $assignment->sga_date . ')')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Score:', 'evaluation-score')) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[0][evaluation-score]', 20, $evaluationScoreDefault, array('id' => 'evaluation-score', 'autocomplete' => 'off')) . ' out of ' . (float)$assignment->sga_value . ' point(s)')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled')) .
                        Html::rawElement('td', null, Xml::check('evaluation-params[0][evaluation-enabled]', $evaluationEnabledDefault, array('id' => 'evaluation-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date')) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[0][evaluation-date]', 20, $evaluationDateDefault, array('id' => 'evaluation-date', 'autocomplete' => 'off', 'class' => 'sg-date-input')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Comment:', 'evaluation-comment')) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[0][evaluation-comment]', 20, $evaluationCommentDefault, array('id' => 'evaluation-comment')))
                    )
                ) .
                $buttons .
                Html::hidden('evaluation-params[0][evaluation-user]', $evaluationUserIdDefault) .
                Html::hidden('evaluation-params[0][evaluation-assignment]', $evaluationAssignmentIdDefault) .
                Html::hidden('wpEditToken', $this->getUser()->getEditToken())
            )
        );

        $page->addHTML($content);

    } /* end showEvaluationForm */


    /**
     * Display the adjustment creation/modification form
     *
     * Generates a form for creating a new adjustment or editing an existing one.
     * If the adjustment id is a valid key for an existing adjustment, the user id
     * is ignored and the form is prepared for adjustment modification. Otherwise,
     * the form is prepared for adjustment creation for the specified user.
     *
     * @param int|bool $id the optional id of an adjustment
     * @param int|bool $user_id the optional user id of an adjustment
     */

    function showAdjustmentForm ( $id = false, $user_id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Check whether adjustment exists
        $adjustment = $dbr->selectRow('scholasticgrading_adjustment', '*', array('sgadj_id' => $id));
        if ( !$adjustment ) {

            # The adjustment does not exist

            # Check whether user exists
            $user = $dbr->selectRow('user', '*', array('user_id' => $user_id));
            if ( !$user ) {

                # Neither the user nor the adjustment exist
                $page->addWikiText('Adjustment (id=' . $id . ') and user (id=' . $user_id . ') do not exist.');
                return;

            } else {

                # The user exists

                # Set default parameters for creating a new adjustment
                $fieldsetTitle = 'Create a new adjustment';
                $buttons = Xml::submitButton('Create adjustment', array('name' => 'create-adjustment'));
                $adjustmentIdDefault = false;
                $adjustmentUserIdDefault = $user_id;
                $adjustmentTitleDefault = '';
                $adjustmentScoreDefault = '';
                $adjustmentValueDefault = 0;
                $adjustmentEnabledDefault = true;
                $adjustmentDateDefault = date('Y-m-d');
                $adjustmentCommentDefault = '';

            }

        } else {

            # The adjustment exists

            # Use its values as default parameters
            $fieldsetTitle = 'Edit an existing adjustment';
            $buttons = Xml::submitButton('Apply changes', array('name' => 'modify-adjustment')) .
                Xml::submitButton('Delete adjustment', array('name' => 'delete-adjustment-0'));
            $adjustmentIdDefault = $id;
            $adjustmentUserIdDefault = $adjustment->sgadj_user_id;
            $adjustmentTitleDefault = $adjustment->sgadj_title;
            $adjustmentScoreDefault = (float)$adjustment->sgadj_score;
            $adjustmentValueDefault = (float)$adjustment->sgadj_value;
            $adjustmentEnabledDefault = $adjustment->sgadj_enabled;
            $adjustmentDateDefault = $adjustment->sgadj_date;
            $adjustmentCommentDefault = $adjustment->sgadj_comment;

        }

        # Build the evaluation form
        $content = Xml::fieldset($fieldsetTitle,
            Html::rawElement('form',
                array(
                    'method' => 'post',
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                ),
                Html::rawElement('table', null,
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('User:', 'adjustment-user')) .
                        Html::rawElement('td', null, $this->getUserDisplayName($adjustmentUserIdDefault))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Title:', 'adjustment-title')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-title]', 20, $adjustmentTitleDefault, array('id' => 'adjustment-title')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Score:', 'adjustment-score')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-score]', 20, $adjustmentScoreDefault, array('id' => 'adjustment-score', 'autocomplete' => 'off')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Value:', 'adjustment-value')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-value]', 20, $adjustmentValueDefault, array('id' => 'adjustment-value', 'autocomplete' => 'off')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'adjustment-enabled')) .
                        Html::rawElement('td', null, Xml::check('adjustment-params[0][adjustment-enabled]', $adjustmentEnabledDefault, array('id' => 'adjustment-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'adjustment-date')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-date]', 20, $adjustmentDateDefault, array('id' => 'adjustment-date', 'autocomplete' => 'off', 'class' => 'sg-date-input')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Comment:', 'adjustment-comment')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-comment]', 20, $adjustmentCommentDefault, array('id' => 'adjustment-comment')))
                    )
                ) .
                $buttons .
                Html::hidden('adjustment-params[0][adjustment-id]', $adjustmentIdDefault) .
                Html::hidden('adjustment-params[0][adjustment-user]', $adjustmentUserIdDefault) .
                Html::hidden('wpEditToken', $this->getUser()->getEditToken())
            )
        );

        $page->addHTML($content);

    } /* end showAdjustmentForm */


    /**
     * Display the group creation/modification form
     *
     * Generates a form for creating a new group or editing an existing one.
     * If no group id is provided, the form will be prepared for group creation.
     * If a valid group id is provided, the form will be prepared for group modification.
     * If an invalid group id is provided, report an error.
     *
     * @param int|bool $id an optional group id
     */

    function showGroupForm ( $id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Set default parameters for creating a new group
        $fieldsetTitle = 'Create a new group';
        $buttons = Xml::submitButton('Create group', array('name' => 'create-group'));
        $groupIdDefault = false;
        $groupTitleDefault = '';
        $groupEnabledDefault = true;

        if ( $id ) {

            # Check whether group exists
            $group = $dbr->selectRow('scholasticgrading_group', '*', array('sgg_id' => $id));
            if ( !$group ) {

                # The group does not exist
                $page->addWikiText('Group (id=' . $id . ') does not exist.');
                return;

            } else {

                # The group exists

                # Use its values as default parameters
                $fieldsetTitle = 'Edit an existing group';
                $buttons = Xml::submitButton('Apply changes', array('name' => 'modify-group')) .
                    Xml::submitButton('Delete group', array('name' => 'delete-group-0'));
                $groupIdDefault = $id;
                $groupTitleDefault = $group->sgg_title;
                $groupEnabledDefault = $group->sgg_enabled;

            }

        }

        # Query for all users
        $users = $dbr->select('user', '*');

        # Create a hidden for each group user
        $groupuserfields = '';
        foreach ( $users as $user ) {

            $groupuser = $dbr->selectRow('scholasticgrading_groupuser', '*',
                array('sggu_group_id' => $groupIdDefault, 'sggu_user_id' => $user->user_id));
            if ( $groupuser ) {

                # The user is a member of the group
                $groupuserfields .= Html::hidden('group-params[0][group-user][' . $user->user_id . ']', 1);

            } else {

                # The user is not a member of the group
                $groupuserfields .= Html::hidden('group-params[0][group-user][' . $user->user_id . ']', 0);

            }

        }

        # Build the group form
        $content = Xml::fieldset($fieldsetTitle,
            Html::rawElement('form',
                array(
                    'method' => 'post',
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                 ),
                 Html::rawElement('table', null,
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Title:', 'group-title')) .
                        Html::rawElement('td', null, Xml::input('group-params[0][group-title]', 20, $groupTitleDefault, array('id' => 'group-title')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'group-enabled')) .
                        Html::rawElement('td', null, Xml::check('group-params[0][group-enabled]', $groupEnabledDefault, array('id' => 'group-enabled')))
                    )
                ) .
                $groupuserfields .
                $buttons .
                Html::hidden('group-params[0][group-id]', $groupIdDefault) .
                Html::hidden('wpEditToken', $this->getUser()->getEditToken())
            )
        );

        $page->addHTML($content);

    } /* end showGroupForm */


    /**
     * Display all evaluation forms and adjustment forms for a user
     *
     * Generates a page for creating and editing evaluations for
     * all enabled assignments and all adjustments for a single user.
     *
     * @param int|bool $user_id the user id
     */

    function showUserScoreForms ( $user_id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Check whether user exists
        $user = $dbr->selectRow('user', '*', array('user_id' => $user_id));
        if ( !$user ) {

            # The user does not exist
            $page->addWikiText('User (id=' . $user_id . ') does not exist.');
            return;

        }

        # The user exists

        # Initialize the cumulative score, cumulative value,
        # and the course total value for this student
        $cumulativeScore = 0;
        $cumulativeValue = 0;
        $totalValue = 0;

        # Query for the enabled groups the user belongs to
        $groupusers = $dbr->select('scholasticgrading_groupuser', '*', array('sggu_user_id' => $user_id));
        $groupIDs = array();
        foreach ( $groupusers as $groupuser )
            array_push($groupIDs, $groupuser->sggu_group_id);
        if ( !count($groupIDs) )
            $groupIDs = null;
        $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_id' => $groupIDs, 'sgg_enabled' => true));
        $groupIDs = array();
        foreach ( $groups as $group )
            array_push($groupIDs, $group->sgg_id);
        if ( !count($groupIDs) )
            $groupIDs = null;

        # Query for the enabled assignments attached to these groups
        $groupassignments = $dbr->select('scholasticgrading_groupassignment', '*', array('sgga_group_id' => $groupIDs));
        $assignmentIDs = array();
        foreach ( $groupassignments as $groupassignment )
            array_push($assignmentIDs, $groupassignment->sgga_assignment_id);
        $assignmentIDs = array_unique($assignmentIDs);
        if ( !count($assignmentIDs) )
            $assignmentIDs = null;
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $assignmentIDs, 'sga_enabled' => true));

        # Query for all adjustments belonging to the user
        $adjustments = $dbr->select('scholasticgrading_adjustment', '*', array('sgadj_user_id' => $user_id));

        # Store dates, titles, and ids for each enabled assignment
        $scores = array();
        foreach ( $assignments as $assignment ) {

            array_push($scores, array(
                'date'  => $assignment->sga_date,
                'title' => $assignment->sga_title,
                'assignmentID' => $assignment->sga_id
            ));

        }

        # Store dates, titles, and ids for each adjustment
        foreach ( $adjustments as $adjustment ) {

            array_push($scores, array(
                'date'  => $adjustment->sgadj_date,
                'title' => $adjustment->sgadj_title,
                'adjustmentID' => $adjustment->sgadj_id
            ));

        }

        # Sort the assignments and adjustments by date, or title if dates are equivalent
        usort($scores, array('SpecialGrades', 'sortScores'));

        # Build the user scores page
        $content = '';
        $content .= Html::openElement('form', array(
            'method' => 'post',
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
        ));
        $content .= Html::openElement('table', array('class' => 'wikitable sg-userscoresformtable')) . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', array('id' => 'sg-userscoresformtable-header'),
            Html::element('th', null, 'Date') .
            Html::element('th', null, 'Assignment') .
            Html::element('th', null, 'Score') .
            Html::element('th', null, 'Value') .
            Html::element('th', null, 'Comment') .
            Html::element('th', null, 'Enabled') .
            Html::element('th', null, 'Delete')
        ) . "\n";

        # Create a row for each score
        $evaluationParamSetCounter = 0;
        $adjustmentParamSetCounter = 0;
        foreach ( $scores as $score ) {

            if ( $score['assignmentID'] ) {

                # The next row is an evaluation for an assignment
                $assignment = $dbr->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $score['assignmentID']));

                # Increment the course total value
                $totalValue += $assignment->sga_value;

                # Check whether evaluation exists
                $evaluation = $dbr->selectRow('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment->sga_id));
                if ( !$evaluation ) {

                    # The evaluation does not exist
                    # Set default parameters for creating a new evaluation
                    $evaluationDateDefault = $assignment->sga_date;
                    $evaluationScoreDefault = '';
                    $evaluationCommentDefault = '';
                    $evaluationEnabledDefault = true;
                    $evaluationRowClass = 'sg-userscoresformtable-row sg-userscoresformtable-unevaluated';
                    $evaluationDeleteButtonAttr = array('name' => 'delete-evaluation-' . $evaluationParamSetCounter, 'disabled');

                } else {

                    # The evaluation exists

                    # Use its values as default parameters
                    $evaluationDateDefault = $evaluation->sge_date;
                    $evaluationScoreDefault = (float)$evaluation->sge_score;
                    $evaluationCommentDefault = $evaluation->sge_comment;
                    $evaluationEnabledDefault = $evaluation->sge_enabled;
                    $evaluationDeleteButtonAttr = array('name' => 'delete-evaluation-' . $evaluationParamSetCounter);

                    if ( $evaluation->sge_enabled ) {

                        $evaluationRowClass = 'sg-userscoresformtable-row';

                        # Increment the cumulative score and value
                        $cumulativeScore += $evaluation->sge_score;
                        $cumulativeValue  += $assignment->sga_value;

                    } else {

                        $evaluationRowClass = 'sg-userscoresformtable-row sg-userscoresformtable-disabled';

                    }

                }

                $content .= Html::rawElement('tr', array('class' => $evaluationRowClass),
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-date'), Xml::input('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-date]', 10, $evaluationDateDefault, array('autocomplete' => 'off', 'class' => 'sg-date-input'))) .
                    Html::element('td', array('class' => 'sg-userscoresformtable-title'), $assignment->sga_title . ' (' . $assignment->sga_date . ')') .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-score'), Xml::input('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-score]', 5, $evaluationScoreDefault, array('autocomplete' => 'off'))) .
                    Html::element('td', array('class' => 'sg-userscoresformtable-value'), (float)$assignment->sga_value) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-comment'), Xml::input('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-comment]', 50, $evaluationCommentDefault)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-enabled'), Xml::check('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-enabled]', $evaluationEnabledDefault)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-delete'), Xml::submitButton('Delete', $evaluationDeleteButtonAttr))
                );

                $content .= Html::hidden('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-user]', $user_id);
                $content .= Html::hidden('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-assignment]', $assignment->sga_id);
                $content .= "\n";

                $evaluationParamSetCounter += 1;

            } elseif ( $score['adjustmentID'] ) {

                # The next row is an adjustment
                $adjustment = $dbr->selectRow('scholasticgrading_adjustment', '*', array('sgadj_id' => $score['adjustmentID']));

                if ( $adjustment->sgadj_enabled ) {

                    $adjustmentRowClass = 'sg-userscoresformtable-row';

                    # Increment the course total value
                    $totalValue += $adjustment->sgadj_value;

                    # Increment the cumulative score and value
                    $cumulativeScore += $adjustment->sgadj_score;
                    $cumulativeValue  += $adjustment->sgadj_value;

                } else {

                    $adjustmentRowClass = 'sg-userscoresformtable-row sg-userscoresformtable-disabled';

                }

                $content .= Html::rawElement('tr', array('class' => $adjustmentRowClass),
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-date'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-date]', 10, $adjustment->sgadj_date, array('autocomplete' => 'off', 'class' => 'sg-date-input'))) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-title'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-title]', 50, $adjustment->sgadj_title)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-score'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-score]', 5, (float)$adjustment->sgadj_score, array('autocomplete' => 'off'))) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-value'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-value]', 5, (float)$adjustment->sgadj_value, array('autocomplete' => 'off'))) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-comment'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-comment]', 50, $adjustment->sgadj_comment)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-enabled'), Xml::check('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-enabled]', $adjustment->sgadj_enabled)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-delete'), Xml::submitButton('Delete', array('name' => 'delete-adjustment-' . $adjustmentParamSetCounter)))
                );

                $content .= Html::hidden('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-id]', $adjustment->sgadj_id);
                $content .= Html::hidden('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-user]', $user_id);
                $content .= "\n";

                $adjustmentParamSetCounter += 1;

            } else {

                # Something is wrong with the score
                $page->addWikiText('Score appears to be neither an assignment nor an adjustment.');
                return;

            }

        }

        # Create a row for a new adjustment
        $content .= Html::rawElement('tr', array('class' => 'sg-userscoresformtable-row'),
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-date'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-date]', 10, date('Y-m-d'), array('autocomplete' => 'off', 'class' => 'sg-date-input'))) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-title'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-title]', 50, '')) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-score'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-score]', 5, '', array('autocomplete' => 'off'))) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-value'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-value]', 5, '0', array('autocomplete' => 'off'))) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-comment'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-comment]', 50, '')) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-enabled'), Xml::check('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-enabled]', true)) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-delete'), Xml::submitButton('Delete', array('name' => 'delete-adjustment-' . $adjustmentParamSetCounter, 'disabled')))
        );

        $content .= Html::hidden('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-id]', false);
        $content .= Html::hidden('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-user]', $user_id);
        $content .= "\n";

        $adjustmentParamSetCounter += 1;

        # Create a row for point totals
        $content .= Html::rawElement('tr', array('id' => 'sg-userscoresformtable-footer'),
            Html::element('th', null, '') .
            Html::element('th', null, '') .
            Html::element('th', null, $cumulativeScore) .
            Html::element('th', null, $cumulativeValue) .
            Html::element('th', null, 'Current grade: ' . round(100*$cumulativeScore/$cumulativeValue , 2) . '%') .
            Html::element('th', null, '') .
            Html::element('th', null, '')
        ) . "\n";
        $content .= Html::closeElement('table') . "\n";

        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-evaluation'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        # Insert the racetrack image at the top of the page
        $page->addHTML(Html::rawElement('div', array('class' => 'racetrack'),
                Html::element('img', array('src' => '/django/credit/racetrack/' . round($cumulativeScore/$totalValue, 3) . '/' . round($cumulativeValue/$totalValue, 3) . '/racetrack.png'), '')
            )) . "\n";

        $page->addHTML($content);

    } /* end showUserScoreForms */


    /**
     * Display all evaluation forms for an assignment
     *
     * Generates a page for creating and editing evaluations for
     * all users for a single assignment.
     *
     * @param int|bool $id the assignment id
     */

    function showAssignmentEvaluationForms ( $id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Check whether assignment exists
        $assignment = $dbr->selectRow('scholasticgrading_assignment', '*', array('sga_id' => $id));
        if ( !$assignment ) {

            # The assignment does not exist
            $page->addWikiText('Assignment (id=' . $id . ') does not exist.');
            return;

        }

        # The assignment exists

        # Query for the enabled groups the assignment belongs to
        $groupassignments = $dbr->select('scholasticgrading_groupassignment', '*', array('sgga_assignment_id' => $id));
        $groupIDs = array();
        foreach ( $groupassignments as $groupassignment )
            array_push($groupIDs, $groupassignment->sgga_group_id);
        if ( !count($groupIDs) )
            $groupIDs = null;
        $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_id' => $groupIDs, 'sgg_enabled' => true));
        $groupIDs = array();
        foreach ( $groups as $group )
            array_push($groupIDs, $group->sgg_id);
        if ( !count($groupIDs) )
            $groupIDs = null;

        # Query for the users in these groups
        $groupusers = $dbr->select('scholasticgrading_groupuser', '*', array('sggu_group_id' => $groupIDs));
        $userIDs = array();
        foreach ( $groupusers as $groupuser )
            array_push($userIDs, $groupuser->sggu_user_id);
        $userIDs = array_unique($userIDs);
        if ( !count($userIDs) )
            $userIDs = null;
        $users = $dbr->select('user', '*', array('user_id' => $userIDs), __METHOD__,
            array('ORDER BY' => 'user_name'));

        # Abort if there are no users
        if ( $users->numRows() === 0 ) {
            $page->addWikiText('There are no users to evaluate because all enabled groups this assignment belongs to contain no users.');
            return;
        }

        # Build the assignment scores page
        $content = '';
        $content .= Html::openElement('form', array(
            'method' => 'post',
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
        ));
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-assignmentscoresformtable')) . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', array('id' => 'sg-assignmentscoresformtable-header'),
            Html::element('th', null, 'User') .
            Html::element('th', array('class' => 'unsortable'), 'Date') .
            Html::element('th', array('class' => 'unsortable'), 'Score') .
            Html::element('th', array('class' => 'unsortable'), 'Value') .
            Html::element('th', array('class' => 'unsortable'), 'Comment') .
            Html::element('th', array('class' => 'unsortable'), 'Enabled') .
            Html::element('th', array('class' => 'unsortable'), 'Delete')
        ) . "\n";

        # Create a row for each user
        $paramSetCounter = 0;
        foreach ( $users as $user ) {

            # Check whether evaluation exists
            $evaluation = $dbr->selectRow('scholasticgrading_evaluation', '*', array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $id));
            if ( !$evaluation ) {

                # The evaluation does not exist
                # Set default parameters for creating a new evaluation
                $evaluationDateDefault = $assignment->sga_date;
                $evaluationScoreDefault = '';
                $evaluationCommentDefault = '';
                $evaluationEnabledDefault = true;
                $evaluationRowClass = 'sg-assignmentscoresformtable-row sg-assignmentscoresformtable-unevaluated';
                $evaluationDeleteButtonAttr = array('name' => 'delete-evaluation-' . $paramSetCounter, 'disabled');

            } else {

                # The evaluation exists

                # Use its values as default parameters
                $evaluationDateDefault = $evaluation->sge_date;
                $evaluationScoreDefault = (float)$evaluation->sge_score;
                $evaluationCommentDefault = $evaluation->sge_comment;
                $evaluationEnabledDefault = $evaluation->sge_enabled;
                $evaluationDeleteButtonAttr = array('name' => 'delete-evaluation-' . $paramSetCounter);

                if ( $evaluation->sge_enabled ) {

                    $evaluationRowClass = 'sg-assignmentscoresformtable-row';

                } else {

                    $evaluationRowClass = 'sg-assignmentscoresformtable-row sg-assignmentscoresformtable-disabled';

                }

            }

            $content .= Html::rawElement('tr', array('class' => $evaluationRowClass),
                Html::element('td', array('class' => 'sg-assignmentscoresformtable-user'), $this->getUserDisplayName($user->user_id)) .
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-date'), Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-date]', 10, $evaluationDateDefault, array('autocomplete' => 'off', 'class' => 'sg-date-input'))) .
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-score'), Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-score]', 5, $evaluationScoreDefault, array('autocomplete' => 'off'))) .
                Html::element('td', array('class' => 'sg-assignmentscoresformtable-value'), (float)$assignment->sga_value) .
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-comment'), Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-comment]', 50, $evaluationCommentDefault)) .
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-enabled'), Xml::check('evaluation-params[' . $paramSetCounter . '][evaluation-enabled]', $evaluationEnabledDefault)) .
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-delete'), Xml::submitButton('Delete', $evaluationDeleteButtonAttr))
            );

            $content .= Html::hidden('evaluation-params[' . $paramSetCounter . '][evaluation-user]', $user->user_id);
            $content .= Html::hidden('evaluation-params[' . $paramSetCounter . '][evaluation-assignment]', $id);
            $content .= "\n";

            $paramSetCounter += 1;

        }

        $content .= Html::closeElement('table') . "\n";
        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-evaluation'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        $page->addHTML($content);

    } /* showAssignmentEvaluationForms */


    /**
     * Display a table of all assignments
     *
     * Generates a table of assignments with controls
     * for modifying and deleting assignments
     */

    function showAllAssignments () {

        $page = $this->getOutput();

        # Query for all assignments and all enabled groups
        $dbr = wfGetDB(DB_SLAVE);
        $assignments = $dbr->select('scholasticgrading_assignment', '*', '', __METHOD__,
            array('ORDER BY' => array('ISNULL(sga_date)', 'sga_date', 'sga_title')));
        $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_enabled' => true), __METHOD__,
            array('ORDER BY' => array('sgg_title')));

        # Build the assignment table
        $content = '';
        $content .= Html::openElement('form', array(
            'method' => 'post',
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
        ));
        $content .= Html::openElement('table', array('class' => 'wikitable sg-manageassignmentstable')) . "\n";

        $totalValue = array();

        # Create a column header for each field
        $content .= Html::openElement('tr', array('id' => 'sg-manageassignmentstable-header')) .
            Html::element('th', null, 'Date') .
            Html::element('th', null, 'Title') .
            Html::element('th', null, 'Value') .
            Html::element('th', null, 'Enabled');
        foreach ( $groups as $group ) {
            $content .= Html::element('th', null, $group->sgg_title);


            # Initialize the total value for this group
            $totalValue[$group->sgg_id] = 0;
        }
        $content .= Html::element('th', null, 'Delete');
        $content .= Html::closeElement('tr');
        $content .= "\n";

        # Create a row for each assignment
        $paramSetCounter = 0;
        foreach ( $assignments as $assignment ) {

            if ( $assignment->sga_enabled ) {
                $assignmentRowClass = 'sg-manageassignmentstable-row';
            } else {
                $assignmentRowClass = 'sg-manageassignmentstable-row sg-manageassignmentstable-disabled';
            }

            $content .= Html::openElement('tr', array('class' => $assignmentRowClass)) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-date'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-date]', 10, $assignment->sga_date, array('autocomplete' => 'off', 'class' => 'sg-date-input'))) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-title'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-title]', 50, $assignment->sga_title)) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-value'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-value]', 5, (float)$assignment->sga_value, array('autocomplete' => 'off'))) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-enabled'), Xml::check('assignment-params[' . $paramSetCounter . '][assignment-enabled]', $assignment->sga_enabled));

            # Create a cell for each group
            foreach ( $groups as $group ) {

                $groupassignment = $dbr->selectRow('scholasticgrading_groupassignment', '*',
                    array('sgga_group_id' => $group->sgg_id, 'sgga_assignment_id' => $assignment->sga_id));
                if ( $groupassignment ) {

                    # The assignment is a member of the group
                    $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-group'),
                        Html::hidden('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', 0) .
                        Xml::check('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', true)
                    );

                    # Increment the total value for this group
                    if ( $assignment->sga_enabled )
                        $totalValue[$group->sgg_id] += $assignment->sga_value;

                } else {

                    # The assignment is not a member of the group
                    $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-group'),
                        Html::hidden('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', 0) .
                        Xml::check('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', false)
                    );

                }

            }

            $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-delete'), Xml::submitButton('Delete', array('name' => 'delete-assignment-' . $paramSetCounter)));
            $content .= Html::closeElement('tr');

            $content .= Html::hidden('assignment-params[' . $paramSetCounter . '][assignment-id]', $assignment->sga_id);
            $content .= "\n";

            $paramSetCounter += 1;

        }

        # Create a hidden row that will serve as a template for
        # new rows added dynamically for new assignments
        $content .= Html::openElement('tr', array('id' => 'sg-manageassignmentstable-new', 'class' => 'sg-manageassignmentstable-row', 'hidden')) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-date'), Xml::input('assignment-params[paramSetCounterPlaceholder][assignment-date]', 10, date('Y-m-d'), array('autocomplete' => 'off', 'class' => 'sg-date-input'))) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-title'), Xml::input('assignment-params[paramSetCounterPlaceholder][assignment-title]', 50, '')) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-value'), Xml::input('assignment-params[paramSetCounterPlaceholder][assignment-value]', 5, '', array('autocomplete' => 'off'))) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-enabled'), Xml::check('assignment-params[paramSetCounterPlaceholder][assignment-enabled]', true));

        foreach ( $groups as $group ) {
            $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-group'),
                Html::hidden('assignment-params[paramSetCounterPlaceholder][assignment-group][' . $group->sgg_id . ']', 0) .
                Xml::check('assignment-params[paramSetCounterPlaceholder][assignment-group][' . $group->sgg_id . ']', false)
            );
        }

        $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-delete'), Xml::submitButton('Delete', array('name' => 'delete-assignment-paramSetCounterPlaceholder', 'disabled')));
        $content .= Html::closeElement('tr');

        $content .= Html::hidden('assignment-params[paramSetCounterPlaceholder][assignment-id]', false);
        $content .= "\n";

        # Report value totals for each group
        $content .= Html::openElement('tr', array('id' => 'sg-manageassignmentstable-footer'));
        $content .= Html::element('th', null, '') . Html::element('th', null, '') . Html::element('th', null, '') . Html::element('th', null, '');
        foreach ( $groups as $group )
            $content .= Html::element('th', null, $totalValue[$group->sgg_id]);
        $content .= Html::element('th', null, '');
        $content .= Html::closeElement('tr') . "\n";

        $content .= Html::closeElement('table') . "\n";
        $content .= Html::rawElement('p', null,
            Html::element('a', array('class' => 'sg-appendassignment', 'href' => 'javascript:void(0);'), 'Add another assignment')) . "\n";
        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-assignment'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        $page->addHTML($content);

    } /* end showAllAssignments */


    /**
     * Display a table of all groups and user group membership controls
     *
     * Generates a table of groups with controls for modifying
     * and deleting groups, and a table with controls for modifying
     * user group memberships
     */

    function showAllGroups () {

        $page = $this->getOutput();

        # Query for all users and all groups
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*', '', __METHOD__,
            array('ORDER BY' => array('user_name')));
        $groups = $dbr->select('scholasticgrading_group', '*', '', __METHOD__,
            array('ORDER BY' => array('sgg_title')));

        # Build the group table
        $content = '';
        $content .= Html::openElement('form', array(
            'method' => 'post',
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
        ));
        $content .= Html::openElement('table', array('class' => 'wikitable sg-managegroupstable')) . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', array('id' => 'sg-managegroupstable-header'),
            Html::element('th', null, 'Title') .
            Html::element('th', null, 'Enabled') .
            Html::element('th', null, 'Delete')
        ) . "\n";

        # Create a row for each group
        $paramSetCounter = 0;
        foreach ( $groups as $group ) {

            if ( $group->sgg_enabled ) {
                $groupRowClass = 'sg-managegroupstable-row';
            } else {
                $groupRowClass = 'sg-managegroupstable-row sg-managegroupstable-disabled';
            }

            $content .= Html::rawElement('tr', array('class' => $groupRowClass),
                Html::rawElement('td', array('class' => 'sg-managegroupstable-title'), Xml::input('group-params[' . $paramSetCounter . '][group-title]', 50, $group->sgg_title)) .
                Html::rawElement('td', array('class' => 'sg-managegroupstable-enabled'), Xml::check('group-params[' . $paramSetCounter . '][group-enabled]', $group->sgg_enabled)) .
                Html::rawElement('td', array('class' => 'sg-managegroupstable-delete'), Xml::submitButton('Delete', array('name' => 'delete-group-' . $paramSetCounter)))
            );

            $content .= Html::hidden('group-params[' . $paramSetCounter . '][group-id]', $group->sgg_id);
            $content .= "\n";

            $paramSetCounter += 1;

        }

        # Create a row for a new group
        $content .= Html::rawElement('tr', array('class' => 'sg-managegroupstable-row'),
            Html::rawElement('td', array('class' => 'sg-managegroupstable-title'), Xml::input('group-params[' . $paramSetCounter . '][group-title]', 50, '')) .
            Html::rawElement('td', array('class' => 'sg-managegroupstable-enabled'), Xml::check('group-params[' . $paramSetCounter . '][group-enabled]', true)) .
            Html::rawElement('td', array('class' => 'sg-managegroupstable-delete'), Xml::submitButton('Delete', array('name' => 'delete-group-' . $paramSetCounter, 'disabled')))
        );

        $content .= Html::hidden('group-params[' . $paramSetCounter . '][group-user][]', '');
        $content .= Html::hidden('group-params[' . $paramSetCounter . '][group-id]', false);
        $content .= "\n";

        $paramSetCounter += 1;

        $content .= Html::closeElement('table') . "\n";

        # Build the group users table
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-managegroupstable')) . "\n";

        # Create a column header for each field
        $content .= Html::openElement('tr', array('id' => 'sg-managegroupstable-header'));
        $content .= Html::element('th', null, 'User');
        foreach ( $groups as $group )
            $content .= Html::element('th', array('class' => 'unsortable'), $group->sgg_title);
        $content .= Html::closeElement('tr');
        $content .= "\n";

        # Create a row for each user
        foreach ( $users as $user ) {

            $content .= Html::openElement('tr', array('class' => 'sg-managegroupstable-row'));
            $content .= Html::element('td', array('class' => 'sg-managegroupstable-user'), $this->getUserDisplayName($user->user_id));

            # Create a cell for each group
            $paramSetCounter = 0;
            foreach ( $groups as $group ) {

                $groupuser = $dbr->selectRow('scholasticgrading_groupuser', '*',
                    array('sggu_group_id' => $group->sgg_id, 'sggu_user_id' => $user->user_id));
                if ( $groupuser ) {

                    # The user is a member of the group
                    $content .= Html::rawElement('td', array('class' => 'sg-managegroupstable-groupuser'),
                        Html::hidden('group-params[' . $paramSetCounter . '][group-user][' . $user->user_id . ']', 0) .
                        Xml::check('group-params[' . $paramSetCounter . '][group-user][' . $user->user_id . ']', true)
                    );

                } else {

                    # The user is not a member of the group
                    $content .= Html::rawElement('td', array('class' => 'sg-managegroupstable-groupuser'),
                        Html::hidden('group-params[' . $paramSetCounter . '][group-user][' . $user->user_id . ']', 0) .
                        Xml::check('group-params[' . $paramSetCounter . '][group-user][' . $user->user_id . ']', false)
                    );

                }

                $paramSetCounter += 1;

            }

            $content .= Html::closeElement('tr');

        }

        $content .= Html::closeElement('table') . "\n";
        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-group'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        $page->addHTML($content);

    } /* end showAllGroups */


    /**
     * Display tables of scores for each assignment-user combination for each group
     *
     * Generates a table for each enabled group of evaluations,
     * where columns represent users and rows represent assignments.
     */

    function showGradeGrids () {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Query for all enabled groups
        $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_enabled' => true), __METHOD__,
            array('ORDER BY' => array('sgg_title')));

        # Create the grade grid tabs
        $content = '';
        $content .= Html::openElement('div', array('id' => 'sg-gradegrid-tabs')) . "\n";
        $content .= Html::openElement('ul') . "\n";
        foreach ( $groups as $group ) {
            $content .= Html::rawElement('li', null, '<a href="#sg-gradegrid-tabs-' . $group->sgg_id . '">' . $group->sgg_title . '</a>') . "\n";
        }
        $content .= Html::closeElement('ul') . "\n";

        # Create a grade grid for each enabled group
        foreach ( $groups as $group ) {

            $cumulativeScore = array();
            $cumulativeValue = array();

            # Query for all users in the group
            $groupusers = $dbr->select('scholasticgrading_groupuser', '*', array('sggu_group_id' => $group->sgg_id));
            $userIDs = array();
            foreach ( $groupusers as $groupuser )
                array_push($userIDs, $groupuser->sggu_user_id);
            if ( !count($userIDs) )
                $userIDs = null;
            $users = $dbr->select('user', '*', array('user_id' => $userIDs), __METHOD__,
                array('ORDER BY' => array('user_name')));

            # Query for all enabled assignments in the group
            $groupassignments = $dbr->select('scholasticgrading_groupassignment', '*', array('sgga_group_id' => $group->sgg_id));
            $assignmentIDs = array();
            foreach ( $groupassignments as $groupassignment )
                array_push($assignmentIDs, $groupassignment->sgga_assignment_id);
            if ( !count($assignmentIDs) )
                $assignmentIDs = null;
            $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $assignmentIDs, 'sga_enabled' => true), __METHOD__,
                array('ORDER BY' => array('ISNULL(sga_date)', 'sga_date', 'sga_title')));

            # Build the grade grid
            $content .= Html::openElement('div', array('id' => 'sg-gradegrid-tabs-' . $group->sgg_id)) . "\n";
            $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-gradegrid')) . "\n";

            # Create a column header for each student
            $content .= Html::openElement('tr', array('id' => 'sg-gradegrid-header'));
            $content .= Html::element('th', null, 'Date') . Html::element('th', null, 'Assignment');
            foreach ( $users as $user ) {
                $content .= Html::rawElement('th', array('class' => 'sg-gradegrid-user'),
                    Html::rawElement('div', null,
                        Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                            array('action' => 'edituserscores', 'user' => $user->user_id))
                    )
                );

                # Initialize the cumulative score and value for this student
                $cumulativeScore[$user->user_name] = 0;
                $cumulativeValue[$user->user_name] = 0;
            }
            $content .= Html::closeElement('tr') . "\n";

            # Create a row for each enabled assignment
            foreach ( $assignments as $assignment ) {

                $content .= Html::openElement('tr', array('class' => 'sg-gradegrid-row'));
                $content .= Html::element('td', array('class' => 'sg-gradegrid-date', 'data-sort-value' => $assignment->sga_date ? $assignment->sga_date : '9999'), $assignment->sga_date ? date_format(date_create($assignment->sga_date), 'D m/d') : '');
                $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-assignment', 'title' =>
                    'Value: ' . (float)$assignment->sga_value),
                    Linker::linkKnown($this->getTitle(), $assignment->sga_title, array('title' => 
                        'Value: ' . (float)$assignment->sga_value),
                        array('action' => 'editassignmentscores', 'id' => $assignment->sga_id)));

                # Create a cell for each user
                foreach ( $users as $user ) {

                    $evaluationCellClass = 'sg-gradegrid-cell';
                    $evaluation = $dbr->selectRow('scholasticgrading_evaluation', '*',
                        array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $assignment->sga_id));
                    if ( $evaluation ) {

                        # An evaluation exists for this (user,assignment) combination
                        if ( $evaluation->sge_enabled ) {

                            # The evaluation is enabled
                            if ( $assignment->sga_value == 0 ) {

                                # The assignment is extra credit
                                $evaluationCellClass .= ' sg-gradegrid-extracredit';
                                $content .= Html::rawElement('td', array('class' => $evaluationCellClass, 'title' =>
                                    'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                                 'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                                 'Score: ' . (float)$evaluation->sge_score . ' out of ' . (float)$assignment->sga_value),
                                    Linker::linkKnown($this->getTitle(), '+' . (float)$evaluation->sge_score, array('title' =>
                                        'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                                     'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                                     'Score: ' . (float)$evaluation->sge_score . ' out of ' . (float)$assignment->sga_value),
                                        array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                            } else {

                                # The assignment is not extra credit
                                $evaluationPercentage = $evaluation->sge_score / $assignment->sga_value * 100;
                                if ( $evaluationPercentage > 100.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-extracredit';
                                } elseif ( $evaluationPercentage == 100.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-fullcredit';
                                } elseif ( $evaluationPercentage >= 90.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-90s';
                                } elseif ( $evaluationPercentage >= 80.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-80s';
                                } elseif ( $evaluationPercentage >= 70.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-70s';
                                } elseif ( $evaluationPercentage >= 60.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-60s';
                                } elseif ( $evaluationPercentage >= 50.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-50s';
                                } elseif ( $evaluationPercentage >= 40.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-40s';
                                } elseif ( $evaluationPercentage >= 30.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-30s';
                                } elseif ( $evaluationPercentage >= 20.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-20s';
                                } elseif ( $evaluationPercentage >= 10.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-10s';
                                } elseif ( $evaluationPercentage >  0.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-0s';
                                } elseif ( $evaluationPercentage == 0.0 ) {
                                    $evaluationCellClass .= ' sg-gradegrid-zerocredit';
                                } else {
                                    # Should not get here
                                    $evaluationCellClass .= '';
                                }
                                $content .= Html::rawElement('td', array('class' => $evaluationCellClass, 'title' =>
                                    'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                                 'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                                 'Score: ' . (float)$evaluation->sge_score . ' out of ' . (float)$assignment->sga_value),
                                    Linker::linkKnown($this->getTitle(), round($evaluationPercentage, 2) . '%', array('title' =>
                                        'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                                     'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                                     'Score: ' . (float)$evaluation->sge_score . ' out of ' . (float)$assignment->sga_value),
                                        array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                            }

                            # Increment the cumulative score and value for this student
                            $cumulativeScore[$user->user_name] += $evaluation->sge_score;
                            $cumulativeValue[$user->user_name] += $assignment->sga_value;

                        } else {

                            # The evaluation is disabled
                            $evaluationCellClass .= '';
                            $content .= Html::rawElement('td', array('class' => $evaluationCellClass, 'title' =>
                                'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                             'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                             'Evaluation disabled'),
                                Linker::linkKnown($this->getTitle(), '**', array('title' =>
                                    'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                                 'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                                 'Evaluation disabled'),
                                    array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                        }

                    } else {

                        # An evaluation does not exist for this (user,assignment) combination
                        $evaluationCellClass .= '';
                        $content .= Html::rawElement('td', array('class' => $evaluationCellClass, 'title' =>
                            'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                         'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                         'Unevaluated'),
                            Linker::linkKnown($this->getTitle(), '--', array('title' =>
                                'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                             'Assignment: ' . $assignment->sga_title . ' (' . $assignment->sga_date . ')' . '
' .                             'Unevaluated'),
                                array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                    }

                } /* end for each user */

                $content .= Html::closeElement('tr') . "\n";

            } /* end for each enabled assignment */

            # Create a row listing point adjustment sums
            $content .= Html::openElement('tr', array('class' => 'sg-gradegrid-row'));
            $content .= Html::element('td', array('class' => 'sg-gradegrid-date', 'data-sort-value' => '9999'), '');
            $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-assignment'), 'Point adjustments');

            # Create a cell for each user
            foreach ( $users as $user ) {

                $adjustmentScoreSum = 0;
                $adjustmentValueSum = 0;

                $adjustments = $dbr->select('scholasticgrading_adjustment', '*',
                    array('sgadj_user_id' => $user->user_id, 'sgadj_enabled' => true));
                foreach ( $adjustments as $adjustment ) {

                    $adjustmentScoreSum += $adjustment->sgadj_score;
                    $adjustmentValueSum += $adjustment->sgadj_value;

                    # Increment the cumulative score and value for this student
                    $cumulativeScore[$user->user_name] += $adjustment->sgadj_score;
                    $cumulativeValue[$user->user_name] += $adjustment->sgadj_value;

                }

                $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-cell', 'title' =>
                    'User: ' . $this->getUserDisplayName($user->user_id)),
                    $adjustmentScoreSum . ' / ' . $adjustmentValueSum);

            }

            $content .= Html::closeElement('tr') . "\n";

            # Report point totals for each student
            $content .= Html::openElement('tr', array('id' => 'sg-gradegrid-footer'));
            $content .= Html::element('th', null, '') . Html::element('th', null, '');
            foreach ( $users as $user ) {
                $content .= Html::element('th', array('title' =>
                    'User: ' . $this->getUserDisplayName($user->user_id) . '
' .                 round(100*$cumulativeScore[$user->user_name]/$cumulativeValue[$user->user_name], 2) . '%'),
                    $cumulativeScore[$user->user_name] . ' / ' . $cumulativeValue[$user->user_name]
                );
            }
            $content .= Html::closeElement('tr') . "\n";

            $content .= Html::closeElement('table') . "\n";
            $content .= Html::closeElement('div') . "\n";

        } /* end for each group */

        $content .= Html::closeElement('div');

        $page->addHTML($content);

    } /* end showGradeGrids */


    /**
     * Display all scores for a user
     *
     * Generates a page for viewing all enabled evaluations for
     * all enabled assignments, all enabled assignments that do
     * not have an enabled evaluation, and all adjustments for
     * a single user.
     *
     * @param int|bool $user_id the user id
     */

    function showUserScores ( $user_id = false ) {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Check whether user exists
        $user = $dbr->selectRow('user', '*', array('user_id' => $user_id));
        if ( !$user ) {

            # The user does not exist
            $page->addWikiText('User (id=' . $user_id . ') does not exist.');
            return;

        }

        # The user exists

        # Initialize the cumulative score, cumulative value,
        # and the course total value for this student
        $cumulativeScore = 0;
        $cumulativeValue = 0;
        $totalValue = 0;

        # Query for the enabled groups the user belongs to
        $groupusers = $dbr->select('scholasticgrading_groupuser', '*', array('sggu_user_id' => $user_id));
        $groupIDs = array();
        foreach ( $groupusers as $groupuser )
            array_push($groupIDs, $groupuser->sggu_group_id);
        if ( !count($groupIDs) )
            $groupIDs = null;
        $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_id' => $groupIDs, 'sgg_enabled' => true));
        $groupIDs = array();
        foreach ( $groups as $group )
            array_push($groupIDs, $group->sgg_id);
        if ( !count($groupIDs) )
            $groupIDs = null;

        # Query for the enabled assignments attached to these groups
        $groupassignments = $dbr->select('scholasticgrading_groupassignment', '*', array('sgga_group_id' => $groupIDs));
        $assignmentIDs = array();
        foreach ( $groupassignments as $groupassignment )
            array_push($assignmentIDs, $groupassignment->sgga_assignment_id);
        $assignmentIDs = array_unique($assignmentIDs);
        if ( !count($assignmentIDs) )
            $assignmentIDs = null;
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $assignmentIDs, 'sga_enabled' => true));

        # Query for all enabled adjustments belonging to the user
        $adjustments = $dbr->select('scholasticgrading_adjustment', '*', array('sgadj_user_id' => $user_id, 'sgadj_enabled' => true));

        # Abort if there are no assignments or adjustments
        if ( $assignments->numRows() === 0 && $adjustments->numRows() === 0 ) {
            $page->addWikiText('There are currently no assignments assigned to you.');
            return;
        }

        # Store scores for each enabled assignment
        $scores = array();
        foreach ( $assignments as $assignment ) {

            # Increment the course total value
            $totalValue += $assignment->sga_value;

            # Check whether evaluation exists and is enabled
            $evaluation = $dbr->selectRow('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment->sga_id, 'sge_enabled' => true));
            if ( $evaluation ) {

                # The evaluation exists and is enabled

                # Increment the cumulative score and value
                $cumulativeScore += $evaluation->sge_score;
                $cumulativeValue  += $assignment->sga_value;

                # Store the score for this assignment
                array_push($scores, array(
                    'date'    => $evaluation->sge_date,
                    'title'   => $assignment->sga_title,
                    'score'   => $evaluation->sge_score,
                    'value'   => $assignment->sga_value,
                    'comment' => $evaluation->sge_comment
                ));

            } else {

                # The evaluation either does not exist or is disabled

                # Store the score for this assignment
                array_push($scores, array(
                    'date'    => $assignment->sga_date,
                    'title'   => $assignment->sga_title,
                    'score'   => false,
                    'value'   => $assignment->sga_value,
                    'comment' => ''
                ));

            }

        }

        # Store scores for each enabled adjustment
        foreach ( $adjustments as $adjustment ) {

            # Increment the course total value
            $totalValue += $adjustment->sgadj_value;

            # Increment the cumulative score and value
            $cumulativeScore += $adjustment->sgadj_score;
            $cumulativeValue  += $adjustment->sgadj_value;

            # Store the score for this adjustment
            array_push($scores, array(
                'date'    => $adjustment->sgadj_date,
                'title'   => $adjustment->sgadj_title,
                'score'   => $adjustment->sgadj_score,
                'value'   => $adjustment->sgadj_value,
                'comment' => $adjustment->sgadj_comment
            ));

        }

        # Sort the scores by date, or title if dates are equivalent
        usort($scores, array('SpecialGrades', 'sortScores'));

        # Build the user scores page
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-userscorestable')) . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', array('id' => 'sg-userscorestable-header'),
            Html::element('th', null, 'Date') .
            Html::element('th', null, 'Assignment') .
            Html::element('th', null, 'Score') .
            Html::element('th', null, 'Value') .
            Html::element('th', null, 'Comment')
        ) . "\n";

        # Create a row for each score
        foreach ( $scores as $score ) {

            if ( $score['score'] ) {

                # Evaluated assignments and adjustments have scores
                $content .= Html::rawElement('tr', array('class' => 'sg-userscorestable-row'),
                    Html::element('td', array('class' => 'sg-userscorestable-date', 'data-sort-value' => $score['date'] ? $score['date'] : '9999'), $score['date'] ? date_format(date_create($score['date']), 'D m/d') : '') .
                    Html::element('td', array('class' => 'sg-userscorestable-title'), $score['title']) .
                    Html::element('td', array('class' => 'sg-userscorestable-score'), (float)$score['score']) .
                    Html::element('td', array('class' => 'sg-userscorestable-value'), (float)$score['value']) .
                    Html::element('td', array('class' => 'sg-userscorestable-comment'), $score['comment'])
                ) . "\n";

            } else {

                # Unevaluated assignments do not have scores
                $content .= Html::rawElement('tr', array('class' => 'sg-userscorestable-row sg-userscorestable-unevaluated'),
                    Html::element('td', array('class' => 'sg-userscorestable-date', 'data-sort-value' => $score['date'] ? $score['date'] : '9999'), $score['date'] ? date_format(date_create($score['date']), 'D m/d') : '') .
                    Html::element('td', array('class' => 'sg-userscorestable-title'), $score['title']) .
                    Html::element('td', array('class' => 'sg-userscorestable-score'), '-') .
                    Html::element('td', array('class' => 'sg-userscorestable-value'), (float)$score['value']) .
                    Html::element('td', array('class' => 'sg-userscorestable-comment'), $score['comment'])
                ) . "\n";

            }

        }

        # Create a row for point totals
        $content .= Html::rawElement('tr', array('id' => 'sg-userscorestable-footer'),
            Html::element('th', null, '') .
            Html::element('th', null, '') .
            Html::element('th', null, $cumulativeScore) .
            Html::element('th', null, $cumulativeValue) .
            Html::element('th', null, 'Current grade: ' . round(100*$cumulativeScore/$cumulativeValue , 2) . '%')
        ) . "\n";
        $content .= Html::closeElement('table') . "\n";

        # Insert the racetrack image at the top of the page
        $page->addHTML(Html::rawElement('div', array('class' => 'racetrack'),
                Html::element('img', array('src' => '/django/credit/racetrack/' . round($cumulativeScore/$totalValue, 3) . '/' . round($cumulativeValue/$totalValue, 3) . '/racetrack.png'), '')
            )) . "\n";

        $page->addHTML($content);

    } /* end showUserScores */


    /**
     * Display all scores for all users
     *
     * Generates a page for viewing all enabled evaluations for
     * all enabled assignments, all enabled assignments that do
     * not have an enabled evaluation, and all adjustments for
     * each user that belongs to at least one enabled group.
     */

    function showAllUserScores () {

        $page = $this->getOutput();
        $dbr = wfGetDB(DB_SLAVE);

        # Query for all enabled groups
        $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_enabled' => true));
        $groupIDs = array();
        foreach( $groups as $group )
            array_push($groupIDs, $group->sgg_id);
        if ( !count($groupIDs) )
            $groupIDs = null;

        # Query for the users in these groups
        $groupusers = $dbr->select('scholasticgrading_groupuser', '*', array('sggu_group_id' => $groupIDs));
        $userIDs = array();
        foreach ( $groupusers as $groupuser )
            array_push($userIDs, $groupuser->sggu_user_id);
        $userIDs = array_unique($userIDs);
        if ( !count($userIDs) )
            $userIDs = null;
        $users = $dbr->select('user', '*', array('user_id' => $userIDs), __METHOD__,
            array('ORDER BY' => 'user_name'));

        # Abort if there are no users
        if ( $users->numRows() === 0 ) {
            $page->addWikiText('There are no users belonging to enabled groups.');
            return;
        }

        # Create user score tables for each user
        foreach ( $users as $user ) {
            $page->addHTML(Html::rawElement('h3', array('class' => 'sg-allusertables-header'),
                Linker::linkKnown($this->getTitle(), $this->getUserDisplayName($user->user_id), array(),
                    array('action' => 'edituserscores', 'user' => $user->user_id))) . "\n");
            $this->showUserScores( $user->user_id );
            $page->addHTML(Html::element('br'));
            $page->addHTML(Html::element('hr'));
            $page->addHTML(Html::element('br'));
        }

    } /* end showAllUserScores */


    /**
     * Display a table of all wiki users
     *
     * Dumps a portion of the user table from the wiki database.
     * Used for development.
     */

    function showUsers () {

        $page = $this->getOutput();

        # Query for all users
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*');

        # Build the user table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Users') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'name') .
            Html::element('th', null, 'real name')
        ) . "\n";

        # Create a row for each user
        foreach ( $users as $user ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $user->user_id) .
                Html::element('td', null, $user->user_name) .
                Html::element('td', null, $user->user_real_name)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showUsers */


    /**
     * Display a table of all assignments
     *
     * Dumps the assignment table from the wiki database.
     * Used for development.
     */

    function showAssignments () {

        $page = $this->getOutput();

        # Query for all assignments
        $dbr = wfGetDB(DB_SLAVE);
        $assignments = $dbr->select('scholasticgrading_assignment', '*');

        # Build the assignment table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Assignments') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'title') .
            Html::element('th', null, 'value') .
            Html::element('th', null, 'enabled') .
            Html::element('th', null, 'date')
        ) . "\n";

        # Create a row for each assignment
        foreach ( $assignments as $assignment ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $assignment->sga_id) .
                Html::element('td', null, $assignment->sga_title) .
                Html::element('td', null, $assignment->sga_value) .
                Html::element('td', null, $assignment->sga_enabled) .
                Html::element('td', null, $assignment->sga_date)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showAssignments */


    /**
     * Displays a table of all evaluations
     *
     * Dumps the evaluation table from the wiki database.
     * Used for development.
     */

    function showEvaluations () {

        $page = $this->getOutput();

        # Query for all evaluations
        $dbr = wfGetDB(DB_SLAVE);
        $evaluations = $dbr->select('scholasticgrading_evaluation', '*');

        # Build the evaluations table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Evaluations') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'user id') .
            Html::element('th', null, 'assignment id') .
            Html::element('th', null, 'score') .
            Html::element('th', null, 'enabled') .
            Html::element('th', null, 'date') .
            Html::element('th', null, 'comment')
        ) . "\n";

        # Create a row for each evaluation
        foreach ( $evaluations as $evaluation ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $evaluation->sge_user_id) .
                Html::element('td', null, $evaluation->sge_assignment_id) .
                Html::element('td', null, $evaluation->sge_score) .
                Html::element('td', null, $evaluation->sge_enabled) .
                Html::element('td', null, $evaluation->sge_date) .
                Html::element('td', null, $evaluation->sge_comment)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showEvaluations */


    /**
     * Displays a table of all adjustments
     *
     * Dumps the adjustment table from the wiki database.
     * Used for development.
     */

    function showAdjustments () {

        $page = $this->getOutput();

        # Query for all adjustments
        $dbr = wfGetDB(DB_SLAVE);
        $adjustments = $dbr->select('scholasticgrading_adjustment', '*');

        # Build the adjustments table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Adjustments') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'user id') .
            Html::element('th', null, 'title') .
            Html::element('th', null, 'score') .
            Html::element('th', null, 'value') .
            Html::element('th', null, 'enabled') .
            Html::element('th', null, 'date') .
            Html::element('th', null, 'comment')
        ) . "\n";

        # Create a row for each adjustment
        foreach ( $adjustments as $adjustment ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $adjustment->sgadj_id) .
                Html::element('td', null, $adjustment->sgadj_user_id) .
                Html::element('td', null, $adjustment->sgadj_title) .
                Html::element('td', null, $adjustment->sgadj_score) .
                Html::element('td', null, $adjustment->sgadj_value) .
                Html::element('td', null, $adjustment->sgadj_enabled) .
                Html::element('td', null, $adjustment->sgadj_date) .
                Html::element('td', null, $adjustment->sgadj_comment)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showAdjustments */


    /**
     * Displays a table of all groups
     *
     * Dumps the group table from the wiki database.
     * Used for development.
     */

    function showGroups () {

        $page = $this->getOutput();

        # Query for all groups
        $dbr = wfGetDB(DB_SLAVE);
        $groups = $dbr->select('scholasticgrading_group', '*');

        # Build the groups table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Groups') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'title') .
            Html::element('th', null, 'enabled')
        ) . "\n";

        # Create a row for each group
        foreach ( $groups as $group ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $group->sgg_id) .
                Html::element('td', null, $group->sgg_title) .
                Html::element('td', null, $group->sgg_enabled)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showGroups */


    /**
     * Displays a table of all group users
     *
     * Dumps the groupuser table from the wiki database.
     * Used for development.
     */

    function showGroupUsers () {

        $page = $this->getOutput();

        # Query for all group users
        $dbr = wfGetDB(DB_SLAVE);
        $groupusers = $dbr->select('scholasticgrading_groupuser', '*');

        # Build the group users table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Group Users') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'group id') .
            Html::element('th', null, 'user id')
        ) . "\n";

        # Create a row for each group user
        foreach ( $groupusers as $groupuser ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $groupuser->sggu_group_id) .
                Html::element('td', null, $groupuser->sggu_user_id)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showGroupUsers */


    /**
     * Displays a table of all group assignments
     *
     * Dumps the groupassignment table from the wiki database.
     * Used for development.
     */

    function showGroupAssignments () {

        $page = $this->getOutput();

        # Query for all group assignments
        $dbr = wfGetDB(DB_SLAVE);
        $groupassignments = $dbr->select('scholasticgrading_groupassignment', '*');

        # Build the group assignments table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $content .= Html::element('caption', null, 'Group Assignments') . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', null,
            Html::element('th', null, 'group id') .
            Html::element('th', null, 'assignment id')
        ) . "\n";

        # Create a row for each group assignment
        foreach ( $groupassignments as $groupassignment ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $groupassignment->sgga_group_id) .
                Html::element('td', null, $groupassignment->sgga_assignment_id)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showGroupAssignments */


} /* end SpecialGrades */
