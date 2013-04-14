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

        # Get request data from, e.g.
        $param = $request->getText('param');

        # Do stuff
        # ...
        $wikitext = 'Hello, ' . $user . '!';
        $this->getOutput()->addWikiText($wikitext);

        # Process requests
        $action = $par ? $par : $request->getVal('action', $par);
        switch ( $action ) {
        case 'submit':
            $this->doAssignmentSubmit();
        }

        $this->showAssignmentForm();
        $this->showAssignments();

        $this->showEvaluations();
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
            $log->addEntry('add', $this->getTitle(), $assignmentTitle);
        }
    }


    # Show the assignment creation form
    public function showAssignmentForm () {
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
                Xml::submitButton('Create assignment')
            )
        );
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
        $out .= Html::closeElement('table');
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
        $out .= Html::closeElement('table');
        $this->getOutput()->addHTML($out);
    }

}
