<?php
/**
 * 简化测试脚本
 * 用于快速测试创建一个包含视频和练习题的简单课程
 */

echo "=== 酷瓜云课堂混合内容测试脚本 ===\n\n";

// ============= 请修改这里的配置 =============
$database_password = '1qaz2wsx3edc';           // 修改为您的数据库密码
$course_id = 1;                                // 修改为您的课程ID
$minio_endpoint = 'http://192.168.1.24:9000'; // 修改为您的MinIO地址
$video_filename = '01医疗器械及UDI基础知识培训-林磊.mp4'; // 修改为您的视频文件名

// ============= 数据库连接测试 =============
echo "1. 测试数据库连接...\n";
try {
    $pdo = new PDO("mysql:host=localhost;port=3306;dbname=ctc;charset=utf8mb4", 'ctc', $database_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✓ 数据库连接成功\n";
} catch (PDOException $e) {
    echo "   ✗ 数据库连接失败: " . $e->getMessage() . "\n";
    echo "   请检查数据库密码是否正确\n";
    exit(1);
}

// ============= 课程存在性检查 =============
echo "\n2. 检查课程是否存在...\n";
try {
    $stmt = $pdo->prepare("SELECT id, title FROM kg_course WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if ($course) {
        echo "   ✓ 找到课程: {$course['title']} (ID: {$course_id})\n";
    } else {
        echo "   ✗ 课程ID {$course_id} 不存在\n";
        echo "   现有课程列表:\n";
        
        $stmt = $pdo->query("SELECT id, title FROM kg_course WHERE deleted = 0 ORDER BY id DESC LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "     - ID: {$row['id']}, 标题: {$row['title']}\n";
        }
        exit(1);
    }
} catch (PDOException $e) {
    echo "   ✗ 查询课程失败: " . $e->getMessage() . "\n";
    exit(1);
}

// ============= MinIO连接测试 =============
echo "\n3. 测试MinIO连接...\n";
$video_url = "{$minio_endpoint}/course-files/{$video_filename}";
echo "   测试视频地址: {$video_url}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $video_url);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result !== false && $httpCode == 200) {
    echo "   ✓ 视频文件可以访问\n";
} else {
    echo "   ⚠ 视频文件无法访问 (HTTP {$httpCode})\n";
    echo "   请检查MinIO地址和文件路径\n";
}

// ============= 询问是否继续 =============
echo "\n4. 准备创建测试章节...\n";
echo "将创建以下内容:\n";
echo "   - 1个视频章节: 【测试】视频播放\n";
echo "   - 1个练习章节: 【测试】练习题目\n\n";

echo "确认创建测试章节？(y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) !== 'y' && trim($line) !== 'Y') {
    echo "操作已取消\n";
    exit(0);
}

// ============= 创建测试章节 =============
echo "\n=== 开始创建测试章节 ===\n";

try {
    $pdo->beginTransaction();
    
    // 创建视频章节
    echo "\n1. 创建视频章节...\n";
    $stmt = $pdo->prepare("
        INSERT INTO kg_chapter (course_id, title, model, sort, published, create_time, update_time) 
        VALUES (?, ?, 1, 10, 1, ?, ?)
    ");
    $stmt->execute([$course_id, '【测试】视频播放', time(), time()]);
    $video_chapter_id = $pdo->lastInsertId();
    echo "   ✓ 视频章节创建成功 (ID: {$video_chapter_id})\n";
    
    // 创建VOD记录
    $fileRemote = [
        'duration' => ['hours' => 0, 'minutes' => 45, 'seconds' => 0],
        'hd' => ['url' => $video_url],
        'sd' => ['url' => $video_url],
        'fd' => ['url' => $video_url]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO kg_chapter_vod (course_id, chapter_id, file_remote, create_time, update_time) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$course_id, $video_chapter_id, json_encode($fileRemote), time(), time()]);
    echo "   ✓ 视频外链配置成功\n";
    
    // 更新章节属性
    $attrs = [
        'file' => ['status' => 'uploaded'],
        'duration' => 2700  // 45分钟
    ];
    $stmt = $pdo->prepare("UPDATE kg_chapter SET attrs = ? WHERE id = ?");
    $stmt->execute([json_encode($attrs), $video_chapter_id]);
    
    // 创建练习章节
    echo "\n2. 创建练习章节...\n";
    $stmt = $pdo->prepare("
        INSERT INTO kg_chapter (course_id, title, model, sort, published, create_time, update_time) 
        VALUES (?, ?, 3, 20, 1, ?, ?)
    ");
    $stmt->execute([$course_id, '【测试】练习题目', time(), time()]);
    $exercise_chapter_id = $pdo->lastInsertId();
    echo "   ✓ 练习章节创建成功 (ID: {$exercise_chapter_id})\n";
    
    // 创建练习内容
    $exercise_content = '
<h3>🧠 测试练习题</h3>
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
    <div style="margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h4 style="color: #333; margin-bottom: 15px;">题目1：这是一道测试题，请选择正确答案</h4>
        <div style="margin-left: 20px; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                <input type="radio" name="q1" value="A" style="margin-right: 8px;">
                A. 选项A - 错误答案
            </label>
            <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                <input type="radio" name="q1" value="B" style="margin-right: 8px;">
                B. 选项B - 正确答案
            </label>
            <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                <input type="radio" name="q1" value="C" style="margin-right: 8px;">
                C. 选项C - 错误答案
            </label>
        </div>
        <button onclick="checkAnswer1()" style="padding: 8px 16px; background: #1890ff; color: white; border: none; border-radius: 4px; cursor: pointer;">提交答案</button>
        <div id="result-q1" style="display: none; margin-top: 15px; padding: 10px; border-radius: 4px;"></div>
        
        <details style="margin-top: 15px;">
            <summary style="cursor: pointer; color: #1890ff; font-weight: bold;">点击查看答案解析</summary>
            <div style="margin-top: 10px; padding: 10px; background: #f0f8ff; border-radius: 4px;">
                <p><strong>正确答案：</strong>B</p>
                <p><strong>解析：</strong>这是一道测试题，正确答案是选项B。</p>
            </div>
        </details>
    </div>
</div>

<script>
function checkAnswer1() {
    var selected = document.querySelector(\'input[name="q1"]:checked\');
    var resultDiv = document.getElementById(\'result-q1\');
    
    if (selected) {
        if (selected.value === \'B\') {
            resultDiv.innerHTML = \'<p style="color: #52c41a; font-weight: bold;">✓ 回答正确！</p>\';
            resultDiv.style.background = \'#f6ffed\';
        } else {
            resultDiv.innerHTML = \'<p style="color: #ff4d4f; font-weight: bold;">✗ 回答错误，请查看答案解析</p>\';
            resultDiv.style.background = \'#fff2f0\';
        }
        resultDiv.style.display = \'block\';
    } else {
        alert(\'请选择一个答案\');
    }
}
</script>
';
    
    $stmt = $pdo->prepare("
        INSERT INTO kg_chapter_read (course_id, chapter_id, content, create_time, update_time) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$course_id, $exercise_chapter_id, $exercise_content, time(), time()]);
    echo "   ✓ 练习内容创建成功\n";
    
    $pdo->commit();
    
    echo "\n=== 创建完成 ===\n";
    echo "✅ 成功创建了2个测试章节！\n\n";
    
    echo "📋 创建结果:\n";
    echo "   视频章节ID: {$video_chapter_id}\n";
    echo "   练习章节ID: {$exercise_chapter_id}\n";
    echo "   视频地址: {$video_url}\n\n";
    
    echo "🎯 下一步操作:\n";
    echo "   1. 登录后台 → 课程管理 → 章节管理\n";
    echo "   2. 查看新创建的测试章节\n";
    echo "   3. 测试视频播放和练习题功能\n";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "✗ 创建失败: " . $e->getMessage() . "\n";
}
?>