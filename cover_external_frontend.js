/**
 * 课程封面外链输入扩展
 * 在现有的封面上传功能基础上，添加外链输入支持
 * 
 * 使用方法：
 * 1. 将此文件保存到 public/static/admin/js/cover.external.js
 * 2. 在课程编辑页面引入此JS文件
 * 3. 确保页面已引入 layui 和 jQuery
 */

layui.use(['jquery', 'layer'], function () {
    
    var $ = layui.jquery;
    var layer = layui.layer;
    
    // 等待页面加载完成
    $(document).ready(function() {
        
        // 检查是否存在封面相关元素
        if ($('#change-cover').length === 0) {
            console.log('未找到封面上传按钮，跳过外链功能初始化');
            return;
        }
        
        // 在现有的"更换"按钮后添加"外链"按钮
        var externalButton = '<button id="external-cover" class="layui-btn layui-btn-sm layui-btn-normal" type="button" style="margin-left: 10px;">外链</button>';
        $('#change-cover').after(externalButton);
        
        // 外链按钮点击事件
        $('#external-cover').click(function() {
            var currentCover = $('input[name=cover]').val() || '';
            
            layer.prompt({
                title: '设置封面外链地址',
                formType: 2, // 文本域
                area: ['600px', '200px'],
                value: currentCover,
                btn: ['确定', '测试链接', '取消'],
                btn1: function(index, layero) {
                    // 确定按钮
                    var value = layero.find('.layui-layer-input').val().trim();
                    if (validateAndSetCover(value)) {
                        layer.close(index);
                    }
                },
                btn2: function(index, layero) {
                    // 测试链接按钮
                    var value = layero.find('.layui-layer-input').val().trim();
                    testCoverLink(value);
                    return false; // 阻止关闭弹层
                },
                btn3: function(index) {
                    // 取消按钮
                    layer.close(index);
                }
            });
        });
        
        // 添加封面预览增强功能
        addCoverPreviewEnhancement();
        
        console.log('封面外链功能已初始化');
    });
    
    /**
     * 验证并设置封面
     */
    function validateAndSetCover(value) {
        if (!value) {
            layer.msg('请输入封面链接地址', {icon: 2});
            return false;
        }
        
        // 验证URL格式
        if (!isValidUrl(value)) {
            layer.msg('请输入有效的HTTP/HTTPS链接', {icon: 2});
            return false;
        }
        
        // 设置封面
        setCover(value);
        layer.msg('封面外链设置成功', {icon: 1});
        return true;
    }
    
    /**
     * 设置封面
     */
    function setCover(url) {
        $('#img-cover').attr('src', url);
        $('input[name=cover]').val(url);
        
        // 添加加载状态
        $('#img-cover').on('error', function() {
            $(this).attr('src', '/static/admin/img/default-cover.jpg'); // 默认封面
            layer.msg('封面图片加载失败，请检查链接是否正确', {icon: 2});
        });
    }
    
    /**
     * 测试封面链接
     */
    function testCoverLink(url) {
        if (!url) {
            layer.msg('请先输入链接地址', {icon: 2});
            return;
        }
        
        if (!isValidUrl(url)) {
            layer.msg('链接格式不正确', {icon: 2});
            return;
        }
        
        // 显示测试中状态
        var loadingIndex = layer.load(2, {content: '正在测试链接...'});
        
        // 创建临时图片元素测试
        var testImg = new Image();
        var timeout = setTimeout(function() {
            layer.close(loadingIndex);
            layer.msg('链接测试超时，请检查网络或链接是否正确', {icon: 2});
        }, 10000);
        
        testImg.onload = function() {
            clearTimeout(timeout);
            layer.close(loadingIndex);
            
            var info = '✓ 链接测试成功<br>' +
                      '图片尺寸: ' + this.width + ' × ' + this.height + ' 像素<br>' +
                      '建议尺寸: 400 × 300 像素或等比例';
            
            layer.alert(info, {
                title: '测试结果',
                icon: 1,
                area: ['400px', '200px']
            });
        };
        
        testImg.onerror = function() {
            clearTimeout(timeout);
            layer.close(loadingIndex);
            layer.msg('链接无法访问或不是有效的图片', {icon: 2});
        };
        
        testImg.src = url;
    }
    
    /**
     * 验证URL格式
     */
    function isValidUrl(url) {
        try {
            var urlObj = new URL(url);
            return urlObj.protocol === 'http:' || urlObj.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }
    
    /**
     * 添加封面预览增强功能
     */
    function addCoverPreviewEnhancement() {
        // 封面点击放大预览
        $('#img-cover').css('cursor', 'pointer').click(function() {
            var src = $(this).attr('src');
            if (src && src !== '' && !src.includes('default')) {
                layer.photos({
                    photos: {
                        "title": "封面预览",
                        "data": [{
                            "src": src,
                            "alt": "课程封面"
                        }]
                    },
                    anim: 5
                });
            }
        });
        
        // 添加封面信息提示
        var coverInfo = $('<div class="layui-form-mid layui-word-aux" style="margin-top: 5px;">' +
                         '点击封面可放大预览，支持上传文件或外链地址</div>');
        $('#img-cover').parent().after(coverInfo);
    }
    
    /**
     * 常用封面尺寸建议
     */
    function showCoverSizeGuide() {
        var content = '<div style="padding: 10px;">' +
                     '<h4>封面尺寸建议：</h4>' +
                     '<ul>' +
                     '<li>推荐尺寸：400 × 300 像素（4:3比例）</li>' +
                     '<li>最小尺寸：300 × 225 像素</li>' +
                     '<li>最大尺寸：800 × 600 像素</li>' +
                     '<li>文件大小：建议不超过 2MB</li>' +
                     '<li>支持格式：JPG, PNG, GIF</li>' +
                     '</ul>' +
                     '<h4>外链要求：</h4>' +
                     '<ul>' +
                     '<li>必须是 http:// 或 https:// 开头</li>' +
                     '<li>确保链接可以公开访问</li>' +
                     '<li>建议使用稳定的图床服务</li>' +
                     '</ul>' +
                     '</div>';
        
        layer.open({
            type: 1,
            title: '封面设置指南',
            area: ['500px', '400px'],
            content: content
        });
    }
    
    // 添加右键菜单（可选功能）
    $('#img-cover').on('contextmenu', function(e) {
        e.preventDefault();
        
        layer.open({
            type: 1,
            title: '封面操作',
            area: ['200px', '150px'],
            content: '<div style="padding: 10px;">' +
                    '<button class="layui-btn layui-btn-sm layui-btn-fluid" onclick="showCoverSizeGuide()">查看尺寸建议</button><br><br>' +
                    '<button class="layui-btn layui-btn-sm layui-btn-fluid layui-btn-normal" onclick="$(\'#external-cover\').click()">设置外链</button>' +
                    '</div>',
            offset: [e.pageY, e.pageX]
        });
    });
    
    // 将函数暴露到全局作用域
    window.showCoverSizeGuide = showCoverSizeGuide;
});

/**
 * 使用说明：
 * 
 * 1. 文件部署：
 *    将此文件保存为 public/static/admin/js/cover.external.js
 * 
 * 2. 页面引入：
 *    在课程编辑页面（edit_basic.volt）中添加：
 *    <script src="/static/admin/js/cover.external.js"></script>
 * 
 * 3. 确保依赖：
 *    页面需要已引入 layui 和现有的 cover.upload.js
 * 
 * 4. 功能特性：
 *    - 在"更换"按钮旁添加"外链"按钮
 *    - 支持外链URL验证和测试
 *    - 封面点击放大预览
 *    - 右键菜单快捷操作
 *    - 尺寸建议和使用指南
 * 
 * 5. 兼容性：
 *    - 完全兼容现有上传功能
 *    - 不影响原有代码逻辑
 *    - 支持混合使用（上传+外链）
 */