<?php

/**
 * Implements Special:Grades
 *
 * @ingroup SpecialPage
 *
 * @author Jeffrey Gill <jeffrey.p.gill@gmail.com>
 */


class SpecialGrades extends SpecialPage {

    # Constructor (sets up the new special page)
    function __construct() {
        parent::__construct('Grades');
    }


    # Show the special page (main method, run automatically)
    function execute ( $par ) {
        $this->setHeaders();

        # Needed so dates are displayed correctly
        date_default_timezone_set('UTC');

        # Load JavaScript and CSS
        $this->getOutput()->addModules( 'ext.ScholasticGrading.assignment-date' );
        $this->getOutput()->addModules( 'ext.ScholasticGrading.evaluation-date' );
        $this->getOutput()->addModules( 'ext.ScholasticGrading.vertical-text' );

        # Process requests
        $request = $this->getRequest();
        $action = $par ? $par : $request->getVal('action', $par);

        switch ( $action ) {
        case 'assignment':
            if ( $this->canModify( $this->getOutput() ) ) {
                $this->showAssignmentForm( $request->getVal('id', false) );
            }
            $this->getOutput()->returnToMain(false, $this->getTitle());
            break;
        case 'evaluation':
            if ( $this->canModify( $this->getOutput() ) ) {
                $this->showEvaluationForm( $request->getVal('user', false), $request->getVal('assignment', false) );
            }
            $this->getOutput()->returnToMain(false, $this->getTitle());
            break;
        case 'submit':
            if ( !$this->canModify( $this->getOutput() ) ) {
                # Error msg added by canModify()
            } elseif ( !$request->wasPosted() || !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
                # Prevent cross-site request forgeries
                $this->getOutput()->addWikiMsg( 'sessionfailure' );
            } else {
                switch ( $request->getVal('wpScholasticGradingAction') ) {
                case 'assignment':
                    $this->doAssignmentSubmit( $request->getVal('assignment-id') );
                    break;
                case 'evaluation':
                    $this->doEvaluationSubmit();
                    break;
                }
            }
            $this->getOutput()->returnToMain(false, $this->getTitle());
            break;
        default:
            $this->getOutput()->addHTML(Html::rawElement('p', null,
                Linker::linkKnown($this->getTitle('assignment'), 'Create a new assignment')) . "\n");
            $this->showGradeGrid();
            $this->showAssignments();
            $this->showEvaluations();
            $this->showUsers();
            break;
        }
    }


    # Returns bool whether the user can modify the data
    public function canModify ( $out = false ) {
        if ( !$this->getUser()->isAllowed( 'editgrades' ) ) {
            # Check user permissions
            if ( $out ) {
                throw new PermissionsError( 'editgrades' );
            }
            return false;
        } elseif ( wfReadOnly() ) {
            # Is the database in read-only mode?
            if ( $out ) {
                $out->readOnlyPage();
            }
            return false;
        }
        return true;
    }


    # Create an assignment
    public function doAssignmentSubmit ( $id = false ) {
        $request = $this->getRequest();
        $assignmentTitle   = $request->getVal('assignment-title');
        $assignmentValue   = $request->getVal('assignment-value');
        $assignmentEnabled = $request->getCheck('assignment-enabled') ? 1 : 0;
        $assignmentDate    = $request->getVal('assignment-date');

        $dbw = wfGetDB(DB_MASTER);
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
                $this->getOutput()->addWikiText('Database unchanged.');
            } else {
                $this->getOutput()->addWikiText('\'\'\'"' . $assignmentTitle . '" (' . $assignmentDate . ') added!\'\'\'');

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
                    $this->getOutput()->addWikiText('Database unchanged.');
                } else {
                    $this->getOutput()->addWikiText('\'\'\'"' . $assignmentTitle . '" (' . $assignmentDate . ') updated!\'\'\'');

                    $log = new LogPage('grades', false);
                    $log->addEntry('editAssignment', $this->getTitle(), null, array($assignmentTitle, $assignmentDate));
                }
            } else {
                # The assignment does not exist
                $this->getOutput()->addWikiText('Assignment id=' . $id . ' does not exist.');
            }
        }
    }


    # Create an evaluation
    public function doEvaluationSubmit () {
        $request = $this->getRequest();
        $evaluationUser       = $request->getVal('evaluation-user');
        $evaluationAssignment = $request->getVal('evaluation-assignment');
        $evaluationScore      = $request->getVal('evaluation-score');
        $evaluationEnabled    = $request->getCheck('evaluation-enabled') ? 1 : 0;
        $evaluationDate       = $request->getVal('evaluation-date');

        # Check whether evaluation exists
        $dbw = wfGetDB( DB_MASTER );
        $evaluations = $dbw->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));
        if ( $evaluations->numRows() == 0 ) {
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
                $this->getOutput()->addWikiText('Database unchanged.');
            } else {
                $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();
                $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));
                $this->getOutput()->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignmentDate . ') added!\'\'\'');

                $log = new LogPage('grades', false);
                $log->addEntry('addEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignmentDate));
            }
        } else {
            # Edit the existing evaluation
            $dbw->update('scholasticgrading_evaluation', array(
                'sge_score'         => $evaluationScore,
                'sge_enabled'       => $evaluationEnabled,
                'sge_date'          => $dbw->timestamp($evaluationDate . ' 00:00:00'),
            ), array('sge_user_id' => $evaluationUser, 'sge_assignment_id' => $evaluationAssignment));

            # Report success and create a new log entry
            if ( $dbw->affectedRows() === 0 ) {
                $this->getOutput()->addWikiText('Database unchanged.');
            } else {
                $user = $dbw->select('user', '*', array('user_id' => $evaluationUser))->next();
                $assignment = $dbw->select('scholasticgrading_assignment', '*', array('sga_id' => $evaluationAssignment))->next();
                $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));
                $this->getOutput()->addWikiText('\'\'\'Score for [[User:' . $user->user_name . '|' . $user->user_name . ']] for "' . $assignment->sga_title . '" (' . $assignmentDate . ') updated!\'\'\'');

                $log = new LogPage('grades', false);
                $log->addEntry('editEvaluation', $this->getTitle(), 'for [[User:' . $user->user_name . '|' . $user->user_name .']]', array($assignment->sga_title, $assignmentDate));
            }
        }
    }


    # Show the assignment creation form
    public function showAssignmentForm ( $id = false ) {
        if ( !$id ) {
            # Create a new assignment
            $out = '';
            $out .= Xml::fieldset( "Create a new assignment",
                Html::rawElement('form', array('method' => 'post',
                    'action' => $this->getTitle()->getLocalUrl(
                        array('action' => 'submit'))),
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
            $this->getOutput()->addHTML($out);
        } else {
            # Check whether assignment exists
            $dbr = wfGetDB(DB_SLAVE);
            $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $id));
            if ( $assignments->numRows() > 0 ) {
                # Edit the existing assignment
                $assignment = $assignments->next();
                $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));
                $out = '';
                $out .= Xml::fieldset( "Edit an existing assignment",
                    Html::rawElement('form', array('method' => 'post',
                        'action' => $this->getTitle()->getLocalUrl(
                            array('action' => 'submit'))),
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
                $this->getOutput()->addHTML($out);
            } else {
                # The assignment does not exist
                $this->getOutput()->addWikiText('Assignment (id=' . $id . ') does not exist.');
            }
        }
    }


    # Show the evaluation creation form
    public function showEvaluationForm ( $user_id = false, $assignment_id = false ) {
        $dbr = wfGetDB(DB_SLAVE);

        # Check whether user and assignment exist
        $users = $dbr->select('user', '*', array('user_id' => $user_id));
        $assignments = $dbr->select('scholasticgrading_assignment', '*', array('sga_id' => $assignment_id));
        if ( $users->numRows() > 0 && $assignments->numRows() > 0 ) {
            $user = $users->next();
            $assignment = $assignments->next();
            $assignmentDate = date('Y-m-d', wfTimestamp(TS_UNIX, $assignment->sga_date));

            # Check whether evaluation exists
            $evaluations = $dbr->select('scholasticgrading_evaluation', '*', array('sge_user_id' => $user_id, 'sge_assignment_id' => $assignment_id));
            if ( $evaluations->numRows() == 0 ) {
                # Create a new evaluation
                $out = '';
                $out .= Xml::fieldset( "Create a new evaluation",
                    Html::rawElement('form', array('method' => 'post',
                        'action' => $this->getTitle()->getLocalUrl(
                            array('action' => 'submit'))),
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
                $this->getOutput()->addHTML($out);
            } else {
                $evaluation = $evaluations->next();
                $evaluationDate = date('Y-m-d', wfTimestamp(TS_UNIX, $evaluation->sge_date));

                # Edit the existing evaluation
                $out = '';
                $out .= Xml::fieldset( "Edit an existing evaluation",
                    Html::rawElement('form', array('method' => 'post',
                        'action' => $this->getTitle()->getLocalUrl(
                            array('action' => 'submit'))),
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
                $this->getOutput()->addHTML($out);
            }
        } else {
            # Either the user or assignment does not exist
            $this->getOutput()->addWikiText('Either user (id=' . $user_id . ') or assignment (id=' . $assignment_id . ') does not exist.');
        }
    }


    # Show the grade grid
    public function showGradeGrid () {
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*');
        $assignments = $dbr->select('scholasticgrading_assignment', '*', '', __METHOD__,
            array('ORDER BY' => 'sga_date'));

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable')) . "\n";
        $out .= Html::element('caption', null, 'Grade Grid') . "\n";
        $out .= Html::openElement('tr');
        $out .= Html::element('td', null, '') . Html::element('td', null, '');
        foreach ( $users as $user ) {
            $out .= Html::rawElement('th', array('class' => 'vertical'),
                Html::element('div', array('class' => 'vertical'), $user->user_real_name)
            );
        }
        $out .= Html::closeElement('tr') . "\n";
        foreach ( $assignments as $assignment ) {
            $out .= Html::openElement('tr');
            $out .= Html::element('th', array('style' => 'text-align: right'), date('D m/d', wfTimestamp(TS_UNIX, $assignment->sga_date)));
            $out .= Html::rawElement('th', null,
                Linker::linkKnown($this->getTitle(), $assignment->sga_title, array(),
                    array('action' => 'assignment', 'id' => $assignment->sga_id)));
            foreach ( $users as $user ) {
                $evaluations = $dbr->select('scholasticgrading_evaluation', '*',
                    array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $assignment->sga_id));
                if ( $evaluations->numRows() > 0 ) {
                    $evaluation = $evaluations->next();
                    if ( $assignment->sga_value == 0 ) {
                        $out .= Html::rawElement('td', null, 
                            Linker::linkKnown($this->getTitle(), '+' . $evaluation->sge_score, array(),
                                array('action' => 'evaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));
                    } else {
                        $out .= Html::rawElement('td', null, 
                            Linker::linkKnown($this->getTitle(), $evaluation->sge_score / $assignment->sga_value * 100 . '%', array(),
                                array('action' => 'evaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));
                    }
                } else {
                    $out .= Html::rawElement('td', null, 
                        Linker::linkKnown($this->getTitle(), '--', array(),
                            array('action' => 'evaluation', 'user' => $user->user_id, 'assignment' => $assignment->sga_id)));
                }
            }
            $out .= Html::closeElement('tr') . "\n";
        }
        $out .= Html::closeElement('table') . "\n";
        $this->getOutput()->addHTML($out);
    }


    # List all users
    public function showUsers () {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('user', '*');

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $out .= Html::element('caption', null, 'Users') . "\n";
        $out .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'name') .
            Html::element('th', null, 'real name')
        ) . "\n";
        foreach ( $res as $row ) {
            $out .= Html::rawElement('tr', null,
                Html::element('td', null, $row->user_id) .
                Html::element('td', null, $row->user_name) .
                Html::element('td', null, $row->user_real_name)
            ) . "\n";
        }
        $out .= Html::closeElement('table') . "\n";
        $this->getOutput()->addHTML($out);
    }


    # List all assignments
    public function showAssignments () {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('scholasticgrading_assignment', '*');

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $out .= Html::element('caption', null, 'Assignments') . "\n";
        $out .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'title') .
            Html::element('th', null, 'value') .
            Html::element('th', null, 'enabled') .
            Html::element('th', null, 'date')
        ) . "\n";
        foreach ( $res as $row ) {
            $out .= Html::rawElement('tr', null,
                Html::element('td', null, $row->sga_id) .
                Html::element('td', null, $row->sga_title) .
                Html::element('td', null, $row->sga_value) .
                Html::element('td', null, $row->sga_enabled) .
                Html::element('td', null, $row->sga_date)
            ) . "\n";
        }
        $out .= Html::closeElement('table') . "\n";
        $this->getOutput()->addHTML($out);
    }


    # List all evaluations
    public function showEvaluations () {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('scholasticgrading_evaluation', '*');

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable sortable')) . "\n";
        $out .= Html::element('caption', null, 'Evaluations') . "\n";
        $out .= Html::rawElement('tr', null,
            Html::element('th', null, 'user id') .
            Html::element('th', null, 'assignment id') .
            Html::element('th', null, 'score') .
            Html::element('th', null, 'enabled') .
            Html::element('th', null, 'date')
        ) . "\n";
        foreach ( $res as $row ) {
            $out .= Html::rawElement('tr', null,
                Html::element('td', null, $row->sge_user_id) .
                Html::element('td', null, $row->sge_assignment_id) .
                Html::element('td', null, $row->sge_score) .
                Html::element('td', null, $row->sge_enabled) .
                Html::element('td', null, $row->sge_date)
            ) . "\n";
        }
        $out .= Html::closeElement('table') . "\n";
        $this->getOutput()->addHTML($out);
    }

}
