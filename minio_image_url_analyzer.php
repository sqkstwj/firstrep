<?php
/**
 * MinIO图片URL分析和转换工具
 * 分析用户提供的MinIO URL并提供正确的外链地址
 */

echo "=== MinIO图片URL分析工具 ===\n\n";

// 用户提供的URL
$userUrl = 'http://192.168.1.24:9001/browser/course-files/01--PPT%2F01--UDI%E5%9B%BD%E5%AE%B6%E6%94%BF%E7%AD%96%E8%A6%81%E6%B1%82%E5%92%8C%E5%9F%BA%E6%9C%AC%E6%B3%95%E8%A7%84%E8%A6%81%E6%B1%82_01.png';

echo "🔍 分析您提供的URL:\n";
echo "原始URL: {$userUrl}\n\n";

// 解析URL
$parsedUrl = parse_url($userUrl);
echo "📊 URL解析结果:\n";
echo "协议: {$parsedUrl['scheme']}\n";
echo "主机: {$parsedUrl['host']}\n";
echo "端口: {$parsedUrl['port']}\n";
echo "路径: {$parsedUrl['path']}\n\n";

// 分析问题
echo "❌ 发现的问题:\n";
echo "1. 端口错误: 使用了9001端口（MinIO管理界面端口）\n";
echo "2. 路径错误: 包含了'/browser/'路径（管理界面路径）\n";
echo "3. 这是MinIO管理界面的URL，不是直接访问文件的URL\n\n";

// 提取文件信息
$pathParts = explode('/', trim($parsedUrl['path'], '/'));
$bucket = '';
$filePath = '';

if (count($pathParts) >= 3 && $pathParts[0] === 'browser') {
    $bucket = $pathParts[1];
    $filePath = implode('/', array_slice($pathParts, 2));
}

echo "📁 提取的文件信息:\n";
echo "存储桶: {$bucket}\n";
echo "文件路径: {$filePath}\n";
echo "解码后路径: " . urldecode($filePath) . "\n\n";

// 生成正确的URL
$correctUrls = [];

// 方法1: 标准MinIO API访问（推荐）
$correctUrls['api'] = "http://192.168.1.24:9000/{$bucket}/" . urldecode($filePath);

// 方法2: 如果文件路径需要编码
$correctUrls['encoded'] = "http://192.168.1.24:9000/{$bucket}/" . $filePath;

// 方法3: 重新编码特殊字符
$reEncodedPath = rawurlencode(urldecode($filePath));
$correctUrls['reencoded'] = "http://192.168.1.24:9000/{$bucket}/" . $reEncodedPath;

echo "✅ 正确的外链URL（选择其中一个测试）:\n\n";

foreach ($correctUrls as $type => $url) {
    echo "方法" . (array_search($type, array_keys($correctUrls)) + 1) . " ({$type}):\n";
    echo "{$url}\n";
    
    // 测试URL可访问性
    echo "测试结果: ";
    $testResult = testUrl($url);
    if ($testResult['success']) {
        echo "✅ 可访问 (HTTP {$testResult['http_code']})";
        if ($testResult['content_type']) {
            echo " - {$testResult['content_type']}";
        }
        if ($testResult['content_length']) {
            echo " - " . formatFileSize($testResult['content_length']);
        }
    } else {
        echo "❌ 无法访问 (HTTP {$testResult['http_code']})";
    }
    echo "\n\n";
}

// 提供使用建议
echo "💡 使用建议:\n";
echo "1. 在富文本编辑器中使用测试成功的URL\n";
echo "2. 如果所有URL都无法访问，请检查:\n";
echo "   - MinIO服务器是否正在运行\n";
echo "   - 端口9000是否开放\n";
echo "   - 存储桶权限是否设置为公开读取\n";
echo "   - 文件是否真实存在\n\n";

// 提供MinIO配置检查
echo "🔧 MinIO配置检查:\n";
echo "1. 确认MinIO API端口: 9000 (不是管理界面的9001)\n";
echo "2. 检查存储桶策略:\n";
echo "   mc anonymous set public myminio/{$bucket}\n";
echo "3. 或设置特定路径的公开访问:\n";
echo "   mc anonymous set public myminio/{$bucket}/01--PPT/*\n\n";

// 生成测试脚本
echo "📝 快速测试脚本:\n";
echo "您可以在浏览器中直接访问以下URL来测试:\n\n";

foreach ($correctUrls as $type => $url) {
    echo "测试{$type}: {$url}\n";
}

echo "\n如果图片能在浏览器中正常显示，就可以在富文本编辑器中使用该URL。\n";

// 辅助函数
function testUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($result !== false && $httpCode == 200),
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'content_length' => $contentLength,
        'error' => $error
    ];
}

function formatFileSize($bytes) {
    if ($bytes <= 0) return '未知大小';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

echo "\n程序结束\n";
?>