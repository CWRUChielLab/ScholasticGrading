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

        case 'submitassignment':

            if ( $this->canModify(true) ) {
                if ( $request->wasPosted() && $this->getUser()->matchEditToken($request->getVal('wpEditToken')) ) {
                    $this->submitAssignment(
                        $request->getVal('assignment-id', false),
                        $request->getVal('assignment-title', false),
                        $request->getVal('assignment-value', false),
                        $request->getCheck('assignment-enabled') ? 1 : 0,
                        $request->getVal('assignment-date', false)
                    );
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
                    $this->submitEvaluation(
                        $request->getVal('evaluation-user', false),
                        $request->getVal('evaluation-assignment', false),
                        $request->getVal('evaluation-score', false),
                        $request->getCheck('evaluation-enabled') ? 1 : 0,
                        $request->getVal('evaluation-date', false),
                        $request->getVal('evaluation-comment', false)
                    );
                } else {
                    # Prevent cross-site request forgeries
                    $page->addWikiMsg('sessionfailure');
                }
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        default:

            $page->addHTML(Html::rawElement('p', null,
                Linker::linkKnown($this->getTitle('assignments'), 'Manage assignments')) . "\n");

            $this->showGradeTable();
            //$this->showAssignments();
            //$this->showEvaluations();
            //$this->showUsers();
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
     * Echo the contents of the web request
     *
     * Reports all data passed in the URL or POSTed
     * by a form.
     */

    public function dumpRequest () {

        $request = $this->getRequest();

        foreach ( $request->getValues() as $key => $value ) {
            if ( !is_array($value) ) {
                echo $key . " => " . $value . "\n";
            } else {
                echo $key . " => [";
                foreach ( $value as $key2 => $value2 ) {
                    echo $key2 . " => " . $value2 . ", ";
                }
                echo "]\n";
            }
        }

    } /* end dumpRequest */


    /**
     * Submit an assignment creation/modification request
     *
     * Processes an assignment form and modifies the database.
     * If the (id) key provided in the request corresponds to an
     * existing assignment, the function will modify that assignment.
     * Otherwise, the function will create a new assignment.
     *
     * @param int|bool    $assignmentID the id of an assignment
     * @param int|bool    $assignmentTitle the title of an assignment
     * @param float|bool  $assignmentValue the value of an assignment
     * @param int|bool    $assignmentEnabled the enabled status of an assignment
     * @param string|bool $assignmentDate the date of an assignment
     */

    public function submitAssignment ( $assignmentID = false, $assignmentTitle = false, $assignmentValue = false, $assignmentEnabled = false, $assignmentDate = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

        # Check whether assignment exists
        $assignments = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $assignmentID));
        if ( $assignments->numRows() > 0 ) {

            # The assignment exists
            $assignment = $assignments->next();

            if ( $request->getVal('modify-assignment') ) {

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

            } elseif ( $request->getVal('delete-assignment') ) {

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
                            $content .= Html::rawElement('li', null, $user->user_real_name) . "\n";
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
                        Html::hidden('assignment-id', $assignmentID) .
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

            } else {

                # Unknown action
                $page->addWikiText('Unknown action. What button was pressed?');

            }

        } else {

            # The assignment does not exist

            if ( $request->getVal('create-assignment') ) {

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

            } else {

                # Unknown action
                $page->addWikiText('Unknown action. What button was pressed?');

            }

        }

    } /* end submitAssignment */


    /**
     * Submit an evaluation creation/modification request
     *
     * Processes an evaluation form and modifies the database.
     * If the (user,assignment) key provided corresponds to an
     * existing evaluation, the function will modify that evaluation.
     * Otherwise, the function will create a new evaluation.
     *
     * @param int|bool    $evaluationUser the user id of an evaluation
     * @param int|bool    $evaluationAssignment the assignment id of an evaluation
     * @param float|bool  $evaluationScore the score of an evaluation
     * @param int|bool    $evaluationEnabled the enabled status of an evaluation
     * @param string|bool $evaluationDate the date of an evaluation
     * @param string|bool $evaluationComment the comment of an evaluation
     */

    public function submitEvaluation ( $evaluationUser = false, $evaluationAssignment = false, $evaluationScore = false, $evaluationEnabled = false, $evaluationDate = false, $evaluationComment = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

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

                if ( $request->getVal('modify-evaluation') ) {

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

                } elseif ( $request->getVal('delete-evaluation') ) {

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
                            Html::hidden('evaluation-user', $evaluationUser) .
                            Html::hidden('evaluation-assignment', $evaluationAssignment) .
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

                } else {

                    # Unknown action
                    $page->addWikiText('Unknown action. What button was pressed?');

                }

            } else {

                # The evaluation does not exist

                if ( $request->getVal('create-evaluation') ) {

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

                } else {

                    # Unknown action
                    $page->addWikiText('Unknown action. What button was pressed?');

                }

            }

        }

    } /* end submitEvaluation */


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
        $assignmentTitleDefault = '';
        $assignmentValueDefault = 0;
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
                        Html::rawElement('td', null, Xml::input('assignment-title', 20, $assignmentTitleDefault, array('id' => 'assignment-title')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Point value:', 'assignment-value')) .
                        Html::rawElement('td', null, Xml::input('assignment-value', 20, $assignmentValueDefault, array('id' => 'assignment-value')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'assignment-enabled')) .
                        Html::rawElement('td', null, Xml::check('assignment-enabled', $assignmentEnabledDefault, array('id' => 'assignment-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'assignment-date')) .
                        Html::rawElement('td', null, Xml::input('assignment-date', 20, $assignmentDateDefault, array('id' => 'assignment-date')))
                    )
                ) .
                $buttons .
                Html::hidden('assignment-id', $id) .
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
                $evaluationScoreDefault = 0;
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
                        Html::rawElement('td', null, $user->user_name)
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Assignment:', 'evaluation-assignment')) .
                        Html::rawElement('td', null, $assignment->sga_title . ' (' . $assignment->sga_date . ')')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Score:', 'evaluation-score')) .
                        Html::rawElement('td', null, Xml::input('evaluation-score', 20, $evaluationScoreDefault, array('id' => 'evaluation-score')) . ' out of ' . (float)$assignment->sga_value . ' point(s)')
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled')) .
                        Html::rawElement('td', null, Xml::check('evaluation-enabled', $evaluationEnabledDefault, array('id' => 'evaluation-enabled')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date')) .
                        Html::rawElement('td', null, Xml::input('evaluation-date', 20, $evaluationDateDefault, array('id' => 'evaluation-date')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Comment:', 'evaluation-comment')) .
                        Html::rawElement('td', null, Xml::input('evaluation-comment', 20, $evaluationCommentDefault, array('id' => 'evaluation-comment')))
                    )
                ) .
                $buttons .
                Html::hidden('evaluation-user', $user_id) .
                Html::hidden('evaluation-assignment', $assignment_id) .
                Html::hidden('wpEditToken', $this->getUser()->getEditToken())
            )
        );

        $page->addHTML($content);

    } /* end showEvaluationForm */


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
        $pointTotal = array();

        # Query for all users and all assignments
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*');
        $assignments = $dbr->select('scholasticgrading_assignment', '*', '', __METHOD__,
            array('ORDER BY' => 'sga_date'));

        # Build the grade table
        $content = '';
        $content .= Html::openElement('table', array('class' => 'wikitable')) . "\n";
        $content .= Html::element('caption', null, 'Grades') . "\n";

        # Create a column header for each student
        $content .= Html::openElement('tr');
        $content .= Html::element('td', null, '') . Html::element('td', null, '');
        foreach ( $users as $user ) {
            $content .= Html::rawElement('th', array('class' => 'vertical'),
                Html::element('div', array('class' => 'vertical'), $user->user_real_name)
            );

            # Initialize the points earned and the point total for this student
            $pointsEarned[$user->user_name] = 0;
            $pointTotal[$user->user_name] = 0;
        }
        $content .= Html::closeElement('tr') . "\n";

        # Create a row for each enabled assignment
        foreach ( $assignments as $assignment ) {

            if ( $assignment->sga_enabled ) {

                $content .= Html::openElement('tr');
                $content .= Html::element('th', array('style' => 'text-align: right'), date_format(date_create($assignment->sga_date), 'D m/d'));
                $content .= Html::rawElement('th', null,
                    Linker::linkKnown($this->getTitle(), $assignment->sga_title, array(),
                        array('action' => 'editassignment', 'id' => $assignment->sga_id)));

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
                                $content .= Html::rawElement('td', null, 
                                    Linker::linkKnown($this->getTitle(), '+' . (float)$evaluation->sge_score, array(),
                                        array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                            } else {

                                # The assignment is not extra credit
                                $content .= Html::rawElement('td', null, 
                                    Linker::linkKnown($this->getTitle(), $evaluation->sge_score / $assignment->sga_value * 100 . '%', array(),
                                        array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                            }

                            # Increment the points earned and the point total for this student
                            $pointsEarned[$user->user_name] += $evaluation->sge_score;
                            $pointTotal[$user->user_name] += $assignment->sga_value;

                        } else {

                            # The evaluation is disabled
                            $content .= Html::rawElement('td', null, 
                                Linker::linkKnown($this->getTitle(), '**', array(),
                                    array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                        }

                    } else {

                        # An evaluation does not exist for this (user,assignment) combination
                        $content .= Html::rawElement('td', null, 
                            Linker::linkKnown($this->getTitle(), '--', array(),
                                array('action' => 'editevaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                    }

                } /* end for each user */

                $content .= Html::closeElement('tr') . "\n";

            } /* end if assignment enabled */

        } /* end for each assignment */

        # Report point totals for each student
        $content .= Html::openElement('tr');
        $content .= Html::element('th', null, '') . Html::element('th', null, 'TOTAL');
        foreach ( $users as $user ) {
            $content .= Html::element('td', null,
                $pointsEarned[$user->user_name] . ' / ' . $pointTotal[$user->user_name]
            );
        }
        $content .= Html::closeElement('tr') . "\n";

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showGradeTable */


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


} /* end SpecialGrades */
