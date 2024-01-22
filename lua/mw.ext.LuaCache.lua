local LuaCache = {}
local php

-- Use binser to serialize Lua values for the cache
-- Note that attempting to serialize a function will raise because loadstring
-- and dump are disabled.
local binser = require 'binser'

-- This function is called by Scribunto to load the module
function LuaCache.setupInterface()
	-- Clear setupInterface so this isn't loaded twice
	LuaCache.setupInterface = nil
	-- Store the mw_interface global
	php = mw_interface
	mw_interface = nil

	-- Register this library in the "mw" global
	mw = mw or {}
	mw.ext = mw.ext or {}
	mw.ext.LuaCache = LuaCache

	-- Register with Lua's require
	package.loaded['mw.ext.LuaCache'] = LuaCache
end

-- Deserialize return values from PHP's get or getMulti
local function deserialize(value)
	-- the PHP interface returns false or the serialized data
	if type(value) == 'string' then
		return binser.deserializeN(value, 1)
	end
	return nil
end

-- Get the item associated with key from the main object cache.
-- Returns nil if the item does not exist.
function LuaCache.get(key)
	return deserialize(php.get(key))
end

-- Set an item in the main object cache.
-- Returns true if the item was stored successfully; false otherwise.
function LuaCache.set(key, value, exptime)
	return php.set(key, binser.serialize(value), exptime)
end

-- Get multiple items identified by keys (a table of strings)
-- Returns a table of keys and values for each item which exists.
function LuaCache.getMulti(keys)
	local result = php.getMulti(keys)
	local deserializedResult = {}
	for k, v in pairs(result) do
		deserializedResult[k] = deserialize(v)
	end
	return deserializedResult
end

-- Set multiple items in the main object cache.
-- data is a table with string keys
-- Returns true if the items were stored successfully; false otherwise.
function LuaCache.setMulti(data, exptime)
	local serializedData = {}
	for k, v in pairs(data) do
		serializedData[k] = binser.serialize(v)
	end
	return php.setMulti(serializedData, exptime)
end

-- Delete the item associated with key from the main object cache.
-- Returns true if the item was deleted successfully; false otherwise.
function LuaCache.delete(key)
	return php.delete(key)
end

return LuaCache
