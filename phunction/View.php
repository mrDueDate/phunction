<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Lukas <admin@sosna.pl>
**/

class phunction_View extends phunction 
{
	
	function Data() 
	{
		return get_object_vars($this);
	}
	
}