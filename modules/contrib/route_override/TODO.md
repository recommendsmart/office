# TODO on RouteOverride
## Implement RouteOverrideControllerInterface::getListCacheability()
RouteOverrides add item caching to their routes. But there is no list caching, so even if (say) a new webform causes a route override, route won't get invalidated.

=> Add list cacheability on RouteOverrideControllers, improve cache invalidator.