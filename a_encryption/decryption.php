<?php
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
    header('Content-Type: application/json; charset=utf-8');
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["status" => false, 'data' => [], 'mgs' => 'parameter not correct!']);
    return;
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
    header('Content-Type: application/json; charset=utf-8');
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["status" => false, 'data' => [], 'mgs' => "file not exist. path: $filepath"]);
    return;
}

try {
    $newFilePath = rtrim("$filepath", ".enc");
    $pw = $text;

    $content = base64_decode(file_get_contents($filepath));
    $ivSize = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($content, 0, $ivSize);
    $encrypted = substr($content, $ivSize);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $pw, 0, $iv);

    file_put_contents($newFilePath, $decrypted);
    @unlink($filepath);

    $phpPath = exec('which php');
    $command = "$phpPath occ files:scan admin";
    exec($command);

    return Response::success([]);
} catch (Exception $e) {
    return Response::badRequest($e->getMessage());
}
