<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Lukas <admin@sosna.pl>
**/

class phunction_View extends phunction 
{
	
	/**
	 *	Function returns an array of defined View variables	 
	 */
	function Data() 
	{
		return (array)get_object_vars($this);
	}
	
	/**
	 *	Sets or gets path to layout. 
	 *	Layout file should be a php file containing $innerContent variable which will be replaced by the actual view content.
	 *	This works only while using ph()->View->Render().
	 */
	function Layout($path=null) 
	{
		static $layout;
		if ($path === null && isset($layout)) return $layout;
		else if ($path && is_file(($path = str_replace('::', DIRECTORY_SEPARATOR, $path)) . '.php') === true) $layout = $path;
		else $layout = null;
	}
	
	/**
	 *	Returns view file rendered inside current layout.
	 */
	function Render($path, $minify=false, $return=false) 
	{
		$layout = ph()->View->Layout();
		
		if ($layout === null) return ph()->View($path,null,$minify,$return);
		
		return ph()->View(
			$layout, 
			array('innerContent' => ph()->View($path,null,$minify,true)) + ph()->View->Data(), 
			$minify, 
			$return
		);
	}
	
}