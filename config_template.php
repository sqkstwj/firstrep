<?php
/**
 * 配置模板文件
 * 请根据您的实际情况填写以下配置信息
 */

// ============= 请在这里填写您的配置信息 =============

// MinIO配置
$minio_endpoint = 'http://192.168.1.100:9000';  // 您的MinIO服务器地址
$minio_bucket = 'videos';                       // 您的bucket名称

// 数据库配置  
$db_host = 'localhost';                         // 数据库主机地址
$db_port = 3306;                               // 数据库端口
$db_name = 'ctc';                              // 数据库名称
$db_user = 'ctc';                              // 数据库用户名
$db_pass = 'your_password_here';               // 数据库密码

// ============= 配置信息整合 =============
$config = [
    'minio' => [
        'endpoint' => $minio_endpoint,
        'bucket' => $minio_bucket
    ],
    'database' => [
        'host' => $db_host,
        'port' => $db_port,
        'dbname' => $db_name,
        'username' => $db_user,
        'password' => $db_pass
    ]
];

// ============= 配置验证 =============
echo "配置信息检查\n";
echo "============\n";
echo "MinIO地址: {$minio_endpoint}\n";
echo "MinIO Bucket: {$minio_bucket}\n";
echo "数据库: {$db_user}@{$db_host}:{$db_port}/{$db_name}\n";
echo "\n";

// 检查必填项
$errors = [];

if (empty($minio_endpoint) || $minio_endpoint === 'http://192.168.1.100:9000') {
    $errors[] = "请设置正确的MinIO服务器地址";
}

if (empty($minio_bucket) || $minio_bucket === 'videos') {
    $errors[] = "请设置正确的MinIO bucket名称";
}

if (empty($db_pass) || $db_pass === 'your_password_here') {
    $errors[] = "请设置正确的数据库密码";
}

if (!empty($errors)) {
    echo "⚠️  配置错误:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n请修改配置后重新运行\n";
    exit(1);
}

echo "✅ 配置检查通过\n\n";

// ============= 连接测试 =============
echo "开始连接测试...\n";

// 测试数据库连接
echo "1. 测试数据库连接...\n";
try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kg_chapter WHERE model = 1");
    $result = $stmt->fetch();
    
    echo "   ✓ 数据库连接成功\n";
    echo "   ✓ 找到 {$result['count']} 个点播章节\n";
    
} catch (PDOException $e) {
    echo "   ✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 测试MinIO连接
echo "\n2. 测试MinIO连接...\n";
$testUrl = rtrim($minio_endpoint, '/') . '/' . $minio_bucket . '/';
echo "   测试地址: {$testUrl}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result !== false && $httpCode < 500) {
    echo "   ✓ MinIO服务器可访问 (HTTP {$httpCode})\n";
} else {
    echo "   ✗ MinIO服务器无法访问 (HTTP {$httpCode})\n";
    echo "   请检查MinIO服务器地址和网络连接\n";
    exit(1);
}

echo "\n🎉 所有测试通过！您可以继续使用主脚本了。\n";
echo "\n下一步操作:\n";
echo "1. 编辑 minio_external_link.php 中的视频列表\n";
echo "2. 运行: php minio_external_link.php\n";

return $config;
?>