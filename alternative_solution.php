<?php
/**
 * MinIO视频外链添加到酷瓜云课堂工具
 * 
 * 这个方案不需要重新上传视频到腾讯云，而是直接使用MinIO的外链地址
 * 适用于MinIO服务器可以公网访问的情况
 */

class MinIOExternalLinkManager
{
    private $pdo;
    private $minioConfig;
    
    public function __construct($config)
    {
        $this->minioConfig = $config['minio'];
        $this->initDatabase($config['database']);
    }
    
    /**
     * 初始化数据库连接
     */
    private function initDatabase($dbConfig)
    {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    /**
     * 获取MinIO文件的外链地址
     */
    private function getMinIOUrl($objectName)
    {
        $endpoint = rtrim($this->minioConfig['endpoint'], '/');
        $bucket = $this->minioConfig['bucket'];
        
        return "{$endpoint}/{$bucket}/{$objectName}";
    }
    
    /**
     * 获取视频文件的基本信息
     */
    private function getVideoInfo($filePath)
    {
        // 这里可以使用FFmpeg或其他工具获取视频信息
        // 简化处理，返回默认值
        return [
            'duration' => ['hours' => 0, 'minutes' => 30, 'seconds' => 0], // 默认30分钟
            'format' => pathinfo($filePath, PATHINFO_EXTENSION)
        ];
    }
    
    /**
     * 为章节添加MinIO外链视频
     */
    public function addExternalVideo($chapterId, $minioObjectName, $videoInfo = null)
    {
        echo "为章节 {$chapterId} 添加外链视频: {$minioObjectName}\n";
        
        $videoUrl = $this->getMinIOUrl($minioObjectName);
        
        if (!$videoInfo) {
            $videoInfo = $this->getVideoInfo($minioObjectName);
        }
        
        // 构建外链视频数据
        $fileRemote = [
            'duration' => $videoInfo['duration'],
            'hd' => ['url' => $videoUrl],  // 高清
            'sd' => ['url' => $videoUrl],  // 标清  
            'fd' => ['url' => $videoUrl]   // 极速
        ];
        
        try {
            // 检查是否存在VOD记录
            $stmt = $this->pdo->prepare("SELECT id FROM kg_chapter_vod WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // 更新现有记录
                $stmt = $this->pdo->prepare("
                    UPDATE kg_chapter_vod 
                    SET file_remote = ?, update_time = ? 
                    WHERE chapter_id = ?
                ");
                $stmt->execute([json_encode($fileRemote), time(), $chapterId]);
                echo "更新章节VOD外链记录成功\n";
            } else {
                // 插入新记录
                $stmt = $this->pdo->prepare("
                    INSERT INTO kg_chapter_vod (course_id, chapter_id, file_remote, create_time, update_time) 
                    SELECT course_id, id, ?, ?, ? FROM kg_chapter WHERE id = ?
                ");
                $stmt->execute([json_encode($fileRemote), time(), time(), $chapterId]);
                echo "创建章节VOD外链记录成功\n";
            }
            
            // 更新章节状态和时长
            $totalSeconds = $videoInfo['duration']['hours'] * 3600 + 
                           $videoInfo['duration']['minutes'] * 60 + 
                           $videoInfo['duration']['seconds'];
            
            $stmt = $this->pdo->prepare("
                UPDATE kg_chapter 
                SET attrs = JSON_SET(
                    attrs, 
                    '$.file.status', 'uploaded',
                    '$.duration', ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$totalSeconds, $chapterId]);
            
            return true;
            
        } catch (Exception $e) {
            echo "数据库操作失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * 批量添加外链视频
     */
    public function batchAddExternalVideos($videoList)
    {
        $results = [];
        
        foreach ($videoList as $video) {
            $success = $this->addExternalVideo(
                $video['chapter_id'],
                $video['minio_path'],
                $video['video_info'] ?? null
            );
            
            $results[] = [
                'chapter_id' => $video['chapter_id'],
                'minio_path' => $video['minio_path'],
                'success' => $success
            ];
        }
        
        return $results;
    }
    
    /**
     * 从数据库获取章节列表
     */
    public function getChapterList($courseId = null)
    {
        $sql = "SELECT id, course_id, title FROM kg_chapter WHERE model = 1"; // model=1 表示点播课程
        $params = [];
        
        if ($courseId) {
            $sql .= " AND course_id = ?";
            $params[] = $courseId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 配置信息
$config = [
    'minio' => [
        'endpoint' => 'http://your-minio-server:9000',
        'bucket' => 'your-bucket-name'
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'ctc',
        'username' => 'ctc',
        'password' => '1qaz2wsx3edc'
    ]
];

// 使用示例
if (php_sapi_name() === 'cli') {
    $manager = new MinIOExternalLinkManager($config);
    
    // 方案1: 手动指定章节和视频对应关系
    $videoList = [
        [
            'chapter_id' => 1,
            'minio_path' => 'videos/lesson1.mp4',
            'video_info' => [
                'duration' => ['hours' => 0, 'minutes' => 45, 'seconds' => 30]
            ]
        ],
        [
            'chapter_id' => 2,
            'minio_path' => 'videos/lesson2.mp4',
            'video_info' => [
                'duration' => ['hours' => 1, 'minutes' => 20, 'seconds' => 0]
            ]
        ]
    ];
    
    $results = $manager->batchAddExternalVideos($videoList);
    
    echo "\n=== 外链添加结果 ===\n";
    foreach ($results as $result) {
        echo sprintf(
            "章节ID: %s | MinIO路径: %s | 状态: %s\n",
            $result['chapter_id'],
            $result['minio_path'],
            $result['success'] ? '成功' : '失败'
        );
    }
    
    // 方案2: 查看现有章节列表，手动匹配
    echo "\n=== 现有章节列表 ===\n";
    $chapters = $manager->getChapterList();
    foreach ($chapters as $chapter) {
        echo sprintf(
            "章节ID: %s | 课程ID: %s | 标题: %s\n",
            $chapter['id'],
            $chapter['course_id'],
            $chapter['title']
        );
    }
}