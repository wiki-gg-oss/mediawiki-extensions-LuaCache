<?php
/**
 * LuaCache
 * LuaCache Scribunto Lua Library
 *
 * @author  Robert Nix
 * @license MIT
 * @package LuaCache
 * @link    https://github.com/HydraWiki/LuaCache
 *
**/

namespace MediaWiki\Extension\LuaCache;

use BagOStuff;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\MediaWikiServices;

class LuaCacheLibrary extends LibraryBase {
	private BagOStuff $cache;

	public function __construct( LuaEngine $engine ) {
		parent::__construct( $engine );

		$this->cache = MediaWikiServices::getInstance()->getMainObjectStash();
	}

	/**
	 * Register the Lua extension with Scribunto
	 *
	 * @access public
	 * @return array Lua package
	 */
	public function register() {
		$luaPath = __DIR__ . '/../lua';

		// Register the binser package dependency
		$this->getEngine()->registerInterface(
			"$luaPath/binser.lua", []
		);

		// Register the LuaCache package
		return $this->getEngine()->registerInterface(
			"$luaPath/mw.ext.LuaCache.lua", [
				'get'      => [$this, 'get'],
				'set'      => [$this, 'set'],
				'getMulti' => [$this, 'getMulti'],
				'setMulti' => [$this, 'setMulti'],
				'delete'   => [$this, 'delete'],
			]
		);
	}

	/**
	 * Get an item from the main object cache
	 *
	 * @access public
	 * @param  string Cache key
	 * @return array  Lua result array containing false or the string value
	 */
	public function get( $key ) {
		$this->checkType( 'get', 1, $key, 'string' );

		$cacheKey = $this->cache->makeKey( 'LuaCache', $key );
		return [ $this->cache->get( $cacheKey ) ];
	}

	/**
	 * Set an item in the main object cache
	 *
	 * @access public
	 * @param  string  $key     Cache key
	 * @param  string  $value   Cache value
	 * @param  integer $exptime Expiration time in seconds
	 * @return array            Lua result array containing boolean success
	 */
	public function set( $key, $value, $exptime ) {
		$this->checkType( 'set', 1, $key, 'string' );
		$this->checkType( 'set', 2, $value, 'string' );
		$this->checkTypeOptional( 'set', 3, $exptime, 'number', 0 );

		$cacheKey = $this->cache->makeKey( 'LuaCache', $key );
		return [ $this->cache->set( $cacheKey, $value, $exptime ) ];
	}

	/**
	 * Get multiple items from the main object cache
	 *
	 * @access public
	 * @param  array $keys Array of string cache keys
	 * @return array       Lua result array containing an array of results (false or string)
	 */
	public function getMulti( $keys ) {
		$this->checkType( 'getMulti', 1, $keys, 'table' );

		$cacheKeys = [];
		$cacheKeyToKey = [];
		foreach ( $keys as $key ) {
			$keyType = $this->getLuaType( $key );
			if ( $keyType !== 'string' ) {
				throw new Scribunto_LuaError(
					"bad argument 1 to getMulti (string expected for table key, get $keyType)"
				);
			}

			$cacheKey = $this->cache->makeKey( 'LuaCache', $key );
			$cacheKeys[] = $cacheKey;
			$cacheKeyToKey[$cacheKey] = $key;
		}
		$cacheData = $this->cache->getMulti( $cacheKeys );

		// Rename the keys to match what was passed in
		$data = [];
		foreach ( $cacheData as $cacheKey => $value ) {
			if ( array_key_exists( $cacheKey, $cacheKeyToKey ) ) {
				$key = $cacheKeyToKey[$cacheKey];
				$data[$key] = $value;
			}
		}
		return [ $data ];
	}

	/**
	 * Set multiple items in the main object cache
	 *
	 * @access public
	 * @param  array   $data    Array of string keys => string values
	 * @param  integer $exptime Expiration time in seconds
	 * @return array            Lua result array containing an array of boolean results
	 */
	public function setMulti( $data, $exptime ) {
		$this->checkType( 'setMulti', 1, $data, 'table' );
		$this->checkTypeOptional( 'setMulti', 2, $exptime, 'number', 0 );

		$cacheData = [];
		foreach ( $data as $key => $value ) {
			$keyType = $this->getLuaType( $key );
			if ( $keyType !== 'string' ) {
				throw new LuaError(
					"bad argument 1 to setMulti (string expected for table key, get $keyType)"
				);
			}
			$valueType = $this->getLuaType( $value );
			if ( $valueType !== 'string' ) {
				throw new LuaError(
					"bad argument 1 to setMulti (string expected for table value, get $valueType)"
				);
			}

			$cacheKey = $this->cache->makeKey( 'LuaCache', $key );
			$cacheData[$cacheKey] = $value;
		}
		return [ $this->cache->setMulti( $cacheData, $exptime ) ];
	}

	/**
	 * Set multiple items in the main object cache
	 *
	 * @access public
	 * @param  string $key Name of the item to delete
	 * @return array       Lua result array containing a boolean result
	 */
	public function delete( $key ) {
		$this->checkType( 'delete', 1, $key, 'string' );

		$cacheKey = $this->cache->makeKey( 'LuaCache', $key );
		return [ $this->cache->delete( $cacheKey ) ];
	}
}
