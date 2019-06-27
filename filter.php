<?php
/**
  * Filter to turn Pod urls into iframe for video integration,
  * like what the multimedia filter is doing.
  *
  * @package	filter
  * @subpackage	pod
  * @author		Gaël Mifsud <gael.mifsud@univ-lille.fr> / Obled Joel <joel.obled@univ-lille1.fr>
  * @copyright	2014-2020 DIP - Université de Lille
  * @license	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This classe can turn Pod's URLs into an iframe that contains the Pod's video player
 */
class filter_pod extends moodle_text_filter {

	/**
	 * Pod filter for Moodle
	 *
	 * @param string $text text that possibly contains a pod's url to filter
	 * @return string the text that contains urls into iframe
	 */
	public function filter($text, array $options = array()) {
		global $CFG, $COURSE, $PAGE;

		// Initialize default values in case the administrator has not completed correctly the global configuration of the filter
		$config['url']		= 'pod.univ-lille.fr';
		$config['size']		= 480;
		$config['width']	= 854;
		$config['height']	= 480;
		$config['width_interactive']	= 625;
		$config['height_interactive']	= 530;
		$courseconfig = array();

		// We retrieve the current course ID and then retrieve the context in which the filter runs
		$courseid		= (isset($COURSE->id)) ? $COURSE->id : null;
		$coursecontext	= context_course::instance($courseid);

		// We retrieve the filter parameters into the current running context
		$courseconfig = get_active_filters($coursecontext->id);

		// If no local parameter defines pod's server URL, we first search into the context, and then a global value in last resort
		if (isset($this->localconfig['url'])) {
			$config['url'] = $this->localconfig['url'];
		} elseif (isset($courseconfig['url'])) {
			$config['url'] = $courseconfig['url'];
		} elseif (isset($CFG->filter_pod_url) && ($CFG->filter_pod_url != null)) {
			$config['url'] = $CFG->filter_pod_url;
		}

		// Quick verification if url are not in the text to filter, to prevent unnecessary work then
		if (stripos($text, $config['url']) === false) {
			return $text;
		}

		// Depending on the existence or not of local parameters, contextual and then generals, we are defining the url's parameters values
		if (isset($this->localconfig['size'])) {
			$config['size'] = $this->localconfig['size'];
		} elseif (isset($courseconfig['size'])) {
			$config['size'] = $courseconfig['size'];
		} elseif (isset($CFG->filter_pod_size) && ($CFG->filter_pod_size != null)) {
			$config['size'] = $CFG->filter_pod_size;
		}

		if (isset($this->localconfig['width'])) {
			$config['width'] = $this->localconfig['width'];
		} elseif (isset($courseconfig['width'])) {
			$config['width'] = $courseconfig['width'];
		} elseif (isset($CFG->filter_pod_width) && ($CFG->filter_pod_width != null)) {
			$config['width'] = $CFG->filter_pod_width;
		}

		if (isset($this->localconfig['height'])) {
			$config['height'] = $this->localconfig['height'];
		} elseif (isset($courseconfig['height'])) {
			$config['height'] = $courseconfig['height'];
		} elseif (isset($CFG->filter_pod_height) && ($CFG->filter_pod_height != null)) {
			$config['height'] = $CFG->filter_pod_height;
		}

		// We stock values into the localconfig variable to recover them in the callback function later
		$this->localconfig['config'] = $config;

		$matches = array();

		// Regular expression to define a standard pod's url and avoid those already contained in a iframe
		$word = addslashes($config['url']);			// We protect the URL's slash
		$text = htmlspecialchars_decode($text);		// We filter the eventual &amp; and &quote; from the rich text-editor
		$text = preg_replace('/(<a href="|<video.*><source src=")(.*)(">.*<\/a>|">.*<\/video>)/', '$2', $text);	// Prevent tag a href or video source
		$iframetagpattern	= '(?P<ifr>iframe\s+src\s*=\s*")?';	// We are capturing an iframe with the "ifr" key
		// We are capturing the video URL
		$podpattern 		= '((?:https?\:)?(?:\/\/)?(?P<pod>'.$word.'\/[a-zA-Z\d\-\/_]*(video|video_priv)\/([a-zA-Z\d\-\/_]+|[a-zA-Z\d\-_]+\/)))';
		$parampattern		= '(?:([(\?|\&)a-zA-Z_]*=)([a-zA-Z\d]*))?';		// We are capturing the parameters in the URL
		
		// They cannot have more of 4 parameters in a pod's video url : is_iframe, start, size and autoplay
		$pat = '('.$iframetagpattern.$podpattern.$parampattern.$parampattern.$parampattern.$parampattern.')';
		// We run the replace :
		$text = preg_replace_callback($pat, array(&$this, 'filter_pod::filter_process_pod'), $text, -1, $cpt);
		
		// We return the filtered text
		return $text;
	}

	/**
	 * Function that retrieves the preg_replace result and
	 * uses the callback function to make the replace.
	 * It checks if we already have a iframe. If we have that, we don't replace.
	 * It checks also if we have a link (a href), to properly replace the tag.
	 * @param array $matches an array that contains the captured regular expressions
	 * @return string the text of the iframe that replaces the url
	 */
	function filter_process_pod($matches) {
		// We don't filter a pod's url already in an iframe
		if ($matches['ifr']) {
			return $matches[0];
		} else {
			return replace_url($matches, $this->localconfig['config']);
		}
	}
}

/**
 * Function that returns the text with the iframe
 *
 * @param array $matches an array that contains the captured regular expressions
 * @param array $config an array that contains the default parameters for the url
 * @return string the text of the iframe that replaces the video url
 */
function replace_url($matches, $config) {

	$u = $matches['pod'];
	
	// By default, we define the values according to the filter configuration in the activity
	$width 		= ' width="'.$config['width'].'" ';
	$height 	= ' height="'.$config['height'].'" ';
	$size 		= '&size='.$config['size'];
	$autoplay	= '';
	$start		= '';
	$interactive= '';

	// We retrieve the possible parameters in the video url
	while(list(, $m)=each($matches)) {
		switch($m) {
			case "&start=":
			case "?start=":
				$start			= "&start=".current($matches);
				break;
			case "&size=":
			case "?size=":
				$size 			= "&size=".current($matches);
				break;
			case "&autoplay=":
			case "?autoplay=":
				$autoplay 		= "&autoplay=".current($matches);
				break;
			case "&interactive=":
			case "?interactive=":
				$interactive 	= "&interactive=".current($matches);
				if(current($matches)=="true") {
					$width 		= ' width="'.$config['width_interactive'].'" ';
					$height 	= ' height="'.$config['height_interactive'].'" ';
				}
				break;
		}
	}

	// We return the filtered url in an iframe with all the parameters
	return '<iframe src="//'.$u.'?is_iframe=true'.$size.$start.$autoplay.$interactive.'"'.$width.$height.' style="padding: 0; margin: 0; border: 0" allowfullscreen></iframe>';
}

/**
 * https://docs.moodle.org/dev/Filter_enable/disable_by_context#Getting_filter_configuration
 *
 * Get the list of active filters, in the order that they should be used
 * for a particular context.
 *
 * @param object $context a context
 * @return array an array where the keys are the filter names and the values are any local
 *      configuration for that filter, as an array of name => value pairs
 *      from the filter_config table. In a lot of cases, this will be an
 *      empty array.
 */
function get_active_filters($contextid) {
    global $DB;
    
    $sql = "SELECT fc.id, active.FILTER, fc.name, fc.VALUE
            FROM (SELECT f.FILTER
            FROM {filter_active} f
            JOIN {context} ctx ON f.contextid = ctx.id
            WHERE ctx.id IN ($contextid) AND f.FILTER LIKE 'podlille1'
            GROUP BY FILTER
            HAVING MAX(f.active * ctx.depth) > -MIN(f.active * ctx.depth)
            ORDER BY MAX(f.sortorder)) active
            LEFT JOIN {filter_config} fc ON fc.FILTER = active.FILTER AND fc.contextid = $contextid";
    
    $courseconfig = array();
    
    if ($results = $DB->get_records_sql($sql, null)) {
        // On récupère les paramètres du filtre, locaux au contexte dont l'ID a été passé en paramètre
        foreach ($results as $res) {
            if ($res->filter=="podlille1") {
                switch($res->name) {
                case "url":
                    $courseconfig['url']   = $res->value;
                    break;
                case "size":
                    $courseconfig['size']  = $res->value;
                    break;
                case "height":
                    $courseconfig['height']= $res->value;
                    break;
                case "width":
                    $courseconfig['width'] = $res->value;
                    break;
                }
            }
        }
    }
    
    return $courseconfig;
}

?>
