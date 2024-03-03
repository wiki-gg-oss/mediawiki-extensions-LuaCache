## LuaCache

LuaCache exposes MediaWiki's ObjectCache through a Lua interface. Anything stored in the cache is available on *every* MediaWiki page. There are two main reasons to use this extension:

* Caching expensive Cargo/SMW/DPL results
* Reducing the number of backlinks to a particular page, improving jobqueue performance

While theoretically you could use LuaCache as a *substitute* for Cargo/SMW, this is not recommended; see Permissions below for more information.

### Installation
* Extract the extension folder to extensions/LuaCache/
* Add the following line to LocalSettings.php:

	wfLoadExtension( 'LuaCache' );

### Basic usage

Store a value to cache:

```lua
mw.ext.LuaCache.set( key, value )
```

Read a value from cache:

```lua
mw.ext.LuaCache.get( key )
```

Because keys are **global**, you should **namespace** your variables. For example:

```lua
-- To totally nullify existing cache, increment `01`
local luaCachePrefix = 'Users_01_'

local p = {}
function p.setUserValue(frame)
   -- Set 2nd arg into a key indicated by the first arg, prefixed by our namespace for user data
   return mw.ext.LuaCache.set( luaCachePrefix .. frame.args[1], frame.args[2] )
end

function p.getUserValue(frame)
   -- Get the value keyed by the first arg, and prefixed by our namespace for user data
   return mw.ext.LuaCache.get( luaCachePrefix .. frame.args[1] )
end
return p
```
### Permissions
Unrestricted, you could overwrite LuaCache key-value pairs through previews or API actions `expandtemplates` or `parse`. This is not desirable, so LuaCache writes to a more temporary cache layer in every action other than a page save. LuaCache does not function in the Scribunto console.

However, you may need to write cache via the API in various scenarios like including a gadget to "commit" values from an updated data module. Thus, the user right `luacachecanexpand` is added, by default to all logged-in users. This right, along with the API parameter `luacachewrite` specified as true in the `expandtemplates` action, will enable the user to commit LuaCache data to the wiki. For high-traffic wikis it's recommended to consider restricting the right to the sysop group.

By default any `expandtemplates` query that's permitted to write LuaCache keys will be logged in the `luacache` logs, regardless of whether any variables were changed. You can disable these logs via `$wgLuaCacheHideApiLogs`. By default, these logs are visible (not hidden). You can change this via `LuaCacheHideApiLogs`. It is recommended to hide/disable these logs **only** if `luacachecanexpand` is restricted to sysops, or if your wiki is private.

#### Lua module best practices
While it might be beneficial for testing, it's not recommended to write setter functions that can write arbitrary values, as this opens your wiki up to vandalism via injection into LuaCache.

```lua
local luaCachePrefix = 'Users_01_'

local p = {}
function p.setUserValueUnsafe(frame)
   -- Don't do this
   return mw.ext.LuaCache.set( luaCachePrefix .. frame.args[1], frame.args[2] )
end

function p.setUserValueSafe(frame)
   -- Do this
   local res = mw.ext.cargo.query('Users', "Name", { where = ("ID='%s'"):format(frame.args[1])})
   return mw.ext.LuaCache.set( luaCachePrefix .. frame.args[1], res[1].Name )
end

return p
```

Keep in mind that vandalism will always be possible on a public wiki with unprotected pages, but these practices along with aggressive logging should make it possible to combat.

### Advanced usage

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
