/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.7 Patch Level 2
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2011 vBulletin Solutions, Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
var tag_add_comp;if(fetch_object("tag_edit_link")){YAHOO.util.Event.on(fetch_object("tag_edit_link"),"click",tag_edit_click)}function tag_edit_click(A){YAHOO.util.Event.stopEvent(A);if(!this.tag_editor){this.tag_editor=new vB_AJAX_TagThread("tag_list_cell",this.id)}this.tag_editor.fetch_form()}function vB_AJAX_TagThread(B,C){this.edit_form="tag_edit_form";this.edit_cancel="tag_edit_cancel";this.form_progress="tag_form_progress";this.submit_progress="tag_edit_progress";this.form_visible=false;this.do_ajax_submit=true;this.tag_container=B;var A=fetch_object(C).href.match(/(\?|&)t=([0-9]+)/);this.threadid=A[2]}vB_AJAX_TagThread.prototype.fetch_form=function(){if(!this.form_visible){YAHOO.util.Connect.asyncRequest("POST","threadtag.php?t="+this.threadid,{success:this.handle_ajax_form,failure:this.handle_ajax_form_error,timeout:vB_Default_Timeout,scope:this},SESSIONURL+"securitytoken="+SECURITYTOKEN+"&t="+this.threadid+"&ajax=1");if(fetch_object(this.form_progress)){fetch_object(this.form_progress).style.display=""}}};vB_AJAX_TagThread.prototype.handle_ajax_form=function(C){if(C.responseXML&&!this.form_visible){var B=C.responseXML.getElementsByTagName("error");if(B.length){alert(B[0].firstChild.nodeValue)}else{if(C.responseXML.getElementsByTagName("html")[0]){var A=fetch_object(this.tag_container);A.origInnerHTML=A.innerHTML;A.innerHTML=C.responseXML.getElementsByTagName("html")[0].firstChild.nodeValue;YAHOO.util.Event.on(this.edit_form,"submit",this.submit_tag_edit,this,true);YAHOO.util.Event.on(this.edit_cancel,"click",this.cancel_tag_edit,this,true);if(fetch_object("tag_add_wrapper_menu")&&fetch_object("tag_add_input")){vbmenu_register("tag_add_wrapper",true);tag_add_comp=new vB_AJAX_TagSuggest("tag_add_comp","tag_add_input","tag_add_wrapper");tag_add_comp.allow_multiple=true;var D=C.responseXML.getElementsByTagName("delimiters")[0];if(D&&D.firstChild){tag_add_comp.set_delimiters(D.firstChild.nodeValue)}fetch_object("tag_add_input").focus();fetch_object("tag_add_input").focus()}this.form_visible=true}}}if(fetch_object(this.form_progress)){fetch_object(this.form_progress).style.display="none"}};vB_AJAX_TagThread.prototype.handle_ajax_form_error=function(A){vBulletin_AJAX_Error_Handler(A);window.location="threadtag.php?"+SESSIONURL+"t="+this.threadid};vB_AJAX_TagThread.prototype.submit_tag_edit=function(B){if(this.do_ajax_submit){YAHOO.util.Event.stopEvent(B);var A=new vB_Hidden_Form(null);A.add_variables_from_object(fetch_object(this.edit_form));YAHOO.util.Connect.asyncRequest("POST","threadtag.php?do=managetags&t="+this.threadid,{success:this.handle_ajax_submit,failure:this.handle_ajax_submit_error,timeout:vB_Default_Timeout,scope:this},SESSIONURL+"securitytoken="+SECURITYTOKEN+"&do=managetags&ajax=1&"+A.build_query_string());if(fetch_object(this.submit_progress)){fetch_object(this.submit_progress).style.display=""}}};vB_AJAX_TagThread.prototype.handle_ajax_submit=function(C){if(C.responseXML){var A=C.responseXML.getElementsByTagName("error");if(A.length){alert(A[0].firstChild.nodeValue)}else{var D=C.responseXML.getElementsByTagName("taghtml");if(D.length&&D[0].firstChild&&D[0].firstChild.nodeValue!==""){fetch_object(this.tag_container).innerHTML=D[0].firstChild.nodeValue}var B=C.responseXML.getElementsByTagName("warning");if(B.length&&B[0].firstChild){alert(B[0].firstChild.nodeValue)}this.form_visible=false}}if(fetch_object(this.submit_progress)){fetch_object(this.submit_progress).style.display="none"}};vB_AJAX_TagThread.prototype.handle_ajax_submit_error=function(A){vBulletin_AJAX_Error_Handler(A);this.do_ajax_submit=false;fetch_object(this.edit_form).submit()};vB_AJAX_TagThread.prototype.cancel_tag_edit=function(){var A=fetch_object(this.tag_container);if(A.origInnerHTML){A.innerHTML=A.origInnerHTML;A.origInnerHTML=""}if(fetch_object(this.form_progress)){fetch_object(this.form_progress).style.display="none"}this.form_visible=false};