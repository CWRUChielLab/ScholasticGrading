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
