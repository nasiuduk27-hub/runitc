<?php
header('Content-Type: text/plain');
var_dump('exec_exists', function_exists('exec'));
var_dump('disable_functions', ini_get('disable_functions'));
var_dump('open_basedir', ini_get('open_basedir'));
var_dump('is_exec_gs', @is_executable('/usr/bin/gs'));
var_dump('PATH', getenv('PATH'));
$out = []; $code = -1;
@exec('/usr/bin/gs --version 2>&1', $out, $code);
var_dump('gs_run', $code, $out);
