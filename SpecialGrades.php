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

    function execute($par) {
        $request = $this->getRequest();
        $output = $this->getOutput();
        $this->setHeaders();

        # Get request data from, e.g.
        $param = $request->getText('param');

        # Do stuff
        # ...
        $wikitext = 'Hello world!';
        $output->addWikiText($wikitext);
    }
}