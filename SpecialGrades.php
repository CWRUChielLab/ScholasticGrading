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

        # Get request data from, e.g.
        $param = $request->getText('param');

        # Do stuff
        # ...
        $wikitext = 'Hello, ' . $user . '!';
        $this->getOutput()->addWikiText($wikitext);

        # Process requests
        $action = $par ? $par : $request->getVal('action', $par);
        $form   = $par ? $par : $request->getVal('form', $par);
        switch ( $action ) {
        case 'submit':
            switch ( $form ) {
            case 'test':
                $this->doSubmit();
                break;
            case 'assignment':
                $this->doAssignmentSubmit();
                break;
            }
        }

        //$this->showForm();
        //$this->showList();

        $this->showAssignmentForm();
        $this->showAssignments();
    }


    public function doSubmit () {
        $request = $this->getRequest();
        $input = $request->getVal('input-name');

        $dbw = wfGetDB( DB_MASTER );
        $dbw->insert('test', array('foo' => $input));

        if ( $dbw->affectedRows() === 0 ) {
            $this->getOutput()->addWikiText('Database unchanged.');
        } else {
            $this->getOutput()->addWikiText('\'\'\'"' . $input . '" added!\'\'\'');

            $log = new LogPage('grades', false);
            $log->addEntry('add', $this->getTitle(), $input);
        }
    }


    public function showForm () {
        # Add entries to the database table test
        $out = '';
        $out .= Xml::fieldset( "fieldset",
            Html::rawElement('form', array('method' => 'post',
                'action' => $this->getTitle()->getLocalUrl(
                    array('action' => 'submit', 'form' => 'test', 'param' => 'form-param'))),
                 Html::rawElement('table', null,
                    Html::rawElement('tr', null,
                        Html::rawElement('td', null, Xml::label('label-label', 'input-id')) .
                        Html::rawElement('td', null, Xml::input('input-name', 20, 'input-value', array('id' => 'input-id'))) .
                        Html::rawElement('td', null, Xml::submitButton('submit-value'))
                    )
                )
            )
        );
        $this->getOutput()->addHTML($out);
    }


    public function showList () {
        # Report the contents of the database table test
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('test', '*');

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable')) . "\n";
        $out .= Html::rawElement('tr', null,
            Html::element('th', null, 'id') .
            Html::element('th', null, 'foo')
        ) . "\n";
        foreach ( $res as $row ) {
            $out .= Html::rawElement('tr', null,
                Html::element('td', null, $row->test_id) .
                Html::element('td', null, $row->foo)
            ) . "\n";
        }
        $out .= Html::closeElement('table');
        $this->getOutput()->addHTML($out);
    }


    public function doAssignmentSubmit () {
        $request = $this->getRequest();
        $assignmentTitle = $request->getVal('assignment-title');
        $assignmentValue = $request->getVal('assignment-value');
        $assignmentEnabled = $request->getCheck('assignment-enabled') ? 1 : 0;

        $dbw = wfGetDB( DB_MASTER );
        $dbw->insert('scholasticgrading_assignment', array(
            'sga_title' => $assignmentTitle,
            'sga_value' => $assignmentValue,
            'sga_enabled' => $assignmentEnabled
        ));

        if ( $dbw->affectedRows() === 0 ) {
            $this->getOutput()->addWikiText('Database unchanged.');
        } else {
            $this->getOutput()->addWikiText('\'\'\'"' . $assignmentTitle . '" added!\'\'\'');

            $log = new LogPage('grades', false);
            $log->addEntry('add', $this->getTitle(), $assignmentTitle);
        }
    }


    public function showAssignmentForm () {
        # Add entries to the database table test
        $out = '';
        $out .= Xml::fieldset( "Create a new assignment",
            Html::rawElement('form', array('method' => 'post',
                'action' => $this->getTitle()->getLocalUrl(
                    array('action' => 'submit', 'form' => 'assignment'))),
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
                    )
                ) .
                Xml::submitButton('Create assignment')
            )
        );
        $this->getOutput()->addHTML($out);
    }


    public function showAssignments () {
        # List all assignments
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('scholasticgrading_assignment', '*');

        $out = '';
        $out .= Html::openElement('table', array('class' => 'wikitable')) . "\n";
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

}
