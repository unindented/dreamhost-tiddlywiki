<?php

$rootDir = dirname(__DIR__, 1);

require $rootDir . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Laminas\Diactoros\ServerRequestFactory;

$dotenv = Dotenv::createImmutable($rootDir);
$dotenv->load();
$dotenv->required(['USER', 'PASSWORD']);

$request = ServerRequestFactory::fromGlobals();

$uploadOptions = getUploadOptions($request);
$userFile = getUserFile($request);

checkCredentials($uploadOptions);

backupUserFile($uploadOptions, $userFile);
moveUserFile($uploadOptions, $userFile);

function getUploadOptions($request) {
  $parsedBody = $request->getParsedBody();
  $uploadOptionsStr = $parsedBody['UploadPlugin'];
  $uploadOptionsStr = trim($uploadOptionsStr, "; \t\n\r\0\x0B");
  $uploadOptionsStr = str_replace(';', '&', $uploadOptionsStr);
  parse_str($uploadOptionsStr, $uploadOptions);
  return $uploadOptions;
}

function getUserFile($request) {
  $uploadedFiles = $request->getUploadedFiles();
  $userFile = $uploadedFiles['userfile'];
  return $userFile;
}

function backupUserFile($uploadOptions, $userFile) {
  $uploadDir = $uploadOptions['uploaddir'];
  $backupDir = $uploadOptions['backupDir'];
  $userFileName = $userFile->getClientFilename();
  $userFileNameInfo = pathinfo($userFileName);
  $backupFileName = $userFileNameInfo['filename'] . date('.Ymd.His') . '.' . $userFileNameInfo['extension'];
  $userFilePath = $uploadDir . '/' . $userFileName;
  $backupFilePath = $backupDir . '/' . $backupFileName;

  checkPathTraversal(dirname($userFilePath));
  checkPathTraversal(dirname($backupFilePath));

  if (file_exists($userFilePath) && !copy($userFilePath, $backupFilePath)) {
    echo 'Failed to back up';
    exit;
  }
}

function moveUserFile($uploadOptions, $userFile) {
  $uploadDir = $uploadOptions['uploaddir'];
  $userFileName = $userFile->getClientFilename();
  $userFilePath = $uploadDir . '/' . $userFileName;

	checkPathTraversal(dirname($userFilePath));

  $userFile->moveTo($userFilePath);
}

function checkCredentials($uploadOptions) {
  $user = $uploadOptions['user'];
  $password = $uploadOptions['password'];
  if ($user !== $_SERVER['USER'] || $password !== $_SERVER['PASSWORD']) {
    echo 'Unauthorized';
    exit;
  }
}

function checkPathTraversal($path) {
  if (strpos(realpath($path), realpath('.')) !== 0) {
    echo 'Bad path "' . $path . '"';
    exit;
  }
}

?>
