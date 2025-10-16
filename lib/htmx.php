<?php
class Htmx {
  public static function handle(callable $next) {
    return function() use ($next) {
      $isHx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';

      // Request weiterreichen und [$status, $headers, $body] zurückerwarten
      [$status, $headers, $body] = $next($isHx);

      // Gemeinsame Header
      header('Content-Type: text/html; charset=utf-8');
      http_response_code($status);

      // HTMX-Header setzen
      foreach ($headers as $k => $v) {
        header($k . ': ' . $v);
      }

      echo $body;
      exit;
    };
  }

  // Helfer: Redirect (303) kompatibel mit htmx
  public static function hxRedirect(string $url): array {
    // htmx bevorzugt HX-Redirect statt 30x
    return [200, ['HX-Redirect' => $url], ''];
  }

  // Helfer: Trigger-Events an htmx
  public static function hxTrigger(array $events): array {
    return [200, ['HX-Trigger' => json_encode($events)], ''];
  }
}
