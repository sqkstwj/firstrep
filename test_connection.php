<?php
/**
 * 连接测试脚本
 * 用于测试MinIO和数据库连接是否正常
 */

// ============= 配置区域 =============
$config = [
    'minio' => [
        'endpoint' => 'http://192.168.1.100:9000',  // 替换为您的MinIO地址
        'bucket' => 'videos'                        // 替换为您的bucket名称
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'ctc',
        'username' => 'ctc',
        'password' => '1qaz2wsx3edc'
    ]
];

echo "连接测试工具\n";
echo "============\n\n";

// 测试数据库连接
echo "1. 测试数据库连接...\n";
try {
    $db = $config['database'];
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 测试查询
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kg_chapter WHERE model = 1");
    $result = $stmt->fetch();
    
    echo "   ✓ 数据库连接成功\n";
    echo "   ✓ 找到 {$result['count']} 个点播章节\n";
    
} catch (PDOException $e) {
    echo "   ✗ 数据库连接失败: " . $e->getMessage() . "\n";
    echo "   请检查数据库配置信息\n";
}

echo "\n";

// 测试MinIO连接
echo "2. 测试MinIO连接...\n";
$endpoint = rtrim($config['minio']['endpoint'], '/');
$bucket = $config['minio']['bucket'];
$testUrl = "{$endpoint}/{$bucket}/";

echo "   测试地址: {$testUrl}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($result !== false && $httpCode < 500) {
    echo "   ✓ MinIO服务器可访问 (HTTP {$httpCode})\n";
    if ($httpCode == 200) {
        echo "   ✓ Bucket访问正常\n";
    } elseif ($httpCode == 403) {
        echo "   ⚠ Bucket访问受限，但服务器可达\n";
    }
} else {
    echo "   ✗ MinIO服务器无法访问\n";
    if ($error) {
        echo "   错误信息: {$error}\n";
    }
    echo "   HTTP状态码: {$httpCode}\n";
    echo "   请检查MinIO服务器地址和网络连接\n";
}

echo "\n";

// 显示配置信息
echo "3. 当前配置信息:\n";
echo "   MinIO地址: {$config['minio']['endpoint']}\n";
echo "   MinIO Bucket: {$config['minio']['bucket']}\n";
echo "   数据库主机: {$config['database']['host']}:{$config['database']['port']}\n";
echo "   数据库名称: {$config['database']['dbname']}\n";

echo "\n";

// 显示示例外链地址
echo "4. 示例外链地址:\n";
$samplePath = "course1/lesson1.mp4";
$sampleUrl = "{$endpoint}/{$bucket}/{$samplePath}";
echo "   如果您的视频路径是: {$samplePath}\n";
echo "   生成的外链地址是: {$sampleUrl}\n";

echo "\n=== 测试完成 ===\n";
echo "如果以上测试都通过，您可以继续使用 minio_external_link.php 脚本\n";
?>