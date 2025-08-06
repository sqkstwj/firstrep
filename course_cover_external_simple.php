<?php
/**
 * 课程封面外链设置脚本（简化版）
 * 直接利用系统现有机制设置外链封面
 */

echo "=== 课程封面外链设置工具（简化版） ===\n\n";

// ============= 配置区域 =============
$config = [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'ctc', 
        'username' => 'ctc',
        'password' => '1qaz2wsx3edc'  // 修改为您的数据库密码
    ]
];

// ============= 封面设置 =============
$coverMappings = [
    // 课程ID => MinIO外链地址
    1 => 'http://192.168.1.24:9000/course-files/covers/course-1.jpg',
    2 => 'http://192.168.1.24:9000/course-files/covers/course-2.jpg',
    3 => 'http://192.168.1.24:9000/course-files/covers/course-3.jpg',
    // 添加更多课程...
];

// ============= 数据库连接 =============
function connectDatabase($config) {
    try {
        $dsn = "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("数据库连接失败: " . $e->getMessage() . "\n");
    }
}

// ============= 测试封面链接 =============
function testCoverUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

// ============= 主程序 =============
try {
    echo "🔗 连接数据库...\n";
    $pdo = connectDatabase($config);
    echo "✅ 数据库连接成功\n\n";
    
    echo "📋 将要设置的课程封面:\n";
    foreach ($coverMappings as $courseId => $coverUrl) {
        // 获取课程信息
        $stmt = $pdo->prepare("SELECT id, title, cover FROM kg_course WHERE id = ?");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch();
        
        if ($course) {
            echo "  课程 {$courseId}: {$course['title']}\n";
            echo "  当前封面: " . ($course['cover'] ?: '未设置') . "\n";
            echo "  新封面: {$coverUrl}\n";
            
            // 测试新封面链接
            if (testCoverUrl($coverUrl)) {
                echo "  状态: ✅ 链接可访问\n";
            } else {
                echo "  状态: ⚠️ 链接无法访问\n";
            }
            echo "\n";
        } else {
            echo "  课程 {$courseId}: ❌ 不存在\n\n";
        }
    }
    
    echo "确认设置这些课程封面吗？(y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if ($line !== 'y' && $line !== 'Y') {
        echo "操作已取消\n";
        exit(0);
    }
    
    echo "\n🚀 开始设置课程封面...\n";
    
    $successCount = 0;
    $totalCount = count($coverMappings);
    
    foreach ($coverMappings as $courseId => $coverUrl) {
        try {
            // 🔥 关键：直接设置完整URL，系统会自动处理
            $stmt = $pdo->prepare("UPDATE kg_course SET cover = ?, update_time = ? WHERE id = ?");
            $stmt->execute([$coverUrl, time(), $courseId]);
            
            if ($stmt->rowCount() > 0) {
                echo "  ✅ 课程 {$courseId} 封面设置成功\n";
                $successCount++;
            } else {
                echo "  ⚠️ 课程 {$courseId} 没有更新（可能不存在）\n";
            }
        } catch (Exception $e) {
            echo "  ❌ 课程 {$courseId} 设置失败: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📊 设置完成: {$successCount}/{$totalCount} 成功\n";
    
    if ($successCount > 0) {
        echo "\n🎯 验证结果:\n";
        echo "1. 登录后台 → 课程管理，查看封面显示\n";
        echo "2. 访问前台课程页面，确认封面正常显示\n";
        echo "3. 检查封面图片是否能正常加载\n";
        
        echo "\n📝 验证SQL:\n";
        $courseIds = implode(',', array_keys($coverMappings));
        echo "SELECT id, title, cover FROM kg_course WHERE id IN ({$courseIds});\n";
    }
    
} catch (Exception $e) {
    echo "❌ 操作失败: " . $e->getMessage() . "\n";
}

echo "\n程序结束\n";

/*
使用说明：

1. 修改配置：
   - 数据库密码：$config['database']['password']
   - 封面映射：$coverMappings 数组

2. 运行脚本：
   php course_cover_external_simple.php

3. 验证效果：
   - 后台：课程管理 → 查看封面
   - 前台：课程页面 → 确认显示
   - 数据库：检查 cover 字段

4. 工作原理：
   系统会自动识别完整URL并正确处理：
   - 保存时：提取路径部分存储
   - 读取时：如果是完整URL则直接返回
   
5. 兼容性：
   - 完全兼容现有腾讯云COS封面
   - 支持混合使用（部分腾讯云，部分外链）
   - 不影响系统其他功能
*/
?>