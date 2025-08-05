<?php
/**
 * MinIO视频上传到酷瓜云课堂工具
 * 
 * 使用方法：
 * 1. 配置MinIO和腾讯云VOD参数
 * 2. 运行脚本：php minio_video_uploader.php
 */

require_once 'vendor/autoload.php';

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Vod\V20180717\VodClient;
use TencentCloud\Vod\V20180717\Models\ApplyUploadRequest;
use TencentCloud\Vod\V20180717\Models\CommitUploadRequest;

class MinIOVideoUploader
{
    private $minioConfig;
    private $vodConfig;
    private $vodClient;
    private $pdo;
    
    public function __construct($config)
    {
        $this->minioConfig = $config['minio'];
        $this->vodConfig = $config['vod'];
        $this->initVodClient();
        $this->initDatabase($config['database']);
    }
    
    /**
     * 初始化腾讯云VOD客户端
     */
    private function initVodClient()
    {
        $credential = new Credential($this->vodConfig['secret_id'], $this->vodConfig['secret_key']);
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint('vod.tencentcloudapi.com');
        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        
        $this->vodClient = new VodClient($credential, $this->vodConfig['region'], $clientProfile);
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
     * 从MinIO下载文件到临时目录
     */
    private function downloadFromMinIO($objectName, $tempPath)
    {
        $minioClient = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => $this->minioConfig['endpoint'],
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->minioConfig['access_key'],
                'secret' => $this->minioConfig['secret_key'],
            ],
        ]);
        
        try {
            $result = $minioClient->getObject([
                'Bucket' => $this->minioConfig['bucket'],
                'Key' => $objectName,
                'SaveAs' => $tempPath
            ]);
            
            return file_exists($tempPath);
        } catch (Exception $e) {
            echo "MinIO下载失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * 上传视频到腾讯云VOD
     */
    public function uploadVideoToVOD($minioObjectName, $chapterId = null)
    {
        echo "开始处理视频: {$minioObjectName}\n";
        
        // 1. 从MinIO下载文件到临时目录
        $tempDir = sys_get_temp_dir();
        $fileName = basename($minioObjectName);
        $tempPath = $tempDir . '/' . $fileName;
        
        if (!$this->downloadFromMinIO($minioObjectName, $tempPath)) {
            echo "从MinIO下载文件失败\n";
            return false;
        }
        
        echo "文件已下载到临时目录: {$tempPath}\n";
        
        // 2. 申请上传
        $applyRequest = new ApplyUploadRequest();
        $applyRequest->MediaType = pathinfo($fileName, PATHINFO_EXTENSION);
        $applyRequest->MediaName = pathinfo($fileName, PATHINFO_FILENAME);
        
        try {
            $applyResponse = $this->vodClient->ApplyUpload($applyRequest);
            echo "申请上传成功，获得上传URL\n";
        } catch (Exception $e) {
            echo "申请上传失败: " . $e->getMessage() . "\n";
            unlink($tempPath);
            return false;
        }
        
        // 3. 上传文件到腾讯云COS
        $uploadUrl = $applyResponse->MediaStoragePath;
        $sessionKey = $applyResponse->TempCertificate->SessionToken;
        
        if (!$this->uploadToCOS($tempPath, $applyResponse)) {
            echo "上传到COS失败\n";
            unlink($tempPath);
            return false;
        }
        
        echo "文件已上传到腾讯云COS\n";
        
        // 4. 确认上传
        $commitRequest = new CommitUploadRequest();
        $commitRequest->VodSessionKey = $applyResponse->VodSessionKey;
        
        try {
            $commitResponse = $this->vodClient->CommitUpload($commitRequest);
            $fileId = $commitResponse->FileId;
            echo "上传完成，获得FileId: {$fileId}\n";
        } catch (Exception $e) {
            echo "确认上传失败: " . $e->getMessage() . "\n";
            unlink($tempPath);
            return false;
        }
        
        // 5. 更新数据库
        if ($chapterId) {
            $this->updateChapterVod($chapterId, $fileId, $fileName);
        }
        
        // 6. 清理临时文件
        unlink($tempPath);
        
        return $fileId;
    }
    
    /**
     * 上传文件到腾讯云COS
     */
    private function uploadToCOS($filePath, $applyResponse)
    {
        $cosClient = new Qcloud\Cos\Client([
            'region' => $this->vodConfig['region'],
            'schema' => 'https',
            'credentials' => [
                'secretId' => $applyResponse->TempCertificate->SecretId,
                'secretKey' => $applyResponse->TempCertificate->SecretKey,
                'token' => $applyResponse->TempCertificate->Token,
            ]
        ]);
        
        try {
            $result = $cosClient->upload(
                $applyResponse->StorageBucket,
                $applyResponse->MediaStoragePath,
                fopen($filePath, 'rb')
            );
            
            return !empty($result['Location']);
        } catch (Exception $e) {
            echo "COS上传失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * 更新章节VOD信息
     */
    private function updateChapterVod($chapterId, $fileId, $fileName)
    {
        try {
            // 检查是否存在记录
            $stmt = $this->pdo->prepare("SELECT id FROM kg_chapter_vod WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // 更新现有记录
                $stmt = $this->pdo->prepare("
                    UPDATE kg_chapter_vod 
                    SET file_id = ?, update_time = ? 
                    WHERE chapter_id = ?
                ");
                $stmt->execute([$fileId, time(), $chapterId]);
                echo "更新章节VOD记录: chapter_id={$chapterId}, file_id={$fileId}\n";
            } else {
                // 插入新记录
                $stmt = $this->pdo->prepare("
                    INSERT INTO kg_chapter_vod (course_id, chapter_id, file_id, create_time, update_time) 
                    SELECT course_id, id, ?, ?, ? FROM kg_chapter WHERE id = ?
                ");
                $stmt->execute([$fileId, time(), time(), $chapterId]);
                echo "创建章节VOD记录: chapter_id={$chapterId}, file_id={$fileId}\n";
            }
            
            // 更新章节状态
            $stmt = $this->pdo->prepare("
                UPDATE kg_chapter 
                SET attrs = JSON_SET(attrs, '$.file.status', 'uploaded', '$.file.id', ?) 
                WHERE id = ?
            ");
            $stmt->execute([$fileId, $chapterId]);
            
        } catch (Exception $e) {
            echo "数据库更新失败: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * 批量上传MinIO中的视频文件
     */
    public function batchUpload($videoList)
    {
        $results = [];
        
        foreach ($videoList as $video) {
            $minioObjectName = $video['minio_path'];
            $chapterId = $video['chapter_id'] ?? null;
            
            $fileId = $this->uploadVideoToVOD($minioObjectName, $chapterId);
            
            $results[] = [
                'minio_path' => $minioObjectName,
                'chapter_id' => $chapterId,
                'file_id' => $fileId,
                'success' => $fileId !== false
            ];
            
            // 避免请求过于频繁
            sleep(2);
        }
        
        return $results;
    }
}

// 配置信息
$config = [
    'minio' => [
        'endpoint' => 'http://your-minio-server:9000',
        'access_key' => 'your-access-key',
        'secret_key' => 'your-secret-key',
        'bucket' => 'your-bucket-name'
    ],
    'vod' => [
        'secret_id' => 'your-tencent-secret-id',
        'secret_key' => 'your-tencent-secret-key',
        'region' => 'ap-guangzhou'  // 根据你的腾讯云地域设置
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
    $uploader = new MinIOVideoUploader($config);
    
    // 单个文件上传示例
    // $fileId = $uploader->uploadVideoToVOD('videos/sample.mp4', 123);
    
    // 批量上传示例
    $videoList = [
        ['minio_path' => 'videos/lesson1.mp4', 'chapter_id' => 1],
        ['minio_path' => 'videos/lesson2.mp4', 'chapter_id' => 2],
        // 添加更多视频...
    ];
    
    $results = $uploader->batchUpload($videoList);
    
    echo "\n=== 上传结果 ===\n";
    foreach ($results as $result) {
        echo sprintf(
            "MinIO路径: %s | 章节ID: %s | FileId: %s | 状态: %s\n",
            $result['minio_path'],
            $result['chapter_id'] ?: 'N/A',
            $result['file_id'] ?: 'N/A',
            $result['success'] ? '成功' : '失败'
        );
    }
}