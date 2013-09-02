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
                $this->showUserScores(
                    $request->getVal('user', false)
                );
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
                    Linker::linkKnown($this->getTitle(), 'See student\'s view.', array(),
                        array('action' => 'viewuserscores', 'user' => $request->getVal('user', false)))) . "\n");
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
                        Linker::linkKnown($this->getTitle(), 'Manage assignments', array(),
                            array('action' => 'assignments'))) . "\n");

                    $page->addHTML(Html::rawElement('p', null,
                        Linker::linkKnown($this->getTitle(), 'Manage groups', array(),
                            array('action' => 'groups'))) . "\n");

                    $this->showGradeGrid();
                    //$this->showAssignments();
                    //$this->showEvaluations();
                    //$this->showAdjustments();
                    //$this->showGroups();
                    //$this->showGroupUsers();
                    //$this->showGroupAssignments();
                    //$this->showUsers();
                } else {
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
                $users = $dbr->select('user', '*', array('user_id' => $request->getVal('user', false)));
                if ( $users->numRows() > 0 ) {

                    # The user exists
                    $user = $users->next();
                    return 'Edit scores for ' . $this->getUserDisplayName($user->user_id);

                } else {

                    # The user does not exist
                    return $this->msg('grades')->plain();

                }

                break;

            case 'editassignmentscores':

                # Check whether assignment exists
                $dbr = wfGetDB(DB_SLAVE);
                $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $request->getVal('id', false)));
                if ( $assignments->numRows() > 0 ) {

                    # The assignment exists
                    $assignment = $assignments->next();
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
            $users = $dbr->select('user', '*', array('user_id' => $user_id));
            if ( $users->numRows() === 0 ) {

                # The user does not exist
                return false;

            } else {

                # The user exists
                $user = $users->next();

                # Determine if the user has a real name
                if ( $user->user_real_name ) {

                    # Return the user real name with user name
                    return $user->user_real_name . ' (' . $user->user_name . ')';

                } else {

                    # Return user name only
                    return $user->user_name;

                }

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

                    $this->writeAssignment(
                        $assignmentParams[$key]['assignment-id'],
                        $assignmentParams[$key]['assignment-title'],
                        $assignmentParams[$key]['assignment-value'],
                        $assignmentParams[$key]['assignment-enabled'],
                        $assignmentParams[$key]['assignment-date'],
                        $assignmentParams[$key]['assignment-group'],
                        true
                    );
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

                    $this->writeEvaluation(
                        $evaluationParams[$key]['evaluation-user'],
                        $evaluationParams[$key]['evaluation-assignment'],
                        $evaluationParams[$key]['evaluation-score'],
                        $evaluationParams[$key]['evaluation-enabled'],
                        $evaluationParams[$key]['evaluation-date'],
                        $evaluationParams[$key]['evaluation-comment'],
                        true
                    );
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

                    $this->writeAdjustment(
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

                # Check whether a delete button was pressed
                if ( $request->getVal('delete-group-' . $key, false) ) {

                    $this->writeGroup(
                        $groupParams[$key]['group-id'],
                        $groupParams[$key]['group-title'],
                        $groupParams[$key]['group-enabled'],
                        true
                    );
                    return;

                }

            }

        }

        # A delete button was not pressed, so write all changes

        if ( $assignmentParams ) {

            # Make database changes for each assignment in assignment-params
            foreach ( $assignmentParams as $assignment ) {

                $this->writeAssignment(
                    $assignment['assignment-id'],
                    $assignment['assignment-title'],
                    $assignment['assignment-value'],
                    $assignment['assignment-enabled'],
                    $assignment['assignment-date'],
                    $assignment['assignment-group'],
                    false
                );

            }

        }

        if ( $evaluationParams ) {

            # Make database changes for each evaluation in evaluation-params
            foreach ( $evaluationParams as $evaluation ) {
                
                $this->writeEvaluation(
                    $evaluation['evaluation-user'],
                    $evaluation['evaluation-assignment'],
                    $evaluation['evaluation-score'],
                    $evaluation['evaluation-enabled'],
                    $evaluation['evaluation-date'],
                    $evaluation['evaluation-comment'],
                    false
                );

            }

        }

        if ( $adjustmentParams ) {

            # Make database changes for each adjustment in adjustment-params
            foreach ( $adjustmentParams as $adjustment ) {

                $this->writeAdjustment(
                    $adjustment['adjustment-id'],
                    $adjustment['adjustment-user'],
                    $adjustment['adjustment-title'],
                    $adjustment['adjustment-value'],
                    $adjustment['adjustment-score'],
                    $adjustment['adjustment-enabled'],
                    $adjustment['adjustment-date'],
                    $adjustment['adjustment-comment'],
                    false
                );

            }

        }

        if ( $groupParams ) {

            # Make database changes for each group in group-params
            foreach ( $groupParams as $group ) {

                $this->writeGroup(
                    $group['group-id'],
                    $group['group-title'],
                    $group['group-enabled'],
                    false
                );

            }

        }

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
     */

    function writeAssignment ( $assignmentID = false, $assignmentTitle = false, $assignmentValue = false, $assignmentEnabled = false, $assignmentDate = false, $assignmentGroups = false, $delete = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

        # Validate/sanitize assignment parameters
        $assignmentID          = filter_var($assignmentID, FILTER_VALIDATE_INT);
        $assignmentTitle       = filter_var($assignmentTitle, FILTER_CALLBACK, array('options' => array($this, 'validateTitle')));
        $assignmentValue       = filter_var($assignmentValue, FILTER_VALIDATE_FLOAT);
        if ( !is_bool($assignmentEnabled) ) {
            $assignmentEnabled = filter_var($assignmentEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        $assignmentDate        = filter_var($assignmentDate, FILTER_CALLBACK, array('options' => array($this, 'validateDate')));
        if ( $assignmentTitle === false ) {
            $page->addWikiText('Invalid title for assignment (may not be empty).');
            return;
        }
        if ( $assignmentValue === false ) {
            $page->addWikiText('Invalid value for assignment (must be a float).');
            return;
        }
        if ( !is_bool($assignmentEnabled) ) {
            $page->addWikiText('Invalid enabled status for assignment (must be a boolean).');
            return;
        }
        if ( $assignmentDate === false ) {
            $page->addWikiText('Invalid date for assignment (must have form YYYY-MM-DD).');
            return;
        }
        if ( !is_array($assignmentGroups) ) {
            $page->addWikiText('Invalid group membership array for assignment (must be an array).');
            return;
        }

        # Check whether assignment exists
        $assignments = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $assignmentID));
        if ( $assignments->numRows() > 0 ) {

            # The assignment exists
            $assignment = $assignments->next();

            if ( !$delete ) {

                # Edit the existing assignment
                $dbw->update('scholasticgrading_assignment', array(
                    'sga_title'   => $assignmentTitle,
                    'sga_value'   => $assignmentValue,
                    'sga_enabled' => $assignmentEnabled,
                    'sga_date'    => $assignmentDate,
                ), array('sga_id' => $assignmentID));
                $affectedRows = $dbw->affectedRows();

                # Edit the assignment's group memberships
                foreach ( $assignmentGroups as $groupID => $isMember ) {
                    $memberships = $dbw->select('scholasticgrading_groupassignment', '*', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignmentID));
                    if ( $memberships->numRows() > 0 && !$isMember ) {
                        $dbw->delete('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignmentID));
                        $affectedRows += $dbw->affectedRows();
                    } elseif ( $memberships->numRows() == 0 && $isMember ) {
                        $dbw->insert('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignmentID));
                        $affectedRows += $dbw->affectedRows();
                    }
                }

                # Report success and create a new log entry
                if ( $affectedRows === 0 ) {

                    $page->addWikiText('Database unchanged.');

                } else {

                    $page->addWikiText('\'\'\'"' . $assignmentTitle . '" (' . $assignmentDate . ') updated!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('editAssignment', $this->getTitle(), null, array($assignmentTitle, $assignmentDate));

                }

            } else {

                # Prepare to delete the existing assignment

                if ( !$request->getVal('confirm-delete') ) {

                    # Ask for confirmation of delete
                    $page->addWikiText('Are you sure you want to delete "' . $assignment->sga_title . '" (' . $assignment->sga_date . ')?');

                    # List all evaluations that will be deleted with the assignment
                    $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_assignment_id' => $assignmentID));
                    if ( $evaluations->numRows() > 0 ) {
                        $content = '';
                        $content .= Html::element('p', null, 'Evaluations for the following users will be deleted:') . "\n";
                        $content .= Html::openElement('ul', null);
                        foreach ( $evaluations as $evaluation ) {
                            $user = $dbw->select('user', '*', array('user_id' => $evaluation->sge_user_id))->next();
                            $content .= Html::rawElement('li', null, $this->getUserDisplayName($user->user_id)) . "\n";
                        }
                        $content .= Html::closeElement('ul') . "\n";
                        $page->addHtml($content);
                    }

                    # Provide a delete button
                    $page->addHtml(Html::rawElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        ),
                        Xml::submitButton('Delete assignment', array('name' => 'delete-assignment-0')) .
                        Html::hidden('confirm-delete', true) .
                        Html::hidden('assignment-params[0][assignment-id]',      $assignmentID) .
                        Html::hidden('assignment-params[0][assignment-title]',   $assignmentTitle) .
                        Html::hidden('assignment-params[0][assignment-value]',   $assignmentValue) .
                        Html::hidden('assignment-params[0][assignment-enabled]', $assignmentEnabled ? 1 : 0) .
                        Html::hidden('assignment-params[0][assignment-date]',    $assignmentDate) .
                        Html::hidden('wpEditToken', $this->getUser()->getEditToken())
                    ));

                } else {

                    # Delete is confirmed so delete the existing assignment
                    $dbw->delete('scholasticgrading_assignment', array('sga_id' => $assignmentID));

                    # Report success and create a new log entry
                    if ( $dbw->affectedRows() === 0 ) {

                        $page->addWikiText('Database unchanged.');

                    } else {

                        $page->addWikiText('\'\'\'"' . $assignment->sga_title . '" (' . $assignment->sga_date . ') deleted!\'\'\'');

                        $log = new LogPage('grades', false);
                        $log->addEntry('deleteAssignment', $this->getTitle(), null, array($assignment->sga_title, $assignment->sga_date));

                    }

                }

            }

        } else {

            # The assignment does not exist

            # Create a new assignment
            $dbw->insert('scholasticgrading_assignment', array(
                'sga_title'   => $assignmentTitle,
                'sga_value'   => $assignmentValue,
                'sga_enabled' => $assignmentEnabled,
                'sga_date'    => $assignmentDate,
            ));
            $affectedRows = $dbw->affectedRows();

            # Attempt to get the id for the newly created assignment
            $maxAssignmentID = $dbw->select('scholasticgrading_assignment', array('maxid' => 'MAX(sga_id)'))->next()->maxid;
            $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $maxAssignmentID))->next();
            if ( $assignment->sga_title != $assignmentTitle || $assignment->sga_value != $assignmentValue || $assignment->sga_enabled != $assignmentEnabled || $assignment->sga_date != $assignmentDate ) {

                # The query result does not match the new assignment
                $page->addWikiText('Unable to retrieve id of new assignment. Groups were not assigned.');
                return;

            }

            # Create the assignment's group memberships
            foreach ( $assignmentGroups as $groupID => $isMember ) {

                if ( $isMember ) {

                    $dbw->insert('scholasticgrading_groupassignment', array('sgga_group_id' => $groupID, 'sgga_assignment_id' => $assignment->sga_id));
                    $affectedRows += $dbw->affectedRows();

                }

            }

            # Report success and create a new log entry
            if ( $affectedRows === 0 ) {

                $page->addWikiText('Database unchanged.');

            } else {

                $page->addWikiText('\'\'\'"' . $assignmentTitle . '" (' . $assignmentDate . ') added!\'\'\'');

                $log = new LogPage('grades', false);
                $log->addEntry('addAssignment', $this->getTitle(), null, array($assignmentTitle, $assignmentDate));

            }

        }

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
     */

    function writeEvaluation ( $evaluationUser = false, $evaluationAssignment = false, $evaluationScore = false, $evaluationEnabled = false, $evaluationDate = false, $evaluationComment = false, $delete = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

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
            $page->addWikiText('Invalid user id for evaluation (must be an integer).');
            return;
        }
        if ( $evaluationAssignment === false ) {
            $page->addWikiText('Invalid assignment id for evaluation (must be an integer).');
            return;
        }
        if ( $evaluationScore === false ) {
            $page->addWikiText('Invalid score for evaluation (must be a float).');
            return;
        }
        if ( !is_bool($evaluationEnabled) ) {
            $page->addWikiText('Invalid enabled status for evaluation (must be a boolean).');
            return;
        }
        if ( $evaluationDate === false ) {
            $page->addWikiText('Invalid date for evaluation (must have form YYYY-MM-DD).');
            return;
        }

        # Check whether user and assignment exist
        $users = $dbw->select('user', '*', array('user_id' => $evaluationUser));
        $assignments = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment));
        if ( $users->numRows() === 0 || $assignments->numRows() === 0 ) {

            # Either the user or assignment does not exist
            $page->addWikiText('Either user (id=' . $evaluationUser . ') or assignment (id=' . $evaluationAssignment . ') does not exist.');
            return;

        } else {

            # The user and assignment both exist
            $user = $users->next();
            $assignment = $assignments->next();

            # Check whether evaluation exists
            $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));
            if ( $evaluations->numRows() > 0 ) {

                # The evaluation exists
                $evaluation = $evaluations->next();

                if ( !$delete ) {

                    # Edit the existing evaluation
                    $dbw->update('scholasticgrading_evaluation', array(
                        'sge_score'   => $evaluationScore,
                        'sge_enabled' => $evaluationEnabled,
                        'sge_date'    => $evaluationDate,
                        'sge_comment' => $evaluationComment,
                    ), array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));

                    # Report success and create a new log entry
                    if ( $dbw->affectedRows() === 0 ) {

                        $page->addWikiText('Database unchanged.');

                    } else {

                        $page->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ') updated!\'\'\'');

                        $log = new LogPage('grades', false);
                        $log->addEntry('editEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignment->sga_date));

                    }

                } else {

                    # Prepare to delete the existing evaluation

                    if ( !$request->getVal('confirm-delete') ) {

                        # Ask for confirmation of delete
                        $page->addWikiText('Are you sure you want to delete the evaluation for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ')?');

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

                    } else {

                        # Delete is confirmed so delete the existing evaluation
                        $dbw->delete('scholasticgrading_evaluation', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));

                        # Report success and create a new log entry
                        if ( $dbw->affectedRows() === 0 ) {

                            $page->addWikiText('Database unchanged.');

                        } else {

                            $page->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ') deleted!\'\'\'');

                            $log = new LogPage('grades', false);
                            $log->addEntry('deleteEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignment->sga_date));

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

                # Report success and create a new log entry
                if ( $dbw->affectedRows() === 0 ) {

                    $page->addWikiText('Database unchanged.');

                } else {

                    $page->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ') added!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('addEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignment->sga_date));

                }

            }

        }

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
     */

    function writeAdjustment ( $adjustmentID = false, $adjustmentUser = false, $adjustmentTitle = false, $adjustmentValue = false, $adjustmentScore = false, $adjustmentEnabled = false, $adjustmentDate = false, $adjustmentComment = false, $delete = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

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
            $page->addWikiText('Invalid user id for adjustment (must be an integer).');
            return;
        }
        if ( $adjustmentTitle === false ) {
            $page->addWikiText('Invalid title for adjustment (may not be empty).');
            return;
        }
        if ( $adjustmentValue === false ) {
            $page->addWikiText('Invalid value for adjustment (must be a float).');
            return;
        }
        if ( $adjustmentScore === false ) {
            $page->addWikiText('Invalid score for adjustment (must be a float).');
            return;
        }
        if ( !is_bool($adjustmentEnabled) ) {
            $page->addWikiText('Invalid enabled status for adjustment (must be a boolean).');
            return;
        }
        if ( $adjustmentDate === false ) {
            $page->addWikiText('Invalid date for adjustment (must have form YYYY-MM-DD).');
            return;
        }

        # Check whether user exist
        $users = $dbw->select('user', '*', array('user_id' => $adjustmentUser));
        if ( $users->numRows() === 0 ) {

            # User does not exist
            $page->addWikiText('User (id=' . $adjustmentUser . ') does not exist.');
            return;

        } else {

            # The user exists
            $user = $users->next();

            # Check whether adjustment exists
            $adjustments = $dbw->select('scholasticgrading_adjustment', '*', array('sgadj_id' => $adjustmentID));
            if ( $adjustments->numRows() > 0 ) {

                # The adjustment exists
                $adjustment = $adjustments->next();

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

                    # Report success and create a new log entry
                    if ( $dbw->affectedRows() === 0 ) {

                        $page->addWikiText('Database unchanged.');

                    } else {

                        $page->addWikiText('\'\'\'Point adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustmentTitle . '" (' . $adjustmentDate . ') updated!\'\'\'');

                        $log = new LogPage('grades', false);
                        $log->addEntry('editAdjustment', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($adjustmentTitle, $adjustmentDate));

                    }

                } else {

                    # Prepare to delete the existing adjustment

                    if ( !$request->getVal('confirm-delete') ) {

                        # Ask for confirmation of delete
                        $page->addWikiText('Are you sure you want to delete the point adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustment->sgadj_title . '" (' . $adjustment->sgadj_date . ')?');

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

                    } else {

                        # Delete is confirmed so delete the existing adjustment
                        $dbw->delete('scholasticgrading_adjustment', array('sgadj_id' => $adjustmentID));

                        # Report success and create a new log entry
                        if ( $dbw->affectedRows() === 0 ) {

                            $page->addWikiText('Database unchanged.');

                        } else {

                            $page->addWikiText('\'\'\'Point adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustmentTitle . '" (' . $adjustmentDate . ') deleted!\'\'\'');

                            $log = new LogPage('grades', false);
                            $log->addEntry('deleteAdjustment', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($adjustmentTitle, $adjustmentDate));

                        }

                    }

                }

            } else {

                # The adjustment does not exist

                # Create a new adjustment
                $dbw->insert('scholasticgrading_adjustment', array(
                    'sgadj_user_id' => $adjustmentUser,
                    'sgadj_title'   => $adjustmentTitle,
                    'sgadj_value'   => $adjustmentValue,
                    'sgadj_score'   => $adjustmentScore,
                    'sgadj_enabled' => $adjustmentEnabled,
                    'sgadj_date'    => $adjustmentDate,
                    'sgadj_comment' => $adjustmentComment,
                ));

                # Report success and create a new log entry
                if ( $dbw->affectedRows() === 0 ) {

                    $page->addWikiText('Database unchanged.');

                } else {

                    $page->addWikiText('\'\'\'Point adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustmentTitle . '" (' . $adjustmentDate . ') added!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('addAdjustment', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($adjustmentTitle, $adjustmentDate));

                }

            }

        }

    } /* end writeAdjustment */


    /**
     * Execute group creation/modification/deletion
     *
     * Creates, modifies, or deletes a group by directly modifying
     * the database. If the (id) key provided corresponds to an
     * existing group, the function will modify or delete that
     * group depending on whether the delete flag is set.
     * Otherwise, the function will create a new group.
     * Parameters are initially validated and sanitized.
     *
     * @param int|bool    $groupID the id of a group
     * @param int|bool    $groupTitle the title of a group
     * @param int|bool    $groupEnabled the enabled status of a group
     * @param bool        $delete whether to delete the group or not
     */

    function writeGroup ( $groupID = false, $groupTitle = false, $groupEnabled = false, $delete = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

        # Validate/sanitize group parameters
        $groupID          = filter_var($groupID, FILTER_VALIDATE_INT);
        $groupTitle       = filter_var($groupTitle, FILTER_CALLBACK, array('options' => array($this, 'validateTitle')));
        if ( !is_bool($groupEnabled) ) {
            $groupEnabled = filter_var($groupEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ( $groupTitle === false ) {
            $page->addWikiText('Invalid title for group (may not be empty).');
            return;
        }
        if ( !is_bool($groupEnabled) ) {
            $page->addWikiText('Invalid enabled status for group (must be a boolean).');
            return;
        }

        # Check whether group exists
        $groups = $dbw->select('scholasticgrading_group', '*', array('sgg_id' => $groupID));
        if ( $groups->numRows() > 0 ) {

            # The group exists
            $group = $groups->next();

            if ( !$delete ) {

                # Edit the existing group
                $dbw->update('scholasticgrading_group', array(
                    'sgg_title'   => $groupTitle,
                    'sgg_enabled' => $groupEnabled,
                ), array('sgg_id' => $groupID));

                # Report success and create a new log entry
                if ( $dbw->affectedRows() === 0 ) {

                    $page->addWikiText('Database unchanged.');

                } else {

                    $page->addWikiText('\'\'\'"' . $groupTitle . '" updated!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('editGroup', $this->getTitle(), null, array($groupTitle));

                }

            } else {

                # Prepare to delete the existing group

                if ( !$request->getVal('confirm-delete') ) {

                    # Ask for confirmation of delete
                    $page->addWikiText('Are you sure you want to delete "' . $group->sgg_title . '"?');

                    # Provide a delete button
                    $page->addHtml(Html::rawElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        ),
                        Xml::submitButton('Delete group', array('name' => 'delete-group-0')) .
                        Html::hidden('confirm-delete', true) .
                        Html::hidden('group-params[0][group-id]',      $groupID) .
                        Html::hidden('group-params[0][group-title]',   $groupTitle) .
                        Html::hidden('group-params[0][group-enabled]', $groupEnabled ? 1 : 0) .
                        Html::hidden('wpEditToken', $this->getUser()->getEditToken())
                    ));

                } else {

                    # Delete is confirmed so delete the existing group
                    $dbw->delete('scholasticgrading_group', array('sgg_id' => $groupID));

                    # Report success and create a new log entry
                    if ( $dbw->affectedRows() === 0 ) {

                        $page->addWikiText('Database unchanged.');

                    } else {

                        $page->addWikiText('\'\'\'"' . $group->sgg_title . '" deleted!\'\'\'');

                        $log = new LogPage('grades', false);
                        $log->addEntry('deleteGroup', $this->getTitle(), null, array($group->sgg_title));

                    }

                }

            }

        } else {

            # The group does not exist

            # Create a new group
            $dbw->insert('scholasticgrading_group', array(
                'sgg_title'   => $groupTitle,
                'sgg_enabled' => $groupEnabled,
            ));

            # Report success and create a new log entry
            if ( $dbw->affectedRows() === 0 ) {

                $page->addWikiText('Database unchanged.');

            } else {

                $page->addWikiText('\'\'\'"' . $groupTitle . '" added!\'\'\'');

                $log = new LogPage('grades', false);
                $log->addEntry('addGroup', $this->getTitle(), null, array($groupTitle));

            }

        }

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
            $dbr = wfGetDB(DB_SLAVE);
            $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $id));
            if ( $assignments->numRows() === 0 ) {

                # The assignment does not exist
                $page->addWikiText('Assignment (id=' . $id . ') does not exist.');
                return;

            } else {

                # The assignment exists
                $assignment = $assignments->next();

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
                        Html::rawElement('td', null, Xml::input('assignment-params[0][assignment-value]', 20, $assignmentValueDefault, array('id' => 'assignment-value')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'assignment-enabled')) .
                        Html::rawElement('td', null, Xml::check('assignment-params[0][assignment-enabled]', $assignmentEnabledDefault, array('id' => 'assignment-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'assignment-date')) .
                        Html::rawElement('td', null, Xml::input('assignment-params[0][assignment-date]', 20, $assignmentDateDefault, array('id' => 'assignment-date', 'class' => 'sg-date-input')))
                    )
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
        $users = $dbr->select('user', '*', array('user_id' => $user_id));
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $assignment_id));
        if ( $users->numRows() === 0 || $assignments->numRows() === 0 ) {

            # Either the user or assignment does not exist
            $page->addWikiText('Either user (id=' . $user_id . ') or assignment (id=' . $assignment_id . ') does not exist.');
            return;

        } else {

            # The user and assignment both exist
            $user = $users->next();
            $assignment = $assignments->next();

            # Check whether evaluation exists
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment_id));
            if ( $evaluations->numRows() === 0 ) {

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
                $evaluation = $evaluations->next();

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
                        Html::rawElement('td', null, Xml::input('evaluation-params[0][evaluation-score]', 20, $evaluationScoreDefault, array('id' => 'evaluation-score')) . ' out of ' . (float)$assignment->sga_value . ' point(s)')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled')) .
                        Html::rawElement('td', null, Xml::check('evaluation-params[0][evaluation-enabled]', $evaluationEnabledDefault, array('id' => 'evaluation-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date')) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[0][evaluation-date]', 20, $evaluationDateDefault, array('id' => 'evaluation-date', 'class' => 'sg-date-input')))
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
        $adjustments = $dbr->select('scholasticgrading_adjustment', '*', array('sgadj_id' => $id));
        if ( $adjustments->numRows() === 0 ) {

            # The adjustment does not exist

            # Check whether user exists
            $users = $dbr->select('user', '*', array('user_id' => $user_id));
            if ( $users->numRows() === 0 ) {

                # Neither the user nor the adjustment exist
                $page->addWikiText('Adjustment (id=' . $id . ') and user (id=' . $user_id . ') do not exist.');
                return;

            } else {

                # The user exists
                $user = $users->next();

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
            $adjustment = $adjustments->next();

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
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-score]', 20, $adjustmentScoreDefault, array('id' => 'adjustment-score')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Value:', 'adjustment-value')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-value]', 20, $adjustmentValueDefault, array('id' => 'adjustment-value')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'adjustment-enabled')) .
                        Html::rawElement('td', null, Xml::check('adjustment-params[0][adjustment-enabled]', $adjustmentEnabledDefault, array('id' => 'adjustment-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'adjustment-date')) .
                        Html::rawElement('td', null, Xml::input('adjustment-params[0][adjustment-date]', 20, $adjustmentDateDefault, array('id' => 'adjustment-date', 'class' => 'sg-date-input')))
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

        # Set default parameters for creating a new group
        $fieldsetTitle = 'Create a new group';
        $buttons = Xml::submitButton('Create group', array('name' => 'create-group'));
        $groupIdDefault = false;
        $groupTitleDefault = '';
        $groupEnabledDefault = true;

        if ( $id ) {

            # Check whether group exists
            $dbr = wfGetDB(DB_SLAVE);
            $groups = $dbr->select('scholasticgrading_group', '*', array('sgg_id' => $id));
            if ( $groups->numRows() === 0 ) {

                # The group does not exist
                $page->addWikiText('Group (id=' . $id . ') does not exist.');
                return;

            } else {

                # The group exists
                $group = $groups->next();

                # Use its values as default parameters
                $fieldsetTitle = 'Edit an existing group';
                $buttons = Xml::submitButton('Apply changes', array('name' => 'modify-group')) .
                    Xml::submitButton('Delete group', array('name' => 'delete-group-0'));
                $groupIdDefault = $id;
                $groupTitleDefault = $group->sgg_title;
                $groupEnabledDefault = $group->sgg_enabled;

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
        $users = $dbr->select('user', '*', array('user_id' => $user_id));
        if ( $users->numRows() === 0 ) {

            # The user does not exist
            $page->addWikiText('User (id=' . $user_id . ') does not exist.');
            return;

        } else {

            # The user exists
            $user = $users->next();

        }

        $scores = array();

        # Initialize the points earned, the ideal score,
        # and the course total points for this student
        $pointsEarned = 0;
        $pointsIdeal = 0;
        $pointsAllAssignments = 0;

        # Query for all enabled assignments and all adjustments
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_enabled' => true));
        $adjustments = $dbr->select('scholasticgrading_adjustment', '*', array('sgadj_user_id' => $user_id));

        # Store dates, titles, and ids for each enabled assignment
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
                $assignment = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $score['assignmentID']))->next();
                # Increment the course total points
                $pointsAllAssignments += $assignment->sga_value;

                # Check whether evaluation exists
                $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment->sga_id));
                if ( $evaluations->numRows() === 0 ) {

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
                    $evaluation = $evaluations->next();

                    # Use its values as default parameters
                    $evaluationDateDefault = $evaluation->sge_date;
                    $evaluationScoreDefault = (float)$evaluation->sge_score;
                    $evaluationCommentDefault = $evaluation->sge_comment;
                    $evaluationEnabledDefault = $evaluation->sge_enabled;
                    $evaluationDeleteButtonAttr = array('name' => 'delete-evaluation-' . $evaluationParamSetCounter);

                    if ( $evaluation->sge_enabled ) {

                        $evaluationRowClass = 'sg-userscoresformtable-row';

                        # Increment the points earned and the ideal score
                        $pointsEarned += $evaluation->sge_score;
                        $pointsIdeal  += $assignment->sga_value;

                    } else {

                        $evaluationRowClass = 'sg-userscoresformtable-row sg-userscoresformtable-disabled';

                    }

                }

                $content .= Html::rawElement('tr', array('class' => $evaluationRowClass),
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-date'), Xml::input('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-date]', 10, $evaluationDateDefault, array('class' => 'sg-date-input'))) .
                    Html::element('td', array('class' => 'sg-userscoresformtable-title'), $assignment->sga_title . ' (' . $assignment->sga_date . ')') .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-score'), Xml::input('evaluation-params[' . $evaluationParamSetCounter . '][evaluation-score]', 5, $evaluationScoreDefault)) .
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
                $adjustment = $dbr->select('scholasticgrading_adjustment', '*', array('sgadj_id' => $score['adjustmentID']))->next();

                if ( $adjustment->sgadj_enabled ) {

                    $adjustmentRowClass = 'sg-userscoresformtable-row';

                    # Increment the course total points
                    $pointsAllAssignments += $adjustment->sgadj_value;

                    # Increment the points earned and the ideal score
                    $pointsEarned += $adjustment->sgadj_score;
                    $pointsIdeal  += $adjustment->sgadj_value;

                } else {

                    $adjustmentRowClass = 'sg-userscoresformtable-row sg-userscoresformtable-disabled';

                }

                $content .= Html::rawElement('tr', array('class' => $adjustmentRowClass),
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-date'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-date]', 10, $adjustment->sgadj_date, array('class' => 'sg-date-input'))) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-title'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-title]', 50, $adjustment->sgadj_title)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-score'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-score]', 5, (float)$adjustment->sgadj_score)) .
                    Html::rawElement('td', array('class' => 'sg-userscoresformtable-value'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-value]', 5, (float)$adjustment->sgadj_value)) .
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
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-date'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-date]', 10, date('Y-m-d'), array('class' => 'sg-date-input'))) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-title'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-title]', 50, '')) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-score'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-score]', 5, '')) .
            Html::rawElement('td', array('class' => 'sg-userscoresformtable-value'), Xml::input('adjustment-params[' . $adjustmentParamSetCounter . '][adjustment-value]', 5, '0')) .
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
            Html::element('th', null, $pointsEarned) .
            Html::element('th', null, $pointsIdeal) .
            Html::element('th', null, 'Current grade: ' . round(100*$pointsEarned/$pointsIdeal , 2) . '%') .
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
                Html::element('img', array('src' => '/django/credit/racetrack/' . round($pointsEarned/$pointsAllAssignments, 3) . '/' . round($pointsIdeal/$pointsAllAssignments, 3) . '/racetrack.png'), '')
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
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $id));
        if ( $assignments->numRows() === 0 ) {

            # The assignment does not exist
            $page->addWikiText('Assignment (id=' . $id . ') does not exist.');
            return;

        } else {

            # The assignment exists
            $assignment = $assignments->next();

        }

        # Query for all users
        $users = $dbr->select('user', '*');

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
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $id));
            if ( $evaluations->numRows() === 0 ) {

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
                $evaluation = $evaluations->next();

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
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-date'), Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-date]', 10, $evaluationDateDefault, array('class' => 'sg-date-input'))) .
                Html::rawElement('td', array('class' => 'sg-assignmentscoresformtable-score'), Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-score]', 5, $evaluationScoreDefault)) .
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

        # Query for all assignments and groups
        $dbr = wfGetDB(DB_SLAVE);
        $assignments = $dbr->select('scholasticgrading_assignment', '*', '', __METHOD__,
            array('ORDER BY' => array('ISNULL(sga_date)', 'sga_date', 'sga_title')));
        $groups = $dbr->select('scholasticgrading_group', '*', '', __METHOD__,
            array('ORDER BY' => array('sgg_title')));

        # Build the assignment table
        $content = '';
        $content .= Html::openElement('form', array(
            'method' => 'post',
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
        ));
        $content .= Html::openElement('table', array('class' => 'wikitable sg-manageassignmentstable')) . "\n";

        # Create a column header for each field
        $content .= Html::openElement('tr', array('id' => 'sg-manageassignmentstable-header')) .
            Html::element('th', null, 'Date') .
            Html::element('th', null, 'Title') .
            Html::element('th', null, 'Value') .
            Html::element('th', null, 'Enabled');
        foreach ( $groups as $group )
            $content .= Html::element('th', null, $group->sgg_title);
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
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-date'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-date]', 10, $assignment->sga_date, array('class' => 'sg-date-input'))) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-title'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-title]', 50, $assignment->sga_title)) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-value'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-value]', 5, (float)$assignment->sga_value)) .
                Html::rawElement('td', array('class' => 'sg-manageassignmentstable-enabled'), Xml::check('assignment-params[' . $paramSetCounter . '][assignment-enabled]', $assignment->sga_enabled));

            # Create a cell for each group
            foreach ( $groups as $group ) {

                $groupassignments = $dbr->select('scholasticgrading_groupassignment', '*',
                    array('sgga_group_id' => $group->sgg_id, 'sgga_assignment_id' => $assignment->sga_id));
                if ( $groupassignments->numRows() > 0 ) {

                    # The assignment is a member of the group
                    $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-group'),
                        Html::hidden('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', 0) .
                        Xml::check('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', true)
                    );

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

        # Create a row for a new assignment
        $content .= Html::openElement('tr', array('class' => 'sg-manageassignmentstable-row')) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-date'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-date]', 10, date('Y-m-d'), array('class' => 'sg-date-input'))) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-title'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-title]', 50, '')) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-value'), Xml::input('assignment-params[' . $paramSetCounter . '][assignment-value]', 5, '')) .
            Html::rawElement('td', array('class' => 'sg-manageassignmentstable-enabled'), Xml::check('assignment-params[' . $paramSetCounter . '][assignment-enabled]', true));

        foreach ( $groups as $group ) {
            $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-group'),
                Html::hidden('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', 0) .
                Xml::check('assignment-params[' . $paramSetCounter . '][assignment-group][' . $group->sgg_id . ']', false)
            );
        }

        $content .= Html::rawElement('td', array('class' => 'sg-manageassignmentstable-delete'), Xml::submitButton('Delete', array('name' => 'delete-assignment-' . $paramSetCounter, 'disabled')));
        $content .= Html::closeElement('tr');

        $content .= Html::hidden('assignment-params[' . $paramSetCounter . '][assignment-id]', false);
        $content .= "\n";

        $paramSetCounter += 1;

        $content .= Html::closeElement('table') . "\n";
        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-assignment'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        $page->addHTML($content);

    } /* end showAllAssignments */


    /**
     * Display a table of all groups
     *
     * Generates a table of groups with controls
     * for modifying and deleting groups
     */

    function showAllGroups () {

        $page = $this->getOutput();

        # Query for all groups
        $dbr = wfGetDB(DB_SLAVE);
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

        $content .= Html::hidden('group-params[' . $paramSetCounter . '][group-id]', false);
        $content .= "\n";

        $paramSetCounter += 1;

        $content .= Html::closeElement('table') . "\n";
        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-group'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        $page->addHTML($content);

    } /* end showAllGroups */


    /**
     * Display a table of assignments, students, and evaluations
     *
     * Generates a table of evaluations, where columns represent
     * students and rows represent assignments.
     */

    function showGradeGrid () {

        $page = $this->getOutput();
        $pointsEarned = array();
        $pointsIdeal = array();

        # Query for all users and all enabled assignments
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*');
        $assignments = $dbr->select('scholasticgrading_assignment', '*',
            array('sga_enabled' => true), __METHOD__, array('ORDER BY' => array('ISNULL(sga_date)', 'sga_date', 'sga_title')));

        # Build the grade grid
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-gradegrid')) . "\n";
        $content .= Html::element('caption', null, 'Grades') . "\n";

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

            # Initialize the points earned and the ideal score for this student
            $pointsEarned[$user->user_name] = 0;
            $pointsIdeal[$user->user_name] = 0;
        }
        $content .= Html::closeElement('tr') . "\n";

        # Create a row for each enabled assignment
        foreach ( $assignments as $assignment ) {

            $content .= Html::openElement('tr', array('class' => 'sg-gradegrid-row'));
            $content .= Html::element('td', array('class' => 'sg-gradegrid-date', 'data-sort-value' => $assignment->sga_date ? $assignment->sga_date : '9999'), $assignment->sga_date ? date_format(date_create($assignment->sga_date), 'D m/d') : '');
            $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-assignment'),
                Linker::linkKnown($this->getTitle(), $assignment->sga_title, array(),
                    array('action' => 'editassignmentscores', 'id' => $assignment->sga_id)));

            # Create a cell for each user
            foreach ( $users as $user ) {

                $evaluations = $dbr->select('scholasticgrading_evaluation', '*',
                    array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $assignment->sga_id));
                if ( $evaluations->numRows() > 0 ) {

                    # An evaluation exists for this (user,assignment) combination
                    $evaluation = $evaluations->next();
                    if ( $evaluation->sge_enabled ) {

                        # The evaluation is enabled
                        if ( $assignment->sga_value == 0 ) {

                            # The assignment is extra credit
                            $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-cell'), 
                                Linker::linkKnown($this->getTitle(), '+' . (float)$evaluation->sge_score, array(),
                                    array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                        } else {

                            # The assignment is not extra credit
                            $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-cell'), 
                                Linker::linkKnown($this->getTitle(), $evaluation->sge_score / $assignment->sga_value * 100 . '%', array(),
                                    array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                        }

                        # Increment the points earned and the ideal score for this student
                        $pointsEarned[$user->user_name] += $evaluation->sge_score;
                        $pointsIdeal[$user->user_name] += $assignment->sga_value;

                    } else {

                        # The evaluation is disabled
                        $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-cell'), 
                            Linker::linkKnown($this->getTitle(), '**', array(),
                                array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                    }

                } else {

                    # An evaluation does not exist for this (user,assignment) combination
                    $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-cell'), 
                        Linker::linkKnown($this->getTitle(), '--', array(),
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

                # Increment the points earned and the ideal score for this student
                $pointsEarned[$user->user_name] += $adjustment->sgadj_score;
                $pointsIdeal[$user->user_name] += $adjustment->sgadj_value;

            }

            $content .= Html::rawElement('td', array('class' => 'sg-gradegrid-cell'),
                $adjustmentScoreSum . ' / ' . $adjustmentValueSum);

        }

        $content .= Html::closeElement('tr') . "\n";

        # Report point totals for each student
        $content .= Html::openElement('tr', array('id' => 'sg-gradegrid-footer'));
        $content .= Html::element('th', null, '') . Html::element('th', null, '');
        foreach ( $users as $user ) {
            $content .= Html::element('th', null,
                $pointsEarned[$user->user_name] . ' / ' . $pointsIdeal[$user->user_name]
            );
        }
        $content .= Html::closeElement('tr') . "\n";

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showGradeGrid */


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
        $users = $dbr->select('user', '*', array('user_id' => $user_id));
        if ( $users->numRows() === 0 ) {

            # The user does not exist
            $page->addWikiText('User (id=' . $user_id . ') does not exist.');
            return;

        } else {

            # The user exists
            $user = $users->next();

        }

        $scores = array();

        # Initialize the points earned, the ideal score,
        # and the course total points for this student
        $pointsEarned = 0;
        $pointsIdeal = 0;
        $pointsAllAssignments = 0;

        # Query for all enabled assignments and all enabled adjustments
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_enabled' => true));
        $adjustments = $dbr->select('scholasticgrading_adjustment', '*', array('sgadj_user_id' => $user_id, 'sgadj_enabled' => true));

        # Store scores for each enabled assignment
        foreach ( $assignments as $assignment ) {

            # Increment the course total points
            $pointsAllAssignments += $assignment->sga_value;

            # Check whether evaluation exists and is enabled
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment->sga_id, 'sge_enabled' => true));
            if ( $evaluations->numRows() > 0 ) {

                # The evaluation exists and is enabled
                $evaluation = $evaluations->next();

                # Increment the points earned and the ideal score
                $pointsEarned += $evaluation->sge_score;
                $pointsIdeal  += $assignment->sga_value;

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

            # Increment the course total points
            $pointsAllAssignments += $adjustment->sgadj_value;

            # Increment the points earned and the ideal score
            $pointsEarned += $adjustment->sgadj_score;
            $pointsIdeal  += $adjustment->sgadj_value;

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
            Html::element('th', null, $pointsEarned) .
            Html::element('th', null, $pointsIdeal) .
            Html::element('th', null, 'Current grade: ' . round(100*$pointsEarned/$pointsIdeal , 2) . '%')
        ) . "\n";
        $content .= Html::closeElement('table') . "\n";

        # Insert the racetrack image at the top of the page
        $page->addHTML(Html::rawElement('div', array('class' => 'racetrack'),
                Html::element('img', array('src' => '/django/credit/racetrack/' . round($pointsEarned/$pointsAllAssignments, 3) . '/' . round($pointsIdeal/$pointsAllAssignments, 3) . '/racetrack.png'), '')
            )) . "\n";

        $page->addHTML($content);

    } /* end showUserScores */


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
