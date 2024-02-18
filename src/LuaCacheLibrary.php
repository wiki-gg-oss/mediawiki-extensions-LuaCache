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
use HashBagOStuff;
use ManualLogEntry;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\MediaWikiServices;
use RequestContext;
use WebRequest;

class LuaCacheLibrary extends LibraryBase {
	/**
	 * LuaCache operations invoked in these API actions will be shielded with an in-process cache on writes to prevent
	 * rogue actors from poisoning any site-wide cache entries. This list can be bypassed with the `luacachecanexpand`
	 * right.
	 *
	 * Of course, since this is hand-picked this isn't perfect...
	 *
	 * @var string[]
	 */
	private const PROTECTED_ACTIONS = [
		// The following can be spammed with rogue data:
		'expandtemplates',
		'parse',
	];

	/**
	 * Operations executed within these API calls will always be isolated.
	 *
	 * @var string[]
	 */
	private const UNWRITABLE_ACTIONS = [
		// Scribunto's interactive Lua console - there's no way to persist state between requests, and the console
		// reevaluates all calls every request. Don't allow disabling isolation to allow log spam.
		'scribunto-console',
	];

	private BagOStuff $primaryCache;
	private ?HashBagOStuff $memoryCache = null;
	private ?array $logParams = null;

	public function __construct( LuaEngine $engine ) {
		parent::__construct( $engine );
		$this->primaryCache = MediaWikiServices::getInstance()->getMainObjectStash();

		if ( $this->checkActionSafeguards() ) {
			$this->memoryCache = new HashBagOStuff();
		}
	}

	/**
	 * Check if LuaCache should be isolated in current request context.
	 *
	 * @return bool
	 */
	private function checkActionSafeguards(): bool {
		$reqContext = RequestContext::getMain();
		$request = $reqContext->getRequest();

		$action = strtolower( $request->getRawVal( 'action', '' ) );

		// Check if always isolated
		if ( in_array( $action, self::UNWRITABLE_ACTIONS ) ) {
			return true;
		}
		// Check if isolation can be bypassed
		if ( !in_array( $action, self::PROTECTED_ACTIONS ) ) {
			return false;
		}

		// Check if user requested an isolation bypass
		$right = $request->getBool( 'lcwritable', false ) ? 'luacachecanexpand' : false;
		if ( $right !== false && $reqContext->getAuthority()->isAllowed( $right ) ) {
			$this->logParams = [
				'action' => 'apiwrite',
				'identity' => $reqContext->getUser(),
			];
			return false;
		}

		return true;
	}
	
	private function logWriteIfNeeded(): void {
		if ( !$this->logParams ) {
			return;
		}

        $logEntry = new ManualLogEntry( 'luacache', $this->logParams['action'] );
        $logEntry->setPerformer( $this->logParams['identity'] );
		$logEntry->setTarget( $this->getTitle() );

        $logId = $logEntry->insert();
        $logEntry->publish( $logId );

		$this->logParams = null;
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
	 * Make a cache key in LuaCache's keyspace for given components
	 *
	 * @param string|int ...$components Ordered, key components for entity IDs
	 * @return string
	 */
	private function makeKey( ...$components ) {
		return $this->primaryCache->makeKey( 'LuaCache', ...$components );
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

		$cacheKey = $this->makeKey( 'LuaCache', $key );

		$cache = $this->primaryCache;
		if ( $this->memoryCache && $this->memoryCache->hasKey( $cacheKey ) ) {
			$cache = $this->memoryCache;
		}

		return [ $cache->get( $cacheKey ) ];
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

		$cacheKey = $this->makeKey( 'LuaCache', $key );
		$cache = $this->memoryCache ?? $this->primaryCache;
		$this->logWriteIfNeeded();
		return [ $cache->set( $cacheKey, $value, $exptime ) ];
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
				throw new LuaError(
					"bad argument 1 to getMulti (string expected for table key, get $keyType)"
				);
			}

			$cacheKey = $this->makeKey( 'LuaCache', $key );
			$cacheKeys[] = $cacheKey;
			$cacheKeyToKey[$cacheKey] = $key;
		}

		// Read from primary first, then if untrusted append primary's data onto the process cache
		$cacheData = $this->primaryCache->getMulti( $cacheKeys );
		if ( $this->memoryCache ) {
			$cacheData = $this->memoryCache->getMulti( $cacheKeys ) + $cacheData;
		}

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

			$cacheKey = $this->makeKey( 'LuaCache', $key );
			$cacheData[$cacheKey] = $value;
		}

		$cache = $this->memoryCache ?? $this->primaryCache;
		$this->logWriteIfNeeded();
		return [ $cache->setMulti( $cacheData, $exptime ) ];
	}

	/**
	 * Deletes an item in the main object cache
	 *
	 * @access public
	 * @param  string $key Name of the item to delete
	 * @return array       Lua result array containing a boolean result
	 */
	public function delete( $key ) {
		$this->checkType( 'delete', 1, $key, 'string' );

		$cacheKey = $this->makeKey( 'LuaCache', $key );
		$cache = $this->memoryCache ?? $this->primaryCache;
		$this->logWriteIfNeeded();
		return [ $cache->delete( $cacheKey ) ];
	}
}
