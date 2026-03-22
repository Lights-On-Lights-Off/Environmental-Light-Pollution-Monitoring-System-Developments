<?php
// Detects the app's subfolder and exposes a url() helper.
// Include this after session.php in every PHP page.
// Works at localhost/, localhost/lpms/, or any live domain path.

function url(string $path): string {
    $docRoot  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $appRoot  = str_replace('\\', '/', dirname(__DIR__));
    $relative = str_replace($docRoot, '', $appRoot);
    $relative = '/' . ltrim($relative, '/');
    return rtrim($relative, '/') . '/' . ltrim($path, '/');
}
