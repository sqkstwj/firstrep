<?php
/**
 * 视频外链测试工具
 * 用于测试MinIO视频外链是否可以正常访问
 */

// 您的视频地址（请根据实际情况修改）
$video_urls = [
    // 原始地址（可能不正确）
    'original' => 'http://192.168.1.24:9001/browser/course-files/01%E5%8C%BB%E7%96%97%E5%99%A8%E6%A2%B0%E5%8F%8AUDI%E5%9F%BA%E7%A1%80%E7%9F%A5%E8%AF%86%E5%9F%B9%E8%AE%AD-%E6%9E%97%E7%A3%8A.mp4',
    
    // 修正后的地址（推荐）
    'corrected' => 'http://192.168.1.24:9000/course-files/01%E5%8C%BB%E7%96%97%E5%99%A8%E6%A2%B0%E5%8F%8AUDI%E5%9F%BA%E7%A1%80%E7%9F%A5%E8%AF%86%E5%9F%B9%E8%AE%AD-%E6%9E%97%E7%A3%8A.mp4',
    
    // 解码后的地址
    'decoded' => 'http://192.168.1.24:9000/course-files/01医疗器械及UDI基础知识培训-林磊.mp4'
];

echo "视频外链测试工具\n";
echo "================\n\n";

foreach ($video_urls as $type => $url) {
    echo "测试 {$type} 地址:\n";
    echo "URL: {$url}\n";
    
    // 测试HTTP连接
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);  // 只获取头部信息
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($result !== false && $httpCode == 200) {
        echo "   ✓ 连接成功 (HTTP {$httpCode})\n";
        echo "   ✓ 内容类型: {$contentType}\n";
        if ($contentLength > 0) {
            echo "   ✓ 文件大小: " . formatBytes($contentLength) . "\n";
        }
        echo "   ✓ 这个地址可以用于外链！\n";
    } elseif ($httpCode == 403) {
        echo "   ⚠ 访问被拒绝 (HTTP {$httpCode})\n";
        echo "   提示: 可能需要设置MinIO的访问策略\n";
    } elseif ($httpCode == 404) {
        echo "   ✗ 文件不存在 (HTTP {$httpCode})\n";
        echo "   提示: 请检查文件路径是否正确\n";
    } else {
        echo "   ✗ 连接失败 (HTTP {$httpCode})\n";
        if ($error) {
            echo "   错误: {$error}\n";
        }
    }
    
    echo "\n";
}

// 生成正确的外链地址建议
echo "=== 外链地址建议 ===\n";
echo "根据您的MinIO配置，正确的外链地址应该是:\n";
echo "http://192.168.1.24:9000/course-files/文件名\n\n";

echo "在酷瓜云课堂后台填写时，请使用:\n";
echo "高清地址: http://192.168.1.24:9000/course-files/01%E5%8C%BB%E7%96%97%E5%99%A8%E6%A2%B0%E5%8F%8AUDI%E5%9F%BA%E7%A1%80%E7%9F%A5%E8%AF%86%E5%9F%B9%E8%AE%AD-%E6%9E%97%E7%A3%8A.mp4\n";
echo "标清地址: (同上)\n";
echo "极速地址: (同上)\n\n";

echo "注意事项:\n";
echo "1. 端口使用9000而不是9001\n";
echo "2. 去掉/browser/路径\n";
echo "3. 中文文件名使用URL编码\n";
echo "4. 确保MinIO允许公开访问\n";

/**
 * 格式化字节大小
 */
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>