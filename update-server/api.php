<?php
/**
 * 版本管理 API - 简化版
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $config = require __DIR__ . '/config.php';
    date_default_timezone_set($config['timezone']);

    $dataDir = dirname($config['versions_file']);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    if (!is_dir($config['upload_dir'])) {
        mkdir($config['upload_dir'], 0755, true);
    }

    $versionsFile = $config['versions_file'];
    if (!file_exists($versionsFile)) {
        file_put_contents($versionsFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $versions = json_decode(file_get_contents($versionsFile), true) ?: [];

    function getBaseUrl($config) {
        if (!empty($config['base_url'])) {
            return rtrim($config['base_url'], '/');
        }
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        $path = str_replace('\\', '/', $path);
        return $protocol . '://' . $host . $path;
    }

    function parseVersionFromFileName($fileName) {
        if (preg_match('/v(\d+\.\d+\.\d+)\.apk$/i', $fileName, $matches)) {
            return $matches[1];
        }
        return null;
    }

    function compareVersions($v1, $v2) {
        return version_compare($v1, $v2);
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'latest':
            if (empty($versions)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No versions found'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            usort($versions, function($a, $b) {
                return compareVersions($b['versionName'], $a['versionName']);
            });

            $latest = $versions[0];
            $baseUrl = getBaseUrl($config);
            $latest['apkUrl'] = $baseUrl . '/apks/' . $latest['apkFile'];

            echo json_encode([
                'success' => true,
                'data' => $latest
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'list':
            usort($versions, function($a, $b) {
                return compareVersions($b['versionName'], $a['versionName']);
            });

            $baseUrl = getBaseUrl($config);
            foreach ($versions as &$version) {
                $version['apkUrl'] = $baseUrl . '/apks/' . $version['apkFile'];
            }

            echo json_encode([
                'success' => true,
                'data' => $versions
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Method not allowed'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $password = $_POST['password'] ?? '';
            if ($password !== $config['admin_password']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid password'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $changelog = trim($_POST['changelog'] ?? '');

            if (!isset($_FILES['apk']) || $_FILES['apk']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'success' => false,
                    'error' => 'APK 文件上传失败'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $file = $_FILES['apk'];
            $fileSize = $file['size'];
            if ($fileSize > $config['max_file_size']) {
                echo json_encode([
                    'success' => false,
                    'error' => '文件太大'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), $config['allowed_types'])) {
                echo json_encode([
                    'success' => false,
                    'error' => '无效的文件类型'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $versionName = parseVersionFromFileName($file['name']);
            if ($versionName === null) {
                echo json_encode([
                    'success' => false,
                    'error' => '文件名格式错误，请使用格式：sms-forwarder-v2.6.4.apk'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            foreach ($versions as $v) {
                if ($v['versionName'] === $versionName) {
                    echo json_encode([
                        'success' => false,
                        'error' => "版本 {$versionName} 已经存在"
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $apkFileName = 'sms-forwarder-v' . $versionName . '.apk';
            $uploadPath = $config['upload_dir'] . '/' . $apkFileName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to save file'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $newVersion = [
                'versionName' => $versionName,
                'changelog' => $changelog,
                'apkFile' => $apkFileName,
                'fileSize' => $fileSize,
                'releaseDate' => date('Y-m-d H:i:s')
            ];

            $versions[] = $newVersion;
            file_put_contents($versionsFile, json_encode($versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            echo json_encode([
                'success' => true,
                'message' => 'Version uploaded successfully',
                'data' => $newVersion
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Method not allowed'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $password = $_POST['password'] ?? '';
            if ($password !== $config['admin_password']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid password'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $versionNameToDelete = trim($_POST['versionName'] ?? '');
            if (empty($versionNameToDelete)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Version name required'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $indexToDelete = -1;
            $apkToDelete = '';
            foreach ($versions as $index => $version) {
                if ($version['versionName'] === $versionNameToDelete) {
                    $indexToDelete = $index;
                    $apkToDelete = $version['apkFile'];
                    break;
                }
            }

            if ($indexToDelete === -1) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Version not found'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $apkPath = $config['upload_dir'] . '/' . $apkToDelete;
            if (file_exists($apkPath)) {
                unlink($apkPath);
            }

            array_splice($versions, $indexToDelete, 1);
            file_put_contents($versionsFile, json_encode($versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            echo json_encode([
                'success' => true,
                'message' => 'Version deleted successfully'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => '服务器错误: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
