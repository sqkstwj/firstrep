-- ===================================
-- 课程封面外链设置 SQL 脚本
-- ===================================

-- 查看当前课程列表和封面状态
SELECT 
    id,
    title,
    cover,
    CASE 
        WHEN cover IS NULL OR cover = '' THEN '未设置'
        WHEN cover LIKE 'http%' THEN '外链'
        ELSE '本地'
    END as cover_type,
    create_time,
    update_time
FROM kg_course 
WHERE deleted = 0 
ORDER BY id DESC;

-- ===================================
-- 方式1：单个课程设置封面
-- ===================================

-- 设置课程ID为1的封面
UPDATE kg_course 
SET 
    cover = 'http://192.168.1.24:9000/course-files/covers/medical-equipment.jpg',
    update_time = UNIX_TIMESTAMP()
WHERE id = 1;

-- 验证设置结果
SELECT id, title, cover FROM kg_course WHERE id = 1;

-- ===================================
-- 方式2：批量设置封面（映射方式）
-- ===================================

-- 批量设置多个课程的封面
UPDATE kg_course 
SET 
    cover = CASE id
        WHEN 1 THEN 'http://192.168.1.24:9000/course-files/covers/medical-equipment.jpg'
        WHEN 2 THEN 'http://192.168.1.24:9000/course-files/covers/healthcare-basics.jpg'
        WHEN 3 THEN 'http://192.168.1.24:9000/course-files/covers/device-maintenance.jpg'
        WHEN 4 THEN 'http://192.168.1.24:9000/course-files/covers/safety-training.jpg'
        WHEN 5 THEN 'http://192.168.1.24:9000/course-files/covers/quality-control.jpg'
        ELSE cover
    END,
    update_time = UNIX_TIMESTAMP()
WHERE id IN (1, 2, 3, 4, 5);

-- ===================================
-- 方式3：规则批量设置
-- ===================================

-- 按规则批量设置封面（文件名 = course-{id}.jpg）
UPDATE kg_course 
SET 
    cover = CONCAT('http://192.168.1.24:9000/course-files/covers/course-', id, '.jpg'),
    update_time = UNIX_TIMESTAMP()
WHERE id BETWEEN 1 AND 10;

-- ===================================
-- 方式4：条件批量设置
-- ===================================

-- 只为没有封面的课程设置默认封面
UPDATE kg_course 
SET 
    cover = 'http://192.168.1.24:9000/course-files/covers/default-course.jpg',
    update_time = UNIX_TIMESTAMP()
WHERE (cover IS NULL OR cover = '') 
  AND deleted = 0;

-- 为特定分类的课程设置封面
UPDATE kg_course 
SET 
    cover = 'http://192.168.1.24:9000/course-files/covers/medical-category.jpg',
    update_time = UNIX_TIMESTAMP()
WHERE category_id = 1  -- 医疗器械分类
  AND deleted = 0;

-- ===================================
-- 数据验证和查询
-- ===================================

-- 查看设置结果
SELECT 
    id,
    title,
    cover,
    FROM_UNIXTIME(update_time) as last_updated
FROM kg_course 
WHERE id IN (1, 2, 3, 4, 5)
ORDER BY id;

-- 统计封面设置情况
SELECT 
    CASE 
        WHEN cover IS NULL OR cover = '' THEN '未设置'
        WHEN cover LIKE 'http%' THEN '外链'
        ELSE '本地'
    END as cover_type,
    COUNT(*) as count
FROM kg_course 
WHERE deleted = 0
GROUP BY cover_type;

-- 查找封面链接异常的课程
SELECT id, title, cover
FROM kg_course 
WHERE deleted = 0
  AND cover IS NOT NULL 
  AND cover != ''
  AND cover NOT LIKE 'http%'
  AND cover NOT LIKE '/%';

-- ===================================
-- 封面链接修复
-- ===================================

-- 修复相对路径封面（如果需要转换为完整URL）
UPDATE kg_course 
SET 
    cover = CONCAT('https://your-cos-domain.com', cover),
    update_time = UNIX_TIMESTAMP()
WHERE cover LIKE '/%' 
  AND cover NOT LIKE 'http%';

-- 批量替换域名（如果需要更换图床）
UPDATE kg_course 
SET 
    cover = REPLACE(cover, 'http://old-domain.com', 'http://192.168.1.24:9000'),
    update_time = UNIX_TIMESTAMP()
WHERE cover LIKE 'http://old-domain.com%';

-- ===================================
-- 备份和恢复
-- ===================================

-- 创建封面备份表
CREATE TABLE kg_course_cover_backup AS
SELECT id, cover, update_time, NOW() as backup_time
FROM kg_course;

-- 从备份恢复封面
UPDATE kg_course c
JOIN kg_course_cover_backup b ON c.id = b.id
SET 
    c.cover = b.cover,
    c.update_time = UNIX_TIMESTAMP()
WHERE c.deleted = 0;

-- ===================================
-- 实用查询
-- ===================================

-- 查找最近更新封面的课程
SELECT 
    id,
    title,
    cover,
    FROM_UNIXTIME(update_time) as updated_at
FROM kg_course 
WHERE deleted = 0
  AND update_time > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY))
ORDER BY update_time DESC;

-- 查找特定MinIO路径的封面
SELECT id, title, cover
FROM kg_course 
WHERE cover LIKE '%192.168.1.24:9000%'
ORDER BY id;

-- 检查封面URL格式
SELECT 
    id,
    title,
    cover,
    CASE 
        WHEN cover LIKE 'https://%' THEN 'HTTPS'
        WHEN cover LIKE 'http://%' THEN 'HTTP'
        WHEN cover LIKE '/%' THEN 'Relative'
        WHEN cover IS NULL OR cover = '' THEN 'Empty'
        ELSE 'Other'
    END as url_type
FROM kg_course 
WHERE deleted = 0
ORDER BY url_type, id;

-- ===================================
-- 使用说明
-- ===================================

/*
使用步骤：

1. 准备工作：
   - 确保MinIO中已上传封面图片
   - 确认图片可通过外链访问
   - 备份数据库（可选）

2. 选择合适的更新方式：
   - 单个设置：适用于少量课程
   - 批量映射：适用于每个课程有不同封面
   - 规则批量：适用于统一命名规则的封面
   - 条件批量：适用于特定条件的课程

3. 执行前验证：
   - 先执行查询语句检查当前状态
   - 确认要更新的课程ID
   - 测试封面链接是否可访问

4. 执行更新：
   - 复制对应的UPDATE语句
   - 修改MinIO地址和文件路径
   - 执行SQL语句

5. 验证结果：
   - 执行验证查询检查结果
   - 在后台查看封面显示效果
   - 在前台测试课程封面

注意事项：
- 所有UPDATE语句都会同时更新update_time字段
- 建议先在测试环境执行
- 封面链接必须可公开访问
- 推荐图片尺寸：400×300像素
*/