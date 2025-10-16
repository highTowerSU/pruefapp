<?php
class Router {
  public static function dispatch(array $routes, bool $isHx): array {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = self::normalizePath(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    foreach ($routes as $r) {
      [$m, $pattern, $handler] = $r;
      if ($m !== $method) {
        continue;
      }

      $regex = '#^' . preg_replace('#\{(\w+)\}#','(?P<$1>[^/]+)',$pattern) . '$#';
      if (preg_match($regex, $path, $mats)) {
        return $handler($mats, $isHx);
      }
    }
    return [404, [], '<h1>404 Not Found</h1>'];
  }

  private static function normalizePath(string $path): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($base !== '' && strpos($path, $base) === 0) {
      $path = substr($path, strlen($base));
    }

    if ($path === '' || $path === false) {
      $path = '/';
    }

    if ($path === '/index.php') {
      $path = '/';
    }

    return $path;
  }
}
