<?php
require_once(LIB_DIR . '/composer/autoload.php');
require_once(LIB_DIR . '/response.php');

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
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

$newFilePath = rtrim("$filepath", ".pdf").'.sg.pdf';
$contents = file_get_contents($filepath);
$contents_hash = hash('sha256', $contents);
openssl_sign($contents_hash, $signature, $text, OPENSSL_ALGO_SHA256);
$result = $contents.$signature;
file_put_contents($newFilePath, $result);

try {
    //pdf
    $pdf = new Fpdi();
    // $pageCount = $pdf->setSourceFile($filepath);
    try {
        $pageCount = $pdf->setSourceFile($filepath);
    } catch (Exception $e) {
        if (!($e instanceof CrossReferenceException)) {
            return Response::badRequest($e->getMessage(), ['step' => 0]);
        }
        try {
            exec("gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dBATCH -sOutputFile=\"$filepath\" \"$filepath\"");
            $pageCount = $pdf->setSourceFile($filepath);
        } catch (Exception $e) {
            return Response::badRequest(
                $e->getMessage(),
                [
                    'a' => "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dBATCH -sOutputFile=\"$filepath\" \"$filepath\""
                ]
            );
        }
    }
    $pdf->setPDFVersion($version = '1.4');
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $templateId = $pdf->importPage($pageNo);
        $pdf->AddPage();
        $pdf->useTemplate($templateId);
    }

//sign
    $res = openssl_pkey_new([
        "digest_alg" => "sha256",
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ]);
    $info = [
        'Name' => $user.' - NextCloud',
        'Location' => 'VietNam',
        'Reason' => 'Aproval',
        'ContactInfo' => 'Thanh Tri, Ha Noi'
    ];
    $dn = array(
        "countryName" => "VietNam",
        "organizationName" => "KMA",
    );
    $csr = openssl_csr_new($dn, $res, array('digest_alg' => 'sha256'));
    $x509 = openssl_csr_sign($csr, null, $res, $days=365, array('digest_alg' => 'sha256'));
    openssl_csr_export($csr, $csrout);
    openssl_x509_export($x509, $certout);
    $pw = '12345';
    $certType = 2;
    $pdf->setSignature($certout, $text, $pw, '', $certType, $info);

    // $ok = $pdf->getSign();
    // if($ok) {
    //     file_put_contents("$folder/pwd.txt", $ok);
    // }
    $pdf->Output("$newFilePath", 'F');
    $phpPath = exec('which php');
    $command = "$phpPath occ files:scan $user";
    exec($command);

    return Response::success(['certout' => $certout]);
} catch (Exception $e) {
    return Response::badRequest($e->getMessage());
}
