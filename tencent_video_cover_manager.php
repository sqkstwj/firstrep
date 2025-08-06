<?php
/**
 * 腾讯云视频封面管理工具
 * 获取、查看、替换腾讯云VOD视频封面
 */

echo "=== 腾讯云视频封面管理工具 ===\n\n";

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

// ============= 获取腾讯云VOD封面 =============
function getTencentVodCover($fileId) {
    // 注意：这里需要配置腾讯云VOD的密钥和服务
    // 实际使用时需要根据项目中的VodService来实现
    
    try {
        // 模拟调用腾讯云VOD API获取媒体信息
        // 实际代码应该使用项目中的VodService
        
        /*
        $vodService = new \App\Services\Vod();
        $mediaInfo = $vodService->getMediaInfo($fileId);
        
        if (isset($mediaInfo['MediaInfoSet'][0]['BasicInfo']['CoverUrl'])) {
            return $mediaInfo['MediaInfoSet'][0]['BasicInfo']['CoverUrl'];
        }
        */
        
        // 临时返回null，实际使用时需要实现上述逻辑
        return null;
        
    } catch (Exception $e) {
        echo "  ⚠️ 获取腾讯云封面失败: " . $e->getMessage() . "\n";
        return null;
    }
}

// ============= 测试封面链接 =============
function testCoverUrl($url) {
    if (empty($url)) return false;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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

// ============= 查看所有视频章节封面状态 =============
function viewAllVideoCovers($pdo) {
    echo "--- 查看所有视频章节封面状态 ---\n";
    
    $stmt = $pdo->query("
        SELECT 
            c.id as chapter_id,
            c.title,
            c.course_id,
            cv.file_id,
            cv.file_remote,
            co.title as course_title
        FROM kg_chapter c
        LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id
        LEFT JOIN kg_course co ON c.course_id = co.id
        WHERE c.model = 1 AND c.deleted = 0
        ORDER BY c.course_id, c.sort
    ");
    
    $chapters = $stmt->fetchAll();
    
    if (empty($chapters)) {
        echo "  没有找到视频章节\n";
        return;
    }
    
    $currentCourseId = null;
    $totalChapters = 0;
    $tencentVodChapters = 0;
    $externalChapters = 0;
    $noCoverChapters = 0;
    
    foreach ($chapters as $chapter) {
        $totalChapters++;
        
        // 显示课程分组
        if ($currentCourseId !== $chapter['course_id']) {
            $currentCourseId = $chapter['course_id'];
            echo "\n📚 课程: {$chapter['course_title']} (ID: {$chapter['course_id']})\n";
            echo str_repeat("-", 60) . "\n";
        }
        
        echo "  📹 章节 {$chapter['chapter_id']}: {$chapter['title']}\n";
        
        // 分析封面来源
        if (!empty($chapter['file_id'])) {
            // 腾讯云VOD视频
            $tencentVodChapters++;
            echo "    类型: 腾讯云VOD (FileID: {$chapter['file_id']})\n";
            
            $coverUrl = getTencentVodCover($chapter['file_id']);
            if ($coverUrl) {
                echo "    封面: {$coverUrl}\n";
                $testResult = testCoverUrl($coverUrl);
                $status = $testResult['success'] ? '✅ 可访问' : '❌ 无法访问';
                echo "    状态: {$status}\n";
            } else {
                echo "    封面: ⚠️ 未获取到腾讯云封面\n";
                $noCoverChapters++;
            }
            
        } elseif (!empty($chapter['file_remote'])) {
            // 外链视频
            $externalChapters++;
            echo "    类型: 外链视频\n";
            
            $fileRemote = json_decode($chapter['file_remote'], true) ?: [];
            $coverUrl = $fileRemote['cover']['url'] ?? '';
            
            if (!empty($coverUrl)) {
                echo "    封面: {$coverUrl}\n";
                $testResult = testCoverUrl($coverUrl);
                $status = $testResult['success'] ? '✅ 可访问' : '❌ 无法访问';
                echo "    状态: {$status}\n";
            } else {
                echo "    封面: ❌ 未设置外链封面\n";
                $noCoverChapters++;
            }
            
        } else {
            echo "    类型: ⚠️ 无视频文件\n";
            $noCoverChapters++;
        }
        
        echo "\n";
    }
    
    // 显示统计信息
    echo "📊 统计信息:\n";
    echo "  总章节数: {$totalChapters}\n";
    echo "  腾讯云VOD: {$tencentVodChapters}\n";
    echo "  外链视频: {$externalChapters}\n";
    echo "  无封面/问题: {$noCoverChapters}\n";
}

// ============= 查看特定课程的视频封面 =============
function viewCourseVideoCovers($pdo, $courseId) {
    echo "--- 查看课程 {$courseId} 的视频封面 ---\n";
    
    // 获取课程信息
    $stmt = $pdo->prepare("SELECT title FROM kg_course WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo "  ❌ 课程ID {$courseId} 不存在\n";
        return;
    }
    
    echo "📚 课程: {$course['title']}\n\n";
    
    // 获取视频章节
    $stmt = $pdo->prepare("
        SELECT 
            c.id as chapter_id,
            c.title,
            cv.file_id,
            cv.file_remote
        FROM kg_chapter c
        LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id
        WHERE c.course_id = ? AND c.model = 1 AND c.deleted = 0
        ORDER BY c.sort
    ");
    $stmt->execute([$courseId]);
    $chapters = $stmt->fetchAll();
    
    if (empty($chapters)) {
        echo "  该课程没有视频章节\n";
        return;
    }
    
    foreach ($chapters as $chapter) {
        echo "📹 章节 {$chapter['chapter_id']}: {$chapter['title']}\n";
        
        if (!empty($chapter['file_id'])) {
            echo "  类型: 腾讯云VOD\n";
            echo "  FileID: {$chapter['file_id']}\n";
            
            $coverUrl = getTencentVodCover($chapter['file_id']);
            if ($coverUrl) {
                echo "  封面: {$coverUrl}\n";
                $testResult = testCoverUrl($coverUrl);
                $status = $testResult['success'] ? '✅ 可访问' : '❌ 无法访问';
                echo "  状态: {$status}\n";
            } else {
                echo "  封面: ⚠️ 未获取到\n";
            }
            
        } elseif (!empty($chapter['file_remote'])) {
            echo "  类型: 外链视频\n";
            
            $fileRemote = json_decode($chapter['file_remote'], true) ?: [];
            $coverUrl = $fileRemote['cover']['url'] ?? '';
            
            if (!empty($coverUrl)) {
                echo "  封面: {$coverUrl}\n";
                $testResult = testCoverUrl($coverUrl);
                $status = $testResult['success'] ? '✅ 可访问' : '❌ 无法访问';
                echo "  状态: {$status}\n";
            } else {
                echo "  封面: ❌ 未设置\n";
            }
            
        } else {
            echo "  类型: ⚠️ 无视频文件\n";
        }
        
        echo "\n";
    }
}

// ============= 导出封面信息到CSV =============
function exportCoverInfo($pdo, $filename = 'video_covers.csv') {
    echo "--- 导出封面信息到 {$filename} ---\n";
    
    $stmt = $pdo->query("
        SELECT 
            c.id as chapter_id,
            c.title as chapter_title,
            c.course_id,
            co.title as course_title,
            cv.file_id,
            cv.file_remote
        FROM kg_chapter c
        LEFT JOIN kg_chapter_vod cv ON c.id = cv.chapter_id
        LEFT JOIN kg_course co ON c.course_id = co.id
        WHERE c.model = 1 AND c.deleted = 0
        ORDER BY c.course_id, c.sort
    ");
    
    $chapters = $stmt->fetchAll();
    
    $fp = fopen($filename, 'w');
    
    // 写入CSV头部
    fputcsv($fp, [
        '章节ID',
        '章节标题', 
        '课程ID',
        '课程标题',
        '视频类型',
        'FileID',
        '封面URL',
        '封面状态'
    ]);
    
    foreach ($chapters as $chapter) {
        $videoType = '';
        $fileId = '';
        $coverUrl = '';
        $coverStatus = '';
        
        if (!empty($chapter['file_id'])) {
            $videoType = '腾讯云VOD';
            $fileId = $chapter['file_id'];
            $coverUrl = getTencentVodCover($chapter['file_id']) ?: '';
            
            if ($coverUrl) {
                $testResult = testCoverUrl($coverUrl);
                $coverStatus = $testResult['success'] ? '可访问' : '无法访问';
            } else {
                $coverStatus = '未获取到';
            }
            
        } elseif (!empty($chapter['file_remote'])) {
            $videoType = '外链视频';
            $fileRemote = json_decode($chapter['file_remote'], true) ?: [];
            $coverUrl = $fileRemote['cover']['url'] ?? '';
            
            if ($coverUrl) {
                $testResult = testCoverUrl($coverUrl);
                $coverStatus = $testResult['success'] ? '可访问' : '无法访问';
            } else {
                $coverStatus = '未设置';
            }
        } else {
            $videoType = '无视频文件';
            $coverStatus = '无视频';
        }
        
        fputcsv($fp, [
            $chapter['chapter_id'],
            $chapter['chapter_title'],
            $chapter['course_id'],
            $chapter['course_title'],
            $videoType,
            $fileId,
            $coverUrl,
            $coverStatus
        ]);
    }
    
    fclose($fp);
    echo "  ✅ 封面信息已导出到 {$filename}\n";
}

// ============= 主程序 =============
try {
    echo "🔗 连接数据库...\n";
    $pdo = connectDatabase($config);
    echo "✅ 数据库连接成功\n\n";
    
    echo "请选择操作:\n";
    echo "1. 查看所有视频章节封面状态\n";
    echo "2. 查看特定课程的视频封面\n";
    echo "3. 导出封面信息到CSV文件\n";
    echo "4. 查看课程列表\n";
    echo "\n请输入选项 (1-4): ";
    
    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);
    
    echo "\n";
    
    switch ($choice) {
        case '1':
            viewAllVideoCovers($pdo);
            break;
            
        case '2':
            echo "请输入课程ID: ";
            $handle = fopen("php://stdin", "r");
            $courseId = trim(fgets($handle));
            fclose($handle);
            echo "\n";
            
            if (is_numeric($courseId)) {
                viewCourseVideoCovers($pdo, $courseId);
            } else {
                echo "❌ 无效的课程ID\n";
            }
            break;
            
        case '3':
            echo "请输入文件名 (默认: video_covers.csv): ";
            $handle = fopen("php://stdin", "r");
            $filename = trim(fgets($handle));
            fclose($handle);
            
            if (empty($filename)) {
                $filename = 'video_covers.csv';
            }
            
            exportCoverInfo($pdo, $filename);
            break;
            
        case '4':
            $stmt = $pdo->query("
                SELECT 
                    c.id,
                    c.title,
                    COUNT(ch.id) as chapter_count,
                    COUNT(CASE WHEN ch.model = 1 THEN 1 END) as video_count
                FROM kg_course c
                LEFT JOIN kg_chapter ch ON c.id = ch.course_id AND ch.deleted = 0
                WHERE c.deleted = 0
                GROUP BY c.id, c.title
                ORDER BY c.id DESC
            ");
            $courses = $stmt->fetchAll();
            
            echo "📚 课程列表:\n";
            foreach ($courses as $course) {
                echo "  ID: {$course['id']}, 标题: {$course['title']}, 章节: {$course['chapter_count']}, 视频: {$course['video_count']}\n";
            }
            break;
            
        default:
            echo "❌ 无效选项\n";
            break;
    }
    
} catch (Exception $e) {
    echo "❌ 操作失败: " . $e->getMessage() . "\n";
}

echo "\n📝 重要说明:\n";
echo "1. 腾讯云VOD封面获取功能需要配置VOD服务密钥\n";
echo "2. 实际使用时请修改getTencentVodCover函数实现\n";
echo "3. 外链视频封面需要手动在file_remote中设置\n";
echo "4. 可以通过CSV文件批量分析和处理封面问题\n";

echo "\n程序结束\n";

/*
使用说明：

1. 配置修改：
   - 数据库连接信息
   - 腾讯云VOD服务配置（在getTencentVodCover函数中）

2. 功能说明：
   - 查看所有视频章节的封面状态
   - 按课程查看视频封面
   - 导出封面信息到CSV文件
   - 测试封面链接可访问性

3. 实际部署：
   - 需要在getTencentVodCover函数中实现真实的VOD API调用
   - 可以参考项目中的VodService类
   - 确保有足够的API调用权限

4. 扩展功能：
   - 可以添加批量替换封面功能
   - 可以添加从视频中提取封面帧的功能
   - 可以集成图片处理功能（压缩、裁剪等）
*/
?>