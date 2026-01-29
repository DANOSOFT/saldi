<?php
print <<<HTML
<style>
.hover-highlight:hover {
  outline: 2px solid #b2b2b2;
  background-color: #f9f9f9;
  cursor: pointer;
}

/* Hover preview styles */
.hover-preview {
  position: relative;
  cursor: pointer;
}

.hover-preview-tooltip {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 600px;
  height: 400px;
  background: white;
  border: 2px solid #ccc;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  z-index: 1000;
  display: none;
  overflow: hidden;
}

.hover-preview-tooltip iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.hover-preview-tooltip .loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  font-size: 14px;
  color: #666;
}

.hover-preview-tooltip .close-btn {
  position: absolute;
  top: 5px;
  right: 10px;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 50%;
  width: 25px;
  height: 25px;
  cursor: pointer;
  font-size: 14px;
  z-index: 1001;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('table').forEach(table => {
    table.querySelectorAll('tr').forEach(row => {
      const tds = row.querySelectorAll('td');
      if (tds.length <= 1) return;

      if (row.offsetParent === null) return;

      let skip = false;
      for (let td of tds) {
        if (
          td.querySelector('button,b, select, input, textarea, i, img')
        ) {
          skip = true;
          break;
        }
      }

      if (!skip) row.classList.add('hover-highlight');
    });
  });
});

// Hover preview functionality
function initHoverPreview() {
  document.querySelectorAll('.hover-preview').forEach(element => {
    let hoverTimeout;
    let tooltip = null;
    
    element.addEventListener('mouseenter', function(e) {
      const url = this.getAttribute('data-preview-url');
      if (!url) return;
      
      hoverTimeout = setTimeout(() => {
        showPreview(this, url);
      }, 500); // 500ms delay before showing preview
    });
    
    element.addEventListener('mouseleave', function(e) {
      clearTimeout(hoverTimeout);
      if (tooltip) {
        hidePreview();
      }
    });
    
    function showPreview(element, url) {
      // Create tooltip if it doesn't exist
      if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.className = 'hover-preview-tooltip';
        
        const closeBtn = document.createElement('button');
        closeBtn.className = 'close-btn';
        closeBtn.innerHTML = '×';
        closeBtn.onclick = hidePreview;
        
        const loading = document.createElement('div');
        loading.className = 'loading';
        loading.innerHTML = 'Loading preview...';
        
        const iframe = document.createElement('iframe');
        
        tooltip.appendChild(closeBtn);
        tooltip.appendChild(loading);
        tooltip.appendChild(iframe);
        
        document.body.appendChild(tooltip);
      }
      
      // Show loading indicator
      const loading = tooltip.querySelector('.loading');
      const iframe = tooltip.querySelector('iframe');
      
      loading.style.display = 'flex';
      tooltip.style.display = 'block';
      
      // Set iframe src and handle loading
      iframe.src = url;
      
      iframe.onload = function() {
        loading.style.display = 'none';
      };
      
      // Reset onload handler for subsequent loads
      iframe.onload = function() {
        loading.style.display = 'none';
      };
    }
    
    function hidePreview() {
      if (tooltip) {
        tooltip.style.display = 'none';
        // Don't clear iframe src to maintain content for next hover
      }
    }
  });
}

// Initialize hover preview when DOM is ready
document.addEventListener('DOMContentLoaded', initHoverPreview);
</script>
HTML;
?>
