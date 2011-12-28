/**
 * Sets the content of a div based on a path of classnames to follow from a starting element
 *
 * @param  start_element  The parent div in the DOM tree
 * @param  class_tree     The list of classes to follow
 * @param  level          How deep we are into the tree
 * @param  new_content    The HTML to set on the appropriate element
 */
function set_content_in_class_tree(start_element, class_tree, level, new_content) {
    for(var i = 0; i < start_element.childNodes.length; i++) {
    	if(start_element.childNodes[i].getAttribute("class") == class_tree[level]) {
    		if(level >= (class_tree.length - 1)) {
    			start_element.childNodes[i].innerHTML = new_content;
    		} else {
    			set_content_in_class_tree(start_element.childNodes[i], class_tree, level + 1, new_content);
    		}
    	}
    }
}

/** 
 * Toggles the display of the report
 * 
 * @param  path     The path to the dynamic report-handling ajax script
 * @param  element  The image clicked on
 * @param  id       Report's id
 */
function toggle_report_block(path, element, id, newTitle, defaultTitle) {
    
	//toggle the image
    if(element.src.indexOf("plus") != '-1') {
    	element.src = element.src.replace("plus", "minus");
    	element.title = newTitle;
    	element.alt = newTitle;
    } else {
    	element.src = element.src.replace("minus", "plus");
    	element.title = defaultTitle;
    	element.alt = defaultTitle;
    }
    
    //on success, find the report body and set its contents
    var php_report_success = function(o) {
    	var container = element.parentNode.parentNode.parentNode;
    	set_content_in_class_tree(container, new Array('content', 'php_report_block', 'php_report_body'), 0, o.responseText);
    	// Check for container's name, if it is undefined, it causes errors with my_handler
    	if(container.name) {
        	my_handler = new associate_link_handler(path + 'dynamicreport.php', 'php_report_body_' + id);
        	my_handler.make_links_internal();
    	}
    }
    
    var php_report_failure = function(o) {
    	alert("failure: " + o.responseText);
    }
    
    //similar to profile_value.js
    var callback = {
        success: php_report_success,
        failure: php_report_failure
    }
    
    var requestURL = path + "dynamicreport.php?id=" + id;
    
    YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
}


/**
 * Toggle ClassName
 * 
 * Toggles the display of the report content
 * 
 * @param  path     		The path to the dynamic report-handling ajax script
 * @param  imageElement		The input element clicked on
 * @param  newClassname		alternate classname for the element being toggled
 * @param  defaultClassname	default classname for the element being toggled
 * @param  newImagename		alternate image for the element being toggled
 * @param  defaultImagename	default image for the element being toggled
 * @param  newTitlename		alternate title for the element being toggled
 * @param  defaultTitlename	default title for the element being toggled
 * @param  reportId       	Report's id
 */

function toggleClassname (path, imageElement, newClassname, defaultClassname, newImagename, defaultImagename, newTitle, defaultTitle, reportId) {

	// Change class for image element
	if (hasClass( imageElement, defaultClassname)){
		var re = new RegExp("(^|\\s)" + defaultClassname + "(\\s|$)");
		imageElement.className = imageElement.className.replace(re, ' '+ newClassname +' ');
		imageElement.alt = newTitle;
		imageElement.title = newTitle;
		imageElement.src = newImagename;

	} else if (hasClass( imageElement, newClassname)){
		var re = new RegExp("(^|\\s)" + newClassname + "(\\s|$)");
		imageElement.className = imageElement.className.replace(re, ' '+ defaultClassname +' ');
		imageElement.alt = defaultTitle;
		imageElement.title = defaultTitle;
		imageElement.src = defaultImagename;

	} else
		imageElement.className += ' ' + newClassname;
	
	
	//on success, find the report body and set its contents
    var php_report_success = function(o) {
    	var container = imageElement.parentNode.parentNode.parentNode;
    	set_content_in_class_tree(container, new Array('content', 'php_report_block', 'php_report_body'), 0, o.responseText);    		
    	// Check for container's name, if it is undefined, it causes errors with my_handler
    	if(container.name) {
        	my_handler = new associate_link_handler(path + 'dynamicreport.php', 'php_report_body_' + reportId);
        	my_handler.make_links_internal();
    	}
    }

	var php_report_failure = function(o) {
    	alert("failure: " + o.responseText);
    }
    
    //similar to profile_value.js
    var callback = {
        success: php_report_success,
        failure: php_report_failure
    }
    
    var requestURL = path + "dynamicreport.php?id=" + reportId;
    
    YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
}

/**
 * Has Class? (Matt Kruse)
 * Kruse's hasClass, with slight modification
 * Determine if an object or class string contains a given class.
 * see http://groups.google.com/group/comp.lang.javascript/browse_thread/thread/b68cac304ee6de78/e445c1df18698a3f?lnk=gst&q=hasclass&rnum=3
 */

function hasClass (obj, className) {

	if (typeof obj == 'undefined' || obj==null || !RegExp) {
		return false;
	}

	var re = new RegExp("(^|\\s)" + className + "(\\s|$)");
	if (typeof(obj)=="string") {
		return re.test(obj);
	}
	else if (typeof(obj)=="object" && obj.className) {
		return re.test(obj.className);
	}
	return false;
};