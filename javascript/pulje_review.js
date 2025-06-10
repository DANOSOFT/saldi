let documents = [];
let currentRenameFile = '';
let currentDeleteFile = '';
let selectedDocument = null;
let currentMobileDocument = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadDocuments();
    setupResizeHandle();
    setupMobileFullscreen();
    
    // Event listeners
    document.getElementById('searchInput').addEventListener('input', handleSearch);
    document.getElementById('sortBy').addEventListener('change', loadDocuments);
    document.getElementById('sortOrder').addEventListener('change', loadDocuments);
    document.getElementById('refreshBtn').addEventListener('click', loadDocuments);
    
    // Window resize handler
    window.addEventListener('resize', handleResize);
    
    // Modal event listeners
    setupModalHandlers();
    
    // Initial resize check
    handleResize();
});

// Setup resize handle for left panel
function setupResizeHandle() {
    const resizeHandle = document.querySelector('.resize-handle');
    const leftPanel = document.querySelector('.left-panel');
    let isResizing = false;

    resizeHandle.addEventListener('mousedown', function(e) {
        isResizing = true;
        document.addEventListener('mousemove', handleResize);
        document.addEventListener('mouseup', stopResize);
        e.preventDefault();
    });

    function handleResize(e) {
        if (!isResizing) return;
        
        const containerWidth = document.querySelector('.main-container').offsetWidth;
        const newWidth = (e.clientX / containerWidth) * 100;
        
        if (newWidth >= 30 && newWidth <= 70) {
            leftPanel.style.flex = `0 0 ${newWidth}%`;
        }
    }

    function stopResize() {
        isResizing = false;
        document.removeEventListener('mousemove', handleResize);
        document.removeEventListener('mouseup', stopResize);
    }
}

// Setup mobile fullscreen functionality
function setupMobileFullscreen() {
    const mobileBackBtn = document.getElementById('mobileBackBtn');
    const mobileDownloadBtn = document.getElementById('mobileDownloadBtn');
    const mobileShareBtn = document.getElementById('mobileShareBtn');
    
    mobileBackBtn.addEventListener('click', closeMobileFullscreen);
    
    mobileDownloadBtn.addEventListener('click', function() {
        if (currentMobileDocument) {
            downloadDocument(currentMobileDocument.filename);
        }
    });
    
    mobileShareBtn.addEventListener('click', function() {
        if (currentMobileDocument) {
            const url = `../bilag/${serverData.db}/pulje/${currentMobileDocument.filename}`;
            if (navigator.share) {
                navigator.share({
                    title: currentMobileDocument.filename,
                    url: window.location.origin + '/' + url
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.origin + '/' + url);
                showSuccess('Link kopieret til udklipsholder');
            }
        }
    });
}

// Open document in mobile fullscreen
function openMobileFullscreen(doc) {
    const modal = document.getElementById('mobileFullscreenModal');
    const title = document.getElementById('mobileDocumentTitle');
    const content = document.getElementById('mobileFullscreenContent');
    
    currentMobileDocument = doc;
    title.textContent = doc.filename;
    
    const fileUrl = `../bilag/${serverData.db}/pulje/${doc.filename}`;
    
    // Clear previous content
    content.innerHTML = '';
    
    if (doc.fileExt === 'pdf') {
        // Show PDF in iframe
        const iframe = document.createElement('iframe');
        iframe.className = 'mobile-fullscreen-iframe';
        iframe.src = fileUrl + '#toolbar=1&navpanes=0&scrollbar=1&view=FitH';
        content.appendChild(iframe);
        
    } else if (['jpg', 'jpeg', 'png'].includes(doc.fileExt)) {
        // Show image
        const img = document.createElement('img');
        img.className = 'mobile-fullscreen-image';
        img.src = fileUrl;
        img.alt = doc.filename;
        content.appendChild(img);
    }
    
    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close mobile fullscreen
function closeMobileFullscreen() {
    const modal = document.getElementById('mobileFullscreenModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentMobileDocument = null;
}

// Handle mobile-specific interactions
function setupMobileInteractions() {
    if (window.innerWidth <= 768) {
        // Enhanced document card interactions for mobile
        document.addEventListener('click', function(e) {
            const card = e.target.closest('.document-card');
            if (card && !e.target.classList.contains('action-btn')) {
                const filename = card.getAttribute('data-filename');
                const doc = documents.find(d => d.filename === filename);
                
                if (doc) {
                    // Open in fullscreen modal on mobile
                    openMobileFullscreen(doc);
                }
            }
        });
    }
}

// Update window resize handler
function handleResize() {
    const rightPanel = document.getElementById('rightPanel');
    
    if (window.innerWidth > 768) {
        // Desktop mode - show side panel
        rightPanel.classList.remove('hidden');
        rightPanel.style.cssText = ''; // Reset mobile styles
        
        // Close mobile fullscreen if open
        if (document.getElementById('mobileFullscreenModal').classList.contains('active')) {
            closeMobileFullscreen();
        }
    } else {
        // Mobile mode - hide side panel
        rightPanel.classList.add('hidden');
        setupMobileInteractions();
    }
} 

// Update window resize handler
function handleResize() {
    const rightPanel = document.getElementById('rightPanel');
    const toggleBtn = document.getElementById('togglePreviewBtn');
    
    if (window.innerWidth > 768) {
        // Desktop mode
        rightPanel.classList.remove('hidden', 'visible');
        rightPanel.style.cssText = ''; // Reset mobile styles
        toggleBtn.textContent = '👁️ Forhåndsvisning';
        toggleBtn.classList.remove('active');
        isPreviewVisible = true;
        removeMobileBackdrop();
    } else {
        // Mobile mode
        if (!isPreviewVisible) {
            rightPanel.classList.add('hidden');
            rightPanel.classList.remove('visible');
        }
        setupMobileInteractions();
    }
}

// File size formatter
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Format date
function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}-${month}-${year} ${hours}:${minutes}`;
}

// Get file icon
function getFileIcon(extension) {
    switch (extension.toLowerCase()) {
        case 'pdf':
            return '📄';
        case 'jpg':
        case 'jpeg':
        case 'png':
            return '🖼️';
        default:
            return '📁';
    }
}

// Load documents
async function loadDocuments() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const documentsGrid = document.getElementById('documentsGrid');
    const emptyState = document.getElementById('emptyState');
    
    loadingIndicator.style.display = 'block';
    documentsGrid.style.display = 'none';
    emptyState.style.display = 'none';
    
    try {
        const formData = new FormData();
        formData.append('action', 'loadDocuments');
        formData.append('search', document.getElementById('searchInput').value);
        formData.append('sortBy', document.getElementById('sortBy').value);
        formData.append('sortOrder', document.getElementById('sortOrder').value);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let jsonText = responseText;
        
        const jsonStart = responseText.indexOf('===JSON_START===');
        const jsonEnd = responseText.indexOf('===JSON_END===');
        
        if (jsonStart !== -1 && jsonEnd !== -1) {
            jsonText = responseText.substring(jsonStart + 16, jsonEnd);
        }
        
        const result = JSON.parse(jsonText);
        
        if (result.success) {
            documents = result.documents;
            renderDocuments();
            updateStats();
        } else {
            showError('Fejl ved indlæsning: ' + result.message);
        }
        
    } catch (error) {
        showError('Netværksfejl: ' + error.message);
    } finally {
        loadingIndicator.style.display = 'none';
    }
}

// Render documents
function renderDocuments() {
    const documentsGrid = document.getElementById('documentsGrid');
    const emptyState = document.getElementById('emptyState');
    
    if (documents.length === 0) {
        documentsGrid.style.display = 'none';
        emptyState.style.display = 'block';
        clearPreview();
        return;
    }
    
    documentsGrid.style.display = 'grid';
    emptyState.style.display = 'none';
    
    documentsGrid.innerHTML = '';
    
    documents.forEach((doc, index) => {
        const card = document.createElement('div');
        card.className = 'document-card';
        card.setAttribute('data-filename', doc.filename);
        
        // Add special styling for different file types
        if (['jpg', 'jpeg', 'png'].includes(doc.fileExt)) {
            card.classList.add('image-card');
        } else if (doc.fileExt === 'pdf') {
            card.classList.add('pdf-card');
        }
        
        const icon = getFileIcon(doc.fileExt);
        
        card.innerHTML = `
            <div class="document-card-content">
                <div class="file-icon ${doc.fileExt}">${icon}</div>
                <div class="document-info">
                    <div class="document-name">${escapeHtml(doc.filename)}</div>
                    ${doc.description ? `<div class="document-description">${escapeHtml(doc.description)}</div>` : ''}
                    <div class="document-meta">
                        ${doc.documentDate ? `<div><strong>Dato:</strong> ${doc.documentDate}</div>` : ''}
                        <div><strong>Størrelse:</strong> ${formatFileSize(doc.fileSize)}</div>
                        <div><strong>Ændret:</strong> ${formatDate(doc.modTime)}</div>
                        <div><strong>Type:</strong> ${doc.fileExt.toUpperCase()}</div>
                    </div>
                    <div class="document-actions">
                        <button class="action-btn view-btn" onclick="viewDocument('${escapeHtml(doc.filename)}')">👁️</button>
                        <button class="action-btn download-btn" onclick="downloadDocument('${escapeHtml(doc.filename)}')">⬇️</button>
                        <button class="action-btn rename-btn" onclick="renameDocument('${escapeHtml(doc.filename)}')">✏️</button>
                        <button class="action-btn delete-btn" onclick="deleteDocument('${escapeHtml(doc.filename)}')">🗑️</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add click handler for preview
        card.addEventListener('click', function(e) {
            // Don't trigger preview if clicking on action buttons
            if (e.target.classList.contains('action-btn')) {
                return;
            }
            
            if (window.innerWidth <= 768) {
                // Mobile: Open in fullscreen
                openMobileFullscreen(doc);
            } else {
                // Desktop: Use side panel
                selectDocument(doc, card);
            }
        });
        
        documentsGrid.appendChild(card);
    });
}

// Select document and show preview
function selectDocument(doc, cardElement) {
    // Remove previous selection
    document.querySelectorAll('.document-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selection to current card
    cardElement.classList.add('selected');
    selectedDocument = doc;
    
    showPreview(doc);
}

// Show preview
function showPreview(doc) {
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const previewActions = document.getElementById('previewActions');
    const previewViewBtn = document.getElementById('previewViewBtn');
    const previewDownloadBtn = document.getElementById('previewDownloadBtn');
    
    const fileUrl = `../bilag/${serverData.db}/pulje/${doc.filename}`;
    
    previewTitle.textContent = doc.filename;
    previewActions.style.display = 'flex';
    previewViewBtn.href = fileUrl;
    previewDownloadBtn.href = fileUrl;
    previewDownloadBtn.download = doc.filename;
    
    // Clear previous content
    previewContent.innerHTML = '';
    
    if (doc.fileExt === 'pdf') {
        // Show PDF in iframe
        const iframe = document.createElement('iframe');
        iframe.className = 'preview-iframe';
        iframe.src = fileUrl + '#toolbar=0&navpanes=0&scrollbar=0';
        iframe.onerror = function() {
            showPreviewError('Kunne ikke indlæse PDF forhåndsvisning');
        };
        previewContent.appendChild(iframe);
        
    } else if (['jpg', 'jpeg', 'png'].includes(doc.fileExt)) {
        // Show image
        const img = document.createElement('img');
        img.className = 'preview-image';
        img.src = fileUrl;
        img.alt = doc.filename;
        img.onerror = function() {
            showPreviewError('Kunne ikke indlæse billede');
        };
        previewContent.appendChild(img);
    } else {
        showPreviewError('Forhåndsvisning ikke tilgængelig for denne filtype');
    }
}

// Show preview error
function showPreviewError(message) {
    const previewContent = document.getElementById('previewContent');
    previewContent.innerHTML = `
        <div class="preview-placeholder">
            <div class="preview-placeholder-icon">❌</div>
            <div>${message}</div>
        </div>
    `;
}

// Clear preview
function clearPreview() {
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const previewActions = document.getElementById('previewActions');
    
    previewTitle.textContent = 'Ingen dokument valgt';
    previewActions.style.display = 'none';
    selectedDocument = null;
    
    previewContent.innerHTML = `
        <div class="preview-placeholder">
            <div class="preview-placeholder-icon">📄</div>
            <div>Vælg et dokument for at se forhåndsvisning</div>
        </div>
    `;
    
    // Remove all selections
    document.querySelectorAll('.document-card').forEach(card => {
        card.classList.remove('selected');
    });
}

// Update statistics
function updateStats() {
    const statsContainer = document.getElementById('statsContainer');
    
    if (documents.length === 0) {
        statsContainer.style.display = 'none';
        return;
    }
    
    statsContainer.style.display = 'flex';
    
    const totalFiles = documents.length;
    const totalSize = documents.reduce((sum, doc) => sum + doc.fileSize, 0);
    const pdfCount = documents.filter(doc => doc.fileExt === 'pdf').length;
    const imageCount = documents.filter(doc => ['jpg', 'jpeg', 'png'].includes(doc.fileExt)).length;
    
    document.getElementById('totalFiles').textContent = totalFiles;
    document.getElementById('totalSize').textContent = formatFileSize(totalSize);
    document.getElementById('pdfCount').textContent = pdfCount;
    document.getElementById('imageCount').textContent = imageCount;
}

// Handle search with debounce
let searchTimeout;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadDocuments();
    }, 300);
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Document actions
function viewDocument(filename) {
    const url = `../bilag/${serverData.db}/pulje/${filename}`;
    window.open(url, '_blank');
}

function downloadDocument(filename) {
    const url = `../bilag/${serverData.db}/pulje/${filename}`;
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function renameDocument(filename) {
    currentRenameFile = filename;
    const nameWithoutExt = filename.substring(0, filename.lastIndexOf('.'));
    document.getElementById('newFilename').value = nameWithoutExt;
    document.getElementById('renameModal').style.display = 'block';
}

function deleteDocument(filename) {
    currentDeleteFile = filename;
    document.getElementById('deleteFilename').textContent = filename;
    document.getElementById('deleteModal').style.display = 'block';
}

// Modal handlers
function setupModalHandlers() {
    // Close modal when clicking X
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', closeModals);
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModals();
        }
    });
    
    // Rename modal handlers
    document.getElementById('cancelRename').addEventListener('click', closeModals);
    document.getElementById('confirmRename').addEventListener('click', confirmRename);
    
    // Delete modal handlers
    document.getElementById('cancelDelete').addEventListener('click', closeModals);
    document.getElementById('confirmDelete').addEventListener('click', confirmDelete);
    
    // Enter key handling
    document.getElementById('newFilename').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            confirmRename();
        }
    });
}

function closeModals() {
    document.getElementById('renameModal').style.display = 'none';
    document.getElementById('deleteModal').style.display = 'none';
    currentRenameFile = '';
    currentDeleteFile = '';
}

// Confirm rename
async function confirmRename() {
    const newName = document.getElementById('newFilename').value.trim();
    
    if (!newName) {
        alert('Indtast venligst et nyt navn');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'renameDocument');
        formData.append('oldFilename', currentRenameFile);
        formData.append('newFilename', newName);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let jsonText = responseText;
        
        const jsonStart = responseText.indexOf('===JSON_START===');
        const jsonEnd = responseText.indexOf('===JSON_END===');
        
        if (jsonStart !== -1 && jsonEnd !== -1) {
            jsonText = responseText.substring(jsonStart + 16, jsonEnd);
        }
        
        const result = JSON.parse(jsonText);
        
        if (result.success) {
            showSuccess(result.message);
            closeModals();
            loadDocuments();
            
            // Clear preview if the renamed file was selected
            if (selectedDocument && selectedDocument.filename === currentRenameFile) {
                clearPreview();
            }
        } else {
            showError('Fejl ved omdøbning: ' + result.message);
        }
        
    } catch (error) {
        showError('Netværksfejl: ' + error.message);
    }
}

// Confirm delete
async function confirmDelete() {
    try {
        const formData = new FormData();
        formData.append('action', 'deleteDocument');
        formData.append('filename', currentDeleteFile);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let jsonText = responseText;
        
        const jsonStart = responseText.indexOf('===JSON_START===');
        const jsonEnd = responseText.indexOf('===JSON_END===');
        
        if (jsonStart !== -1 && jsonEnd !== -1) {
            jsonText = responseText.substring(jsonStart + 16, jsonEnd);
        }
        
        const result = JSON.parse(jsonText);
        
        if (result.success) {
            showSuccess(result.message);
            closeModals();
            loadDocuments();
            
            // Clear preview if the deleted file was selected
            if (selectedDocument && selectedDocument.filename === currentDeleteFile) {
                clearPreview();
            }
        } else {
            showError('Fejl ved sletning: ' + result.message);
        }
        
    } catch (error) {
        showError('Netværksfejl: ' + error.message);
    }
}

// Message functions
function showSuccess(message) {
    const successEl = document.getElementById('successMessage');
    successEl.textContent = message;
    successEl.style.display = 'block';
    setTimeout(() => {
        successEl.style.display = 'none';
    }, 5000);
    
    // Hide error message if showing
    document.getElementById('errorMessage').style.display = 'none';
}

function showError(message) {
    const errorEl = document.getElementById('errorMessage');
    errorEl.textContent = message;
    errorEl.style.display = 'block';
    setTimeout(() => {
        errorEl.style.display = 'none';
    }, 5000);
    
    // Hide success message if showing
    document.getElementById('successMessage').style.display = 'none';
}

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('mobileFullscreenModal');
        const backBtn = document.getElementById('mobileBackBtn');

        if (backBtn && modal) {
            backBtn.addEventListener('click', function () {
                modal.classList.remove('active');
            });
        }

        // Example: Function to show modal
        window.showMobileFullscreenModal = function (title = 'Dokument', content = '') {
            const titleEl = document.getElementById('mobileDocumentTitle');
            const contentEl = document.getElementById('mobileFullscreenContent');
            const modalEl = document.getElementById('mobileFullscreenModal');

            if (titleEl) titleEl.textContent = title;
            if (contentEl) contentEl.innerHTML = content;
            if (modalEl) modalEl.classList.add('active');
        };
    });
})();