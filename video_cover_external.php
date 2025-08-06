<?php
/**
 * 视频封面外链批量设置工具
 * 为外链视频添加封面支持
 */

echo "=== 视频封面外链设置工具 ===\n\n";

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
        'cover_path' => 'video-covers'  // 视频封面存储路径
    ]
];

// ============= 封面配置 =============
$coverSettings = [
    // 方式1：单个视频设置封面
    'single' => [
        'chapter_id' => 1,
        'cover_url' => 'http://192.168.1.24:9000/course-files/video-covers/chapter-1-cover.jpg'
    ],
    
    // 方式2：批量设置（按章节ID映射）
    'batch_mapping' => [
        1 => 'medical-video-1.jpg',
        2 => 'medical-video-2.jpg', 
        3 => 'medical-video-3.jpg',
        4 => 'medical-video-4.jpg',
        5 => 'medical-video-5.jpg'
    ],
    
    // 方式3：统一规则批量设置
    'batch_rule' => [
        'chapter_ids' => [1, 2, 3, 4, 5],  // 要设置的章节ID列表
        'filename_pattern' => 'chapter-{id}-cover.jpg'  // 文件名规则
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

// ============= 获取图片尺寸 =============
function getImageSize($url) {
    try {
        $size = getimagesize($url);
        if ($size) {
            return ['width' => $size[0], 'height' => $size[1]];
        }
    } catch (Exception $e) {
        // 忽略错误
    }
    return ['width' => 640, 'height' => 360]; // 默认尺寸
}

// ============= 单个视频封面设置 =============
function setSingleVideoCover($pdo, $chapterId, $coverUrl) {
    echo "--- 设置章节 {$chapterId} 的视频封面 ---\n";
    
    // 检查章节是否存在
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, cv.file_remote 
        FROM kg_chapter c 
        LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id 
        WHERE c.id = ? AND c.model = 1
    ");
    $stmt->execute([$chapterId]);
    $chapter = $stmt->fetch();
    
    if (!$chapter) {
        echo "   ✗ 章节ID {$chapterId} 不存在或不是视频章节\n";
        return false;
    }
    
    echo "   章节: {$chapter['title']}\n";
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
    
    // 获取图片尺寸
    $imageSize = getImageSize($coverUrl);
    echo "   图片尺寸: {$imageSize['width']} x {$imageSize['height']}\n";
    
    // 解析现有的 file_remote
    $fileRemote = [];
    if (!empty($chapter['file_remote'])) {
        $fileRemote = json_decode($chapter['file_remote'], true) ?: [];
    }
    
    // 添加封面信息
    $fileRemote['cover'] = [
        'url' => $coverUrl,
        'width' => $imageSize['width'],
        'height' => $imageSize['height']
    ];
    
    // 更新数据库
    try {
        $stmt = $pdo->prepare("
            UPDATE kg_chapter_vod 
            SET file_remote = ?, update_time = ? 
            WHERE chapter_id = ?
        ");
        $stmt->execute([json_encode($fileRemote), time(), $chapterId]);
        
        echo "   ✓ 视频封面设置成功\n";
        return true;
    } catch (Exception $e) {
        echo "   ✗ 设置失败: " . $e->getMessage() . "\n";
        return false;
    }
}

// ============= 批量设置封面（映射方式） =============
function setBatchCoversMapping($pdo, $config, $mapping) {
    echo "--- 批量设置视频封面（映射方式） ---\n";
    
    $successCount = 0;
    $totalCount = count($mapping);
    
    foreach ($mapping as $chapterId => $filename) {
        $coverUrl = "{$config['minio']['endpoint']}/{$config['minio']['bucket']}/{$config['minio']['cover_path']}/{$filename}";
        
        if (setSingleVideoCover($pdo, $chapterId, $coverUrl)) {
            $successCount++;
        }
        echo "\n";
    }
    
    echo "批量设置完成: {$successCount}/{$totalCount} 成功\n";
    return $successCount;
}

// ============= 批量设置封面（规则方式） =============
function setBatchCoversRule($pdo, $config, $rule) {
    echo "--- 批量设置视频封面（规则方式） ---\n";
    
    $successCount = 0;
    $totalCount = count($rule['chapter_ids']);
    
    foreach ($rule['chapter_ids'] as $chapterId) {
        $filename = str_replace('{id}', $chapterId, $rule['filename_pattern']);
        $coverUrl = "{$config['minio']['endpoint']}/{$config['minio']['bucket']}/{$config['minio']['cover_path']}/{$filename}";
        
        if (setSingleVideoCover($pdo, $chapterId, $coverUrl)) {
            $successCount++;
        }
        echo "\n";
    }
    
    echo "批量设置完成: {$successCount}/{$totalCount} 成功\n";
    return $successCount;
}

// ============= 查看视频封面 =============
function viewVideoCovers($pdo, $chapterId = null) {
    echo "--- 查看视频封面 ---\n";
    
    if ($chapterId) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, cv.file_remote 
            FROM kg_chapter c 
            LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id 
            WHERE c.id = ? AND c.model = 1
        ");
        $stmt->execute([$chapterId]);
        $chapters = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("
            SELECT c.id, c.title, cv.file_remote 
            FROM kg_chapter c 
            LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id 
            WHERE c.model = 1 AND c.deleted = 0 
            ORDER BY c.id DESC LIMIT 10
        ");
        $chapters = $stmt->fetchAll();
    }
    
    if (empty($chapters)) {
        echo "   没有找到视频章节\n";
        return;
    }
    
    foreach ($chapters as $chapter) {
        echo "   章节ID: {$chapter['id']}\n";
        echo "   标题: {$chapter['title']}\n";
        
        $fileRemote = json_decode($chapter['file_remote'], true) ?: [];
        $coverUrl = $fileRemote['cover']['url'] ?? '';
        
        if (!empty($coverUrl)) {
            echo "   封面: {$coverUrl}\n";
            $testResult = testCoverUrl($coverUrl);
            $status = $testResult['success'] ? '✓ 可访问' : '✗ 无法访问';
            echo "   状态: {$status}\n";
            
            if (isset($fileRemote['cover']['width'])) {
                echo "   尺寸: {$fileRemote['cover']['width']} x {$fileRemote['cover']['height']}\n";
            }
        } else {
            echo "   封面: 未设置\n";
            echo "   状态: 无封面\n";
        }
        echo "\n";
    }
}

// ============= 清理封面设置 =============
function clearVideoCovers($pdo, $chapterIds = []) {
    echo "--- 清理视频封面设置 ---\n";
    
    if (empty($chapterIds)) {
        echo "请输入要清理的章节ID（用逗号分隔）: ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        if (empty($input)) {
            echo "操作已取消\n";
            return;
        }
        
        $chapterIds = array_map('trim', explode(',', $input));
    }
    
    $successCount = 0;
    
    foreach ($chapterIds as $chapterId) {
        try {
            // 获取现有的 file_remote
            $stmt = $pdo->prepare("SELECT file_remote FROM kg_chapter_vod WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            $row = $stmt->fetch();
            
            if ($row) {
                $fileRemote = json_decode($row['file_remote'], true) ?: [];
                
                // 移除封面信息
                unset($fileRemote['cover']);
                
                // 更新数据库
                $stmt = $pdo->prepare("UPDATE kg_chapter_vod SET file_remote = ?, update_time = ? WHERE chapter_id = ?");
                $stmt->execute([json_encode($fileRemote), time(), $chapterId]);
                
                echo "   ✓ 章节 {$chapterId} 封面已清理\n";
                $successCount++;
            } else {
                echo "   ⚠ 章节 {$chapterId} 不存在VOD记录\n";
            }
        } catch (Exception $e) {
            echo "   ✗ 章节 {$chapterId} 清理失败: " . $e->getMessage() . "\n";
        }
    }
    
    echo "清理完成: {$successCount} 个章节\n";
}

// ============= 主程序 =============
try {
    // 连接数据库
    echo "1. 连接数据库...\n";
    $pdo = connectDatabase($config);
    echo "   ✓ 数据库连接成功\n\n";
    
    // 显示菜单
    echo "请选择操作:\n";
    echo "1. 设置单个视频封面\n";
    echo "2. 批量设置封面（映射方式）\n";
    echo "3. 批量设置封面（规则方式）\n";
    echo "4. 查看视频封面\n";
    echo "5. 查看所有视频章节列表\n";
    echo "6. 清理视频封面设置\n";
    echo "\n请输入选项 (1-6): ";
    
    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);
    
    echo "\n";
    
    switch ($choice) {
        case '1':
            // 单个设置
            setSingleVideoCover(
                $pdo, 
                $coverSettings['single']['chapter_id'], 
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
            // 查看指定章节封面
            echo "请输入章节ID (留空查看最近10个): ";
            $handle = fopen("php://stdin", "r");
            $chapterId = trim(fgets($handle));
            fclose($handle);
            echo "\n";
            
            viewVideoCovers($pdo, $chapterId ?: null);
            break;
            
        case '5':
            // 查看所有视频章节
            $stmt = $pdo->query("
                SELECT c.id, c.title, cv.file_remote 
                FROM kg_chapter c 
                LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id 
                WHERE c.model = 1 AND c.deleted = 0 
                ORDER BY c.id DESC
            ");
            $chapters = $stmt->fetchAll();
            
            echo "=== 所有视频章节列表 ===\n";
            foreach ($chapters as $chapter) {
                $fileRemote = json_decode($chapter['file_remote'], true) ?: [];
                $hasCover = !empty($fileRemote['cover']['url']) ? '有封面' : '无封面';
                echo "ID: {$chapter['id']}, 标题: {$chapter['title']}, 封面: {$hasCover}\n";
            }
            break;
            
        case '6':
            // 清理封面设置
            clearVideoCovers($pdo);
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