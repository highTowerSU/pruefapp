<?php

class Router
{
    public static function dispatch(array $routes, bool $isHx): array
    {
        return \Ceneos\PhpBase\Compatibility\ArrayRouteDispatcher::dispatch($routes, $isHx);
    }
}
