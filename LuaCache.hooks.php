<?php
/**
 * LuaCache
 * LuaCache Hooks
 *
 * @author  Robert Nix
 * @license MIT
 * @package LuaCache
 * @link    https://github.com/HydraWiki/LuaCache
 *
**/

namespace LuaCache;

class Hooks {
	/**
	 * Hook to register the LuaCache Lua library
	 *
	 * @access public
	 * @param  string $engine         Engine type
	 * @param  array &$extraLibraries Libraries to add
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.LuaCache'] = 'LuaCache\\LuaCacheLibrary';
		}
		return true;
	}
}
