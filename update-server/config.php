<?php
/**
 * 版本管理器配置文件
 */

return [
    // 基础配置
    'site_name' => '短信转发助手 更新服务器',
    'app_name' => '短信转发助手',
    'app_package' => 'com.lanbing.smsforwarder',
    
    // 管理员密码（请修改为强密码）
    'admin_password' => 'admin123',
    
    // 上传配置
    'upload_dir' => __DIR__ . '/apks',
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'allowed_types' => ['apk'],
    
    // 版本数据文件
    'versions_file' => __DIR__ . '/data/versions.json',
    
    // 下载链接基础 URL（如果为空则自动检测）
    'base_url' => '',
    
    // 时区
    'timezone' => 'Asia/Shanghai',
];
