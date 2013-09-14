/**
 * JavaScript for Special:Grades
 */

var ScholasticGrading = {

    // Hide or show user scores tables
    toggleUserScoresTables: function( status ) {

        if ( status ) {
            jQuery('table.sg-userscorestable').hide();
        } else {
            jQuery('table.sg-userscorestable').show();
        }

        var msg;
        if ( status ) {
            msg = 'Show tables';
        } else {
            msg = 'Hide tables';
        }

        jQuery( 'a.sg-toggleuserscores' ).one( 'click', function() {
            ScholasticGrading.toggleUserScoresTables( status ? 0 : 1 );
        } );
        jQuery( 'a.sg-toggleuserscores' ).text( msg );

    },

    // Hide or show unevaluated assignments in user scores tables
    toggleUnevaluatedAssignments: function( status ) {

        if ( status ) {
            jQuery('tr.sg-userscorestable-unevaluated').hide();
        } else {
            jQuery('tr.sg-userscorestable-unevaluated').show();
        }

        var msg;
        if ( status ) {
            msg = 'Show unevaluated assignments';
        } else {
            msg = 'Hide unevaluated assignments';
        }

        jQuery( 'a.sg-toggleunevaluated' ).one( 'click', function() {
            ScholasticGrading.toggleUnevaluatedAssignments( status ? 0 : 1 );
        } );
        jQuery( 'a.sg-toggleunevaluated' ).text( msg );

    },

};


// Execute when the page is loaded
jQuery( document ).ready( function() {

    // Create group tabs for the grade grid
    jQuery( '#sg-gradegrid-tabs' ).tabs();

    // Provide interacive calendars for date input fields
    jQuery( '.sg-date-input' ).datepicker( {
        dateFormat: 'yy-mm-dd',
        showOtherMonths: true,
        selectOtherMonths: true,
    } );

    // Attach on-click event handlers for togglers
    jQuery( 'a.sg-toggleuserscores' ).one( 'click', function() {
        ScholasticGrading.toggleUserScoresTables( 1 );
    } );
    jQuery( 'a.sg-toggleunevaluated' ).one( 'click', function() {
        ScholasticGrading.toggleUnevaluatedAssignments( 1 );
    } );

} );

