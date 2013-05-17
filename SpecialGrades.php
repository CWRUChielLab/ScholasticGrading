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

        # Set the page title
        $this->setHeaders();

        # Set the time zone so assignment and evaluation dates are displayed correctly
        date_default_timezone_set('UTC');

        # Process requests
        $page = $this->getOutput();
        $request = $this->getRequest();
        $action = $subPage ? $subPage : $request->getVal('action', $subPage);

        switch ( $action ) {

        case 'assignment':

            if ( $this->canModify($page) ) {
                $this->showAssignmentForm(
                    $request->getVal('id', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'evaluation':

            if ( $this->canModify($page) ) {
                $this->showEvaluationForm(
                    $request->getVal('user', false),
                    $request->getVal('assignment', false)
                );
            }

            $page->returnToMain(false, $this->getTitle());
            break;

        case 'submit':

            if ( $this->canModify($page) ) {

                if ( $request->wasPosted() && $this->getUser()->matchEditToken($request->getVal('wpEditToken')) ) {

                    switch ( $request->getVal('wpScholasticGradingAction') ) {
                    case 'assignment':
                        $this->submitAssignment(
                            $request->getVal('assignment-id')
                        );
                        break;
                    case 'evaluation':
                        $this->submitEvaluation();
                        break;
                    }

                } else {

                    # Prevent cross-site request forgeries
                    $page->addWikiMsg('sessionfailure');

                }

            }

            $page->returnToMain(false, $this->getTitle());
            break;

        default:

            $page->addHTML(Html::rawElement('p', null,
                Linker::linkKnown($this->getTitle('assignment'), 'Create a new assignment')) . "\n");

            $this->showGradeTable();
            //$this->showAssignments();
            //$this->showEvaluations();
            //$this->showUsers();
            break;

        } /* end switch action */

    } /* end execute */


    /**
     * Check grade editing permissions
     *
     * Determines whether the user has privileges to modify assignments and grades
     *
     * @param OutputPage|bool $page where errors should be printed
     * @return bool whether the user can modify assignments and grades
     */

    public function canModify ( $page = false ) {

        if ( !$this->getUser()->isAllowed('editgrades') ) {

            # The user does not have the correct privileges
            if ( $page ) {
                throw new PermissionsError('editgrades');
            }
            return false;

        } elseif ( wfReadOnly() ) {

            # The database is in read-only mode
            if ( $page ) {
                $page->readOnlyPage();
            }
            return false;

        }

        return true;

    } /* end canModify */


    /**
     * Submit an assignment creation/modification request
     *
     * Processes an assignment form and modifies the database.
     * If no assignment id is provided, the function will create a new assignment.
     * If a valid assignment id is provided, the function will modify that assignment.
     * If an invalid assignment id is provided, report an error.
     *
     * @param int|bool $id an assignment id
     */

    public function submitAssignment ( $id = false ) {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

        $assignmentTitle   = $request->getVal('assignment-title');
        $assignmentValue   = $request->getVal('assignment-value');
        $assignmentEnabled = $request->getCheck('assignment-enabled') ? 1 : 0;
        $assignmentDate    = $request->getVal('assignment-date');

        if ( !$id ) {

            # Create a new assignment
            $dbw->insert('scholasticgrading_assignment', array(
                'sga_title'   => $assignmentTitle,
                'sga_value'   => $assignmentValue,
                'sga_enabled' => $assignmentEnabled,
                'sga_date'    => $dbw->timestamp($assignmentDate . ' 00:00:00'),
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

            # Check whether assignment exists
            $assignments = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $id));
            if ( $assignments->numRows() > 0 ) {

                # Edit the existing assignment
                $dbw->update('scholasticgrading_assignment', array(
                    'sga_title'   => $assignmentTitle,
                    'sga_value'   => $assignmentValue,
                    'sga_enabled' => $assignmentEnabled,
                    'sga_date'    => $dbw->timestamp($assignmentDate . ' 00:00:00'),
                ), array('sga_id' => $id));

                # Report success and create a new log entry
                if ( $dbw->affectedRows() === 0 ) {

                    $page->addWikiText('Database unchanged.');

                } else {

                    $page->addWikiText('\'\'\'"' . $assignmentTitle . '" (' . $assignmentDate . ') updated!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('editAssignment', $this->getTitle(), null, array($assignmentTitle, $assignmentDate));

                }

            } else {

                # The assignment does not exist
                $page->addWikiText('Assignment id=' . $id . ' does not exist.');

            }

        }

    } /* end submitAssignment */


    /**
     * Submit an evaluation creation/modification request
     *
     * Processes an evaluation form and modifies the database.
     * The (user,assignment) key for the evaluation is provided in
     * the request whether the evaluation already exists or not.
     */

    public function submitEvaluation () {

        $page = $this->getOutput();
        $request = $this->getRequest();
        $dbw = wfGetDB(DB_MASTER);

        $evaluationUser       = $request->getVal('evaluation-user');
        $evaluationAssignment = $request->getVal('evaluation-assignment');
        $evaluationScore      = $request->getVal('evaluation-score');
        $evaluationEnabled    = $request->getCheck('evaluation-enabled') ? 1 : 0;
        $evaluationDate       = $request->getVal('evaluation-date');

        # Check whether evaluation exists
        $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));
        if ( $evaluations->numRows() === 0 ) {

            # Create a new evaluation
            $dbw->insert('scholasticgrading_evaluation', array(
                'sge_user_id'       => $evaluationUser,
                'sge_assignment_id' => $evaluationAssignment,
                'sge_score'         => $evaluationScore,
                'sge_enabled'       => $evaluationEnabled,
                'sge_date'          => $dbw->timestamp($evaluationDate . ' 00:00:00'),
            ));

            # Report success and create a new log entry
            if ( $dbw->affectedRows() === 0 ) {

                $page->addWikiText('Database unchanged.');

            } else {

                $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();
                $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date)) ;

                $page->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignmentDate . ') added!\'\'\'');

                $log = new LogPage('grades', false);
                $log->addEntry('addEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignmentDate));

            }

        } else {

            # Edit the existing evaluation
            $dbw->update('scholasticgrading_evaluation', array(
                'sge_score'   => $evaluationScore,
                'sge_enabled' => $evaluationEnabled,
                'sge_date'    => $dbw->timestamp($evaluationDate . ' 00:00:00'),
            ), array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));

            # Report success and create a new log entry
            if ( $dbw->affectedRows() === 0 ) {

                $page->addWikiText('Database unchanged.');

            } else {

                $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();
                $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));

                $page->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignmentDate . ') updated!\'\'\'');

                $log = new LogPage('grades', false);
                $log->addEntry('editEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignmentDate));

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
     * @param int|bool $id an assignment id
     */

    public function showAssignmentForm ( $id = false ) {

        $page = $this->getOutput();

        # Load JavaScript resources
        $page->addModules('ext.ScholasticGrading.assignment-date');

        if ( !$id ) {

            # Create a new assignment
            $content = Xml::fieldset("Create a new assignment",
                Html::rawElement('form',
                    array(
                        'method' => 'post',
                        'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                     ),
                     Html::rawElement('table', null,
                        Html::rawElement('tr', null,
                            Html::rawElement('td', null, Xml::label('Title:', 'assignment-title')) .
                            Html::rawElement('td', null, Xml::input('assignment-title', 20, '', array('id' => 'assignment-title')))
                        ) .
                        Html::rawElement('tr', null,
                            Html::rawElement('td', null, Xml::label('Point value:', 'assignment-value')) .
                            Html::rawElement('td', null, Xml::input('assignment-value', 20, '0', array('id' => 'assignment-value')))
                        ) .
                        Html::rawElement('tr', null,
                            Html::rawElement('td', null, Xml::label('Enabled:', 'assignment-enabled')) .
                            Html::rawElement('td', null, Xml::check('assignment-enabled', true, array('id' => 'assignment-enabled')))
                        ) .
                        Html::rawElement('tr', null,
                            Html::rawElement('td', null, Xml::label('Date:', 'assignment-date')) .
                            Html::rawElement('td', null, Xml::input('assignment-date', 20, '', array('id' => 'assignment-date')))
                        )
                    ) .
                    Xml::submitButton('Create assignment') .
                    Html::hidden('wpEditToken', $this->getUser()->getEditToken()) .
                    Html::hidden('wpScholasticGradingAction', 'assignment')
                )
            );

            $page->addHTML($content);

        } else {

            # Check whether assignment exists
            $dbr = wfGetDB(DB_SLAVE);
            $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $id));
            if ( $assignments->numRows() > 0 ) {

                # Edit the existing assignment
                $assignment = $assignments->next();
                $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));

                $content = Xml::fieldset("Edit an existing assignment",
                    Html::rawElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        ),
                        Html::rawElement('table', null,
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Title:', 'assignment-title')) .
                                Html::rawElement('td', null, Xml::input('assignment-title', 20, $assignment->sga_title, array('id' => 'assignment-title')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Point value:', 'assignment-value')) .
                                Html::rawElement('td', null, Xml::input('assignment-value', 20, $assignment->sga_value, array('id' => 'assignment-value')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Enabled:', 'assignment-enabled')) .
                                Html::rawElement('td', null, Xml::check('assignment-enabled', $assignment->sga_enabled, array('id' => 'assignment-enabled')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Date:', 'assignment-date')) .
                                Html::rawElement('td', null, Xml::input('assignment-date', 20, $assignmentDate, array('id' => 'assignment-date')))
                            )
                        ) .
                        Xml::submitButton('Apply changes') .
                        Html::hidden('assignment-id', $id) .
                        Html::hidden('wpEditToken', $this->getUser()->getEditToken()) .
                        Html::hidden('wpScholasticGradingAction', 'assignment')
                    )
                );

                $page->addHTML($content);

            } else {

                # The assignment does not exist
                $page->addWikiText('Assignment (id=' . $id . ') does not exist.');

            }

        }

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

        # Load JavaScript resources
        $page->addModules('ext.ScholasticGrading.evaluation-date');

        # Check whether user and assignment exist
        $users = $dbr->select('user', '*', array('user_id' => $user_id));
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $assignment_id));
        if ( $users->numRows() > 0 && $assignments->numRows() > 0 ) {

            $user = $users->next();
            $assignment = $assignments->next();
            $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));

            # Check whether evaluation exists
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment_id));
            if ( $evaluations->numRows() === 0 ) {

                # Create a new evaluation
                $content = Xml::fieldset("Create a new evaluation",
                    Html::rawElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        ),
                        Html::rawElement('table', null,
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('User:', 'evaluation-user')) .
                                Html::rawElement('td', null, $user->user_name)
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Assignment:', 'evaluation-assignment')) .
                                Html::rawElement('td', null, $assignment->sga_title . ' (' . $assignmentDate . ')')
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Score:', 'evaluation-score')) .
                                Html::rawElement('td', null, Xml::input('evaluation-score', 20, '0', array('id' => 'evaluation-score')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled')) .
                                Html::rawElement('td', null, Xml::check('evaluation-enabled', true, array('id' => 'evaluation-enabled')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date')) .
                                Html::rawElement('td', null, Xml::input('evaluation-date', 20, '', array('id' => 'evaluation-date')))
                            )
                        ) .
                        Xml::submitButton('Create evaluation') .
                        Html::hidden('evaluation-user', $user_id) .
                        Html::hidden('evaluation-assignment', $assignment_id) .
                        Html::hidden('wpEditToken', $this->getUser()->getEditToken()) .
                        Html::hidden('wpScholasticGradingAction', 'evaluation')
                    )
                );

                $page->addHTML($content);

            } else {

                $evaluation = $evaluations->next();
                $evaluationDate = date('Y-m-d', wfTimestamp(TS_UNIX, $evaluation->sge_date));

                # Edit the existing evaluation
                $content = Xml::fieldset("Edit an existing evaluation",
                    Html::rawElement('form',
                        array(
                            'method' => 'post',
                            'action' => $this->getTitle()->getLocalUrl(array('action' => 'submit'))
                        ),
                        Html::rawElement('table', null,
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('User:', 'evaluation-user')) .
                                Html::rawElement('td', null, $user->user_name)
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Assignment:', 'evaluation-assignment')) .
                                Html::rawElement('td', null, $assignment->sga_title . ' (' . $assignmentDate . ')')
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Score:', 'evaluation-score')) .
                                Html::rawElement('td', null, Xml::input('evaluation-score', 20, $evaluation->sge_score, array('id' => 'evaluation-score')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Enabled:', 'evaluation-enabled')) .
                                Html::rawElement('td', null, Xml::check('evaluation-enabled', $evaluation->sge_enabled, array('id' => 'evaluation-enabled')))
                            ) .
                            Html::rawElement('tr', null,
                                Html::rawElement('td', null, Xml::label('Date:', 'evaluation-date')) .
                                Html::rawElement('td', null, Xml::input('evaluation-date', 20, $evaluationDate, array('id' => 'evaluation-date')))
                            )
                        ) .
                        Xml::submitButton('Apply changes') .
                        Html::hidden('evaluation-user', $user_id) .
                        Html::hidden('evaluation-assignment', $assignment_id) .
                        Html::hidden('wpEditToken', $this->getUser()->getEditToken()) .
                        Html::hidden('wpScholasticGradingAction', 'evaluation')
                    )
                );

                $page->addHTML($content);

            }

        } else {

            # Either the user or assignment does not exist
            $page->addWikiText('Either user (id=' . $user_id . ') or assignment (id=' . $assignment_id . ') does not exist.');

        }

    } /* end showEvaluationForm */


    /**
     * Display a table of assignments, students, and evaluations
     *
     * Generates a table of evaluations, where columns represent
     * students and rows represent assignments.
     */

    public function showGradeTable () {

        $page = $this->getOutput();

        # Load CSS resources
        $page->addModules('ext.ScholasticGrading.vertical-text');

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
        }
        $content .= Html::closeElement('tr') . "\n";

        # Create a row for each assignment
        foreach ( $assignments as $assignment ) {

            $content .= Html::openElement('tr');
            $content .= Html::element('th', array('style' => 'text-align: right'), date('D m/d', wfTimestamp(TS_UNIX, $assignment->sga_date)));
            $content .= Html::rawElement('th', null,
                Linker::linkKnown($this->getTitle(), $assignment->sga_title, array(),
                    array('action' => 'assignment', 'id' => $assignment->sga_id)));

            # Create a cell for each user
            foreach ( $users as $user ) {

                $evaluations = $dbr->select('scholasticgrading_evaluation', '*',
                    array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $assignment->sga_id));
                if ( $evaluations->numRows() > 0 ) {

                    # An evaluation exists for this (user,assignment) combination
                    $evaluation = $evaluations->next();
                    if ( $assignment->sga_value == 0 ) {

                        # The assignment is extra credit
                        $content .= Html::rawElement('td', null, 
                            Linker::linkKnown($this->getTitle(), '+' . $evaluation->sge_score, array(),
                                array('action' => 'evaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                    } else {

                        # The assignment is not extra credit
                        $content .= Html::rawElement('td', null, 
                            Linker::linkKnown($this->getTitle(), $evaluation->sge_score / $assignment->sga_value * 100 . '%', array(),
                                array('action' => 'evaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                    }

                } else {

                    # An evaluation does not exist for this (user,assignment) combination
                    $content .= Html::rawElement('td', null, 
                        Linker::linkKnown($this->getTitle(), '--', array(),
                            array('action' => 'evaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));

                }

            } /* end for each user */

            $content .= Html::closeElement('tr') . "\n";

        } /* end for each assignment */

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
            Html::element('th', null, 'date')
        ) . "\n";

        # Create a row for each evaluation
        foreach ( $evaluations as $evaluation ) {
            $content .= Html::rawElement('tr', null,
                Html::element('td', null, $evaluation->sge_user_id) .
                Html::element('td', null, $evaluation->sge_assignment_id) .
                Html::element('td', null, $evaluation->sge_score) .
                Html::element('td', null, $evaluation->sge_enabled) .
                Html::element('td', null, $evaluation->sge_date)
            ) . "\n";
        }

        $content .= Html::closeElement('table') . "\n";

        $page->addHTML($content);

    } /* end showEvaluations */


} /* end SpecialGrades */
