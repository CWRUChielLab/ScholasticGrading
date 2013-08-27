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

            $page->addHTML(Html::element('p', null, 'Create, modify, and delete assignments.') . "\n");
            $page->addHTML(Html::rawElement('p', null,
                Linker::linkKnown($this->getTitle('editassignment'), 'Create a new assignment')) . "\n");
            $this->showAllAssignments();

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

        case 'edituserscores':

            if ( $this->canModify(true) ) {
                $this->showUserEvaluationForms(
                    $request->getVal('user', false)
                );
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

        case 'submitassignment':

            if ( $this->canModify(true) ) {
                if ( $request->wasPosted() && $this->getUser()->matchEditToken($request->getVal('wpEditToken')) ) {
                    $this->submitAssignments();
                } else {
                    # Prevent cross-site request forgeries
                    $page->addWikiMsg('sessionfailure');
                }
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'submitevaluation':

            if ( $this->canModify(true) ) {
                if ( $request->wasPosted() && $this->getUser()->matchEditToken($request->getVal('wpEditToken')) ) {
                    $this->submitEvaluations();
                } else {
                    # Prevent cross-site request forgeries
                    $page->addWikiMsg('sessionfailure');
                }
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'submitadjustment':

            if ( $this->canModify(true) ) {
                if ( $request->wasPosted() && $this->getUser()->matchEditToken($request->getVal('wpEditToken')) ) {
                    $this->submitAdjustments();
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
                        Linker::linkKnown($this->getTitle('assignments'), 'Manage assignments')) . "\n");

                    $this->showGradeTable();
                    //$this->showAssignments();
                    //$this->showEvaluations();
                    //$this->showUsers();
                } else {
                    $this->showUserEvaluations(
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

    public function allTablesExist () {

        $page = $this->getOutput();
        $allTablesExist = true;
        $dbr = wfGetDB(DB_SLAVE);

        if ( !$dbr->tableExists('scholasticgrading_assignment') ) {
            $page->addHTML(Html::element('p', null, 'Database table scholasticgrading_assignment does not exist.') . "\n");
            $allTablesExist = false;
        }

        if ( !$dbr->tableExists('scholasticgrading_evaluation') ) {
            $page->addHTML(Html::element('p', null, 'Database table scholasticgrading_evaluation does not exist.') . "\n");
            $allTablesExist = false;
        }

        if ( !$allTablesExist ) {
            $page->addHTML(Html::element('p', null, 'Run maintenance/update.php.') . "\n");
        }

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

    public function canModify ( $printErrors = false ) {

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
     * @param string title string to validate
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
     * YYYY-MM-DD. Otherwise, returns false.
     *
     * @param string date string to validate
     * @return sting|bool the date string or false
     */

    function validateDate ( $test_date ) {

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

    public function getUserDisplayName ( $user_id = false ) {

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
     * Process sets of assignment creation/modification/deletion requests
     *
     * Processes assignment forms. Does not directly modify the
     * database. Instead, each set of assignment parameters is sent
     * to the writeAssignment function one at a time.
     */

    public function submitAssignments () {

        $page = $this->getOutput();
        $request = $this->getRequest();

        $assignmentParams = $request->getArray('assignment-params', false);
        if ( $assignmentParams ) {

            # The assignment-params array is present

            # Make database changes for each assignment in assignment-params
            foreach ( $assignmentParams as $assignment ) {

                # Store each parameter for this assignment if it exists
                if ( array_key_exists('assignment-id', $assignment) ) {
                    $assignmentID = $assignment['assignment-id'];
                } else {
                    $assignmentID = false;
                }
                if ( array_key_exists('assignment-title', $assignment) ) {
                    $assignmentTitle = $assignment['assignment-title'];
                } else {
                    $assignmentTitle = false;
                }
                if ( array_key_exists('assignment-value', $assignment) ) {
                    $assignmentValue = $assignment['assignment-value'];
                } else {
                    $assignmentValue = false;
                }
                if ( array_key_exists('assignment-enabled', $assignment) ) {
                    $assignmentEnabled = $assignment['assignment-enabled'] ? 1 : 0;
                } else {
                    # Form posts omit checkboxes that are unchecked
                    $assignmentEnabled = false;
                }
                if ( array_key_exists('assignment-date', $assignment) ) {
                    $assignmentDate = $assignment['assignment-date'];
                } else {
                    $assignmentDate = false;
                }
                
                $this->writeAssignment(
                    $assignmentID,
                    $assignmentTitle,
                    $assignmentValue,
                    $assignmentEnabled,
                    $assignmentDate
                );

            }

        } else {

            # The assignment-params array is missing or invalid
            $page->addWikiText('Assignment parameter array is missing or invalid.');
            return;

        }

    } /* end submitAssignments */


    /**
     * Process sets of evaluation creation/modification/deletion requests
     *
     * Processes evaluation forms. Does not directly modify the
     * database. Instead, each set of evaluation parameters is sent
     * to the writeEvaluation function one at a time.
     */

    public function submitEvaluations () {

        $page = $this->getOutput();
        $request = $this->getRequest();

        $evaluationParams = $request->getArray('evaluation-params', false);
        if ( $evaluationParams ) {

            # The evaluation-params array is present

            # Make database changes for each evaluation in evaluation-params
            foreach ( $evaluationParams as $evaluation ) {

                # Store each parameter for this evaluation if it exists
                if ( array_key_exists('evaluation-user', $evaluation) ) {
                    $evaluationUser = $evaluation['evaluation-user'];
                } else {
                    $evaluationUser = false;
                }
                if ( array_key_exists('evaluation-assignment', $evaluation) ) {
                    $evaluationAssignment = $evaluation['evaluation-assignment'];
                } else {
                    $evaluationAssignment = false;
                }
                if ( array_key_exists('evaluation-score', $evaluation) ) {
                    $evaluationScore = $evaluation['evaluation-score'];
                } else {
                    $evaluationScore = false;
                }
                if ( array_key_exists('evaluation-enabled', $evaluation) ) {
                    $evaluationEnabled = $evaluation['evaluation-enabled'] ? 1 : 0;
                } else {
                    # Form posts omit checkboxes that are unchecked
                    $evaluationEnabled = false;
                }
                if ( array_key_exists('evaluation-date', $evaluation) ) {
                    $evaluationDate = $evaluation['evaluation-date'];
                } else {
                    $evaluationDate = false;
                }
                if ( array_key_exists('evaluation-comment', $evaluation) ) {
                    $evaluationComment = $evaluation['evaluation-comment'];
                } else {
                    $evaluationComment = false;
                }
                
                $this->writeEvaluation(
                    $evaluationUser,
                    $evaluationAssignment,
                    $evaluationScore,
                    $evaluationEnabled,
                    $evaluationDate,
                    $evaluationComment
                );

            }

        } else {

            # The evaluation-params array is missing or invalid
            $page->addWikiText('Evaluation parameter array is missing or invalid.');
            return;

        }

    } /* end submitEvaluations */


    /**
     * Process sets of adjustment creation/modification/deletion requests
     *
     * Processes adjustment forms. Does not directly modify the
     * database. Instead, each set of adjustment parameters is sent
     * to the writeAdjustment function one at a time.
     */

    public function submitAdjustments () {

        $page = $this->getOutput();
        $request = $this->getRequest();

        $adjustmentParams = $request->getArray('adjustment-params', false);
        if ( $adjustmentParams ) {

            # The adjustment-params array is present

            # Make database changes for each adjustment in adjustment-params
            foreach ( $adjustmentParams as $adjustment ) {

                # Store each parameter for this adjustment if it exists
                if ( array_key_exists('adjustment-id', $adjustment) ) {
                    $adjustmentID = $adjustment['adjustment-id'];
                } else {
                    $adjustmentID = false;
                }
                if ( array_key_exists('adjustment-user', $adjustment) ) {
                    $adjustmentUser = $adjustment['adjustment-user'];
                } else {
                    $adjustmentUser = false;
                }
                if ( array_key_exists('adjustment-title', $adjustment) ) {
                    $adjustmentTitle = $adjustment['adjustment-title'];
                } else {
                    $adjustmentTitle = false;
                }
                if ( array_key_exists('adjustment-value', $adjustment) ) {
                    $adjustmentValue = $adjustment['adjustment-value'];
                } else {
                    $adjustmentValue = false;
                }
                if ( array_key_exists('adjustment-score', $adjustment) ) {
                    $adjustmentScore = $adjustment['adjustment-score'];
                } else {
                    $adjustmentScore = false;
                }
                if ( array_key_exists('adjustment-enabled', $adjustment) ) {
                    $adjustmentEnabled = $adjustment['adjustment-enabled'] ? 1 : 0;
                } else {
                    # Form posts omit checkboxes that are unchecked
                    $adjustmentEnabled = false;
                }
                if ( array_key_exists('adjustment-date', $adjustment) ) {
                    $adjustmentDate = $adjustment['adjustment-date'];
                } else {
                    $adjustmentDate = false;
                }
                if ( array_key_exists('adjustment-comment', $adjustment) ) {
                    $adjustmentComment = $adjustment['adjustment-comment'];
                } else {
                    $adjustmentComment = false;
                }
                
                $this->writeAdjustment(
                    $adjustmentID,
                    $adjustmentUser,
                    $adjustmentTitle,
                    $adjustmentValue,
                    $adjustmentScore,
                    $adjustmentEnabled,
                    $adjustmentDate,
                    $adjustmentComment
                );

            }

        } else {

            # The adjustment-params array is missing or invalid
            $page->addWikiText('Adjustment parameter array is missing or invalid.');
            return;

        }

    } /* end submitAdjustments */


    /**
     * Execute assignment creation/modification/deletion
     *
     * Creates, modifies, or deletes an assignment by directly
     * modifying the database. If the (id) key provided corresponds
     * to an existing assignment, the function will modify or delete
     * that assignment depending on whether the delete-assignment
     * variable is set. Otherwise, the function will create a new
     * assignment. Parameters are initially validated and sanitized.
     *
     * @param int|bool    $assignmentID the id of an assignment
     * @param int|bool    $assignmentTitle the title of an assignment
     * @param float|bool  $assignmentValue the value of an assignment
     * @param int|bool    $assignmentEnabled the enabled status of an assignment
     * @param string|bool $assignmentDate the date of an assignment
     */

    public function writeAssignment ( $assignmentID = false, $assignmentTitle = false, $assignmentValue = false, $assignmentEnabled = false, $assignmentDate = false ) {

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

        # Check whether assignment exists
        $assignments = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $assignmentID));
        if ( $assignments->numRows() > 0 ) {

            # The assignment exists
            $assignment = $assignments->next();

            if ( !$request->getVal('delete-assignment') ) {

                # Edit the existing assignment
                $dbw->update('scholasticgrading_assignment', array(
                    'sga_title'   => $assignmentTitle,
                    'sga_value'   => $assignmentValue,
                    'sga_enabled' => $assignmentEnabled,
                    'sga_date'    => $assignmentDate,
                ), array('sga_id' => $assignmentID));

                # Report success and create a new log entry
                if ( $dbw->affectedRows() === 0 ) {

                    $page->addWikiText('Database unchanged.');

                } else {

                    $page->addWikiText('\'\'\'"' . $assignmentTitle . '" (' . $assignmentDate . ') updated!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('editAssignment', $this->getTitle(), null, array($assignmentTitle, $assignmentDate));

                }

            } else {

                # Prepare to delete the existing evaluation

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
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitassignment'))
                        ),
                        Xml::submitButton('Delete assignment', array('name' => 'delete-assignment')) .
                        Html::hidden('confirm-delete', true) .
                        Html::hidden('assignment-params[0][assignment-id]',      $assignmentID) .
                        Html::hidden('assignment-params[0][assignment-title]',   $assignmentTitle) .
                        Html::hidden('assignment-params[0][assignment-value]',   $assignmentValue) .
                        Html::hidden('assignment-params[0][assignment-enabled]', $assignmentEnabled) .
                        Html::hidden('assignment-params[0][assignment-date]',    $assignmentDate) .
                        Html::hidden('wpEditToken', $this->getUser()->getEditToken())
                    ));

                } else {

                    # Delete is confirmed so delete the existing evaluation
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

            # Report success and create a new log entry
            if ( $dbw->affectedRows() === 0 ) {

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
     * delete-evaluation variable is set. Otherwise, the function
     * will create a new evaluation as long as both the user and
     * assignment exist. Parameters are initially validated and
     * sanitized.
     *
     * @param int|bool    $evaluationUser the user id of an evaluation
     * @param int|bool    $evaluationAssignment the assignment id of an evaluation
     * @param float|bool  $evaluationScore the score of an evaluation
     * @param int|bool    $evaluationEnabled the enabled status of an evaluation
     * @param string|bool $evaluationDate the date of an evaluation
     * @param string|bool $evaluationComment the comment of an evaluation
     */

    public function writeEvaluation ( $evaluationUser = false, $evaluationAssignment = false, $evaluationScore = false, $evaluationEnabled = false, $evaluationDate = false, $evaluationComment = false ) {

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

            # Check whether evaluation exists
            $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));
            if ( $evaluations->numRows() > 0 ) {

                # The evaluation exists
                $evaluation = $evaluations->next();

                if ( !$request->getVal('delete-evaluation') ) {

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

                        $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                        $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();

                        $page->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ') updated!\'\'\'');

                        $log = new LogPage('grades', false);
                        $log->addEntry('editEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignment->sga_date));

                    }

                } else {

                    # Prepare to delete the existing evaluation

                    if ( !$request->getVal('confirm-delete') ) {

                        # Ask for confirmation of delete
                        $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                        $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();
                        $page->addWikiText('Are you sure you want to delete the evaluation for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignment->sga_date . ')?');

                        # Provide a delete button
                        $page->addHtml(Html::rawElement('form',
                            array(
                                'method' => 'post',
                                'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitevaluation'))
                            ),
                            Xml::submitButton('Delete evaluation', array('name' => 'delete-evaluation')) .
                            Html::hidden('confirm-delete', true) .
                            Html::hidden('evaluation-params[0][evaluation-user]',       $evaluationUser) .
                            Html::hidden('evaluation-params[0][evaluation-assignment]', $evaluationAssignment) .
                            Html::hidden('evaluation-params[0][evaluation-score]',      $evaluationScore) .
                            Html::hidden('evaluation-params[0][evaluation-enabled]',    $evaluationEnabled) .
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

                            $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                            $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();

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

                    $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                    $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();

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
     * that adjustment depending on whether the delete-adjustment
     * variable is set. Otherwise, the function will create a new
     * adjustment as long as the user exists. Parameters are
     * initially validated and sanitized.
     *
     * @param int|bool    $adjustmentID the id of an adjustment
     * @param int|bool    $adjustmentUser the user id of an adjustment
     * @param int|bool    $adjustmentTitle the title of an adjustment
     * @param float|bool  $adjustmentValue the value of an adjustment
     * @param float|bool  $adjustmentScore the score of an adjustment
     * @param int|bool    $adjustmentEnabled the enabled status of an adjustment
     * @param string|bool $adjustmentDate the date of an adjustment
     * @param string|bool $adjustmentComment the comment of an adjustment
     */

    public function writeAdjustment ( $adjustmentID = false, $adjustmentUser = false, $adjustmentTitle = false, $adjustmentValue = false, $adjustmentScore = false, $adjustmentEnabled = false, $adjustmentDate = false, $adjustmentComment = false ) {

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

            # Check whether adjustment exists
            $adjustments = $dbw->select('scholasticgrading_adjustment', '*', array('sgadj_id' => $adjustmentID));
            if ( $adjustments->numRows() > 0 ) {

                # The adjustment exists
                $adjustment = $adjustments->next();

                if ( !$request->getVal('delete-adjustment') ) {

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

                        $user = $dbw->select('user', '*', array('user_id' => $adjustmentUser))->next();

                        $page->addWikiText('\'\'\'Point adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustmentTitle . '" (' . $adjustmentDate . ') updated!\'\'\'');

                        $log = new LogPage('grades', false);
                        $log->addEntry('editAdjustment', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($adjustmentTitle, $adjustmentDate));

                    }

                } else {

                    # Prepare to delete the existing adjustment

                    if ( !$request->getVal('confirm-delete') ) {

                        # Ask for confirmation of delete
                        $user = $dbw->select('user', '*', array('user_id' => $adjustmentUser))->next();
                        $page->addWikiText('Are you sure you want to delete the adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustment->sgadj_title . '" (' . $adjustment->sgadj_date . ')?');

                        # Provide a delete button
                        $page->addHtml(Html::rawElement('form',
                            array(
                                'method' => 'post',
                                'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitadjustment'))
                            ),
                            Xml::submitButton('Delete adjustment', array('name' => 'delete-adjustment')) .
                            Html::hidden('confirm-delete', true) .
                            Html::hidden('adjustment-params[0][adjustment-id]',      $adjustmentID) .
                            Html::hidden('adjustment-params[0][adjustment-user]',    $adjustmentUser) .
                            Html::hidden('adjustment-params[0][adjustment-title]',   $adjustmentTitle) .
                            Html::hidden('adjustment-params[0][adjustment-value]',   $adjustmentValue) .
                            Html::hidden('adjustment-params[0][adjustment-score]',   $adjustmentScore) .
                            Html::hidden('adjustment-params[0][adjustment-enabled]', $adjustmentEnabled) .
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

                            $user = $dbw->select('user', '*', array('user_id' => $adjustmentUser))->next();

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

                    $user = $dbw->select('user', '*', array('user_id' => $adjustmentUser))->next();

                    $page->addWikiText('\'\'\'Point adjustment for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $adjustmentTitle . '" (' . $adjustmentDate . ') added!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('addAdjustment', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($adjustmentTitle, $adjustmentDate));

                }

            }

        }

    } /* end writeAdjustment */


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

    public function showAssignmentForm ( $id = false ) {

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
                    Xml::submitButton('Delete assignment', array('name' => 'delete-assignment'));
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
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitassignment'))
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

    public function showEvaluationForm ( $user_id = false, $assignment_id = false ) {

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
                    Xml::submitButton('Delete evaluation', array('name' => 'delete-evaluation'));
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
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitevaluation'))
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

    public function showAdjustmentForm ( $id = false, $user_id = false ) {

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
                Xml::submitButton('Delete adjustment', array('name' => 'delete-adjustment'));
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
                    'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitadjustment'))
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
     * Display all evaluation forms for a user
     *
     * Generates a page for creating and editing evaluations for
     * all enabled assignments for a single user.
     *
     * @param int|bool $user_id the user id
     */

    public function showUserEvaluationForms ( $user_id = false ) {

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

        # Initialize the points earned, the ideal score,
        # and the course total points for this student
        $pointsEarned = 0;
        $pointsIdeal = 0;
        $pointsAllAssignments = 0;

        # Query for all enabled assignments
        $assignments = $dbr->select('scholasticgrading_assignment', '*',
            array('sga_enabled' => true), __METHOD__, array('ORDER BY' => 'sga_date'));

        # Build the user scores page
        $content = '';
        $content .= Html::openElement('form', array(
            'method' => 'post',
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitevaluation'))
        ));

        # Create a form for each enabled assignment
        $paramSetCounter = 0;
        foreach ( $assignments as $assignment ) {

            # Increment the course total points
            $pointsAllAssignments += $assignment->sga_value;

            # Check whether evaluation exists
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment->sga_id));
            if ( $evaluations->numRows() === 0 ) {

                # The evaluation does not exist
                # Set default parameters for creating a new evaluation
                $evaluationScoreDefault = '';
                $evaluationEnabledDefault = true;
                $evaluationDateDefault = $assignment->sga_date;
                $evaluationCommentDefault = '';

            } else {

                # The evaluation exists
                $evaluation = $evaluations->next();

                # Use its values as default parameters
                $evaluationScoreDefault = (float)$evaluation->sge_score;
                $evaluationEnabledDefault = $evaluation->sge_enabled;
                $evaluationDateDefault = $evaluation->sge_date;
                $evaluationCommentDefault = $evaluation->sge_comment;

                # Increment the points earned and the ideal score
                $pointsEarned += $evaluation->sge_score;
                $pointsIdeal  += $assignment->sga_value;

            }

            # Build the evaluation form
            $content .= Html::rawElement('fieldset', null,
                Html::element('legend', null, $assignment->sga_title . ' (' . $assignment->sga_date . ')') .
                Html::rawElement('table', null,
#                    Html::rawElement('tr', null,
#                        Html::rawElement('td', null, Xml::label('Assignment:', 'evaluation-assignment')) .
#                        Html::rawElement('td', null, $assignment->sga_title . ' (' . $assignment->sga_date . ')')
#                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Score:', 'evaluation-score-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-score]', 20, $evaluationScoreDefault, array('id' => 'evaluation-score-' . $paramSetCounter)) . ' out of ' . (float)$assignment->sga_value . ' point(s)')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::check('evaluation-params[' . $paramSetCounter . '][evaluation-enabled]', $evaluationEnabledDefault, array('id' => 'evaluation-enabled-' . $paramSetCounter)))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-date]', 20, $evaluationDateDefault, array('id' => 'evaluation-date-' . $paramSetCounter, 'class' => 'sg-date-input')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Comment:', 'evaluation-comment-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-comment]', 20, $evaluationCommentDefault, array('id' => 'evaluation-comment-' . $paramSetCounter)))
                    )
                ) .
                Html::hidden('evaluation-params[' . $paramSetCounter . '][evaluation-user]', $user_id) .
                Html::hidden('evaluation-params[' . $paramSetCounter . '][evaluation-assignment]', $assignment->sga_id)
            );

            $paramSetCounter += 1;

        }

        $content .= Xml::submitButton('Apply changes', array('name' => 'modify-evaluation'));
        $content .= Html::hidden('wpEditToken', $this->getUser()->getEditToken());
        $content .= Html::closeElement('form') . "\n";
        $content .= Html::element('br') . "\n";

        # Insert the racetrack image at the top of the page
        $page->addHTML(Html::rawElement('div', array('class' => 'racetrack'),
                Html::element('img', array('src' => '/django/credit/racetrack/' . round($pointsEarned/$pointsAllAssignments, 3) . '/' . round($pointsIdeal/$pointsAllAssignments, 3) . '/racetrack.png'), '')
            )) . "\n";

        $page->addHTML($content);

    } /* end showUserEvaluationForms */


    /**
     * Display all evaluation forms for an assignment
     *
     * Generates a page for creating and editing evaluations for
     * all users for a single assignment.
     *
     * @param int|bool $id the assignment id
     */

    public function showAssignmentEvaluationForms ( $id = false ) {

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
            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submitevaluation'))
        ));

        # Create a form for each user
        $paramSetCounter = 0;
        foreach ( $users as $user ) {

            # Check whether evaluation exists
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $id));
            if ( $evaluations->numRows() === 0 ) {

                # The evaluation does not exist
                # Set default parameters for creating a new evaluation
                $evaluationScoreDefault = '';
                $evaluationEnabledDefault = true;
                $evaluationDateDefault = $assignment->sga_date;
                $evaluationCommentDefault = '';

            } else {

                # The evaluation exists
                $evaluation = $evaluations->next();

                # Use its values as default parameters
                $evaluationScoreDefault = (float)$evaluation->sge_score;
                $evaluationEnabledDefault = $evaluation->sge_enabled;
                $evaluationDateDefault = $evaluation->sge_date;
                $evaluationCommentDefault = $evaluation->sge_comment;

            }

            # Build the evaluation form
            $content .= Html::rawElement('fieldset', null,
                Html::element('legend', null, $this->getUserDisplayName($user->user_id)) .
                Html::rawElement('table', null,
#                    Html::rawElement('tr', null,
#                        Html::rawElement('td', null, Xml::label('User:', 'evaluation-user')) .
#                        Html::rawElement('td', null, $this->getUserDisplayName($user->user_id))
#                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Score:', 'evaluation-score-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-score]', 20, $evaluationScoreDefault, array('id' => 'evaluation-score-' . $paramSetCounter)) . ' out of ' . (float)$assignment->sga_value . ' point(s)')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::check('evaluation-params[' . $paramSetCounter . '][evaluation-enabled]', $evaluationEnabledDefault, array('id' => 'evaluation-enabled-' . $paramSetCounter)))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-date]', 20, $evaluationDateDefault, array('id' => 'evaluation-date-' . $paramSetCounter, 'class' => 'sg-date-input')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Comment:', 'evaluation-comment-' . $paramSetCounter)) .
                        Html::rawElement('td', null, Xml::input('evaluation-params[' . $paramSetCounter . '][evaluation-comment]', 20, $evaluationCommentDefault, array('id' => 'evaluation-comment-' . $paramSetCounter)))
                    )
                ) .
                Html::hidden('evaluation-params[' . $paramSetCounter . '][evaluation-user]', $user->user_id) .
                Html::hidden('evaluation-params[' . $paramSetCounter . '][evaluation-assignment]', $id)
            );

            $paramSetCounter += 1;

        }

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

    public function showAllAssignments () {

        $page = $this->getOutput();

        # Query for all assignments
        $dbr = wfGetDB(DB_SLAVE);
        $assignments = $dbr->select('scholasticgrading_assignment', '*', '', __METHOD__,
            array('ORDER BY' => 'sga_date'));

        # Build the assignment table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-assignmenttable')) . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', array('id' => 'assignmenttable-header'),
            Html::element('th', null, 'Date') .
            Html::element('th', null, 'Title') .
            Html::element('th', null, 'Value') .
            Html::element('th', null, 'Enabled') .
            Html::element('th', array('class' => 'unsortable'), 'Edit')
        ) . "\n";

        # Create a row for each assignment
        foreach ( $assignments as $assignment ) {
            $content .= Html::rawElement('tr', array('class' => 'sg-assignmenttable-row'),
                Html::element('td', array('class' => 'sg-assignmenttable-date'), $assignment->sga_date) .
                Html::element('td', array('class' => 'sg-assignmenttable-title'), $assignment->sga_title) .
                Html::element('td', array('class' => 'sg-assignmenttable-value'), (float)$assignment->sga_value) .
                Html::element('td', array('class' => 'sg-assignmenttable-enabled'), $assignment->sga_enabled ? 'Yes' : 'No') .
                Html::rawElement('td', array('class' => 'sg-assignmenttable-modify'),
                    Linker::linkKnown($this->getTitle(), 'Edit', array(),
                        array('action' => 'editassignment', 'id' => $assignment->sga_id)))
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showAllAssignments */


    /**
     * Display a table of assignments, students, and evaluations
     *
     * Generates a table of evaluations, where columns represent
     * students and rows represent assignments.
     */

    public function showGradeTable () {

        $page = $this->getOutput();
        $pointsEarned = array();
        $pointsIdeal = array();

        # Query for all users and all enabled assignments
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*');
        $assignments = $dbr->select('scholasticgrading_assignment', '*',
            array('sga_enabled' => true), __METHOD__, array('ORDER BY' => 'sga_date'));

        # Build the grade table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-gradetable')) . "\n";
        $content .= Html::element('caption', null, 'Grades') . "\n";

        # Create a column header for each student
        $content .= Html::openElement('tr', array('id' => 'gradetable-header'));
        $content .= Html::element('th', null, 'Date') . Html::element('th', null, 'Assignment');
        foreach ( $users as $user ) {
            $content .= Html::rawElement('th', array('class' => 'sg-gradetable-user'),
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

            $content .= Html::openElement('tr', array('class' => 'sg-gradetable-row'));
            $content .= Html::element('td', array('class' => 'sg-gradetable-date', 'data-sort-value' => $assignment->sga_date), date_format(date_create($assignment->sga_date), 'D m/d'));
            $content .= Html::rawElement('td', array('class' => 'sg-gradetable-assignment'),
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
                            $content .= Html::rawElement('td', array('class' => 'sg-gradetable-cell'), 
                                Linker::linkKnown($this->getTitle(), '+' . (float)$evaluation->sge_score, array(),
                                    array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                        } else {

                            # The assignment is not extra credit
                            $content .= Html::rawElement('td', array('class' => 'sg-gradetable-cell'), 
                                Linker::linkKnown($this->getTitle(), $evaluation->sge_score / $assignment->sga_value * 100 . '%', array(),
                                    array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                        }

                        # Increment the points earned and the ideal score for this student
                        $pointsEarned[$user->user_name] += $evaluation->sge_score;
                        $pointsIdeal[$user->user_name] += $assignment->sga_value;

                    } else {

                        # The evaluation is disabled
                        $content .= Html::rawElement('td', array('class' => 'sg-gradetable-cell'), 
                            Linker::linkKnown($this->getTitle(), '**', array(),
                                array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                    }

                } else {

                    # An evaluation does not exist for this (user,assignment) combination
                    $content .= Html::rawElement('td', array('class' => 'sg-gradetable-cell'), 
                        Linker::linkKnown($this->getTitle(), '--', array(),
                            array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                }

            } /* end for each user */

            $content .= Html::closeElement('tr') . "\n";

        } /* end for each enabled assignment */

        # Report point totals for each student
        $content .= Html::openElement('tr');
        $content .= Html::element('th', null, '') . Html::element('th', null, '');
        foreach ( $users as $user ) {
            $content .= Html::element('th', null,
                $pointsEarned[$user->user_name] . ' / ' . $pointsIdeal[$user->user_name]
            );
        }
        $content .= Html::closeElement('tr') . "\n";

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showGradeTable */


    /**
     * Display all evaluations for a user
     *
     * Generates a page for viewing enabled evaluations
     * for all enabled assignments for a single user.
     *
     * @param int|bool $user_id the user id
     */

    public function showUserEvaluations ( $user_id = false ) {

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

        # Initialize the points earned, the ideal score,
        # and the course total points for this student
        $pointsEarned = 0;
        $pointsIdeal = 0;
        $pointsAllAssignments = 0;

        # Query for all enabled assignments
        $assignments = $dbr->select('scholasticgrading_assignment', '*',
            array('sga_enabled' => true), __METHOD__, array('ORDER BY' => 'sga_date'));

        # Build the user scores page
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable sortable sg-evaluationtable')) . "\n";

        # Create a column header for each field
        $content .= Html::rawElement('tr', array('id' => 'evaluationtable-header'),
            Html::element('th', null, 'Date') .
            Html::element('th', null, 'Assignment') .
            Html::element('th', null, 'Score') .
            Html::element('th', null, 'Value') .
            Html::element('th', null, 'Comment')
        ) . "\n";

        # Create a row for each enabled assignment that has an evaluation
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

                $content .= Html::rawElement('tr', array('class' => 'sg-evaluationtable-row'),
                    Html::element('td', array('class' => 'sg-evaluationtable-date', 'data-sort-value' => $evaluation->sge_date), date_format(date_create($evaluation->sge_date), 'D m/d')) .
                    Html::element('td', array('class' => 'sg-evaluationtable-title'), $assignment->sga_title) .
                    Html::element('td', array('class' => 'sg-evaluationtable-score'), (float)$evaluation->sge_score) .
                    Html::element('td', array('class' => 'sg-evaluationtable-value'), (float)$assignment->sga_value) .
                    Html::element('td', array('class' => 'sg-evaluationtable-comment'), $evaluation->sge_comment)
                ) . "\n";

            }

        }

        $content .= Html::rawElement('tr', array('class' => 'sg-evaluationtable-row'),
            Html::element('th', null, '') .
            Html::element('th', null, '') .
            Html::element('th', null, $pointsEarned) .
            Html::element('th', null, $pointsIdeal) .
            Html::element('th', null, '')
        ) . "\n";
        $content .= Html::closeElement('table') . "\n";

        # Insert the racetrack image at the top of the page
        $page->addHTML(Html::rawElement('div', array('class' => 'racetrack'),
                Html::element('img', array('src' => '/django/credit/racetrack/' . round($pointsEarned/$pointsAllAssignments, 3) . '/' . round($pointsIdeal/$pointsAllAssignments, 3) . '/racetrack.png'), '')
            )) . "\n";

        $page->addHTML($content);

    } /* end showUserEvaluations */


    /**
     * Display a table of all wiki users
     *
     * Dumps a portion of the user table from the wiki database.
     * Used for development.
     */

    public function showUsers () {

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
     * Dumps the assignments table from the wiki database.
     * Used for development.
     */

    public function showAssignments () {

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
     * Dumps the evaluations table from the wiki database.
     * Used for development.
     */

    public function showEvaluations () {

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
     * Dumps the adjustments table from the wiki database.
     * Used for development.
     */

    public function showAdjustments () {

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


} /* end SpecialGrades */
