# create_mixed_content.php 运行指南

## 🎯 **第一步：修改配置信息**

打开 `create_mixed_content.php` 文件，找到配置区域并修改：

### 1.1 数据库配置
```php
$config = [
    'database' => [
        'host' => 'localhost',           // 改为您的数据库主机
        'port' => 3306,                 // 数据库端口
        'dbname' => 'ctc',              // 数据库名称
        'username' => 'ctc',            // 数据库用户名
        'password' => '您的数据库密码'    // 改为真实密码
    ],
    'minio' => [
        'endpoint' => 'http://192.168.1.24:9000',  // 改为您的MinIO地址
        'bucket' => 'course-files'                  // 改为您的bucket名称
    ]
];
```

### 1.2 课程内容配置
```php
$courseContent = [
    'course_id' => 1,  // 改为您的实际课程ID
    'chapters' => [
        [
            'title' => '【视频】医疗器械概述',
            'type' => 'video',
            'minio_path' => '01医疗器械及UDI基础知识培训-林磊.mp4',  // 改为实际文件路径
            'duration' => ['hours' => 0, 'minutes' => 45, 'seconds' => 0]
        ],
        // ... 更多章节配置
    ]
];
```

## 🔧 **第二步：获取课程ID**

### 方法1：通过后台查看
1. 登录酷瓜云课堂后台
2. 进入 **课程管理**
3. 在课程列表中，查看URL或编辑链接中的ID

### 方法2：通过数据库查询
```sql
SELECT id, title FROM kg_course ORDER BY id DESC LIMIT 10;
```

## 📁 **第三步：准备文件**

### 3.1 确保视频文件在MinIO中
确认以下文件已上传到MinIO：
```
http://192.168.1.24:9000/course-files/01医疗器械及UDI基础知识培训-林磊.mp4
http://192.168.1.24:9000/course-files/documents/ppt/医疗器械基础知识.ppt
http://192.168.1.24:9000/course-files/documents/pdf/医疗器械法规汇编.pdf
```

### 3.2 测试文件访问
在浏览器中访问上述链接，确保可以正常下载/播放。

## 🎯 **第四步：运行脚本**

### 方法1：在服务器上运行（推荐）

#### 1. 上传文件到服务器
```bash
# 使用SCP上传文件
scp create_mixed_content.php user@your-server:/path/to/upload/
```

#### 2. SSH登录服务器
```bash
ssh user@your-server
cd /path/to/upload/
```

#### 3. 运行脚本
```bash
php create_mixed_content.php
```

### 方法2：使用Docker运行

如果本地没有PHP环境：

```bash
# 进入文件所在目录
cd /path/to/your/files

# 使用Docker运行
docker run --rm -v $(pwd):/app -w /app php:7.4-cli php create_mixed_content.php
```

### 方法3：在Windows环境运行

#### 1. 安装PHP
- 下载PHP：https://windows.php.net/download/
- 解压到 `C:\php`
- 添加到系统PATH

#### 2. 运行脚本
```cmd
cd C:\path\to\your\files
php create_mixed_content.php
```

## 📊 **预期运行结果**

脚本运行时会显示：

```
混合内容章节创建工具
===================
✓ 数据库连接成功

将要创建以下章节：
1. 【视频】医疗器械概述 (video)
2. 【资料】基础知识PPT (document)
3. 【文档】法规文件汇编 (document)
4. 【练习】课后测试题 (exercise)

确认创建？(y/n): y

=== 开始创建混合内容章节 ===

--- 创建章节: 【视频】医疗器械概述 ---
✓ 视频章节创建成功
  外链地址: http://192.168.1.24:9000/course-files/01医疗器械及UDI基础知识培训-林磊.mp4

--- 创建章节: 【资料】基础知识PPT ---
✓ 文档章节创建成功
  文档类型: ppt

--- 创建章节: 【文档】法规文件汇编 ---
✓ 文档章节创建成功
  文档类型: pdf

--- 创建章节: 【练习】课后测试题 ---
✓ 练习章节创建成功
  题目数量: 2

=== 创建完成 ===
```

## 🔧 **常见问题解决**

### Q1: "php: command not found"
**解决方法：**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php-cli php-mysql

# CentOS/RHEL
sudo yum install php-cli php-mysql

# 或使用Docker方法
```

### Q2: 数据库连接失败
```
✗ 数据库连接失败: Access denied for user 'ctc'@'localhost'
```
**解决方法：**
- 检查数据库用户名和密码
- 确认数据库服务正在运行
- 检查防火墙设置

### Q3: 课程ID不存在
**解决方法：**
```sql
-- 查询现有课程
SELECT id, title FROM kg_course WHERE deleted = 0;

-- 使用正确的课程ID
```

### Q4: MinIO文件无法访问
**解决方法：**
- 确认MinIO服务正在运行
- 检查文件路径是否正确
- 确认MinIO访问策略设置

## 📝 **自定义配置示例**

### 示例1：单个视频课程
```php
$courseContent = [
    'course_id' => 5,
    'chapters' => [
        [
            'title' => '第一课：产品介绍',
            'type' => 'video',
            'minio_path' => 'videos/lesson1.mp4',
            'duration' => ['hours' => 0, 'minutes' => 30, 'seconds' => 0]
        ]
    ]
];
```

### 示例2：完整课程结构
```php
$courseContent = [
    'course_id' => 3,
    'chapters' => [
        // 视频章节
        [
            'title' => '1.1 【视频】课程导入',
            'type' => 'video',
            'minio_path' => 'course3/intro.mp4',
            'duration' => ['hours' => 0, 'minutes' => 15, 'seconds' => 0]
        ],
        
        // 文档章节
        [
            'title' => '1.2 【资料】学习资料',
            'type' => 'document',
            'content' => [
                'type' => 'pdf',
                'file_url' => 'course3/materials.pdf',
                'description' => '本课程的学习资料和参考文档'
            ]
        ],
        
        // 练习章节
        [
            'title' => '1.3 【练习】课后测试',
            'type' => 'exercise',
            'content' => [
                'questions' => [
                    [
                        'type' => 'single',
                        'question' => '这是一道单选题？',
                        'options' => [
                            'A' => '选项A',
                            'B' => '选项B',
                            'C' => '选项C'
                        ],
                        'answer' => 'B',
                        'explanation' => '答案解析...'
                    ]
                ]
            ]
        ]
    ]
];
```

## ✅ **验证结果**

脚本运行成功后：

### 1. 检查数据库
```sql
-- 查看新创建的章节
SELECT id, title, model FROM kg_chapter WHERE course_id = 您的课程ID ORDER BY sort;

-- 查看VOD记录
SELECT * FROM kg_chapter_vod WHERE course_id = 您的课程ID;

-- 查看图文记录
SELECT * FROM kg_chapter_read WHERE course_id = 您的课程ID;
```

### 2. 检查后台
- 登录后台 → 课程管理 → 章节管理
- 查看新创建的章节是否显示
- 测试视频播放和文档下载

### 3. 检查前台
- 访问课程页面
- 测试各种类型章节的显示效果

## 🎯 **快速开始**

最简单的测试方法：

1. **复制脚本到服务器**
2. **修改前3行关键配置**：
   ```php
   'course_id' => 您的课程ID,
   'password' => '您的数据库密码',
   'endpoint' => 'http://您的MinIO地址:9000',
   ```
3. **运行**: `php create_mixed_content.php`
4. **输入**: `y` 确认创建

就这么简单！脚本会自动为您创建完整的混合内容课程结构。