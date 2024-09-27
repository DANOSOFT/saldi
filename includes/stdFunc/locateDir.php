<?php
if (!function_exists('locateDir')) {
	function locateDir($baseRelativeDir) {
		$i = 0;
		while (!file_exists($baseRelativeDir)) {
			$baseRelativeDir = '../'.$baseRelativeDir;
			$i++;
			if ($i>5) return '';
		}
		return $baseRelativeDir;
/*
		// format of $baseRelativeDir == "importfiler";
		//  "foo", "bar" etc.
		$currentDir = __DIR__;
		// Get the file path of the directory above the current directory
		$parentDir = dirname(__DIR__); //account path
		$baseRelativeDir = $parentDir . "/" . $baseRelativeDir;
		return $baseRelativeDir;
*/
		
	}
}
?>
