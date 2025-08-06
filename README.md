# MinIO视频上传到酷瓜云课堂工具

本工具提供两种方案将MinIO上的视频文件集成到酷瓜云课堂项目中。

## 方案对比

| 特性 | 方案一：上传到腾讯云VOD | 方案二：MinIO外链 |
|------|----------------------|------------------|
| **实现方式** | 从MinIO下载→上传到腾讯云VOD | 直接使用MinIO外链地址 |
| **存储成本** | 需要腾讯云VOD存储费用 | 仅需MinIO存储 |
| **网络要求** | 需要稳定的上传带宽 | MinIO需公网访问 |
| **视频功能** | 支持转码、防盗链、水印等 | 功能相对简单 |
| **播放体验** | 腾讯云CDN加速 | 依赖MinIO服务器性能 |
| **推荐场景** | 生产环境，需要专业视频服务 | 测试环境，或内网使用 |

## 环境要求

- PHP >= 7.3
- Composer
- MySQL数据库访问权限
- MinIO服务器访问权限
- 腾讯云VOD服务（方案一需要）

## 安装依赖

```bash
composer install
```

## 方案一：上传到腾讯云VOD

### 1. 配置参数

编辑 `minio_video_uploader.php` 中的配置信息：

```php
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
        'region' => 'ap-guangzhou'
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'ctc',
        'username' => 'ctc',
        'password' => '1qaz2wsx3edc'
    ]
];
```

### 2. 准备视频列表

```php
$videoList = [
    ['minio_path' => 'videos/lesson1.mp4', 'chapter_id' => 1],
    ['minio_path' => 'videos/lesson2.mp4', 'chapter_id' => 2],
    // 添加更多视频...
];
```

### 3. 执行上传

```bash
php minio_video_uploader.php
```

### 4. 工作流程

1. **下载阶段**：从MinIO下载视频文件到临时目录
2. **申请上传**：向腾讯云VOD申请上传凭证
3. **上传文件**：将文件上传到腾讯云COS
4. **确认上传**：通知腾讯云VOD上传完成
5. **更新数据库**：将FileId写入课堂系统数据库
6. **自动转码**：腾讯云VOD自动进行视频转码

## 方案二：MinIO外链

### 1. 配置参数

编辑 `alternative_solution.php` 中的配置：

```php
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
```

### 2. 准备视频信息

```php
$videoList = [
    [
        'chapter_id' => 1,
        'minio_path' => 'videos/lesson1.mp4',
        'video_info' => [
            'duration' => ['hours' => 0, 'minutes' => 45, 'seconds' => 30]
        ]
    ]
];
```

### 3. 执行添加

```bash
php alternative_solution.php
```

### 4. 注意事项

- MinIO服务器必须可以公网访问
- 需要手动设置视频时长信息
- 播放性能依赖MinIO服务器

## 数据库表结构

### kg_chapter 章节表
```sql
- id: 章节ID
- course_id: 课程ID  
- title: 章节标题
- model: 课程模式（1=点播，2=直播）
- attrs: JSON格式的扩展属性
```

### kg_chapter_vod 点播扩展表
```sql
- id: 主键
- course_id: 课程ID
- chapter_id: 章节ID
- file_id: 腾讯云VOD文件ID
- file_transcode: 转码信息（JSON）
- file_remote: 外链信息（JSON）
```

## 常见问题

### Q1: 上传失败怎么办？
- 检查MinIO连接配置
- 确认腾讯云VOD服务已开通
- 查看错误日志定位问题

### Q2: 视频无法播放？
- 方案一：等待腾讯云转码完成
- 方案二：确认MinIO外链可访问

### Q3: 如何获取章节ID？
运行脚本会显示现有章节列表，或查询数据库：
```sql
SELECT id, course_id, title FROM kg_chapter WHERE model = 1;
```

### Q4: 批量处理大量视频？
建议分批处理，避免超时：
- 每批处理10-20个视频
- 增加错误重试机制
- 记录处理进度

## 扩展功能

### 1. 添加视频信息获取

可以集成FFmpeg获取准确的视频信息：

```bash
# 安装FFmpeg
sudo apt-get install ffmpeg

# 获取视频时长
ffprobe -v quiet -show_entries format=duration -of csv="p=0" video.mp4
```

### 2. 进度监控

添加进度条显示上传状态：

```php
// 使用symfony/console组件
composer require symfony/console
```

### 3. 日志记录

添加详细的操作日志：

```php
// 使用monolog/monolog
composer require monolog/monolog
```

## 技术支持

如有问题，请检查：
1. PHP版本和扩展
2. 网络连接状态  
3. 服务器权限配置
4. 数据库连接参数

---

**注意**：使用前请备份数据库，在测试环境验证后再在生产环境使用。
