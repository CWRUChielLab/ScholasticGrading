<?php

/**
 * Implements Special:Grades
 *
 * @ingroup SpecialPage
 *
 * @author Jeffrey Gill <jeffrey.p.gill@gmail.com>
 */


class SpecialGrades extends SpecialPage {

    function __construct() {
        parent::__construct('Grades');
    }


    function execute ( $par ) {
        $request = $this->getRequest();
        $user = $this->getUser();
        $this->setHeaders();

        $this->getOutput()->addModules( 'ext.ScholasticGrading.assignment-date' );
        $this->getOutput()->addModules( 'ext.ScholasticGrading.evaluation-date' );
        $this->getOutput()->addModules( 'ext.ScholasticGrading.vertical-text' );

        # Get request data from, e.g.
        $param = $request->getText('param');

        # Do stuff
        # ...
        $wikitext = 'Hello, ' . $user . '!';
        $this->getOutput()->addWikiText($wikitext);

        # Process requests
        $action = $par ? $par : $request->getVal('action', $par);
        switch ( $action ) {
        case 'submitAssignment':
            $this->doAssignmentSubmit();
            break;
        case 'submitEvaluation':
            $this->doEvaluationSubmit();
            break;
        }

        $this->showAssignmentForm();
        $this->showAssignments();

        $this->showEvaluationForm();
        $this->showEvaluations();

        $this->showUsers();
        $this->showGradeGrid();
    }


    # Create an assignment
    public function doAssignmentSubmit () {
        $request = $this->getRequest();
        $assignmentTitle   = $request->getVal('assignment-title');
        $assignmentValue   = $request->getVal('assignment-value');
        $assignmentEnabled = $request->getCheck('assignment-enabled') ? 1 : 0;
        $assignmentDate    = $request->getVal('assignment-date');

        $dbw = wfGetDB( DB_MASTER );
        $dbw->insert('scholasticgrading_assignment', array(
            'sga_title'   => $assignmentTitle,
            'sga_value'   => $assignmentValue,
            'sga_enabled' => $assignmentEnabled,
            'sga_date'    => $dbw->timestamp($assignmentDate . ' 00:00:00'),
        ));

        if ( $dbw->affectedRows() === 0 ) {
            $this->getOutput()->addWikiText('Database unchanged.');
        } else {
            $this->getOutput()->addWikiText('\'\'\'"' . $assignmentTitle . '" added!\'\'\'');

            $log = new LogPage('grades', false);
            $log->addEntry('addAssignment', $this->getTitle(), $assignmentTitle);
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

        $dbw = wfGetDB( DB_MASTER );
        $dbw->insert('scholasticgrading_evaluation', array(
            'sge_user_id'       => $evaluationUser,
            'sge_assignment_id' => $evaluationAssignment,
            'sge_score'         => $evaluationScore,
            'sge_enabled'       => $evaluationEnabled,
            'sge_date'          => $dbw->timestamp($evaluationDate . ' 00:00:00'),
        ));

        if ( $dbw->affectedRows() === 0 ) {
            $this->getOutput()->addWikiText('Database unchanged.');
        } else {
            $this->getOutput()->addWikiText('\'\'\'Score for user ' . $evaluationUser . ' for "' . $evaluationAssignment . '" added!\'\'\'');

            $log = new LogPage('grades', false);
            $log->addEntry('addEvaluation', $this->getTitle(), 'User ' . $evaluationUser . ' for ' . $evaluationAssignment);
        }
    }


    # Show the assignment creation form
    public function showAssignmentForm () {
        $out = '';
        $out .= Xml::fieldset( "Create a new assignment",
            Html::rawElement('form', array('method' => 'post',
                'action' => $this->getTitle()->getLocalUrl(
                    array('action' => 'submitAssignment'))),
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
                Xml::submitButton('Create assignment')
            )
        );
        $this->getOutput()->addHTML($out);
    }


    # Show the evaluation creation form
    public function showEvaluationForm () {
        $out = '';
        $out .= Xml::fieldset( "Create a new evaluation",
            Html::rawElement('form', array('method' => 'post',
                'action' => $this->getTitle()->getLocalUrl(
                    array('action' => 'submitEvaluation'))),
                 Html::rawElement('table', null,
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('User:', 'evaluation-user')) .
                        Html::rawElement('td', null, Xml::input('evaluation-user', 20, '', array('id' => 'evaluation-user')))
                    ) .
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('Assignment:', 'evaluation-assignment')) .
                        Html::rawElement('td', null, Xml::input('evaluation-assignment', 20, '', array('id' => 'evaluation-assignment')))
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
                Xml::submitButton('Create evaluation')
            )
        );
        $this->getOutput()->addHTML($out);
    }


    # Show the grade grid
    public function showGradeGrid () {
        $dbr = wfGetDB(DB_SLAVE);
        $users = $dbr->select('user', '*');
        $assignments = $dbr->select('scholasticgrading_assignment', '*');

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable')) . "\n";
        $out .= Html::openElement('tr');
        $out .= Html::element('th', null, '') . Html::element('th', null, '');
        foreach ( $users as $user ) {
            $out .= Html::rawElement('th', array('class' => 'vertical'),
                Html::element('div', array('class' => 'vertical'), $user->user_real_name)
            );
        }
        $out .= Html::closeElement('tr') . "\n";
        foreach ( $assignments as $assignment ) {
            $out .= Html::openElement('tr');
            $out .= Html::element('th', null, $assignment->sga_date);
            $out .= Html::element('th', null, $assignment->sga_title);
            foreach ( $users as $user ) {
                $evaluations = $dbr->select('scholasticgrading_evaluation', '*',
                    array('sge_user_id' => $user->user_id, 'sge_assignment_id' => $assignment->sga_id));
                if ( $evaluations->numRows() > 0 ) {
                    $out .= Html::element('td', null, $evaluations->next()->sge_score / $assignment->sga_value * 100 . '%');
                } else {
                    $out .= Html::element('td', null, '');
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
