LuaCache
========

This extension exposes MediaWiki's ObjectCache through a Lua interface.

Installation
============
* Extract the extension folder to extensions/LuaCache/
* Add the following line to LocalSettings.php:

	wfLoadExtension( 'LuaCache' );

Usage Example
=============

```lua
-- Module:Demo
local p = {}

local cache = require 'mw.ext.LuaCache'

function p.test(frame)
	local args = frame.args
	local keyPrefix = args[1] or 'sample'

	local sampleValue = {
		hello = 'World',
		name = 'Alyanah',
		counter = 0
	}

	local results = {}

	local handleValue = function(r)
		if r ~= nil then
			sampleValue.counter = (r.counter or 0) + 1
			table.insert(results, 'Hello: ' .. tostring(r.hello))
			table.insert(results, 'Name: ' .. tostring(r.name))
			table.insert(results, 'Counter: ' .. tostring(r.counter))
		else
			table.insert(results, '(nil)')
		end
	end

	-- Get an item from the cache
	-- This will be nil the first time this function is run,
	-- and it will have a value afterwards for as long as the
	-- item remains in cache.
	local singleTestKey = keyPrefix .. '.singleTest'
	local res = cache.get(singleTestKey)
	table.insert(results, 'cache.get(\'' .. singleTestKey .. '\')')
	handleValue(res)

	-- Set an item in the cache
	res = cache.set(singleTestKey, sampleValue)
	table.insert(results, 'cache.set returned ' .. tostring(res))

	-- Get the item from the cache again
	res = cache.get(singleTestKey)
	table.insert(results, 'cache.get(\'' .. singleTestKey .. '\')')
	handleValue(res)
	table.insert(results, '')

	-- Set the item in the cache with a 30s expiration
	res = cache.set(singleTestKey, sampleValue, 30)
	table.insert(results, 'cache.set returned ' .. tostring(res))

	-- Set multiple items in the cache
	res = cache.setMulti({
		[keyPrefix .. '.multiTest.1'] = {
			when = 'now',
			what = '불고기'
		},
		[keyPrefix .. '.multiTest.2'] = {
			when = 'tomorrow',
			what = '김치찌개'
		},
		[keyPrefix .. '.multiTest.3'] = {
			when = 'yesterday',
			what = '순두부찌개'
		}
	})
	table.insert(results, 'cache.setMulti returned ' .. tostring(res))

	-- Get one of the items from the cache
	res = cache.get(keyPrefix .. '.multiTest.2')
	if res then
		table.insert(results, 'When: ' .. tostring(res.when))
		table.insert(results, 'What: ' .. tostring(res.what))
	else
		table.insert(results, '(nil)')
	end

	-- Delete one of the items from the cache
	res = cache.delete(keyPrefix .. '.multiTest.2')
	table.insert(results, 'cache.delete returned ' .. tostring(res))

	-- Get all of those items
	res = cache.getMulti({
		keyPrefix .. '.multiTest.1',
		keyPrefix .. '.multiTest.2',
		keyPrefix .. '.multiTest.3',
	})
	for k, v in pairs(res) do
		table.insert(results, tostring(k) .. ' = ')
		if v and type(v) == 'table' then
			table.insert(results, 'When: ' .. tostring(v.when))
			table.insert(results, 'What: ' .. tostring(v.what))
		else
			table.insert(results, tostring(v))
		end
	end

	-- Format the results as preformatted wikitext
	return ' ' .. table.concat(results, '\n ')
end

return p
```

```
{{#invoke:Demo|test}}

  ==>

cache.get('sample.singleTest')
(nil)
cache.set returned true
cache.get('sample.singleTest')
Hello: World
Name: Alyanah
Counter: 0

cache.set returned true
cache.setMulti returned true
When: tomorrow
What: 김치찌개
cache.delete returned true
sample.multiTest.3 = 
When: yesterday
What: 순두부찌개
sample.multiTest.1 = 
When: now
What: 불고기
```
