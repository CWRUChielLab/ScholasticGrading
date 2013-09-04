/**
 * JavaScript for Special:Grades
 */

$(function() {
    $( ".sg-date-input" ).datepicker( {
        dateFormat: "yy-mm-dd",
        showOtherMonths: true,
        selectOtherMonths: true,
    } );
});

$(function() {
    $( "#sg-gradegrid-tabs" ).tabs();
});
