<?php

header('Content-Type: application/javascript');
// header('Content-Type: text/plain');

$uri = preg_replace('/[^\\/]+$/', '', $_SERVER['REQUEST_URI']);
$uri = preg_replace('/\\/+$/', '', $uri);

$uri_prefix = ($_SERVER['HTTPS'] !== 'off' ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . $uri;

$replace = [
    '// @name        Pornhub.com >_dlp UI'    => "// @name        Pornhub.com >_dlp UI ($_SERVER[HTTP_HOST])",
    '// @version     0.2'                     => "// @version     0.2-$_SERVER[HTTP_HOST]",
    "let ytDlpUrl = GM_getValue('ytDlpUrl');" => "let ytDlpUrl = \"$uri_prefix\";"
];

foreach(file(__DIR__.'/userscript/pornhub.user.js') as $line) {
    $line_t = trim($line);

    if (isset($replace[$line_t])) {
        $line = $replace[$line_t] . "\n";
    }

    echo $line;
}
