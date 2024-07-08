<?php

require_once(LIB_DIR . '/response.php');
require_once(ROOT_DIR . '/config/config.php');
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
    $newFilePath = "$filepath.enc";
    $pw = $text;
    $contents = file_get_contents($filepath);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($contents, 'aes-256-cbc', $pw, 0, $iv);
    $encrypted = base64_encode($iv . $encrypted);
    file_put_contents($newFilePath, $encrypted);

    @unlink($filepath);
    echo '<script>alert("Welcome to Geeks for Geeks")</script>';

    $phpPath = exec('which php');
    $command = "$phpPath occ files:scan $user";
    exec($command);

    return Response::success([]);
} catch (Exception $e) {
    return Response::badRequest($e->getMessage());
}
