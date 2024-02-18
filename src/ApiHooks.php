<?php
/**
 * LuaCache
 * API hooks to have the `lcwritable` parameter recognised.
 *
 * @author  alex4401
 * @license MIT
 * @package LuaCache
 * @link    https://github.com/HydraWiki/LuaCache
 *
**/

namespace MediaWiki\Extension\LuaCache;

use ApiBase;
use Wikimedia\ParamValidator\ParamValidator;

class ApiHooks implements
	\MediaWiki\Api\Hook\APIGetAllowedParamsHook
{

	/**
     * Adds the `luacachewrite` parameter where applicable.
     *
	 * @param ApiBase $module
	 * @param array &$params Array of parameters
	 * @param int $flags Zero or OR-ed flags like ApiBase::GET_VALUES_FOR_HELP
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
        if ( in_array( $module->getModuleName(), LuaCacheLibrary::PROTECTED_ACTIONS ) ) {
            $params[LuaCacheLibrary::WRITABLE_API_PARAM_NAME] = [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ApiBase::PARAM_HELP_MSG => [
					'luacache-api-help-param-luacachewrite',
				],
			];
        }
    }
}
