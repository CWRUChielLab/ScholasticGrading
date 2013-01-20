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
        $out = $this->getOutput();
        $user = $this->getUser();
        $this->setHeaders();

        # Get request data from, e.g.
        $param = $request->getText('param');

        # Do stuff
        # ...
        $wikitext = 'Hello, ' . $user . '!';
        $out->addWikiText($wikitext);


        # Report the contents of the database table test
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('test', '*');

        $output = '<table class="wikitable"><tr><th>Foo</th></tr>';
        foreach ( $res as $row ) {
            $output = $output . '<tr><td>' . $row->foo . '</td></tr>';
        }
        $output = $output . '</table>';
        $out->addHTML($output);


        # Report the contents of the database table test
        #$dbr = wfGetDB(DB_SLAVE);
        #$res = $dbr->select('test', '*');
        #
        #$out->addWikiText('{| class="wikitable" ');
        #foreach ( $res as $row ) {
        #    $out->addWikiText('|- ', false);
        #    $out->addWikiText('| ' . $row->foo . ' ', false);
        #}
        #$out->addWikiText('|}', false);
    }

}
