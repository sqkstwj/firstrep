/**
 * 视频封面功能前端扩展
 * 为外链视频添加封面输入和预览功能
 * 
 * 使用方法：
 * 1. 将此文件保存到 public/static/admin/js/video.cover.js
 * 2. 在 edit_lesson_vod.volt 页面引入此JS文件
 * 3. 确保页面已引入 layui 和 jQuery
 */

layui.use(['jquery', 'layer'], function () {
    
    var $ = layui.jquery;
    var layer = layui.layer;
    
    // 等待页面加载完成
    $(document).ready(function() {
        
        // 检查是否在外链视频编辑页面
        if ($('.layui-tab-content .layui-tab-item').length < 2) {
            console.log('不是VOD编辑页面，跳过视频封面功能初始化');
            return;
        }
        
        // 为外链视频表单添加封面输入
        addVideoCoverInput();
        
        // 添加封面预览功能
        addCoverPreviewFeatures();
        
        console.log('视频封面功能已初始化');
    });
    
    /**
     * 添加视频封面输入框
     */
    function addVideoCoverInput() {
        // 找到外链视频的表单（第二个tab）
        var $remoteForm = $('.layui-tab-content .layui-tab-item:eq(1) form');
        
        if ($remoteForm.length === 0) {
            console.log('未找到外链视频表单');
            return;
        }
        
        // 在流畅地址后面添加封面输入
        var $lastUrlInput = $remoteForm.find('input[name*="[fd][url]"]').closest('.layui-form-item');
        
        if ($lastUrlInput.length === 0) {
            console.log('未找到流畅地址输入框');
            return;
        }
        
        // 构建封面输入HTML
        var coverInputHtml = `
            <div class="layui-form-item" id="video-cover-item">
                <label class="layui-form-label">视频封面</label>
                <div class="layui-inline" style="width:55%;">
                    <input id="video-cover-url" class="layui-input" type="text" 
                           name="file_remote[cover][url]" 
                           value=""
                           placeholder="请输入视频封面图片地址（16:9比例，如640x360）">
                </div>
                <div class="layui-inline">
                    <button type="button" class="layui-btn layui-btn-sm" onclick="previewVideoCover()">预览</button>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" onclick="testVideoCoverLink()">测试</button>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-warm" onclick="showCoverGuide()">指南</button>
                </div>
                <div class="layui-form-mid layui-word-aux" style="margin-top: 5px;">
                    推荐尺寸：640×360或1280×720（16:9比例），支持JPG/PNG格式
                </div>
            </div>
        `;
        
        // 插入封面输入框
        $lastUrlInput.after(coverInputHtml);
        
        // 如果已有封面数据，填充到输入框
        loadExistingCoverData();
    }
    
    /**
     * 加载现有的封面数据
     */
    function loadExistingCoverData() {
        // 这里可以从后端获取现有的封面数据
        // 暂时留空，实际使用时需要根据后端数据填充
        
        // 示例：如果后端有传递封面数据
        // var existingCoverUrl = '{{ remote_play_urls.cover.url ?? "" }}';
        // if (existingCoverUrl) {
        //     $('#video-cover-url').val(existingCoverUrl);
        // }
    }
    
    /**
     * 添加封面预览功能
     */
    function addCoverPreviewFeatures() {
        // 封面URL输入框失去焦点时自动预览
        $(document).on('blur', '#video-cover-url', function() {
            var coverUrl = $(this).val().trim();
            if (coverUrl && isValidUrl(coverUrl)) {
                showCoverThumbnail(coverUrl);
            }
        });
        
        // 添加封面缩略图显示区域
        var thumbnailHtml = `
            <div id="cover-thumbnail" style="display: none; margin-top: 10px;">
                <div style="display: inline-block; vertical-align: top; margin-right: 10px;">
                    <img id="cover-preview-img" src="" alt="封面预览" 
                         style="width: 160px; height: 90px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: inline-block; vertical-align: top; padding-top: 10px;">
                    <div id="cover-info" style="font-size: 12px; color: #666;"></div>
                </div>
            </div>
        `;
        
        $('#video-cover-item').append(thumbnailHtml);
    }
    
    /**
     * 显示封面缩略图
     */
    function showCoverThumbnail(url) {
        var $thumbnail = $('#cover-thumbnail');
        var $img = $('#cover-preview-img');
        var $info = $('#cover-info');
        
        $img.on('load', function() {
            var width = this.naturalWidth;
            var height = this.naturalHeight;
            var ratio = (width / height).toFixed(2);
            
            $info.html(`
                尺寸: ${width} × ${height}<br>
                比例: ${ratio}:1<br>
                ${ratio >= 1.7 && ratio <= 1.8 ? '<span style="color: #5FB878;">✓ 比例合适</span>' : '<span style="color: #FF5722;">⚠ 建议16:9比例</span>'}
            `);
            
            $thumbnail.show();
        });
        
        $img.on('error', function() {
            $thumbnail.hide();
        });
        
        $img.attr('src', url);
    }
    
    /**
     * 预览视频封面
     */
    window.previewVideoCover = function() {
        var coverUrl = $('#video-cover-url').val().trim();
        if (!coverUrl) {
            layer.msg('请先输入封面地址', {icon: 2});
            return;
        }
        
        if (!isValidUrl(coverUrl)) {
            layer.msg('请输入有效的HTTP/HTTPS链接', {icon: 2});
            return;
        }
        
        layer.photos({
            photos: {
                "title": "视频封面预览",
                "data": [{
                    "src": coverUrl,
                    "alt": "视频封面"
                }]
            },
            anim: 5
        });
    };
    
    /**
     * 测试视频封面链接
     */
    window.testVideoCoverLink = function() {
        var coverUrl = $('#video-cover-url').val().trim();
        if (!coverUrl) {
            layer.msg('请先输入封面地址', {icon: 2});
            return;
        }
        
        if (!isValidUrl(coverUrl)) {
            layer.msg('链接格式不正确', {icon: 2});
            return;
        }
        
        // 显示测试中状态
        var loadingIndex = layer.load(2, {content: '正在测试封面链接...'});
        
        // 创建临时图片元素测试
        var testImg = new Image();
        var timeout = setTimeout(function() {
            layer.close(loadingIndex);
            layer.msg('链接测试超时，请检查网络或链接是否正确', {icon: 2});
        }, 10000);
        
        testImg.onload = function() {
            clearTimeout(timeout);
            layer.close(loadingIndex);
            
            var width = this.width;
            var height = this.height;
            var ratio = (width / height).toFixed(2);
            var fileSize = '未知';
            
            // 尝试获取文件大小（可能受CORS限制）
            fetch(coverUrl, {method: 'HEAD'})
                .then(response => {
                    var contentLength = response.headers.get('content-length');
                    if (contentLength) {
                        fileSize = formatFileSize(parseInt(contentLength));
                    }
                })
                .catch(() => {})
                .finally(() => {
                    var ratioStatus = (ratio >= 1.7 && ratio <= 1.8) ? 
                        '<span style="color: #5FB878;">✓ 比例合适</span>' : 
                        '<span style="color: #FF5722;">⚠ 建议16:9比例</span>';
                    
                    var info = `
                        <div style="text-align: left;">
                            <p><strong>✓ 链接测试成功</strong></p>
                            <p>图片尺寸: ${width} × ${height} 像素</p>
                            <p>宽高比例: ${ratio}:1 ${ratioStatus}</p>
                            <p>文件大小: ${fileSize}</p>
                            <p>建议尺寸: 640×360 或 1280×720</p>
                        </div>
                    `;
                    
                    layer.alert(info, {
                        title: '测试结果',
                        icon: 1,
                        area: ['400px', '280px']
                    });
                });
        };
        
        testImg.onerror = function() {
            clearTimeout(timeout);
            layer.close(loadingIndex);
            layer.msg('链接无法访问或不是有效的图片', {icon: 2});
        };
        
        testImg.src = coverUrl;
    };
    
    /**
     * 显示封面设置指南
     */
    window.showCoverGuide = function() {
        var content = `
            <div style="padding: 15px; line-height: 1.6;">
                <h4 style="margin-top: 0;">📋 视频封面设置指南</h4>
                
                <h5>🎯 封面要求：</h5>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>尺寸比例：</strong>推荐16:9比例（如640×360、1280×720）</li>
                    <li><strong>文件格式：</strong>支持JPG、PNG格式</li>
                    <li><strong>文件大小：</strong>建议不超过500KB</li>
                    <li><strong>链接要求：</strong>必须是可公开访问的HTTP/HTTPS链接</li>
                </ul>
                
                <h5>🔧 获取封面的方法：</h5>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>视频截图：</strong>从视频中截取关键帧作为封面</li>
                    <li><strong>设计制作：</strong>使用设计软件制作专业封面</li>
                    <li><strong>在线工具：</strong>使用在线视频截图工具</li>
                    <li><strong>MinIO上传：</strong>上传到MinIO获取外链地址</li>
                </ul>
                
                <h5>💡 最佳实践：</h5>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>选择视频中最具代表性的画面</li>
                    <li>确保封面清晰、美观</li>
                    <li>避免使用过于复杂的图片</li>
                    <li>保持与课程内容的相关性</li>
                </ul>
                
                <div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-radius: 4px; font-size: 12px;">
                    <strong>提示：</strong>设置封面后，在视频播放器中会显示为播放前的预览图，提升用户体验。
                </div>
            </div>
        `;
        
        layer.open({
            type: 1,
            title: '视频封面设置指南',
            area: ['600px', '500px'],
            content: content,
            shadeClose: true
        });
    };
    
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
     * 格式化文件大小
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    /**
     * 表单提交前验证封面URL
     */
    $(document).on('submit', '.layui-tab-content .layui-tab-item:eq(1) form', function() {
        var coverUrl = $('#video-cover-url').val().trim();
        
        if (coverUrl && !isValidUrl(coverUrl)) {
            layer.msg('视频封面链接格式不正确，请检查后重试', {icon: 2});
            return false;
        }
        
        // 如果有封面URL，添加尺寸信息（如果能获取到）
        if (coverUrl) {
            var $img = $('#cover-preview-img');
            if ($img.length && $img[0].naturalWidth) {
                // 添加隐藏字段存储尺寸信息
                var $form = $(this);
                $form.append(`<input type="hidden" name="file_remote[cover][width]" value="${$img[0].naturalWidth}">`);
                $form.append(`<input type="hidden" name="file_remote[cover][height]" value="${$img[0].naturalHeight}">`);
            }
        }
        
        return true;
    });
});

/**
 * 使用说明：
 * 
 * 1. 文件部署：
 *    将此文件保存为 public/static/admin/js/video.cover.js
 * 
 * 2. 页面引入：
 *    在 edit_lesson_vod.volt 中添加：
 *    <script src="/static/admin/js/video.cover.js"></script>
 * 
 * 3. 后端支持：
 *    需要修改 ChapterContent.php 中的 updateRemoteChapterVod 方法
 *    来处理 file_remote[cover] 数据
 * 
 * 4. 功能特性：
 *    - 在外链视频表单中添加封面输入框
 *    - 支持封面URL验证和测试
 *    - 实时预览封面缩略图
 *    - 封面尺寸和比例检查
 *    - 设置指南和最佳实践提示
 * 
 * 5. 兼容性：
 *    - 完全兼容现有视频上传功能
 *    - 不影响腾讯云VOD的正常使用
 *    - 向后兼容，不设置封面也能正常工作
 */