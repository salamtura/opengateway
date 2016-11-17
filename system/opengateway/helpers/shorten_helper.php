<?php

/**
* Shorten
*
* Shortens a string, leaves a trailing "..."
*
* @param string $string
* @param int $length
* @param boolean $retain_whole_words
*
* @copyright Electric Function, Inc.
* @package OpenGateway
* @author Electric Function, Inc.
*/
function shorten ($string, $length, $retain_whole_words = FALSE) {
	$string = trim($string);
	
	if (strlen(strip_tags($string)) > $length) {
		if ($retain_whole_words == FALSE) {
			$string = substr($string, 0, ($length - 3));
			$string .= '&hellip;';
		}
		else {
			$string = preg_replace('/\s+?(\S+)?$/', '', substr($string, 0, ($length - 3)));
			$string .= '&hellip;';
		}
		
		return $string;
	}
	else {
		return $string;
	}
}