<?php
/**
 * APK 解析器 - 真正解析 APK 的二进制 AndroidManifest.xml
 * 
 * 优先使用 aapt 工具（如果可用），其次使用 AXML 解析器，最后回退到文件名
 */

class ApkParser {
    private $apkPath;
    
    public function __construct($apkPath) {
        $this->apkPath = $apkPath;
    }
    
    /**
     * 解析 APK 文件
     * @return array|false 返回 ['versionName' => 'x.y.z', 'versionCode' => 123]，失败返回 false
     */
    public function parse() {
        // 1. 优先尝试用 aapt 工具解析（最准确）
        $result = $this->parseWithAapt();
        if ($result !== false) {
            return $result;
        }
        
        // 2. 尝试解析二进制 AndroidManifest.xml
        $result = $this->parseFromBinaryManifest();
        if ($result !== false) {
            return $result;
        }
        
        // 3. 最后回退到从文件名推断
        $result = $this->parseFromFileName();
        if ($result !== false) {
            return $result;
        }
        
        return false;
    }
    
    /**
     * 使用 aapt 工具解析（最准确）
     */
    private function parseWithAapt() {
        // 尝试查找 aapt 工具
        $aaptPaths = [
            'aapt',
            'aapt2',
            '/usr/bin/aapt',
            '/usr/local/bin/aapt',
            'C:\\Android\\Sdk\\build-tools\\34.0.0\\aapt.exe',
        ];
        
        $aaptPath = null;
        foreach ($aaptPaths as $path) {
            if ($this->commandExists($path)) {
                $aaptPath = $path;
                break;
            }
        }
        
        if ($aaptPath === null) {
            return false;
        }
        
        // 执行 aapt dump badging
        $output = [];
        $returnCode = 0;
        $cmd = escapeshellcmd($aaptPath) . ' dump badging ' . escapeshellarg($this->apkPath) . ' 2>&1';
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return false;
        }
        
        $output = implode("\n", $output);
        
        $result = [];
        
        // 解析 versionName
        if (preg_match("/versionName='([^']+)'/", $output, $matches)) {
            $result['versionName'] = $matches[1];
        }
        
        // 解析 versionCode
        if (preg_match("/versionCode='(\d+)'/", $output, $matches)) {
            $result['versionCode'] = intval($matches[1]);
        }
        
        if (!empty($result['versionName']) && !empty($result['versionCode'])) {
            return $result;
        }
        
        return false;
    }
    
    /**
     * 解析二进制 AndroidManifest.xml (AXML)
     */
    private function parseFromBinaryManifest() {
        $zip = new ZipArchive();
        if ($zip->open($this->apkPath) !== true) {
            return false;
        }
        
        // 读取 AndroidManifest.xml
        $manifestContent = $zip->getFromName('AndroidManifest.xml');
        $zip->close();
        
        if ($manifestContent === false) {
            return false;
        }
        
        // 使用简单的 AXML 解析方法
        $result = $this->parseAXML($manifestContent);
        
        if (!empty($result['versionName']) && !empty($result['versionCode'])) {
            return $result;
        }
        
        return false;
    }
    
    /**
     * 简单的 AXML (Android Binary XML) 解析器
     * 仅提取 versionName 和 versionCode
     */
    private function parseAXML($data) {
        $result = [];
        
        // AXML 文件头
        if (substr($data, 0, 8) !== "<?xml\x08\x00\x00\x00") {
            return $result;
        }
        
        // 查找字符串池偏移
        $stringPoolOffset = unpack('V', substr($data, 12, 4))[1];
        
        // 读取字符串池
        $stringPool = $this->readStringPool(substr($data, $stringPoolOffset));
        
        // 在整个文件中搜索 versionName 和 versionCode
        // 这是一个简化方法，搜索相关的字符串
        foreach ($stringPool as $str) {
            if (preg_match('/^\d+\.\d+\.\d+$/', $str)) {
                // 看起来像版本号
                if (empty($result['versionName'])) {
                    $result['versionName'] = $str;
                }
            }
        }
        
        // 尝试从二进制数据中直接搜索
        // versionCode 通常是一个整数
        $offset = 0;
        $len = strlen($data);
        while ($offset < $len - 8) {
            // 查找可能的 versionCode (LEB128 或 32位整数)
            // 这是一个简化的启发式方法
            $val = unpack('V', substr($data, $offset, 4))[1];
            if ($val > 0 && $val < 1000) {
                if (empty($result['versionCode'])) {
                    $result['versionCode'] = $val;
                }
            }
            $offset += 4;
        }
        
        return $result;
    }
    
    /**
     * 读取 AXML 字符串池
     */
    private function readStringPool($data) {
        $strings = [];
        
        // 字符串池头部
        $headerSize = unpack('V', substr($data, 0, 4))[1];
        $stringCount = unpack('V', substr($data, 8, 4))[1];
        $styleCount = unpack('V', substr($data, 12, 4))[1];
        $flags = unpack('V', substr($data, 16, 4))[1];
        $stringsStart = unpack('V', substr($data, 20, 4))[1];
        $stylesStart = unpack('V', substr($data, 24, 4))[1];
        
        // 读取字符串偏移
        $offsets = [];
        $offset = 28;
        for ($i = 0; $i < $stringCount; $i++) {
            $offsets[] = unpack('V', substr($data, $offset, 4))[1];
            $offset += 4;
        }
        
        // 读取字符串
        $utf8 = ($flags & 0x100) !== 0;
        foreach ($offsets as $strOffset) {
            $pos = $stringsStart + $strOffset;
            if ($utf8) {
                // UTF-8 编码字符串
                $len = ord($data[$pos]);
                if ($len & 0x80) {
                    $len = (($len & 0x7F) << 8) | ord($data[$pos + 1]);
                    $pos += 2;
                } else {
                    $pos += 1;
                }
                $str = substr($data, $pos, $len);
            } else {
                // UTF-16 编码字符串
                $len = ord($data[$pos]);
                if ($len & 0x80) {
                    $len = (($len & 0x7F) << 8) | ord($data[$pos + 1]);
                    $pos += 2;
                } else {
                    $pos += 1;
                }
                $str = mb_convert_encoding(substr($data, $pos, $len * 2), 'UTF-8', 'UTF-16LE');
            }
            $strings[] = $str;
        }
        
        return $strings;
    }
    
    /**
     * 检查命令是否存在
     */
    private function commandExists($cmd) {
        $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';
        $returnCode = 0;
        $output = [];
        exec($whereIsCommand . ' ' . escapeshellarg($cmd), $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * 从文件名推断（最后的备用方案）
     */
    private function parseFromFileName() {
        $fileName = basename($this->apkPath);
        
        $result = [];
        
        // 尝试匹配：sms-forwarder-v2.6.4-19.apk (推荐格式)
        if (preg_match('/v(\d+\.\d+\.\d+)-(\d+)\.apk$/i', $fileName, $matches)) {
            $result['versionName'] = $matches[1];
            $result['versionCode'] = intval($matches[2]);
            return $result;
        }
        
        // 尝试匹配：sms-forwarder-v2.6.4.apk (备用格式)
        if (preg_match('/v(\d+\.\d+\.\d+)\.apk$/i', $fileName, $matches)) {
            $result['versionName'] = $matches[1];
            $parts = explode('.', $matches[1]);
            if (count($parts) >= 3) {
                $result['versionCode'] = intval($parts[0]) * 10000 + intval($parts[1]) * 100 + intval($parts[2]);
            }
            return $result;
        }
        
        return false;
    }
}
