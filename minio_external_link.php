<?php
/**
 * MinIO视频外链添加工具 - 简化版
 * 
 * 使用步骤：
 * 1. 修改下面的配置信息
 * 2. 运行：php minio_external_link.php
 */

// ============= 配置区域 =============
$config = [
    // MinIO配置
    'minio' => [
        'endpoint' => 'http://192.168.1.100:9000',  // 替换为您的MinIO服务器地址
        'bucket' => 'videos'                        // 替换为您的bucket名称
    ],
    
    // 数据库配置
    'database' => [
        'host' => 'localhost',                      // 数据库主机
        'port' => 3306,                            // 数据库端口
        'dbname' => 'ctc',                         // 数据库名称
        'username' => 'ctc',                       // 数据库用户名
        'password' => '1qaz2wsx3edc'               // 数据库密码
    ]
];

// ============= 视频配置区域 =============
// 在这里配置您的视频列表
$videoList = [
    [
        'chapter_id' => 1,                         // 章节ID（需要替换为实际的章节ID）
        'minio_path' => 'course1/lesson1.mp4',    // MinIO中的视频路径
        'video_info' => [
            'duration' => ['hours' => 0, 'minutes' => 30, 'seconds' => 0]  // 视频时长
        ]
    ],
    [
        'chapter_id' => 2,
        'minio_path' => 'course1/lesson2.mp4',
        'video_info' => [
            'duration' => ['hours' => 0, 'minutes' => 45, 'seconds' => 30]
        ]
    ]
    // 继续添加更多视频...
];

// ============= 主要功能代码 =============

class SimpleMinIOManager
{
    private $pdo;
    private $config;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->connectDatabase();
    }
    
    /**
     * 连接数据库
     */
    private function connectDatabase()
    {
        $db = $this->config['database'];
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4";
        
        try {
            $this->pdo = new PDO($dsn, $db['username'], $db['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✓ 数据库连接成功\n";
        } catch (PDOException $e) {
            echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * 生成MinIO外链地址
     */
    private function generateMinIOUrl($objectPath)
    {
        $endpoint = rtrim($this->config['minio']['endpoint'], '/');
        $bucket = $this->config['minio']['bucket'];
        
        return "{$endpoint}/{$bucket}/{$objectPath}";
    }
    
    /**
     * 查看现有章节列表
     */
    public function showChapterList($courseId = null)
    {
        echo "\n=== 现有章节列表 ===\n";
        
        $sql = "SELECT id, course_id, title, attrs FROM kg_chapter WHERE model = 1";
        $params = [];
        
        if ($courseId) {
            $sql .= " AND course_id = ?";
            $params[] = $courseId;
        }
        
        $sql .= " ORDER BY course_id, sort";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($chapters)) {
                echo "没有找到点播章节\n";
                return [];
            }
            
            foreach ($chapters as $chapter) {
                $attrs = json_decode($chapter['attrs'], true);
                $duration = isset($attrs['duration']) ? $this->formatDuration($attrs['duration']) : '未设置';
                
                echo sprintf(
                    "章节ID: %s | 课程ID: %s | 标题: %s | 时长: %s\n",
                    $chapter['id'],
                    $chapter['course_id'],
                    $chapter['title'],
                    $duration
                );
            }
            
            return $chapters;
            
        } catch (PDOException $e) {
            echo "查询章节失败: " . $e->getMessage() . "\n";
            return [];
        }
    }
    
    /**
     * 格式化时长显示
     */
    private function formatDuration($seconds)
    {
        if ($seconds <= 0) return '0秒';
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $result = '';
        if ($hours > 0) $result .= $hours . '小时';
        if ($minutes > 0) $result .= $minutes . '分钟';
        if ($secs > 0) $result .= $secs . '秒';
        
        return $result;
    }
    
    /**
     * 为章节添加外链视频
     */
    public function addExternalVideo($chapterId, $minioPath, $videoInfo)
    {
        echo "\n--- 处理章节 {$chapterId} ---\n";
        echo "MinIO路径: {$minioPath}\n";
        
        // 生成外链地址
        $videoUrl = $this->generateMinIOUrl($minioPath);
        echo "外链地址: {$videoUrl}\n";
        
        // 构建外链数据
        $fileRemote = [
            'duration' => $videoInfo['duration'],
            'hd' => ['url' => $videoUrl],  // 高清
            'sd' => ['url' => $videoUrl],  // 标清
            'fd' => ['url' => $videoUrl]   // 极速
        ];
        
        try {
            // 检查章节是否存在
            $stmt = $this->pdo->prepare("SELECT id, title FROM kg_chapter WHERE id = ?");
            $stmt->execute([$chapterId]);
            $chapter = $stmt->fetch();
            
            if (!$chapter) {
                echo "✗ 章节 {$chapterId} 不存在\n";
                return false;
            }
            
            echo "章节标题: {$chapter['title']}\n";
            
            // 检查是否已有VOD记录
            $stmt = $this->pdo->prepare("SELECT id FROM kg_chapter_vod WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            $vodExists = $stmt->fetch();
            
            if ($vodExists) {
                // 更新现有记录
                $stmt = $this->pdo->prepare("
                    UPDATE kg_chapter_vod 
                    SET file_remote = ?, update_time = ? 
                    WHERE chapter_id = ?
                ");
                $stmt->execute([json_encode($fileRemote), time(), $chapterId]);
                echo "✓ 更新VOD外链记录成功\n";
            } else {
                // 创建新记录
                $stmt = $this->pdo->prepare("
                    INSERT INTO kg_chapter_vod (course_id, chapter_id, file_remote, create_time, update_time) 
                    SELECT course_id, id, ?, ?, ? FROM kg_chapter WHERE id = ?
                ");
                $stmt->execute([json_encode($fileRemote), time(), time(), $chapterId]);
                echo "✓ 创建VOD外链记录成功\n";
            }
            
            // 更新章节状态和时长
            $totalSeconds = $videoInfo['duration']['hours'] * 3600 + 
                           $videoInfo['duration']['minutes'] * 60 + 
                           $videoInfo['duration']['seconds'];
            
            $stmt = $this->pdo->prepare("
                UPDATE kg_chapter 
                SET attrs = JSON_SET(
                    COALESCE(attrs, '{}'), 
                    '$.file.status', 'uploaded',
                    '$.duration', ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$totalSeconds, $chapterId]);
            echo "✓ 更新章节状态和时长成功\n";
            
            return true;
            
        } catch (PDOException $e) {
            echo "✗ 数据库操作失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * 批量处理视频
     */
    public function batchProcess($videoList)
    {
        echo "\n=== 开始批量处理 ===\n";
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($videoList as $index => $video) {
            echo "\n进度: " . ($index + 1) . "/" . count($videoList) . "\n";
            
            $success = $this->addExternalVideo(
                $video['chapter_id'],
                $video['minio_path'],
                $video['video_info']
            );
            
            if ($success) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        echo "\n=== 处理完成 ===\n";
        echo "成功: {$successCount} 个\n";
        echo "失败: {$failCount} 个\n";
    }
    
    /**
     * 测试MinIO连接
     */
    public function testMinIOConnection()
    {
        echo "\n=== 测试MinIO连接 ===\n";
        
        $testUrl = $this->generateMinIOUrl('test.txt');
        echo "测试URL: {$testUrl}\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result !== false && $httpCode < 500) {
            echo "✓ MinIO服务器可访问\n";
            return true;
        } else {
            echo "✗ MinIO服务器无法访问 (HTTP Code: {$httpCode})\n";
            echo "请检查MinIO服务器地址和网络连接\n";
            return false;
        }
    }
}

// ============= 执行区域 =============

if (php_sapi_name() === 'cli') {
    echo "MinIO视频外链添加工具\n";
    echo "==================\n";
    
    $manager = new SimpleMinIOManager($config);
    
    // 测试连接
    $manager->testMinIOConnection();
    
    // 显示现有章节
    $chapters = $manager->showChapterList();
    
    if (empty($chapters)) {
        echo "\n请先在酷瓜云课堂后台创建课程和章节\n";
        exit(1);
    }
    
    // 询问是否继续
    echo "\n是否继续添加外链视频？(y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'y' && trim($line) !== 'Y') {
        echo "操作已取消\n";
        exit(0);
    }
    
    // 执行批量处理
    $manager->batchProcess($videoList);
    
} else {
    echo "请在命令行下运行此脚本\n";
}
?>