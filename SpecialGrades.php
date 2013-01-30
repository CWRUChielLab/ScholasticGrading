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
        switch ( $action ) {
        case 'submit':
            $this->doSubmit();
        }

        $this->showForm();
        $this->showList();
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
                    array('action' => 'submit', 'param' => 'form-param'))),
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

}
