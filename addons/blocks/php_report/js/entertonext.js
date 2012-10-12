// Required page globals
var lastevent;
var pgButtons;

function submitTheForm(e) {
    if (!e) e = window.event;
    //alert('submitTheForm(e): ' + e.type + ' ' + (e.which) ? e.which : e.keyCode);
    if (window.lastevent == 13) {
        button = (e.target) ? e.target : e.srcElement;
        //alert('submitTheFormNext_fromEnterKey: event(' + button + ') ' + button.value);
        if (button.value == "cancel") {
            // Switch cancel (from Enter) to next or finish button
            buttonId = -1;
            for (i = 0; i < window.pgButtons.length; ++i) {
                if (button == window.pgButtons[i]) {
                    for (j = i + 1; j < window.pgButtons.length && j <= (i + 2); ++j) {
                        //alert('submitTheForm(e): checking next button #' + j + ' ' + window.pgButtons[j].value);
                        if (window.pgButtons[j].value == "" ||
                            window.pgButtons[j].value == "finish") {
                            buttonId = j;
                            break;
                        }
                    }
                    break;
                }
            }
            if (buttonId >= 0) {
                button.id    = window.pgButtons[buttonId].id;
                button.name  = window.pgButtons[buttonId].name;
                button.value = window.pgButtons[buttonId].value;
            }
        }
    }
    return true;
}

function addScheduleListeners() {
    window.pgButtons = document.getElementsByTagName("button");
    for (i = 0; i < window.pgButtons.length; ++i) {
        if (window.pgButtons[i].value == "cancel") {
            //alert('addScheduleListeners: found cancel-button#' + i + ' , adding event callback to ' + window.pgButtons[i].id + ' ' +  window.pgButtons[i].name);
            window.pgButtons[i].onclick = function(e) { submitTheForm(e); };
        }
    }
    document.onkeydown = function(e) { window.lastevent = (e) ? e.which : window.event.keyCode; };
    document.onmousedown = function(e) { window.lastevent = (e) ? e.which : window.event.keyCode; };
}

addonload( function( ) { addScheduleListeners(); } );

