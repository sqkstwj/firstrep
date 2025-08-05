<?php
/**
 * 课程封面外链批量设置工具
 * 支持单个设置和批量设置课程封面为MinIO外链
 */

echo "=== 课程封面外链设置工具 ===\n\n";

// ============= 配置区域 =============
$config = [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'ctc',
        'username' => 'ctc',
        'password' => '1qaz2wsx3edc'  // 修改为您的数据库密码
    ],
    'minio' => [
        'endpoint' => 'http://192.168.1.24:9000',
        'bucket' => 'course-files',
        'cover_path' => 'covers'  // 封面存储路径
    ]
];

// ============= 封面配置 =============
$coverSettings = [
    // 方式1：单个课程设置
    'single' => [
        'course_id' => 1,
        'cover_url' => 'http://192.168.1.24:9000/course-files/covers/medical-equipment.jpg'
    ],
    
    // 方式2：批量设置（按课程ID映射）
    'batch_mapping' => [
        1 => 'medical-equipment.jpg',
        2 => 'healthcare-basics.jpg', 
        3 => 'device-maintenance.jpg',
        4 => 'safety-training.jpg',
        5 => 'quality-control.jpg'
    ],
    
    // 方式3：统一规则批量设置
    'batch_rule' => [
        'course_ids' => [1, 2, 3, 4, 5],  // 要设置的课程ID列表
        'filename_pattern' => 'course-{id}.jpg'  // 文件名规则，{id}会被替换为课程ID
    ]
];

// ============= 数据库连接 =============
function connectDatabase($config) {
    try {
        $dsn = "mysql:host={$config['database']['host']};port={$config['database']['port']};dbname={$config['database']['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("数据库连接失败: " . $e->getMessage());
    }
}

// ============= 封面链接测试 =============
function testCoverUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    return [
        'success' => ($result !== false && $httpCode == 200),
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'is_image' => strpos($contentType, 'image/') === 0
    ];
}

// ============= 单个课程封面设置 =============
function setSingleCourseCover($pdo, $courseId, $coverUrl) {
    echo "--- 设置课程 {$courseId} 的封面 ---\n";
    
    // 检查课程是否存在
    $stmt = $pdo->prepare("SELECT id, title FROM kg_course WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo "   ✗ 课程ID {$courseId} 不存在\n";
        return false;
    }
    
    echo "   课程: {$course['title']}\n";
    echo "   封面: {$coverUrl}\n";
    
    // 测试封面链接
    echo "   测试封面链接...\n";
    $testResult = testCoverUrl($coverUrl);
    
    if (!$testResult['success']) {
        echo "   ⚠ 封面链接无法访问 (HTTP {$testResult['http_code']})\n";
        echo "   是否继续设置？(y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) !== 'y' && trim($line) !== 'Y') {
            echo "   跳过设置\n";
            return false;
        }
    } else {
        echo "   ✓ 封面链接可以访问\n";
        if ($testResult['is_image']) {
            echo "   ✓ 确认为图片格式 ({$testResult['content_type']})\n";
        } else {
            echo "   ⚠ 不是标准图片格式 ({$testResult['content_type']})\n";
        }
    }
    
    // 更新数据库
    try {
        $stmt = $pdo->prepare("UPDATE kg_course SET cover = ?, update_time = ? WHERE id = ?");
        $stmt->execute([$coverUrl, time(), $courseId]);
        
        echo "   ✓ 封面设置成功\n";
        return true;
    } catch (Exception $e) {
        echo "   ✗ 设置失败: " . $e->getMessage() . "\n";
        return false;
    }
}

// ============= 批量设置封面（映射方式） =============
function setBatchCoversMapping($pdo, $config, $mapping) {
    echo "--- 批量设置封面（映射方式） ---\n";
    
    $successCount = 0;
    $totalCount = count($mapping);
    
    foreach ($mapping as $courseId => $filename) {
        $coverUrl = "{$config['minio']['endpoint']}/{$config['minio']['bucket']}/{$config['minio']['cover_path']}/{$filename}";
        
        if (setSingleCourseCover($pdo, $courseId, $coverUrl)) {
            $successCount++;
        }
        echo "\n";
    }
    
    echo "批量设置完成: {$successCount}/{$totalCount} 成功\n";
    return $successCount;
}

// ============= 批量设置封面（规则方式） =============
function setBatchCoversRule($pdo, $config, $rule) {
    echo "--- 批量设置封面（规则方式） ---\n";
    
    $successCount = 0;
    $totalCount = count($rule['course_ids']);
    
    foreach ($rule['course_ids'] as $courseId) {
        $filename = str_replace('{id}', $courseId, $rule['filename_pattern']);
        $coverUrl = "{$config['minio']['endpoint']}/{$config['minio']['bucket']}/{$config['minio']['cover_path']}/{$filename}";
        
        if (setSingleCourseCover($pdo, $courseId, $coverUrl)) {
            $successCount++;
        }
        echo "\n";
    }
    
    echo "批量设置完成: {$successCount}/{$totalCount} 成功\n";
    return $successCount;
}

// ============= 查看课程封面 =============
function viewCourseCover($pdo, $courseId = null) {
    echo "--- 查看课程封面 ---\n";
    
    if ($courseId) {
        $stmt = $pdo->prepare("SELECT id, title, cover FROM kg_course WHERE id = ?");
        $stmt->execute([$courseId]);
        $courses = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("SELECT id, title, cover FROM kg_course WHERE deleted = 0 ORDER BY id DESC LIMIT 10");
        $courses = $stmt->fetchAll();
    }
    
    if (empty($courses)) {
        echo "   没有找到课程\n";
        return;
    }
    
    foreach ($courses as $course) {
        echo "   课程ID: {$course['id']}\n";
        echo "   标题: {$course['title']}\n";
        echo "   封面: {$course['cover']}\n";
        
        if (!empty($course['cover'])) {
            $testResult = testCoverUrl($course['cover']);
            $status = $testResult['success'] ? '✓ 可访问' : '✗ 无法访问';
            echo "   状态: {$status}\n";
        } else {
            echo "   状态: 未设置封面\n";
        }
        echo "\n";
    }
}

// ============= 主程序 =============
try {
    // 连接数据库
    echo "1. 连接数据库...\n";
    $pdo = connectDatabase($config);
    echo "   ✓ 数据库连接成功\n\n";
    
    // 显示菜单
    echo "请选择操作:\n";
    echo "1. 设置单个课程封面\n";
    echo "2. 批量设置封面（映射方式）\n";
    echo "3. 批量设置封面（规则方式）\n";
    echo "4. 查看课程封面\n";
    echo "5. 查看所有课程列表\n";
    echo "\n请输入选项 (1-5): ";
    
    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);
    
    echo "\n";
    
    switch ($choice) {
        case '1':
            // 单个设置
            setSingleCourseCover(
                $pdo, 
                $coverSettings['single']['course_id'], 
                $coverSettings['single']['cover_url']
            );
            break;
            
        case '2':
            // 批量映射设置
            setBatchCoversMapping($pdo, $config, $coverSettings['batch_mapping']);
            break;
            
        case '3':
            // 批量规则设置
            setBatchCoversRule($pdo, $config, $coverSettings['batch_rule']);
            break;
            
        case '4':
            // 查看指定课程封面
            echo "请输入课程ID (留空查看最近10个): ";
            $handle = fopen("php://stdin", "r");
            $courseId = trim(fgets($handle));
            fclose($handle);
            echo "\n";
            
            viewCourseCover($pdo, $courseId ?: null);
            break;
            
        case '5':
            // 查看所有课程
            $stmt = $pdo->query("SELECT id, title, cover FROM kg_course WHERE deleted = 0 ORDER BY id DESC");
            $courses = $stmt->fetchAll();
            
            echo "=== 所有课程列表 ===\n";
            foreach ($courses as $course) {
                $hasCover = !empty($course['cover']) ? '有封面' : '无封面';
                echo "ID: {$course['id']}, 标题: {$course['title']}, 封面: {$hasCover}\n";
            }
            break;
            
        default:
            echo "无效选项\n";
            break;
    }
    
} catch (Exception $e) {
    echo "✗ 操作失败: " . $e->getMessage() . "\n";
}

echo "\n程序结束\n";
?>