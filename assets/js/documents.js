/**
 * ============================================================================
 * JAF INVESTMENTS 4.6 - DOCUMENT MANAGEMENT SYSTEM
 * Advanced Cloud Storage Interface - JavaScript Module
 * ============================================================================
 */

// ============================================================================
// VARIABLES GLOBALES
// ============================================================================

let selectedFiles = new Set();
let currentFileId = null;
let dragCounter = 0;
let uploadQueue = [];

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeDragAndDrop();
    initializeKeyboardShortcuts();
    loadRecentActivity();
});

// ============================================================================
// EVENT LISTENERS
// ============================================================================

function initializeEventListeners() {
    // Click fuera del context menu para cerrarlo
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#contextMenu')) {
            hideContextMenu();
        }
    });
    
    // Click fuera del inspector para cerrarlo en móviles
    document.addEventListener('click', function(e) {
        const inspector = document.getElementById('inspectorPanel');
        if (window.innerWidth < 1280 && inspector && !inspector.classList.contains('hidden')) {
            if (!e.target.closest('#inspectorPanel') && !e.target.closest('.file-card')) {
                closeInspector();
            }
        }
    });
    
    // Búsqueda en tiempo real
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (e.target.value.length >= 3 || e.target.value.length === 0) {
                    performSearch(e.target.value);
                }
            }, 500);
        });
    }
}

// ============================================================================
// DRAG AND DROP
// ============================================================================

function initializeDragAndDrop() {
    const body = document.body;
    const overlay = document.getElementById('dragOverlay');
    
    // Prevenir comportamiento por defecto del navegador
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        body.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Mostrar overlay cuando se arrastra sobre la página
    body.addEventListener('dragenter', function(e) {
        dragCounter++;
        if (dragCounter === 1) {
            overlay.classList.add('active');
        }
    });
    
    body.addEventListener('dragleave', function(e) {
        dragCounter--;
        if (dragCounter === 0) {
            overlay.classList.remove('active');
        }
    });
    
    // Manejar drop
    body.addEventListener('drop', function(e) {
        dragCounter = 0;
        overlay.classList.remove('active');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFiles(files);
        }
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.add('dragover');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('dragover');
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFiles(files);
    }
}

// ============================================================================
// UPLOAD DE ARCHIVOS
// ============================================================================

function openUploadModal() {
    const modal = document.getElementById('uploadModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
        
        // Limpiar formulario
        const form = document.getElementById('uploadForm');
        if (form) form.reset();
        
        const preview = document.getElementById('filePreviewList');
        if (preview) preview.innerHTML = '';
    }
}

function handleFiles(files) {
    if (!files || files.length === 0) return;
    
    // Abrir modal de upload
    openUploadModal();
    
    // Agregar archivos al preview
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.files = files;
        updateFilePreview(files);
    }
}

function updateFilePreview(files) {
    const preview = document.getElementById('filePreviewList');
    if (!preview) return;
    
    preview.innerHTML = '';
    
    Array.from(files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200';
        
        const icon = getFileIconByType(file.type);
        const color = getFileColorByType(file.type);
        
        fileItem.innerHTML = `
            <div class="flex items-center justify-center w-10 h-10 rounded-lg" style="background: ${color}20;">
                <span class="material-symbols-outlined" style="color: ${color}">${icon}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">${file.name}</p>
                <p class="text-xs text-gray-500">${formatBytes(file.size)}</p>
            </div>
            <button type="button" onclick="removeFileFromPreview(${index})" class="text-gray-400 hover:text-red-600 transition-colors">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        `;
        
        preview.appendChild(fileItem);
    });
}

function removeFileFromPreview(index) {
    const fileInput = document.getElementById('fileInput');
    if (!fileInput) return;
    
    const dt = new DataTransfer();
    const files = Array.from(fileInput.files);
    
    files.forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    fileInput.files = dt.files;
    updateFilePreview(fileInput.files);
}

function uploadFiles() {
    const form = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    
    if (!form || !fileInput || fileInput.files.length === 0) {
        showToast('error', 'Error', 'Por favor selecciona al menos un archivo');
        return;
    }
    
    const formData = new FormData(form);
    
    // Mostrar progress bar
    showUploadProgress();
    
    // Simular upload con progress (en producción usar XMLHttpRequest real)
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            updateUploadProgress(percentComplete, fileInput.files[0].name);
        }
    });
    
    xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showToast('success', 'Éxito', 'Archivo(s) subido(s) correctamente');
                    closeUploadModal();
                    hideUploadProgress();
                    
                    // Recargar la página después de 1 segundo
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('error', 'Error', response.message || 'Error al subir archivos');
                    hideUploadProgress();
                }
            } catch (e) {
                showToast('error', 'Error', 'Error al procesar la respuesta del servidor');
                hideUploadProgress();
            }
        } else {
            showToast('error', 'Error', 'Error al subir archivos');
            hideUploadProgress();
        }
    });
    
    xhr.addEventListener('error', function() {
        showToast('error', 'Error', 'Error de conexión');
        hideUploadProgress();
    });
    
    xhr.open('POST', 'ajax/upload-document.php');
    xhr.send(formData);
}

function showUploadProgress() {
    const progress = document.getElementById('uploadProgress');
    if (progress) {
        progress.classList.add('active');
    }
}

function hideUploadProgress() {
    const progress = document.getElementById('uploadProgress');
    if (progress) {
        progress.classList.remove('active');
    }
}

function updateUploadProgress(percent, fileName) {
    const progressFill = document.getElementById('progressFill');
    const progressPercent = document.getElementById('uploadPercent');
    const fileNameEl = document.getElementById('uploadFileName');
    
    if (progressFill) {
        progressFill.style.width = percent + '%';
    }
    
    if (progressPercent) {
        progressPercent.textContent = Math.round(percent) + '%';
    }
    
    if (fileNameEl && fileName) {
        fileNameEl.textContent = fileName;
    }
}

// ============================================================================
// PREVIEW DE ARCHIVOS
// ============================================================================

function openFilePreview(fileId) {
    currentFileId = fileId;
    
    // Obtener datos del archivo
    fetch(`ajax/get-file-details.php?id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPreviewModal(data.file);
                
                // Registrar visualización
                registerFileActivity(fileId, 'view');
            } else {
                showToast('error', 'Error', data.message || 'No se pudo cargar el archivo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Error al cargar la vista previa');
        });
}

function showPreviewModal(file) {
    const modal = document.getElementById('previewModal');
    const container = document.getElementById('previewContainer');
    
    if (!modal || !container) return;
    
    container.innerHTML = '';
    
    const isImageFile = file.mime_type && file.mime_type.startsWith('image/');
    const isPdfFile = file.mime_type && file.mime_type.includes('pdf');
    
    if (isImageFile) {
        // Preview de imagen
        const img = document.createElement('img');
        img.src = file.file_path;
        img.alt = file.document_name;
        img.className = 'max-w-full max-h-full object-contain';
        container.appendChild(img);
        
    } else if (isPdfFile) {
        // Preview de PDF con iframe
        const iframe = document.createElement('iframe');
        iframe.src = file.file_path;
        iframe.className = 'w-full h-full';
        iframe.style.minHeight = '80vh';
        container.appendChild(iframe);
        
    } else {
        // No preview disponible - mostrar info
        const noPreview = document.createElement('div');
        noPreview.className = 'text-center text-white p-8';
        noPreview.innerHTML = `
            <span class="material-symbols-outlined text-9xl mb-4 opacity-50">description</span>
            <h3 class="text-2xl font-bold mb-2">Vista previa no disponible</h3>
            <p class="text-white/80 mb-6">Este tipo de archivo no se puede previsualizar en el navegador</p>
            <button onclick="downloadFile(${file.id})" class="px-6 py-3 bg-white text-gray-900 rounded-lg font-medium hover:bg-gray-100 transition-colors inline-flex items-center gap-2">
                <span class="material-symbols-outlined">download</span>
                Descargar Archivo
            </button>
        `;
        container.appendChild(noPreview);
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    currentFileId = null;
}

// ============================================================================
// INSPECTOR PANEL
// ============================================================================

function openInspector(fileId) {
    currentFileId = fileId;
    
    const inspector = document.getElementById('inspectorPanel');
    const content = document.getElementById('inspectorContent');
    
    if (!inspector || !content) return;
    
    // Mostrar loading
    content.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
    `;
    
    inspector.classList.remove('hidden', 'closed');
    
    // Cargar detalles del archivo
    fetch(`ajax/get-file-details.php?id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderInspectorContent(data.file);
            } else {
                content.innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <span class="material-symbols-outlined text-6xl mb-3">error</span>
                        <p>Error al cargar detalles</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="text-center py-8 text-red-600">
                    <span class="material-symbols-outlined text-6xl mb-3">error</span>
                    <p>Error de conexión</p>
                </div>
            `;
        });
}

function renderInspectorContent(file) {
    const content = document.getElementById('inspectorContent');
    if (!content) return;
    
    const isImage = file.mime_type && file.mime_type.startsWith('image/');
    
    content.innerHTML = `
        <!-- Preview -->
        <div class="flex flex-col items-center">
            <div class="w-full aspect-video rounded-lg overflow-hidden border border-gray-200 bg-gray-50 mb-3 relative group cursor-pointer" onclick="openFilePreview(${file.id})">
                ${isImage 
                    ? `<img src="${file.file_path}" alt="${file.document_name}" class="w-full h-full object-cover">`
                    : `<div class="w-full h-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-gray-300 text-6xl">${getFileIconByMime(file.mime_type)}</span>
                       </div>`
                }
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <span class="material-symbols-outlined text-white text-4xl">visibility</span>
                </div>
            </div>
            <h4 class="text-base font-semibold text-center text-gray-900 mb-1 w-full truncate px-2" title="${file.document_name}">
                ${file.document_name}
            </h4>
            <p class="text-sm text-gray-500">${file.category_name || 'Sin categoría'}</p>
        </div>
        
        <!-- Properties -->
        <div class="space-y-3">
            <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Información</h5>
            
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Tipo</span>
                <span class="text-gray-900 font-medium">${getFileTypeLabel(file.mime_type)}</span>
            </div>
            
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Tamaño</span>
                <span class="text-gray-900 font-medium">${formatBytes(file.file_size)}</span>
            </div>
            
            ${file.city_name ? `
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Ciudad</span>
                <span class="text-gray-900 font-medium">${file.city_name}</span>
            </div>
            ` : ''}
            
            ${file.property_reference ? `
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Propiedad</span>
                <span class="text-gray-900 font-medium">${file.property_reference}</span>
            </div>
            ` : ''}
            
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Creado</span>
                <span class="text-gray-900 font-medium">${formatDate(file.created_at)}</span>
            </div>
            
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Modificado</span>
                <span class="text-gray-900 font-medium">${formatRelativeTime(file.updated_at)}</span>
            </div>
            
            ${file.access_count > 0 ? `
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Vistas</span>
                <span class="text-gray-900 font-medium">${file.access_count}</span>
            </div>
            ` : ''}
        </div>
        
        ${file.description ? `
        <div class="space-y-2">
            <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Descripción</h5>
            <p class="text-sm text-gray-700">${file.description}</p>
        </div>
        ` : ''}
        
        <!-- Access -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Acceso</h5>
                <button onclick="shareFile(${file.id})" class="text-blue-600 text-xs font-bold hover:underline">
                    Compartir
                </button>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-cover bg-center" style="background-image: url('${file.uploader_picture || 'assets/images/default-avatar.png'}')"></div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">${file.uploader_name || 'Tú'}</p>
                    <p class="text-xs text-gray-500">Propietario</p>
                </div>
            </div>
        </div>
        
        <!-- Activity -->
        <div class="space-y-3 pt-4 border-t border-gray-200">
            <h5 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Actividad Reciente</h5>
            <div id="fileActivity" class="relative pl-4 border-l-2 border-gray-200 space-y-4">
                <div class="text-sm text-gray-500 text-center py-4">Cargando...</div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="pt-4 border-t border-gray-200 flex gap-2">
            <button onclick="downloadFile(${file.id})" class="flex-1 flex items-center justify-center gap-2 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-medium transition-colors text-gray-900">
                <span class="material-symbols-outlined text-lg">download</span>
                Descargar
            </button>
            <button onclick="shareFile(${file.id})" class="flex-1 flex items-center justify-center gap-2 h-10 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-medium transition-colors text-gray-900">
                <span class="material-symbols-outlined text-lg">share</span>
                Compartir
            </button>
        </div>
    `;
    
    // Cargar actividad del archivo
    loadFileActivity(file.id);
}

function closeInspector() {
    const inspector = document.getElementById('inspectorPanel');
    if (inspector) {
        inspector.classList.add('closed');
        setTimeout(() => {
            inspector.classList.add('hidden');
        }, 300);
    }
    currentFileId = null;
}

// ============================================================================
// CONTEXT MENU
// ============================================================================

function showContextMenu(fileId, event) {
    event.preventDefault();
    event.stopPropagation();
    
    currentFileId = fileId;
    
    const menu = document.getElementById('contextMenu');
    if (!menu) return;
    
    // Posicionar el menú
    menu.style.left = event.pageX + 'px';
    menu.style.top = event.pageY + 'px';
    menu.classList.add('active');
    
    // Actualizar texto del botón star según estado
    const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);
    const isStarred = fileCard && fileCard.dataset.starred === '1';
    const starText = document.getElementById('contextStarText');
    if (starText) {
        starText.textContent = isStarred ? 'Quitar destacado' : 'Destacar';
    }
}

function hideContextMenu() {
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.classList.remove('active');
    }
}

// ============================================================================
// ACCIONES DE ARCHIVOS
// ============================================================================

function selectFile(fileId) {
    if (selectedFiles.has(fileId)) {
        selectedFiles.delete(fileId);
    } else {
        selectedFiles.add(fileId);
    }
    
    updateFileSelection();
}

function updateFileSelection() {
    document.querySelectorAll('.file-card').forEach(card => {
        const fileId = parseInt(card.dataset.fileId);
        if (selectedFiles.has(fileId)) {
            card.classList.add('file-selected');
        } else {
            card.classList.remove('file-selected');
        }
    });
}

function previewFile() {
    if (!currentFileId) return;
    openFilePreview(currentFileId);
}

function downloadFile(fileId = null) {
    const id = fileId || currentFileId;
    if (!id) return;
    
    // Registrar descarga
    registerFileActivity(id, 'download');
    
    // Iniciar descarga
    window.location.href = `ajax/download-file.php?id=${id}`;
    
    showToast('info', 'Descargando', 'El archivo se está descargando...');
}

function downloadCurrentFile() {
    downloadFile(currentFileId);
}

function shareFile(fileId = null) {
    const id = fileId || currentFileId;
    if (!id) return;
    
    // Aquí implementar modal de compartir
    showToast('info', 'Compartir', 'Función de compartir en desarrollo');
}

function toggleStar(fileId = null) {
    const id = fileId || currentFileId;
    if (!id) return;
    
    fetch('ajax/toggle-star.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ file_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar UI
            const fileCard = document.querySelector(`[data-file-id="${id}"]`);
            if (fileCard) {
                fileCard.dataset.starred = data.is_starred ? '1' : '0';
                
                const starIcon = fileCard.querySelector('.star-icon');
                if (starIcon) {
                    if (data.is_starred) {
                        starIcon.classList.add('starred', 'filled');
                    } else {
                        starIcon.classList.remove('starred', 'filled');
                    }
                }
            }
            
            showToast('success', 'Éxito', data.is_starred ? 'Archivo destacado' : 'Destacado eliminado');
        } else {
            showToast('error', 'Error', data.message || 'Error al actualizar');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Error de conexión');
    });
}

function renameFile(fileId = null) {
    const id = fileId || currentFileId;
    if (!id) return;
    
    const newName = prompt('Nuevo nombre del archivo:');
    if (!newName) return;
    
    fetch('ajax/rename-file.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ file_id: id, new_name: newName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Éxito', 'Archivo renombrado correctamente');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('error', 'Error', data.message || 'Error al renombrar');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Error de conexión');
    });
}

function moveFile(fileId = null) {
    const id = fileId || currentFileId;
    if (!id) return;
    
    // Implementar modal de mover archivo
    showToast('info', 'Mover', 'Función de mover en desarrollo');
}

function deleteFile(fileId = null) {
    const id = fileId || currentFileId;
    if (!id) return;
    
    if (!confirm('¿Estás seguro de que deseas eliminar este archivo?')) {
        return;
    }
    
    fetch('ajax/delete-file.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ file_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Éxito', 'Archivo eliminado correctamente');
            
            // Remover de UI
            const fileCard = document.querySelector(`[data-file-id="${id}"]`);
            if (fileCard) {
                fileCard.style.opacity = '0';
                setTimeout(() => fileCard.remove(), 300);
            }
            
            closeInspector();
        } else {
            showToast('error', 'Error', data.message || 'Error al eliminar');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Error de conexión');
    });
}

// ============================================================================
// FILTROS Y BÚSQUEDA
// ============================================================================

function applyFilters() {
    const filterDate = document.getElementById('filterDate').value;
    const filterType = document.getElementById('filterType').value;
    
    const url = new URL(window.location);
    url.searchParams.set('date', filterDate);
    url.searchParams.set('type', filterType);
    
    window.location.href = url.toString();
}

function clearFilters() {
    const url = new URL(window.location);
    url.searchParams.delete('date');
    url.searchParams.delete('type');
    url.searchParams.delete('search');
    
    window.location.href = url.toString();
}

function setViewMode(mode) {
    const url = new URL(window.location);
    url.searchParams.set('mode', mode);
    window.location.href = url.toString();
}

function performSearch(query) {
    const url = new URL(window.location);
    if (query) {
        url.searchParams.set('search', query);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

// ============================================================================
// ACTIVIDAD Y ANALYTICS
// ============================================================================

function registerFileActivity(fileId, action) {
    fetch('ajax/register-activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            file_id: fileId, 
            action: action 
        })
    })
    .catch(error => console.error('Error registering activity:', error));
}

function loadFileActivity(fileId) {
    fetch(`ajax/get-file-activity.php?id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('fileActivity');
            if (!container) return;
            
            if (data.success && data.activities && data.activities.length > 0) {
                container.innerHTML = data.activities.map(activity => `
                    <div class="relative">
                        <div class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full ${getActivityColor(activity.action)} ring-4 ring-white"></div>
                        <p class="text-sm text-gray-900">
                            <span class="font-medium">${activity.user_name || 'Sistema'}</span> 
                            ${getActivityText(activity.action)}
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">${formatRelativeTime(activity.created_at)}</p>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">Sin actividad reciente</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const container = document.getElementById('fileActivity');
            if (container) {
                container.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Error al cargar actividad</p>';
            }
        });
}

function loadRecentActivity() {
    // Cargar actividad reciente global si hay un contenedor para ello
    const container = document.getElementById('recentActivityList');
    if (!container) return;
    
    fetch('ajax/get-recent-activity.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.activities) {
                // Renderizar actividades
                container.innerHTML = data.activities.map(activity => `
                    <div class="flex items-start gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <div class="w-8 h-8 rounded-full bg-cover bg-center" style="background-image: url('${activity.profile_picture || 'assets/images/default-avatar.png'}')"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900">
                                <span class="font-medium">${activity.user_name}</span> ${getActivityText(activity.action)} 
                                <span class="font-medium">${activity.document_name}</span>
                            </p>
                            <p class="text-xs text-gray-500">${formatRelativeTime(activity.created_at)}</p>
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => console.error('Error loading recent activity:', error));
}

// ============================================================================
// KEYBOARD SHORTCUTS
// ============================================================================

function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Escape - cerrar modales
        if (e.key === 'Escape') {
            closeUploadModal();
            closePreview();
            hideContextMenu();
        }
        
        // Ctrl/Cmd + U - abrir upload
        if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
            e.preventDefault();
            openUploadModal();
        }
        
        // Ctrl/Cmd + F - focus en búsqueda
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.getElementById('globalSearch')?.focus();
        }
        
        // Delete - eliminar archivo seleccionado
        if (e.key === 'Delete' && currentFileId) {
            deleteFile();
        }
    });
}

// ============================================================================
// UTILIDADES
// ============================================================================

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Hace ' + diff + ' segundo' + (diff !== 1 ? 's' : '');
    if (diff < 3600) return 'Hace ' + Math.floor(diff / 60) + ' minuto' + (Math.floor(diff / 60) !== 1 ? 's' : '');
    if (diff < 86400) return 'Hace ' + Math.floor(diff / 3600) + ' hora' + (Math.floor(diff / 3600) !== 1 ? 's' : '');
    if (diff < 604800) return 'Hace ' + Math.floor(diff / 86400) + ' día' + (Math.floor(diff / 86400) !== 1 ? 's' : '');
    
    return formatDate(dateString);
}

function getFileIconByType(mimeType) {
    if (!mimeType) return 'description';
    if (mimeType.includes('pdf')) return 'picture_as_pdf';
    if (mimeType.includes('image')) return 'image';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'description';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'table_view';
    if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'folder_zip';
    if (mimeType.includes('video')) return 'video_file';
    if (mimeType.includes('audio')) return 'audio_file';
    return 'description';
}

function getFileIconByMime(mimeType) {
    return getFileIconByType(mimeType);
}

function getFileColorByType(mimeType) {
    if (!mimeType) return '#6B7280';
    if (mimeType.includes('pdf')) return '#EF4444';
    if (mimeType.includes('image')) return '#3B82F6';
    if (mimeType.includes('word') || mimeType.includes('document')) return '#1D4ED8';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '#059669';
    if (mimeType.includes('zip')) return '#D97706';
    if (mimeType.includes('video')) return '#8B5CF6';
    return '#6B7280';
}

function getFileTypeLabel(mimeType) {
    if (!mimeType) return 'Archivo';
    if (mimeType.includes('pdf')) return 'Documento PDF';
    if (mimeType.includes('image')) return 'Imagen';
    if (mimeType.includes('word')) return 'Documento Word';
    if (mimeType.includes('excel')) return 'Hoja de cálculo';
    if (mimeType.includes('zip')) return 'Archivo comprimido';
    if (mimeType.includes('video')) return 'Video';
    if (mimeType.includes('audio')) return 'Audio';
    return 'Archivo';
}

function getActivityColor(action) {
    switch(action) {
        case 'uploaded': return 'bg-green-500';
        case 'viewed': return 'bg-blue-500';
        case 'downloaded': return 'bg-purple-500';
        case 'edited': return 'bg-yellow-500';
        case 'shared': return 'bg-pink-500';
        case 'deleted': return 'bg-red-500';
        default: return 'bg-gray-300';
    }
}

function getActivityText(action) {
    switch(action) {
        case 'uploaded': return 'subió';
        case 'viewed': return 'visualizó';
        case 'downloaded': return 'descargó';
        case 'edited': return 'editó';
        case 'shared': return 'compartió';
        case 'deleted': return 'eliminó';
        case 'starred': return 'destacó';
        default: return 'interactuó con';
    }
}

function showToast(type, title, message) {
    const toast = document.getElementById('notificationToast');
    const icon = document.getElementById('toastIcon');
    const titleEl = document.getElementById('toastTitle');
    const messageEl = document.getElementById('toastMessage');
    
    if (!toast) return;
    
    // Reset classes
    toast.className = 'notification-toast active ' + type;
    
    // Set content
    if (icon) {
        const iconMap = {
            'success': 'check_circle',
            'error': 'error',
            'info': 'info',
            'warning': 'warning'
        };
        icon.textContent = iconMap[type] || 'info';
        icon.className = 'material-symbols-outlined text-2xl';
        
        const colorMap = {
            'success': 'text-green-600',
            'error': 'text-red-600',
            'info': 'text-blue-600',
            'warning': 'text-yellow-600'
        };
        icon.classList.add(colorMap[type]);
    }
    
    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    
    // Auto hide después de 5 segundos
    setTimeout(() => {
        hideToast();
    }, 5000);
}

function hideToast() {
    const toast = document.getElementById('notificationToast');
    if (toast) {
        toast.classList.remove('active');
    }
}

// ============================================================================
// EXPORT
// ============================================================================

window.DocumentManager = {
    openUploadModal,
    closeUploadModal,
    openFilePreview,
    closePreview,
    openInspector,
    closeInspector,
    showContextMenu,
    hideContextMenu,
    selectFile,
    downloadFile,
    shareFile,
    toggleStar,
    renameFile,
    moveFile,
    deleteFile,
    applyFilters,
    clearFilters,
    setViewMode
};