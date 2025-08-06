<?php
/**
 * 混合内容章节创建工具
 * 帮助快速创建包含视频、文档、练习题的完整课程结构
 */

// ============= 配置区域 =============
$config = [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'ctc',
        'username' => 'ctc',
        'password' => '1qaz2wsx3edc'
    ],
    'minio' => [
        'endpoint' => 'http://192.168.1.24:9000',
        'bucket' => 'course-files'
    ]
];

// ============= 课程内容配置 =============
$courseContent = [
    'course_id' => 1,  // 替换为您的课程ID
    'chapters' => [
        [
            'title' => '【视频】医疗器械概述',
            'type' => 'video',  // 点播章节
            'minio_path' => '01医疗器械及UDI基础知识培训-林磊.mp4',
            'duration' => ['hours' => 0, 'minutes' => 45, 'seconds' => 0]
        ],
        [
            'title' => '【资料】基础知识PPT',
            'type' => 'document',  // 图文章节
            'content' => [
                'type' => 'ppt',
                'file_url' => 'documents/ppt/医疗器械基础知识.ppt',
                'description' => '本PPT包含医疗器械的基本概念、分类和管理要求'
            ]
        ],
        [
            'title' => '【文档】法规文件汇编',
            'type' => 'document',  // 图文章节
            'content' => [
                'type' => 'pdf',
                'file_url' => 'documents/pdf/医疗器械法规汇编.pdf',
                'description' => '相关法律法规文件汇编，供学员参考学习'
            ]
        ],
        [
            'title' => '【练习】课后测试题',
            'type' => 'exercise',  // 图文章节
            'content' => [
                'questions' => [
                    [
                        'type' => 'single',
                        'question' => '医疗器械UDI的全称是什么？',
                        'options' => [
                            'A' => 'Unique Device Identifier',
                            'B' => 'Universal Device Information', 
                            'C' => 'Unified Device Identity',
                            'D' => 'Unique Data Interface'
                        ],
                        'answer' => 'A',
                        'explanation' => 'UDI是Unique Device Identifier的缩写，即医疗器械唯一标识。'
                    ],
                    [
                        'type' => 'multiple',
                        'question' => '医疗器械按风险程度分为哪几类？（多选）',
                        'options' => [
                            'A' => '第一类',
                            'B' => '第二类',
                            'C' => '第三类',
                            'D' => '第四类'
                        ],
                        'answer' => ['A', 'B', 'C'],
                        'explanation' => '医疗器械按风险程度分为第一类、第二类、第三类。'
                    ]
                ]
            ]
        ]
    ]
];

class MixedContentCreator
{
    private $pdo;
    private $config;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->connectDatabase();
    }
    
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
     * 创建混合内容章节
     */
    public function createMixedContent($courseContent)
    {
        echo "\n=== 开始创建混合内容章节 ===\n";
        
        $courseId = $courseContent['course_id'];
        $chapters = $courseContent['chapters'];
        
        foreach ($chapters as $index => $chapterData) {
            $sort = ($index + 1) * 10;  // 排序号：10, 20, 30...
            
            echo "\n--- 创建章节: {$chapterData['title']} ---\n";
            
            switch ($chapterData['type']) {
                case 'video':
                    $this->createVideoChapter($courseId, $chapterData, $sort);
                    break;
                case 'document':
                    $this->createDocumentChapter($courseId, $chapterData, $sort);
                    break;
                case 'exercise':
                    $this->createExerciseChapter($courseId, $chapterData, $sort);
                    break;
            }
        }
        
        echo "\n=== 创建完成 ===\n";
    }
    
    /**
     * 创建视频章节
     */
    private function createVideoChapter($courseId, $chapterData, $sort)
    {
        // 1. 创建章节记录
        $chapterId = $this->createChapter($courseId, $chapterData['title'], 1, $sort);
        
        if (!$chapterId) {
            echo "✗ 创建章节失败\n";
            return false;
        }
        
        // 2. 创建VOD扩展记录
        $videoUrl = $this->getMinIOUrl($chapterData['minio_path']);
        $duration = $chapterData['duration'];
        
        $fileRemote = [
            'duration' => $duration,
            'hd' => ['url' => $videoUrl],
            'sd' => ['url' => $videoUrl],
            'fd' => ['url' => $videoUrl]
        ];
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO kg_chapter_vod (course_id, chapter_id, file_remote, create_time, update_time) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseId, $chapterId, json_encode($fileRemote), time(), time()]);
            
            // 3. 更新章节状态
            $totalSeconds = $duration['hours'] * 3600 + $duration['minutes'] * 60 + $duration['seconds'];
            $this->updateChapterAttrs($chapterId, [
                'file' => ['status' => 'uploaded'],
                'duration' => $totalSeconds
            ]);
            
            echo "✓ 视频章节创建成功\n";
            echo "  外链地址: {$videoUrl}\n";
            
        } catch (PDOException $e) {
            echo "✗ 创建VOD记录失败: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * 创建文档章节
     */
    private function createDocumentChapter($courseId, $chapterData, $sort)
    {
        // 1. 创建章节记录
        $chapterId = $this->createChapter($courseId, $chapterData['title'], 3, $sort);
        
        if (!$chapterId) {
            echo "✗ 创建章节失败\n";
            return false;
        }
        
        // 2. 生成图文内容
        $content = $this->generateDocumentContent($chapterData['content']);
        
        // 3. 创建图文扩展记录
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO kg_chapter_read (course_id, chapter_id, content, create_time, update_time) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseId, $chapterId, $content, time(), time()]);
            
            echo "✓ 文档章节创建成功\n";
            echo "  文档类型: {$chapterData['content']['type']}\n";
            
        } catch (PDOException $e) {
            echo "✗ 创建图文记录失败: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * 创建练习章节
     */
    private function createExerciseChapter($courseId, $chapterData, $sort)
    {
        // 1. 创建章节记录
        $chapterId = $this->createChapter($courseId, $chapterData['title'], 3, $sort);
        
        if (!$chapterId) {
            echo "✗ 创建章节失败\n";
            return false;
        }
        
        // 2. 生成练习题内容
        $content = $this->generateExerciseContent($chapterData['content']['questions']);
        
        // 3. 创建图文扩展记录
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO kg_chapter_read (course_id, chapter_id, content, create_time, update_time) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseId, $chapterId, $content, time(), time()]);
            
            echo "✓ 练习章节创建成功\n";
            echo "  题目数量: " . count($chapterData['content']['questions']) . "\n";
            
        } catch (PDOException $e) {
            echo "✗ 创建练习记录失败: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * 创建基础章节记录
     */
    private function createChapter($courseId, $title, $model, $sort)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO kg_chapter (course_id, title, model, sort, published, create_time, update_time) 
                VALUES (?, ?, ?, ?, 1, ?, ?)
            ");
            $stmt->execute([$courseId, $title, $model, $sort, time(), time()]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            echo "✗ 创建章节失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * 更新章节属性
     */
    private function updateChapterAttrs($chapterId, $attrs)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE kg_chapter 
                SET attrs = ? 
                WHERE id = ?
            ");
            $stmt->execute([json_encode($attrs), $chapterId]);
            
        } catch (PDOException $e) {
            echo "✗ 更新章节属性失败: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * 生成文档内容HTML
     */
    private function generateDocumentContent($contentData)
    {
        $fileUrl = $this->getMinIOUrl($contentData['file_url']);
        $type = $contentData['type'];
        $description = $contentData['description'];
        
        $html = "<h3>📚 课程资料</h3>\n";
        $html .= "<div style='border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 8px;'>\n";
        
        // 文件信息
        $html .= "<div style='margin-bottom: 15px;'>\n";
        $html .= "<h4 style='color: #1890ff; margin-bottom: 10px;'>";
        
        switch ($type) {
            case 'ppt':
                $html .= "📊 PPT演示文稿";
                break;
            case 'pdf':
                $html .= "📄 PDF文档";
                break;
            case 'doc':
                $html .= "📝 Word文档";
                break;
            default:
                $html .= "📁 文档资料";
        }
        
        $html .= "</h4>\n";
        $html .= "<p style='color: #666; margin-bottom: 15px;'>{$description}</p>\n";
        $html .= "</div>\n";
        
        // 下载链接
        $html .= "<div style='margin-bottom: 20px;'>\n";
        $html .= "<a href='{$fileUrl}' target='_blank' style='display: inline-block; padding: 10px 20px; background: #1890ff; color: white; text-decoration: none; border-radius: 4px;'>\n";
        $html .= "🔗 点击下载/查看\n";
        $html .= "</a>\n";
        $html .= "</div>\n";
        
        // 在线预览（PDF）
        if ($type === 'pdf') {
            $html .= "<div style='margin-top: 20px;'>\n";
            $html .= "<h4>📖 在线预览</h4>\n";
            $html .= "<iframe src='{$fileUrl}' width='100%' height='600px' style='border: 1px solid #ddd;'></iframe>\n";
            $html .= "</div>\n";
        }
        
        $html .= "</div>\n";
        
        return $html;
    }
    
    /**
     * 生成练习题内容HTML
     */
    private function generateExerciseContent($questions)
    {
        $html = "<h3>🧠 课后练习</h3>\n";
        $html .= "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>\n";
        
        foreach ($questions as $index => $question) {
            $questionNum = $index + 1;
            $html .= "<div style='margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>\n";
            
            // 题目
            $html .= "<h4 style='color: #333; margin-bottom: 15px;'>题目{$questionNum}：{$question['question']}</h4>\n";
            
            // 选项
            $html .= "<div style='margin-left: 20px; margin-bottom: 15px;'>\n";
            foreach ($question['options'] as $key => $option) {
                $inputType = $question['type'] === 'multiple' ? 'checkbox' : 'radio';
                $html .= "<label style='display: block; margin-bottom: 8px; cursor: pointer;'>\n";
                $html .= "<input type='{$inputType}' name='q{$questionNum}' value='{$key}' style='margin-right: 8px;'>\n";
                $html .= "{$key}. {$option}\n";
                $html .= "</label>\n";
            }
            $html .= "</div>\n";
            
            // 提交按钮
            $html .= "<button onclick=\"checkAnswer{$questionNum}()\" style='padding: 8px 16px; background: #1890ff; color: white; border: none; border-radius: 4px; cursor: pointer;'>提交答案</button>\n";
            
            // 答案区域
            $html .= "<div id='result-q{$questionNum}' style='display: none; margin-top: 15px; padding: 10px; border-radius: 4px;'></div>\n";
            
            // 答案详解
            $html .= "<details style='margin-top: 15px;'>\n";
            $html .= "<summary style='cursor: pointer; color: #1890ff; font-weight: bold;'>点击查看答案解析</summary>\n";
            $html .= "<div style='margin-top: 10px; padding: 10px; background: #f0f8ff; border-radius: 4px;'>\n";
            
            if (is_array($question['answer'])) {
                $html .= "<p><strong>正确答案：</strong>" . implode(', ', $question['answer']) . "</p>\n";
            } else {
                $html .= "<p><strong>正确答案：</strong>{$question['answer']}</p>\n";
            }
            
            $html .= "<p><strong>解析：</strong>{$question['explanation']}</p>\n";
            $html .= "</div>\n";
            $html .= "</details>\n";
            
            $html .= "</div>\n";
        }
        
        $html .= "</div>\n";
        
        // 添加JavaScript
        $html .= "<script>\n";
        foreach ($questions as $index => $question) {
            $questionNum = $index + 1;
            $correctAnswer = is_array($question['answer']) ? json_encode($question['answer']) : "'{$question['answer']}'";
            
            $html .= "function checkAnswer{$questionNum}() {\n";
            if ($question['type'] === 'multiple') {
                $html .= "  var selected = [];\n";
                $html .= "  var checkboxes = document.querySelectorAll('input[name=\"q{$questionNum}\"]:checked');\n";
                $html .= "  checkboxes.forEach(function(cb) { selected.push(cb.value); });\n";
                $html .= "  var correct = {$correctAnswer};\n";
                $html .= "  var isCorrect = selected.length === correct.length && selected.every(function(val) { return correct.includes(val); });\n";
            } else {
                $html .= "  var selected = document.querySelector('input[name=\"q{$questionNum}\"]:checked');\n";
                $html .= "  var isCorrect = selected && selected.value === {$correctAnswer};\n";
            }
            
            $html .= "  var resultDiv = document.getElementById('result-q{$questionNum}');\n";
            $html .= "  if (isCorrect) {\n";
            $html .= "    resultDiv.innerHTML = '<p style=\"color: #52c41a; font-weight: bold;\">✓ 回答正确！</p>';\n";
            $html .= "    resultDiv.style.background = '#f6ffed';\n";
            $html .= "  } else {\n";
            $html .= "    resultDiv.innerHTML = '<p style=\"color: #ff4d4f; font-weight: bold;\">✗ 回答错误，请查看答案解析</p>';\n";
            $html .= "    resultDiv.style.background = '#fff2f0';\n";
            $html .= "  }\n";
            $html .= "  resultDiv.style.display = 'block';\n";
            $html .= "}\n";
        }
        $html .= "</script>\n";
        
        return $html;
    }
    
    /**
     * 生成MinIO文件URL
     */
    private function getMinIOUrl($filePath)
    {
        $endpoint = rtrim($this->config['minio']['endpoint'], '/');
        $bucket = $this->config['minio']['bucket'];
        
        return "{$endpoint}/{$bucket}/{$filePath}";
    }
}

// ============= 执行区域 =============
if (php_sapi_name() === 'cli') {
    echo "混合内容章节创建工具\n";
    echo "===================\n";
    
    $creator = new MixedContentCreator($config);
    
    // 显示将要创建的内容
    echo "\n将要创建以下章节：\n";
    foreach ($courseContent['chapters'] as $index => $chapter) {
        echo sprintf("%d. %s (%s)\n", $index + 1, $chapter['title'], $chapter['type']);
    }
    
    echo "\n确认创建？(y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) === 'y' || trim($line) === 'Y') {
        $creator->createMixedContent($courseContent);
    } else {
        echo "操作已取消\n";
    }
}
?>