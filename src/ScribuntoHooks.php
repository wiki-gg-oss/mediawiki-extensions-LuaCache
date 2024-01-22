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

namespace MediaWiki\Extension\LuaCache;

class ScribuntoHooks {
	/**
	 * Hook to register the LuaCache Lua library
	 *
	 * @access public
	 * @param  string $engine         Engine type
	 * @param  array &$extraLibraries Libraries to add
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.LuaCache'] = LuaCacheLibrary::class;
		}
		return true;
	}
}
