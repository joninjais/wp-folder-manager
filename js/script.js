jQuery(document).ready(function($) {
    'use strict';
    
    // ========================================================
    // ส่วนที่ 1: Folder Manager Admin Page
    // ========================================================
    
    var isFolderManagerPage = $('.wpfm-wrap').length > 0;
    var isMediaPage = $('body').hasClass('upload-php');
    
    // ตัวแปร global
    var currentFolderId = null;
    var selectedFiles = new Set();
    var wpMediaFrame = null;
    
    // ===== Notification (ใช้ร่วมกันทุกหน้า) =====
    function showNotification(message, type) {
        type = type || 'success';
        var $notification = $('<div class="wpfm-notification wpfm-notification-' + type + '">' + message + '</div>');
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() { $notification.remove(); }, 300);
        }, 3000);
    }
    
    // เพิ่ม CSS สำหรับ Notification
    if ($('.wpfm-notification-style').length === 0) {
        $('head').append(
            '<style class="wpfm-notification-style">' +
            '.wpfm-notification { position: fixed; top: 50px; right: -300px; padding: 15px 20px; background: white; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 999999; transition: right 0.3s; max-width: 300px; }' +
            '.wpfm-notification.show { right: 20px; }' +
            '.wpfm-notification-success { border-left: 4px solid #46b450; }' +
            '.wpfm-notification-error { border-left: 4px solid #dc3232; }' +
            '.wpfm-file-item { cursor: move; }' +
            '.wpfm-file-item.dragging { opacity: 0.5; }' +
            '.wpfm-folder-row.drop-zone-active { transition: background-color 0.2s; }' +
            '.wpfm-folder-row.drag-over { background-color: #e8f5e9 !important; border-left: 3px solid #46b450; }' +
            '</style>'
        );
    }
    
    // ========================================================
    // ส่วนที่ 1A: Folder Manager Admin Page - Folder CRUD
    // ========================================================
    if (isFolderManagerPage) {
        
        // เปิด Modal สร้าง Folder
        $('#wpfm-create-folder').on('click', function() {
            resetForm();
            $('#wpfm-modal-title').text('สร้าง Folder ใหม่');
            $('#wpfm-folder-modal').fadeIn();
        });
        
        // เพิ่ม Subfolder
        $(document).on('click', '.wpfm-add-subfolder', function(e) {
            e.stopPropagation();
            var parentId = $(this).data('folder-id');
            resetForm();
            $('#wpfm-parent-id').val(parentId);
            $('#wpfm-modal-title').text('สร้าง Subfolder');
            $('#wpfm-folder-modal').fadeIn();
        });
        
        // แก้ไข Folder
        $(document).on('click', '.wpfm-edit-folder', function(e) {
            e.stopPropagation();
            var folderId = $(this).data('folder-id');
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_get_folder',
                    nonce: wpfm_ajax.nonce,
                    id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        var folder = response.data;
                        $('#wpfm-folder-id').val(folder.id);
                        $('#wpfm-folder-name').val(folder.name);
                        $('#wpfm-parent-id').val(folder.parent_id);
                        $('input[name="icon"][value="' + folder.icon + '"]').prop('checked', true);
                        $('#wpfm-folder-color').val(folder.color);
                        $('#wpfm-modal-title').text('แก้ไข Folder');
                        $('#wpfm-folder-modal').fadeIn();
                    }
                }
            });
        });
        
        // ลบ Folder
        $(document).on('click', '.wpfm-delete-folder', function(e) {
            e.stopPropagation();
            var folderId = $(this).data('folder-id');
            var $folderItem = $(this).closest('.wpfm-folder-item');
            var folderName = $folderItem.data('folder-name');
            var parentId = $folderItem.data('parent-id') || 0;
            
            if (!confirm('คุณต้องการลบ folder "' + folderName + '" หรือไม่?\n(ไฟล์ภายในจะไม่ถูกลบ เพียงแต่ยกเลิกการจัดกลุ่ม)')) {
                return;
            }
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_delete_folder',
                    nonce: wpfm_ajax.nonce,
                    id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('ลบ folder สำเร็จ', 'success');
                        
                        // ลบ folder ออกจาก UI แบบ real-time
                        removeFolderFromUI(folderId, parentId);
                        
                        // ถ้าลบ folder ที่กำลังดูอยู่ ให้ clear file list
                        if (currentFolderId === folderId) {
                            currentFolderId = null;
                            $('#wpfm-current-folder').text('All Files');
                            $('#wpfm-file-list').html('<p class="wpfm-empty-state">เลือก folder เพื่อดูไฟล์</p>');
                        }
                    } else {
                        showNotification(response.data, 'error');
                    }
                }
            });
        });
        
        // บันทึก Folder
        $('#wpfm-folder-form').on('submit', function(e) {
            e.preventDefault();
            
            var folderId = $('#wpfm-folder-id').val();
            var action = folderId ? 'wpfm_update_folder' : 'wpfm_create_folder';
            var isUpdate = !!folderId;
            
            var data = {
                action: action,
                nonce: wpfm_ajax.nonce,
                id: folderId,
                name: $('#wpfm-folder-name').val(),
                parent_id: $('#wpfm-parent-id').val(),
                icon: $('input[name="icon"]:checked').val(),
                color: $('#wpfm-folder-color').val()
            };
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showNotification(isUpdate ? 'อัพเดทสำเร็จ' : 'สร้างสำเร็จ', 'success');
                        $('#wpfm-folder-modal').fadeOut();
                        
                        if (isUpdate) {
                            // อัปเดต UI แบบ real-time สำหรับการแก้ไข
                            updateFolderInUI(response.data.folder);
                        } else {
                            // เพิ่ม folder ใหม่ใน UI แบบ real-time
                            addFolderToUI(response.data.folder);
                        }
                    } else {
                        showNotification(response.data, 'error');
                    }
                }
            });
        });
        
        // ปิด Modal
        $(document).on('click', '.wpfm-close, .wpfm-cancel', function() {
            $('#wpfm-folder-modal, #wpfm-move-modal').fadeOut();
        });
        
        // Toggle Subfolder
        $(document).on('click', '.wpfm-toggle', function(e) {
            e.stopPropagation();
            $(this).toggleClass('open');
            $(this).closest('.wpfm-folder-item').find('> .wpfm-subfolder').slideToggle(200);
        });
        
        // คลิกที่ Folder เพื่อดูไฟล์
        $(document).on('click', '.wpfm-folder-row', function() {
            $('.wpfm-folder-row').removeClass('active');
            $(this).addClass('active');
            
            var folderId = $(this).closest('.wpfm-folder-item').data('folder-id');
            var folderName = $(this).find('.wpfm-folder-name').text();
            currentFolderId = folderId;
            
            $('#wpfm-current-folder').text(folderName);
            
            selectedFiles.clear();
            updateSelectedCount();
            
            loadFolderFiles(folderId);
        });
        
        // ปุ่มอัพโหลดไฟล์
        $('#wpfm-upload-files').on('click', function() {
            if (!currentFolderId) {
                if (!confirm('คุณยังไม่ได้เลือก folder\nต้องการอัพโหลดไฟล์โดยไม่จัดเข้า folder หรือไม่?\n(ไฟล์จะถูกเพิ่มเข้า Media Library ปกติ)')) {
                    return;
                }
            }
            $('#wpfm-file-input').click();
        });
        
        // เมื่อเลือกไฟล์จาก input
        $('#wpfm-file-input').on('change', function() {
            var files = this.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });
        
        // อัพโหลดไฟล์
        function uploadFiles(files) {
            var formData = new FormData();
            formData.append('action', 'wpfm_upload_files');
            formData.append('nonce', wpfm_ajax.nonce);
            formData.append('folder_id', currentFolderId || 0);
            
            for (var i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }
            
            $('#wpfm-upload-progress').show();
            $('#wpfm-progress-fill').css('width', '0%');
            $('#wpfm-progress-text').text('0%');
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 100);
                            $('#wpfm-progress-fill').css('width', percent + '%');
                            $('#wpfm-progress-text').text(percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var statusMsg = 'อัพโหลดสำเร็จ ' + data.success_count + '/' + data.total + ' ไฟล์';
                        if (data.errors && data.errors.length > 0) {
                            statusMsg += '<br>ข้อผิดพลาด: ' + data.errors.join(', ');
                        }
                        $('#wpfm-upload-status').html(statusMsg);
                        
                        showNotification('อัพโหลดสำเร็จ!', 'success');
                        
                        setTimeout(function() {
                            $('#wpfm-upload-progress').fadeOut();
                            $('#wpfm-file-input').val('');
                            if (currentFolderId) {
                                loadFolderFiles(currentFolderId);
                                updateFolderBadge(currentFolderId);
                            }
                        }, 2000);
                    } else {
                        showNotification(response.data, 'error');
                        $('#wpfm-upload-progress').fadeOut();
                    }
                },
                error: function() {
                    showNotification('เกิดข้อผิดพลาดในการอัพโหลด', 'error');
                    $('#wpfm-upload-progress').fadeOut();
                }
            });
        }
        
        // เลือกจาก Media Library
        $('#wpfm-select-from-library').on('click', function() {
            if (!currentFolderId) {
                alert('กรุณาเลือก folder ก่อน');
                return;
            }
            
            if (wpMediaFrame) {
                wpMediaFrame.open();
                return;
            }
            
            wpMediaFrame = wp.media({
                title: 'เลือกไฟล์จาก Media Library',
                button: {
                    text: 'เพิ่มเข้า Folder'
                },
                multiple: true
            });
            
            wpMediaFrame.on('select', function() {
                var selection = wpMediaFrame.state().get('selection');
                var fileIds = [];
                selection.each(function(attachment) {
                    fileIds.push(attachment.id);
                });
                
                $.ajax({
                    url: wpfm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpfm_add_to_folder',
                        nonce: wpfm_ajax.nonce,
                        folder_id: currentFolderId,
                        file_ids: fileIds
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification(response.data.message, 'success');
                            loadFolderFiles(currentFolderId);
                            updateFolderBadge(currentFolderId);
                        } else {
                            showNotification(response.data, 'error');
                        }
                    }
                });
            });
            
            wpMediaFrame.open();
        });
        
        // ดึงไฟล์ใน Folder
        function loadFolderFiles(folderId) {
            $('#wpfm-file-list').html('<p class="wpfm-empty-state">กำลังโหลด...</p>');
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_get_folder_files',
                    nonce: wpfm_ajax.nonce,
                    folder_id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        displayFiles(response.data);
                    }
                },
                error: function() {
                    $('#wpfm-file-list').html('<p class="wpfm-empty-state">เกิดข้อผิดพลาดในการโหลดไฟล์</p>');
                }
            });
        }
        
        // แสดงไฟล์
        function displayFiles(files) {
            var $fileList = $('#wpfm-file-list');
            $fileList.empty();
            
            if (files.length === 0) {
                $fileList.html('<p class="wpfm-empty-state">ไม่มีไฟล์ใน folder นี้<br>คลิก "อัพโหลดไฟล์" เพื่อเพิ่มไฟล์</p>');
                return;
            }
            
            $.each(files, function(index, file) {
                var $item = $('<div class="wpfm-file-item" data-file-id="' + file.id + '" draggable="true"></div>');
                
                $item.append('<input type="checkbox" class="wpfm-file-checkbox">');
                $item.append('<img src="' + file.thumb + '" class="wpfm-file-thumb" alt="' + file.title + '">');
                
                var $info = $('<div class="wpfm-file-info"></div>');
                $info.append('<div class="wpfm-file-title" title="' + file.title + '">' + file.title + '</div>');
                $info.append('<div class="wpfm-file-meta">' + file.size + ' • ' + file.date + '</div>');
                $item.append($info);
                
                var $actions = $('<div class="wpfm-file-actions"></div>');
                $actions.append('<button class="wpfm-file-action-btn wpfm-view-file" data-url="' + file.url + '" title="ดู"><span class="dashicons dashicons-visibility"></span></button>');
                $actions.append('<button class="wpfm-file-action-btn wpfm-delete-file" data-file-id="' + file.id + '" title="ลบ"><span class="dashicons dashicons-trash"></span></button>');
                $item.append($actions);
                
                $fileList.append($item);
            });
            
            initFileDragEvents();
        }
        
        // เลือก/ยกเลิกเลือกไฟล์
        $(document).on('change', '.wpfm-file-checkbox', function() {
            var $item = $(this).closest('.wpfm-file-item');
            var fileId = $item.data('file-id');
            
            if ($(this).is(':checked')) {
                $item.addClass('selected');
                selectedFiles.add(fileId);
            } else {
                $item.removeClass('selected');
                selectedFiles.delete(fileId);
            }
            
            updateSelectedCount();
        });
        
        // อัพเดทจำนวนไฟล์ที่เลือก
        function updateSelectedCount() {
            var count = selectedFiles.size;
            $('#wpfm-selected-count').text(count);
            
            if (count > 0) {
                $('#wpfm-move-selected').show();
                $('#wpfm-delete-selected').show();
            } else {
                $('#wpfm-move-selected').hide();
                $('#wpfm-delete-selected').hide();
            }
        }
        
        // เปิด Modal ย้ายไฟล์
        $('#wpfm-move-selected').on('click', function() {
            if (selectedFiles.size === 0) return;
            
            $('#wpfm-move-count').text(selectedFiles.size);
            $('#wpfm-target-folder').val('');
            $('#wpfm-move-modal').fadeIn();
        });
        
        // ยืนยันการย้ายไฟล์
        $('#wpfm-confirm-move').on('click', function() {
            var targetFolderId = $('#wpfm-target-folder').val();
            
            if (!targetFolderId) {
                alert('กรุณาเลือก folder ปลายทาง');
                return;
            }
            
            if (selectedFiles.size === 0) return;
            
            var fileIds = Array.from(selectedFiles);
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_move_files',
                    nonce: wpfm_ajax.nonce,
                    file_ids: fileIds,
                    target_folder_id: targetFolderId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        $('#wpfm-move-modal').fadeOut();
                        selectedFiles.clear();
                        updateSelectedCount();
                        
                        if (currentFolderId) {
                            loadFolderFiles(currentFolderId);
                            updateFolderBadge(currentFolderId);
                        }
                    } else {
                        showNotification(response.data || 'เกิดข้อผิดพลาดในการย้ายไฟล์', 'error');
                    }
                },
                error: function() {
                    showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                }
            });
        });
        
        // ลบไฟล์ที่เลือก
        $('#wpfm-delete-selected').on('click', function() {
            if (selectedFiles.size === 0) return;
            
            if (!confirm('คุณต้องการลบไฟล์ ' + selectedFiles.size + ' ไฟล์หรือไม่?\n(ไฟล์จะถูกลบออกจากระบบอย่างถาวร)')) {
                return;
            }
            
            deleteFiles(Array.from(selectedFiles));
        });
        
        // ลบไฟล์เดี่ยว
        $(document).on('click', '.wpfm-delete-file', function(e) {
            e.stopPropagation();
            var fileId = $(this).data('file-id');
            
            if (!confirm('คุณต้องการลบไฟล์นี้หรือไม่?\n(ไฟล์จะถูกลบออกจากระบบอย่างถาวร)')) {
                return;
            }
            
            deleteFiles([fileId]);
        });
        
        // ฟังก์ชันลบไฟล์
        function deleteFiles(fileIds) {
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_delete_files',
                    nonce: wpfm_ajax.nonce,
                    file_ids: fileIds
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        selectedFiles.clear();
                        updateSelectedCount();
                        if (currentFolderId) {
                            loadFolderFiles(currentFolderId);
                            updateFolderBadge(currentFolderId);
                        }
                    } else {
                        showNotification(response.data, 'error');
                    }
                }
            });
        }
        
        // ดูไฟล์
        $(document).on('click', '.wpfm-view-file', function(e) {
            e.stopPropagation();
            var url = $(this).data('url');
            window.open(url, '_blank');
        });
        
        // Drag and Drop สำหรับย้ายไฟล์ระหว่าง Folder
        function initFileDragEvents() {
            $('.wpfm-file-item').on('dragstart', function(e) {
                var fileId = $(this).data('file-id');
                e.originalEvent.dataTransfer.setData('text/plain', fileId);
                e.originalEvent.dataTransfer.effectAllowed = 'move';
                $(this).addClass('dragging');
                $('.wpfm-folder-row').addClass('drop-zone-active');
            });
            
            $('.wpfm-file-item').on('dragend', function() {
                $(this).removeClass('dragging');
                $('.wpfm-folder-row').removeClass('drop-zone-active drag-over');
            });
        }
        
        // Folder Drop Zone
        $(document).on('dragover', '.wpfm-folder-row', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ($('.wpfm-file-item.dragging').length > 0) {
                $(this).addClass('drag-over');
                e.originalEvent.dataTransfer.dropEffect = 'move';
            }
        });
        
        $(document).on('dragleave', '.wpfm-folder-row', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });
        
        $(document).on('drop', '.wpfm-folder-row', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            $('.wpfm-folder-row').removeClass('drop-zone-active');
            
            var fileId = e.originalEvent.dataTransfer.getData('text/plain');
            var targetFolderId = $(this).closest('.wpfm-folder-item').data('folder-id');
            
            if (fileId && targetFolderId) {
                moveFileToFolder(fileId, targetFolderId);
            }
        });
        
        // ฟังก์ชันย้ายไฟล์ไปยัง Folder
        function moveFileToFolder(fileId, targetFolderId) {
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_move_file',
                    nonce: wpfm_ajax.nonce,
                    file_id: fileId,
                    target_folder_id: targetFolderId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('ย้ายไฟล์สำเร็จ', 'success');
                        if (currentFolderId) {
                            loadFolderFiles(currentFolderId);
                            updateFolderBadge(currentFolderId);
                        }
                        updateFolderBadge(targetFolderId);
                    } else {
                        showNotification(response.data || 'เกิดข้อผิดพลาดในการย้ายไฟล์', 'error');
                    }
                },
                error: function() {
                    showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                }
            });
        }
        
        // Drag and Drop สำหรับอัพโหลดไฟล์จากภายนอก
        var $fileList = $('#wpfm-file-list');
        
        $fileList.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if ($('.wpfm-file-item.dragging').length > 0) return;
            if (currentFolderId) {
                $(this).addClass('drag-over');
            }
        });
        
        $fileList.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });
        
        $fileList.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            
            if ($('.wpfm-file-item.dragging').length > 0) return;
            
            if (!currentFolderId) {
                if (!confirm('คุณยังไม่ได้เลือก folder\nต้องการอัพโหลดไฟล์โดยไม่จัดเข้า folder หรือไม่?')) {
                    return;
                }
            }
            
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });
        
        // รีเซ็ตฟอร์ม
        function resetForm() {
            $('#wpfm-folder-form')[0].reset();
            $('#wpfm-folder-id').val('');
            $('#wpfm-parent-id').val('0');
            $('#wpfm-folder-color').val('#FFA500');
        }
        
        // อัพเดทจำนวนไฟล์ใน folder badge
        function updateFolderBadge(folderId) {
            if (!folderId) return;
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_get_folder_file_count',
                    nonce: wpfm_ajax.nonce,
                    folder_id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        var $folderItem = $('.wpfm-folder-item[data-folder-id="' + folderId + '"]');
                        $folderItem.find('.wpfm-file-badge').first().text(response.data.count);
                    }
                }
            });
        }
        
        // ลบ folder ออกจาก UI แบบ real-time
        function removeFolderFromUI(folderId, parentId) {
            var $folderItem = $('.wpfm-folder-item[data-folder-id="' + folderId + '"]');
            
            if ($folderItem.length > 0) {
                // ลบ folder item และ subfolder ของมันด้วย
                $folderItem.fadeOut(300, function() {
                    $(this).remove();
                    
                    // ตรวจสอบว่า parent ยังมี subfolder อื่นอยู่ไหม
                    if (parentId > 0) {
                        var $parentItem = $('.wpfm-folder-item[data-folder-id="' + parentId + '"]');
                        var $subfolder = $parentItem.find('> .wpfm-subfolder');
                        
                        // ถ้าไม่มี subfolder แล้ว ให้ลบ toggle icon
                        if ($subfolder.children('.wpfm-folder-item').length === 0) {
                            $subfolder.remove();
                            $parentItem.find('> .wpfm-folder-row .wpfm-toggle').remove();
                        }
                    }
                });
            }
        }
        
        // เพิ่ม folder ใหม่ใน UI แบบ real-time
        function addFolderToUI(folder) {
            var parentId = parseInt(folder.parent_id);
            var folderHtml = buildFolderHTML(folder, 0);
            
            if (parentId === 0) {
                // เพิ่มใน root level
                $('#wpfm-folder-tree').append(folderHtml);
            } else {
                // เพิ่มเป็น subfolder
                var $parentItem = $('.wpfm-folder-item[data-folder-id="' + parentId + '"]');
                var $subfolder = $parentItem.find('> .wpfm-subfolder');
                
                if ($subfolder.length === 0) {
                    // สร้าง subfolder container ใหม่
                    $subfolder = $('<div class="wpfm-subfolder"></div>');
                    $parentItem.append($subfolder);
                    
                    // เพิ่ม toggle icon ถ้ายังไม่มี
                    var $toggle = $parentItem.find('> .wpfm-folder-row .wpfm-toggle');
                    if ($toggle.length === 0) {
                        $toggle = $('<span class="wpfm-toggle">▶</span>');
                        $parentItem.find('> .wpfm-folder-row').prepend($toggle);
                    }
                }
                
                $subfolder.append(folderHtml);
                $subfolder.show();
                $parentItem.find('> .wpfm-folder-row .wpfm-toggle').addClass('open');
            }
        }
        
        // อัปเดต folder ใน UI แบบ real-time
        function updateFolderInUI(folder) {
            var $folderItem = $('.wpfm-folder-item[data-folder-id="' + folder.id + '"]');
            if ($folderItem.length > 0) {
                // อัปเดตชื่อ ไอคอน และสี
                $folderItem.find('> .wpfm-folder-row .wpfm-folder-icon')
                    .text(folder.icon)
                    .css('color', folder.color);
                $folderItem.find('> .wpfm-folder-row .wpfm-folder-name').text(folder.name);
                
                // อัปเดต data attributes
                $folderItem.attr('data-folder-name', folder.name);
                $folderItem.attr('data-folder-icon', folder.icon);
                $folderItem.attr('data-folder-color', folder.color);
            }
        }
        
        // สร้าง HTML สำหรับ folder item
        function buildFolderHTML(folder, level) {
            var indent = level > 0 ? 'style="padding-left: ' + (level * 20) + 'px;"' : '';
            var html = '<div class="wpfm-folder-item" data-folder-id="' + folder.id + '" data-folder-name="' + folder.name + '" data-folder-icon="' + folder.icon + '" data-folder-color="' + folder.color + '">';
            html += '<div class="wpfm-folder-row" ' + indent + '>';
            html += '<span class="wpfm-folder-icon" style="color: ' + folder.color + ';">' + folder.icon + '</span>';
            html += '<span class="wpfm-folder-name">' + folder.name + '</span>';
            html += '<span class="wpfm-file-badge">0</span>';
            html += '<div class="wpfm-folder-actions">';
            html += '<span class="dashicons dashicons-edit wpfm-edit-folder" data-folder-id="' + folder.id + '" title="แก้ไข"></span>';
            html += '<span class="dashicons dashicons-plus wpfm-add-subfolder" data-folder-id="' + folder.id + '" title="เพิ่ม Subfolder"></span>';
            html += '<span class="dashicons dashicons-trash wpfm-delete-folder" data-folder-id="' + folder.id + '" title="ลบ"></span>';
            html += '</div></div></div>';
            return html;
        }
        
        // รีเฟรช folder tree โดยไม่ reload หน้า
        function refreshFolderTree() {
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_get_folder_tree',
                    nonce: wpfm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#wpfm-folder-tree').html(response.data.html);
                        
                        if (currentFolderId) {
                            var $currentFolder = $('.wpfm-folder-item[data-folder-id="' + currentFolderId + '"]');
                            $currentFolder.find('.wpfm-folder-row').first().addClass('active');
                        }
                    }
                }
            });
        }
        
    } // end isFolderManagerPage
    
    // ========================================================
    // ส่วนที่ 2: Media Library Page - Sidebar (Fixed Left)
    // ========================================================
    if (isMediaPage && $('#wpfm-media-sidebar').length > 0) {
        
        // คลิกที่ folder item ในหน้า Media Library
        $(document).on('click', '.wpfm-folder-item-media', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $item = $(this);
            var folderId = $item.data('folder-id');
            
            // ถ้าคลิกที่ toggle arrow → expand/collapse subfolder
            if ($(e.target).hasClass('wpfm-toggle-arrow') && !$(e.target).hasClass('wpfm-no-children')) {
                var $subfolder = $item.next('.wpfm-subfolder-container');
                $(e.target).toggleClass('open');
                $subfolder.slideToggle(200);
                return;
            }
            
            // Highlight folder ที่เลือก
            $('.wpfm-folder-item-media').removeClass('active');
            $item.addClass('active');
            
            // Filter files ใน Media Library
            filterMediaByFolder(folderId);
        });
        
        // ฟังก์ชัน filter files ตาม folder (แบบไม่ refresh หน้า)
        function filterMediaByFolder(folderId) {
            // ตรวจสอบว่าอยู่ใน List View หรือ Grid View
            var isListView = $('body').hasClass('upload-php') && $('.wp-list-table').length > 0;
            var isGridView = $('body').hasClass('upload-php') && $('#wp-media-grid').length > 0;
            
            if (isGridView) {
                // Grid View: ใช้ wp.media API
                if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                    var frame = wp.media.frame;
                    if (frame && frame.content && frame.content.get()) {
                        var library = frame.content.get().collection;
                        if (library) {
                            library.props.set({
                                wpfm_folder: folderId
                            });
                        }
                    }
                }
                
                // อัพเดท URL โดยไม่ reload
                var params = new URLSearchParams(window.location.search);
                if (folderId && folderId > 0) {
                    params.set('wpfm_folder', folderId);
                } else {
                    params.delete('wpfm_folder');
                }
                var newUrl = window.location.pathname + '?' + params.toString();
                history.pushState(null, '', newUrl);
                
            } else {
                // List View: ต้อง reload หน้า
                var baseUrl = window.location.pathname;
                var params = new URLSearchParams(window.location.search);
                
                if (folderId && folderId > 0) {
                    params.set('wpfm_folder', folderId);
                } else {
                    params.delete('wpfm_folder');
                }
                
                params.delete('paged');
                var newUrl = baseUrl + '?' + params.toString();
                
                if (window.location.href !== newUrl) {
                    window.location.href = newUrl;
                }
            }
        }
        
        // Highlight folder ที่ active ตาม URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var activeFolderId = urlParams.get('wpfm_folder');
        
        if (activeFolderId) {
            $('.wpfm-folder-item-media').removeClass('active');
            $('.wpfm-folder-item-media[data-folder-id="' + activeFolderId + '"]').addClass('active');
            
            // Expand parent folders ถ้า active folder อยู่ใน subfolder
            var $activeFolder = $('.wpfm-folder-item-media[data-folder-id="' + activeFolderId + '"]');
            $activeFolder.parents('.wpfm-subfolder-container').each(function() {
                $(this).show();
                $(this).prev('.wpfm-folder-item-media').find('.wpfm-toggle-arrow').addClass('open');
            });
        }
        
        // เปิดหน้าสร้าง folder จาก Media Library sidebar
        $('#wpfm-media-create-folder').on('click', function(e) {
            e.preventDefault();
            resetMediaForm();
            $('#wpfm-media-modal-title').text('สร้าง Folder ใหม่');
            $('#wpfm-media-folder-modal').fadeIn();
        });
        
        // อัพโหลดไฟล์จาก Media Library sidebar
        var currentUploadFolder = null;
        var mediaUploadFrame = null;
        
        $('#wpfm-media-upload-files').on('click', function(e) {
            e.preventDefault();
            
            // ดึง folder ที่เลือกอยู่
            var $activeFolder = $('.wpfm-folder-item-media.active');
            currentUploadFolder = $activeFolder.data('folder-id') || 0;
            
            // แสดง notice
            if (currentUploadFolder && currentUploadFolder > 0) {
                var folderName = $activeFolder.data('folder-name') || $activeFolder.find('.wpfm-folder-name').text();
                $('#wpfm-current-folder-text').html('ไฟล์จะถูกอัพโหลดเข้า folder: <strong>' + folderName + '</strong>');
                $('#wpfm-current-folder-info').slideDown();
            } else {
                $('#wpfm-current-folder-text').text('ไฟล์จะถูกอัพโหลดโดยไม่จัดเข้า folder');
                $('#wpfm-current-folder-info').slideDown();
            }
            
            // เปิด WordPress Media Uploader
            if (mediaUploadFrame) {
                mediaUploadFrame.open();
                return;
            }
            
            mediaUploadFrame = wp.media({
                title: 'อัพโหลดไฟล์เข้า Folder',
                button: {
                    text: 'อัพโหลด'
                },
                multiple: true
            });
            
            mediaUploadFrame.on('select', function() {
                var selection = mediaUploadFrame.state().get('selection');
                var attachmentIds = [];
                
                selection.each(function(attachment) {
                    attachmentIds.push(attachment.id);
                });
                
                // บันทึกไฟล์เข้า folder
                if (currentUploadFolder && currentUploadFolder > 0) {
                    $.ajax({
                        url: wpfm_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wpfm_add_to_folder',
                            nonce: wpfm_ajax.nonce,
                            folder_id: currentUploadFolder,
                            file_ids: attachmentIds
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('อัพโหลดและจัดเข้า folder สำเร็จ (' + attachmentIds.length + ' ไฟล์)', 'success');
                                // รีเฟรช sidebar
                                refreshMediaSidebar();
                                // รีโหลดหน้าเพื่อแสดงไฟล์ใหม่
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showNotification(response.data || 'เกิดข้อผิดพลาดในการจัดไฟล์เข้า folder', 'error');
                            }
                        }
                    });
                } else {
                    showNotification('อัพโหลดสำเร็จ (' + attachmentIds.length + ' ไฟล์)', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                }
                
                $('#wpfm-current-folder-info').slideUp();
            });
            
            mediaUploadFrame.on('close', function() {
                $('#wpfm-current-folder-info').slideUp();
            });
            
            mediaUploadFrame.open();
        });
        
        // อัพเดท current folder เมื่อคลิกที่ folder
        $(document).on('click', '.wpfm-folder-item-media', function(e) {
            var $item = $(this);
            var folderId = $item.data('folder-id');
            currentUploadFolder = folderId;
        });
        
        // เพิ่ม Subfolder จาก Media Library
        $(document).on('click', '.wpfm-media-add-subfolder', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parentId = $(this).data('folder-id');
            resetMediaForm();
            $('#wpfm-media-parent-id').val(parentId);
            $('#wpfm-media-modal-title').text('สร้าง Subfolder');
            $('#wpfm-media-folder-modal').fadeIn();
        });
        
        // แก้ไข Folder จาก Media Library
        $(document).on('click', '.wpfm-media-edit-folder', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $item = $(this).closest('.wpfm-folder-item-media');
            var folderId = $(this).data('folder-id');
            var folderName = $item.data('folder-name');
            var folderIcon = $item.data('folder-icon');
            var folderColor = $item.data('folder-color');
            var parentId = $item.data('parent-id');
            
            $('#wpfm-media-folder-id').val(folderId);
            $('#wpfm-media-folder-name').val(folderName);
            $('#wpfm-media-parent-id').val(parentId);
            $('input[name="icon"][value="' + folderIcon + '"]').prop('checked', true);
            $('#wpfm-media-folder-color').val(folderColor);
            $('#wpfm-media-modal-title').text('แก้ไข Folder');
            $('#wpfm-media-folder-modal').fadeIn();
        });
        
        // ลบ Folder จาก Media Library
        $(document).on('click', '.wpfm-media-delete-folder', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var folderId = $(this).data('folder-id');
            var $item = $(this).closest('.wpfm-folder-item-media');
            var folderName = $item.data('folder-name');
            var parentId = $item.data('parent-id') || 0;
            
            if (!confirm('คุณต้องการลบ folder "' + folderName + '" หรือไม่?\n(ไฟล์ภายในจะไม่ถูกลบ เพียงแต่ยกเลิกการจัดกลุ่ม)')) {
                return;
            }
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_delete_folder',
                    nonce: wpfm_ajax.nonce,
                    id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('ลบ folder สำเร็จ', 'success');
                        
                        // ลบ folder ออกจาก UI แบบ real-time
                        removeMediaFolderFromUI(folderId, parentId);
                    } else {
                        showNotification(response.data || 'เกิดข้อผิดพลาด', 'error');
                    }
                },
                error: function() {
                    showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                }
            });
        });
        
        // บันทึก Folder จาก Media Library Modal
        $('#wpfm-media-folder-form').on('submit', function(e) {
            e.preventDefault();
            
            var folderId = $('#wpfm-media-folder-id').val();
            var action = folderId ? 'wpfm_update_folder' : 'wpfm_create_folder';
            var isUpdate = !!folderId;
            
            var data = {
                action: action,
                nonce: wpfm_ajax.nonce,
                id: folderId,
                name: $('#wpfm-media-folder-name').val(),
                parent_id: $('#wpfm-media-parent-id').val(),
                icon: $('input[name="icon"]:checked').val(),
                color: $('#wpfm-media-folder-color').val()
            };
            
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showNotification(isUpdate ? 'อัพเดทสำเร็จ' : 'สร้างสำเร็จ', 'success');
                        $('#wpfm-media-folder-modal').fadeOut();
                        
                        if (isUpdate) {
                            // อัปเดต UI แบบ real-time สำหรับการแก้ไข
                            updateMediaFolderInUI(response.data.folder);
                        } else {
                            // เพิ่ม folder ใหม่ใน UI แบบ real-time
                            addMediaFolderToUI(response.data.folder);
                        }
                    } else {
                        showNotification(response.data || 'เกิดข้อผิดพลาด', 'error');
                    }
                },
                error: function() {
                    showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                }
            });
        });
        
        // ปิด Modal
        $(document).on('click', '#wpfm-media-folder-modal .wpfm-close, #wpfm-media-folder-modal .wpfm-cancel', function() {
            $('#wpfm-media-folder-modal').fadeOut();
        });
        
        // รีเซ็ตฟอร์ม
        function resetMediaForm() {
            $('#wpfm-media-folder-form')[0].reset();
            $('#wpfm-media-folder-id').val('');
            $('#wpfm-media-parent-id').val('0');
            $('#wpfm-media-folder-color').val('#FFA500');
        }
        
        // รีเฟรช Media Sidebar
        function refreshMediaSidebar() {
            $.ajax({
                url: wpfm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpfm_get_media_sidebar',
                    nonce: wpfm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.wpfm-media-folder-list').html(response.data.html);
                    }
                }
            });
        }
        
        // ลบ folder ออกจาก Media Sidebar แบบ real-time
        function removeMediaFolderFromUI(folderId, parentId) {
            var $folderItem = $('.wpfm-folder-item-media[data-folder-id="' + folderId + '"]');
            var $subfolderContainer = $folderItem.next('.wpfm-subfolder-container');
            
            if ($folderItem.length > 0) {
                // ลบทั้ง folder item และ subfolder container
                $folderItem.fadeOut(300, function() {
                    $(this).remove();
                });
                
                if ($subfolderContainer.length > 0) {
                    $subfolderContainer.fadeOut(300, function() {
                        $(this).remove();
                    });
                }
                
                // ตรวจสอบว่า parent ยังมี subfolder อื่นอยู่ไหม
                if (parentId > 0) {
                    setTimeout(function() {
                        var $parentItem = $('.wpfm-folder-item-media[data-folder-id="' + parentId + '"]');
                        var $parentSubfolder = $parentItem.next('.wpfm-subfolder-container');
                        
                        // ถ้าไม่มี subfolder แล้ว ให้ซ่อน container และเปลี่ยน toggle arrow
                        if ($parentSubfolder.length > 0 && $parentSubfolder.children('.wpfm-folder-item-media').length === 0) {
                            $parentSubfolder.remove();
                            $parentItem.find('.wpfm-toggle-arrow').addClass('wpfm-no-children').removeClass('open');
                        }
                    }, 350);
                }
            }
        }
        
        // เพิ่ม folder ใหม่ใน Media Sidebar แบบ real-time
        function addMediaFolderToUI(folder) {
            var parentId = parseInt(folder.parent_id);
            var fileCount = 0;
            var folderHtml = buildMediaFolderHTML(folder, fileCount, parentId === 0 ? 0 : 1);
            
            if (parentId === 0) {
                // เพิ่มใน root level
                $('.wpfm-media-folder-list').append(folderHtml);
            } else {
                // เพิ่มเป็น subfolder
                var $parentItem = $('.wpfm-folder-item-media[data-folder-id="' + parentId + '"]');
                var $subfolder = $parentItem.next('.wpfm-subfolder-container');
                
                if ($subfolder.length === 0) {
                    // สร้าง subfolder container ใหม่
                    $subfolder = $('<div class="wpfm-subfolder-container"></div>');
                    $parentItem.after($subfolder);
                    
                    // เพิ่ม toggle arrow
                    var $arrow = $parentItem.find('.wpfm-toggle-arrow');
                    $arrow.removeClass('wpfm-no-children');
                }
                
                $subfolder.append(folderHtml);
                $subfolder.show();
                $parentItem.find('.wpfm-toggle-arrow').addClass('open');
            }
        }
        
        // อัปเดต folder ใน Media Sidebar แบบ real-time
        function updateMediaFolderInUI(folder) {
            var $folderItem = $('.wpfm-folder-item-media[data-folder-id="' + folder.id + '"]');
            if ($folderItem.length > 0) {
                // อัปเดตชื่อ ไอคอน และสี
                $folderItem.find('.wpfm-folder-icon')
                    .text(folder.icon)
                    .css('color', folder.color);
                $folderItem.find('.wpfm-folder-name').text(folder.name);
                
                // อัปเดต data attributes
                $folderItem.attr('data-folder-name', folder.name);
                $folderItem.attr('data-folder-icon', folder.icon);
                $folderItem.attr('data-folder-color', folder.color);
            }
        }
        
        // สร้าง HTML สำหรับ media folder item
        function buildMediaFolderHTML(folder, fileCount, level) {
            var indent = level * 20;
            var html = '<div class="wpfm-folder-item-media" data-folder-id="' + folder.id + '" ';
            html += 'data-parent-id="' + folder.parent_id + '" ';
            html += 'data-folder-name="' + folder.name + '" ';
            html += 'data-folder-icon="' + folder.icon + '" ';
            html += 'data-folder-color="' + folder.color + '" ';
            html += 'style="padding-left: ' + indent + 'px;">';
            html += '<span class="wpfm-toggle-arrow wpfm-no-children"></span>';
            html += '<span class="wpfm-folder-icon" style="color: ' + folder.color + ';">' + folder.icon + '</span>';
            html += '<span class="wpfm-folder-name">' + folder.name + '</span>';
            html += '<span class="wpfm-file-count">' + fileCount + '</span>';
            html += '<div class="wpfm-media-folder-actions">';
            html += '<span class="dashicons dashicons-edit wpfm-media-edit-folder" data-folder-id="' + folder.id + '" title="แก้ไข"></span>';
            html += '<span class="dashicons dashicons-plus wpfm-media-add-subfolder" data-folder-id="' + folder.id + '" title="เพิ่ม Subfolder"></span>';
            html += '<span class="dashicons dashicons-trash wpfm-media-delete-folder" data-folder-id="' + folder.id + '" title="ลบ"></span>';
            html += '</div></div>';
            return html;
        }
        
        // ========================================================
        // Sidebar Resizer - ลากเพื่อปรับขนาด sidebar
        // ========================================================
        var $sidebar = $('#wpfm-media-sidebar');
        var $resizer = $('#wpfm-sidebar-resizer');
        var $wrap = $('.wrap');
        var isResizing = false;
        var startX = 0;
        var startWidth = 0;
        var sidebarLeft = 160; // WordPress admin menu width
        
        // โหลดความกว้างที่บันทึกไว้
        var savedWidth = localStorage.getItem('wpfm_sidebar_width');
        if (savedWidth) {
            savedWidth = parseInt(savedWidth);
            if (savedWidth >= 200 && savedWidth <= 600) {
                $sidebar.css('width', savedWidth + 'px');
                $wrap.css('margin-left', (savedWidth + 20) + 'px');
            }
        }
        
        $resizer.on('mousedown', function(e) {
            isResizing = true;
            startX = e.pageX;
            startWidth = $sidebar.width();
            $resizer.addClass('resizing');
            $('body').css('cursor', 'col-resize').css('user-select', 'none');
            e.preventDefault();
        });
        
        $(document).on('mousemove', function(e) {
            if (!isResizing) return;
            
            var deltaX = e.pageX - startX;
            var newWidth = startWidth + deltaX;
            
            // จำกัดขนาด min-max
            if (newWidth < 200) newWidth = 200;
            if (newWidth > 600) newWidth = 600;
            
            $sidebar.css('width', newWidth + 'px');
            $wrap.css('margin-left', (newWidth + 20) + 'px');
        });
        
        $(document).on('mouseup', function() {
            if (isResizing) {
                isResizing = false;
                $resizer.removeClass('resizing');
                $('body').css('cursor', '').css('user-select', '');
                
                // บันทึกความกว้างลง localStorage
                var currentWidth = $sidebar.width();
                localStorage.setItem('wpfm_sidebar_width', currentWidth);
            }
        });
        
    } // end isMediaPage
    
}); // end jQuery(document).ready

// ========================================================
// ส่วนที่ 3: Media Modal (Insert Media) - Folder Sidebar
// ========================================================
// แยกออกจาก document.ready เพื่อให้ทำงานอิสระจาก Section 1 & 2
// WordPress media modal DOM (ทุก element เป็น position: absolute):
//   .media-modal > .media-modal-content >
//     .media-frame-menu       (left:0, width:200px)
//     .media-frame-title      (left:200px)
//     .media-frame-router     (left:200px, top:50px)
//     .media-frame-tab-panel  >  .media-frame-content (left:200px, top:84px, bottom:61px)
//     .media-frame-toolbar    (left:200px, bottom:60px)
//     .media-frame-uploader
// เมื่อ .hide-menu ทุกอันจะ left:0

(function($) {
    'use strict';
    
    console.log('WPFM Modal: Section 3 loaded (standalone)');
    
    if (typeof wpfm_ajax === 'undefined') {
        console.log('WPFM Modal: wpfm_ajax undefined, ABORT');
        return;
    }
    
    console.log('WPFM Modal: wpfm_ajax OK, wp =', typeof wp, ', wp.media =', (typeof wp !== 'undefined' && wp.media) ? 'EXISTS' : 'NOT YET');
    
    var eventsInitialized = false;
    var hookApplied = false;
    
    // =============================================
    // ตรวจจับ media modal เปิด (3 วิธี)
    // =============================================
    
    // วิธีที่ 1: Hook wp.media.editor.open
    function hookMediaOpen() {
        if (hookApplied) return;
        if (typeof wp !== 'undefined' && wp.media && wp.media.editor && wp.media.editor.open) {
            hookApplied = true;
            var origOpen = wp.media.editor.open;
            wp.media.editor.open = function() {
                console.log('WPFM Modal: wp.media.editor.open CALLED');
                var result = origOpen.apply(this, arguments);
                scheduleInject();
                return result;
            };
            console.log('WPFM Modal: ✓ Hooked wp.media.editor.open');
        }
    }
    
    // ลอง hook ที่หลาย timing เพราะ wp.media อาจโหลดช้า
    hookMediaOpen();
    $(function() { hookMediaOpen(); });
    $(window).on('load', function() { hookMediaOpen(); });
    setTimeout(hookMediaOpen, 1000);
    setTimeout(hookMediaOpen, 3000);
    setTimeout(hookMediaOpen, 6000);
    setTimeout(hookMediaOpen, 10000);
    
    // วิธีที่ 2: ฟัง click events (รวม Elementor / Gutenberg / Classic Editor)
    $(document).on('click', [
        '.insert-media',
        '.set-post-thumbnail',
        '[data-editor]',
        '.wp-media-buttons .button',
        '.upload-php .page-title-action',
        // Elementor selectors
        '.elementor-control-media__preview',
        '.elementor-control-media-upload-button',
        '.elementor-control-gallery-add',
        '[class*="eicon-image"]',
        '[class*="eicon-gallery"]',
        // Generic media trigger
        '[data-setting="background_image"]',
        '.attachment-media-view .upload-button'
    ].join(', '), function() {
        console.log('WPFM Modal: Click on media trigger:', this.className || this.tagName);
        scheduleInject();
    });
    
    // วิธีที่ 3: MutationObserver - จับ .media-modal เพิ่มหรือ show
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (m.addedNodes && m.addedNodes.length > 0) {
                    for (var j = 0; j < m.addedNodes.length; j++) {
                        var node = m.addedNodes[j];
                        if (node.nodeType !== 1) continue;
                        if (node.classList && (node.classList.contains('media-modal-backdrop') || node.classList.contains('media-modal'))) {
                            console.log('WPFM Modal: Observer detected media-modal added');
                            scheduleInject();
                            break;
                        }
                        if (node.querySelector && node.querySelector('.media-modal')) {
                            console.log('WPFM Modal: Observer detected child .media-modal');
                            scheduleInject();
                            break;
                        }
                    }
                }
                if (m.type === 'attributes' && m.target.classList) {
                    if (m.target.classList.contains('media-modal') && m.target.style.display !== 'none') {
                        console.log('WPFM Modal: Observer detected modal attribute change (visible)');
                        scheduleInject();
                    }
                }
            }
        });
        
        // รอ body พร้อมก่อน observe
        function startObserver() {
            if (document.body) {
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });
                console.log('WPFM Modal: ✓ MutationObserver started');
            } else {
                setTimeout(startObserver, 100);
            }
        }
        startObserver();
    }
    
    // =============================================
    // Schedule injection (หลาย timing)
    // =============================================
    var scheduleTimers = [];
    function scheduleInject() {
        for (var i = 0; i < scheduleTimers.length; i++) {
            clearTimeout(scheduleTimers[i]);
        }
        scheduleTimers = [];
        var delays = [100, 300, 600, 1000, 1500, 2500];
        for (var j = 0; j < delays.length; j++) {
            scheduleTimers.push(setTimeout(tryInject, delays[j]));
        }
    }
    
    // =============================================
    // ค้นหา visible media modal แล้วแทรก sidebar
    // =============================================
    function tryInject() {
        // ถ้ามี sidebar อยู่แล้วและ visible ไม่ต้องทำซ้ำ
        var $existing = $('#wpfm-modal-sidebar');
        if ($existing.length > 0 && $existing.is(':visible')) return;
        if ($existing.length > 0) $existing.remove();
        
        // ค้นหา visible media modal
        var $modal = $('.media-modal:visible');
        
        // Fallback: Elementor อาจไม่ใช้ display:block แบบปกติ
        if ($modal.length === 0) {
            $modal = $('.media-modal').filter(function() {
                var $m = $(this);
                return $m.css('display') !== 'none' && $m.css('visibility') !== 'hidden' && $m.height() > 0;
            });
        }
        
        if ($modal.length === 0) return;
        
        var $modalContent = $modal.first().find('.media-modal-content');
        if ($modalContent.length === 0) return;
        
        var $frameContent = $modalContent.find('.media-frame-content');
        if ($frameContent.length === 0) {
            console.log('WPFM Modal: modal found but no .media-frame-content yet. Children:', 
                $modalContent.children().map(function(){ return this.className; }).get().join(', '));
            return;
        }
        
        console.log('WPFM Modal: ✓ Injecting sidebar into visible media modal');
        injectSidebar($modalContent, $frameContent);
    }
    
    // =============================================
    // แทรก sidebar เข้า media modal
    // =============================================
    function injectSidebar($modalContent, $frameContent) {
        var currentLeft = parseInt($frameContent.css('left')) || 0;
        
        var sidebarWidth = 220;
        var savedWidth = localStorage.getItem('wpfm_modal_sidebar_width');
        if (savedWidth) {
            savedWidth = parseInt(savedWidth);
            if (savedWidth >= 150 && savedWidth <= 400) sidebarWidth = savedWidth;
        }
        
        var contentTop = parseInt($frameContent.css('top')) || 84;
        
        var $sidebar = $(
            '<div id="wpfm-modal-sidebar">' +
                '<div id="wpfm-modal-resizer"></div>' +
                '<div class="wpfm-modal-sidebar-inner">' +
                    '<div class="wpfm-modal-header"><h3>📁 Folders</h3></div>' +
                    '<div class="wpfm-modal-folder-list">' +
                        '<div class="wpfm-modal-folder-item active" data-folder-id="0">' +
                            '<span class="wpfm-folder-icon">📂</span>' +
                            '<span class="wpfm-folder-name">All Files</span>' +
                        '</div>' +
                        '<div id="wpfm-modal-folders-loading">กำลังโหลด...</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
        
        // ตั้ง position absolute เหมือน WP elements อื่น
        $sidebar.css({
            position: 'absolute',
            top: contentTop + 'px',
            left: currentLeft + 'px',
            bottom: '0',
            width: sidebarWidth + 'px',
            zIndex: 50,
            background: '#f6f7f7',
            borderRight: '1px solid #c3c4c7',
            overflowY: 'auto',
            overflowX: 'hidden'
        });
        
        $modalContent.append($sidebar);
        
        // ดัน content, toolbar, router ไปทางขวา
        var newLeft = (currentLeft + sidebarWidth) + 'px';
        $frameContent.css('left', newLeft);
        $modalContent.find('.media-frame-toolbar').css('left', newLeft);
        $modalContent.find('.media-frame-router').css('left', newLeft);
        
        console.log('WPFM Modal: ✓ Sidebar injected. left=' + currentLeft + '+' + sidebarWidth + '=' + newLeft);
        
        // โหลด folders via AJAX
        $.ajax({
            url: wpfm_ajax.ajax_url,
            type: 'POST',
            data: { action: 'wpfm_get_modal_folders', nonce: wpfm_ajax.nonce },
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    $sidebar.find('#wpfm-modal-folders-loading').remove();
                    $sidebar.find('.wpfm-modal-folder-list').append(response.data.html);
                } else {
                    $sidebar.find('#wpfm-modal-folders-loading').text('ไม่มี folder');
                }
            },
            error: function() {
                $sidebar.find('#wpfm-modal-folders-loading').text('โหลดไม่สำเร็จ');
            }
        });
        
        // Init events (delegate, ครั้งเดียว)
        if (!eventsInitialized) {
            eventsInitialized = true;
            
            // คลิกเลือก folder
            $(document).on('click', '#wpfm-modal-sidebar .wpfm-modal-folder-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $this = $(this);
                $('#wpfm-modal-sidebar .wpfm-modal-folder-item').removeClass('active');
                $this.addClass('active');
                
                filterByFolder($this.data('folder-id'));
            });
            
            // Toggle subfolder
            $(document).on('click', '#wpfm-modal-sidebar .wpfm-modal-toggle-arrow', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if ($(this).hasClass('wpfm-no-children')) return;
                
                $(this).toggleClass('open');
                $(this).closest('.wpfm-modal-folder-item').next('.wpfm-modal-subfolder-container').slideToggle(200);
            });
            
            // Resize
            var isResizing = false, startX = 0, startW = 0;
            
            $(document).on('mousedown', '#wpfm-modal-resizer', function(e) {
                isResizing = true;
                startX = e.pageX;
                startW = $('#wpfm-modal-sidebar').width();
                $(this).addClass('resizing');
                $('body').css({ cursor: 'col-resize', userSelect: 'none' });
                e.preventDefault();
            });
            
            $(document).on('mousemove', function(e) {
                if (!isResizing) return;
                var newW = Math.min(400, Math.max(150, startW + (e.pageX - startX)));
                var $sb = $('#wpfm-modal-sidebar');
                if ($sb.length === 0) return;
                
                $sb.css('width', newW + 'px');
                
                var $mc = $sb.closest('.media-modal-content');
                var baseLeft = 0;
                var $menu = $mc.find('.media-frame-menu');
                if ($menu.length > 0 && $menu.is(':visible')) {
                    baseLeft = parseInt($menu.css('width')) || 200;
                }
                
                var adjLeft = (baseLeft + newW) + 'px';
                $mc.find('.media-frame-content').css('left', adjLeft);
                $mc.find('.media-frame-toolbar').css('left', adjLeft);
                $mc.find('.media-frame-router').css('left', adjLeft);
            });
            
            $(document).on('mouseup', function() {
                if (isResizing) {
                    isResizing = false;
                    $('#wpfm-modal-resizer').removeClass('resizing');
                    $('body').css({ cursor: '', userSelect: '' });
                    localStorage.setItem('wpfm_modal_sidebar_width', $('#wpfm-modal-sidebar').width());
                }
            });
        }
    }
    
    // =============================================
    // กรองไฟล์ตาม folder
    // =============================================
    function filterByFolder(folderId) {
        if (typeof wp === 'undefined' || !wp.media || !wp.media.frame) return;
        
        try {
            var library = null;
            var content = wp.media.frame.content.get();
            if (content && content.collection) {
                library = content.collection;
            }
            if (!library && wp.media.frame.state() && wp.media.frame.state().get('library')) {
                library = wp.media.frame.state().get('library');
            }
            if (library && library.props) {
                library.props.set({ wpfm_folder: folderId });
                console.log('WPFM Modal: Filter by folder', folderId);
            }
        } catch (err) {
            console.log('WPFM Modal: filter error:', err);
        }
    }
    
})(jQuery);