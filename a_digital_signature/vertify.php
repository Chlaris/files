<?php

require_once(LIB_DIR . '/composer/autoload.php');
require_once(LIB_DIR . '/response.php');
require_once(LIB_DIR . '/insertnotif.php');

use Laravel\SerializableClosure\Serializers\Signed;
use setasign\Fpdi\Tcpdf\Fpdi;

//validate data
function data($name = null, $default = null) {
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

// check file tồn tại trước khi làm các việc khác
if (!file_exists(($filepath))) {
    return Response::badRequest("file not exist. path: $filepath");
}

try {
    $contents = file_get_contents($filepath);
    $data = substr($contents, 0, -128);
    $signature = substr($contents, -128);
    $data_hash= hash('sha256', $data);

    // state whether signature is okay or not
    $ok = openssl_verify($data_hash, $signature, $text, OPENSSL_ALGO_SHA256);
    if ($ok == 1) {
        $new = rtrim("$filepath", ".sg").'-signed.pdf';
        file_put_contents($new, $data);
        InsertNotif::insertNotif($filename, 'Validate successfully!');
    } elseif ($ok == 0) { 
        InsertNotif::insertNotif($filename, 'Public key is not correct!');               
        return;
    } else {
        InsertNotif::insertNotif($filename, 'Public key is not correct!');   
        return;
    }
    
    $phpPath = exec('which php');
    $command = "$phpPath occ files:scan $user";
    exec($command);

    return Response::success(['certout' => $certout]);
} catch (Exception $e) {
    return Response::badRequest($e->getMessage());
}
