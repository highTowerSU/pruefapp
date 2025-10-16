<?php
class Router {
  public static function dispatch(array $routes, bool $isHx): array {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    foreach ($routes as $r) {
      [$m, $pattern, $handler] = $r;
      $regex = '#^' . preg_replace('#\{(\w+)\}#','(?P<$1>[^/]+)',$pattern) . '$#';
      if ($m === $method && preg_match($regex, $path, $mats)) {
        return $handler($mats, $isHx);
      }
    }
    return [404, [], '<h1>404 Not Found</h1>'];
  }
}
