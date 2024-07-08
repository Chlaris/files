<?php
require_once(LIB_DIR . '/composer/autoload.php');
require_once(LIB_DIR . '/response.php');
use setasign\Fpdi\Tcpdf\Fpdi;
function data($name = null, $default = null){
    if (empty($name)) return $_POST;
    return $_POST[$name] ?? $default;
};

$filename = data('filename');
$directory = data('directory');
$user = data('user');
$text = data('text');
if (empty($filename) || empty($directory) || empty($user) || empty($text)) {
    return Response::badRequest('Parameter not correct!');
}

$user = strtolower($user);
$rootPath = realpath(ROOT_DIR . "/data/$user/files");
$folder = $rootPath . $directory;
$lastFolderCharacter = mb_substr($folder, -1);
if ($lastFolderCharacter === '/' || $lastFolderCharacter === '\\') {
    $folder = rtrim($folder, '/');
    $folder = rtrim($folder, '\\');
}
$filepath = "$folder/$filename";

if (!file_exists(($filepath))) {
    return Response::badRequest("file not exist. path: $filepath");
}

try {
    $newFilePath = rtrim("$filepath", ".pdf").'.sg';
    $contents = file_get_contents($filepath);
    $contents_hash = hash('sha256', $contents);
    openssl_sign($contents_hash, $signature, $text, OPENSSL_ALGO_SHA256);
    $result = $contents.$signature;
    file_put_contents($newFilePath, $result);

    $phpPath = exec('which php');
    $command = "$phpPath occ files:scan $user";
    exec($command);

    return Response::success(['certout' => $certout]);
} catch (Exception $e) {
    return Response::badRequest($e->getMessage());
}
