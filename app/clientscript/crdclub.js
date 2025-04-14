




/*
     FILE ARCHIVED ON 7:52:30 Apr 1, 2016 AND RETRIEVED FROM THE
     INTERNET ARCHIVE ON 21:11:06 Jul 13, 2016.
     JAVASCRIPT APPENDED BY WAYBACK MACHINE, COPYRIGHT INTERNET ARCHIVE.

     ALL OTHER CONTENT MAY ALSO BE PROTECTED BY COPYRIGHT (17 U.S.C.
     SECTION 108(a)(3)).
*/
/**
 * Different JS from header and other templates
 */

function open_external(url, id) {
    w = window.open('about:blank', id);
    w.document.write('<meta http-equiv="refresh" content="0;url='+url+'">');
    w.document.close();
    return false;
}

function vms_switch_id(F, E) {
    if (F == "") {
        return
    }
    var B = new String(window.location);
    var A = new String("");
    B = B.split("#");
    if (B[1]) {
        A = "#" + B[1]
    }
    B = B[0];
    if (B.indexOf(E + "id=") != -1 && is_regexp) {
        var D = new RegExp(E + "id=\\d+&?");
        B = B.replace(D, "")
    }
    if (B.indexOf("?") == -1) {
        B += "?"
    } else {
        lastchar = B.substr(B.length - 1);
        if (lastchar != "&" && lastchar != "?") {
            B += "&"
        }
    }
    window.location = B + E + "id=" + F + A
}

(function($) {
$(document).ready(function() {
    $('#close').trigger('closeModal');
    $('#support-notice').easyModal({
        closeButtonClass: ".vr-close",
        'zIndex': function () {
            return 100;
        }
    });
    $('#support-button').click(function (e) {
        $('#support-notice').trigger('openModal');
        e.preventDefault();
    });
})
})(jQuery)