<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=1024">
    <title>Visual Form Editor - Database Integration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            background: #f5f5f5; 
            font-family: Arial, sans-serif;
        }
        
        .toolbar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            padding: 15px; 
            z-index: 1000; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .toolbar h1 {
            color: white;
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }
        
        .form-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .form-selector select {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            background: white;
            font-size: 14px;
        }
        
        .button {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .button.blue {
            background: #4CAF50;
            color: white;
        }
        
        .button.blue:hover {
            background: #45a049;
            transform: translateY(-1px);
        }
        
        .button.gray {
            background: #6c757d;
            color: white;
        }
        
        .button.gray:hover {
            background: #5a6268;
        }
        
        .main-container {
            display: flex;
            margin-top: 80px;
            height: calc(100vh - 80px);
        }
        
        .canvas-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            overflow: auto;
            background: #f0f0f0;
        }
        
        .form-canvas {
            position: relative;
            width: 595px;  /* A4 width in points */
            height: 842px; /* A4 height in points */
            background: white;
            transform-origin: top left;
            margin: 20px;
            border: 1px solid #ddd;
            overflow: hidden; /* Prevent elements from going outside */
        }
        
        .form-canvas::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 40px;
            right: 40px;
            bottom: 40px;
            border: 1px dashed rgba(0,0,0,0.1);
            pointer-events: none;
        }
        
        .ruler-mark {
            position: absolute;
            font-size: 10px;
            color: #999;
            pointer-events: none;
        }
        
        .form-element {
            position: absolute;
            transform-origin: left top;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            pointer-events: all;
        }
        
        .form-line { 
            background: black; 
            min-width: 1px; 
            min-height: 1px; 
        }
        
        .form-text {
            white-space: nowrap;
            line-height: 1;
        }
        
        .form-element:hover {
            border: 1px dashed #007bff;
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        .form-element.selected { 
            border: 2px solid #007bff !important;
            background-color: rgba(0, 123, 255, 0.1);
        }
        
        .form-element.ui-draggable-dragging {
            border: 2px solid #28a745 !important;
            background-color: rgba(40, 167, 69, 0.1);
            z-index: 1000;
        }
        
        .properties { 
            width: 320px; 
            background: white; 
            padding: 20px; 
            overflow-y: auto; 
            border-left: 1px solid #ddd;
            box-shadow: -2px 0 10px rgba(0,0,0,0.05);
        }
        
        .properties h3 {
            margin-top: 0;
            color: #333;
            font-size: 18px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-text.fed { font-weight: bold; }
        .form-text.kursiv { font-style: italic; }
        .form-text.helvetica { font-family: Helvetica, Arial, sans-serif; }
        .form-text.times { font-family: Times, serif; }
        .form-text.ocrbb12 { font-family: 'OCR-B', monospace; }
        .form-text.just-v { text-align: left; }
        .form-text.just-c { text-align: center; }
        .form-text.just-h { text-align: right; }
        
        .property-group {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .property-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .inputbox {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .inputbox:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        .no-selection {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px 20px;
        }
        
        .coordinates {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .coordinates .inputbox {
            width: calc(50% - 5px);
        }
        
        .element-counter {
            background: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: auto;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .zoom-control {
            position: fixed;
            top: 90px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1001;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <script>
// Update the constants to match PDF generation - based on typical A4 measurements
const A4_WIDTH = 595;  // A4 width in points
const A4_HEIGHT = 842; // A4 height in points
const MM_TO_POINTS = 2.834645669; // More precise conversion: 72/25.4

// Get URL parameters
function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// Fixed coordinate transformation based on typical PostScript/PDF coordinate system
function transformPdfToScreen(x, y) {
    const screenX = x * MM_TO_POINTS; // Convert mm to points/pixels
    const screenY = A4_HEIGHT - (y * MM_TO_POINTS); // Flip Y and convert
    
    return {
        x: Math.round(screenX),
        y: Math.round(screenY)
    };
}

function transformScreenToPdf(x, y) {
    const pdfX = x / MM_TO_POINTS; // Convert points/pixels to mm
    const pdfY = (A4_HEIGHT - y) / MM_TO_POINTS; // Flip Y and convert
    
    return {
        x: Math.round(pdfX * 10) / 10, // Round to 1 decimal place
        y: Math.round(pdfY * 10) / 10
    };
}

// Alternative transformation if coordinates are stored as points
function transformPdfToScreenPoints(x, y) {
    const screenX = x;
    const screenY = A4_HEIGHT - y; // Just flip Y coordinate
    
    return {
        x: Math.round(screenX),
        y: Math.round(screenY)
    };
}

function transformScreenToPdfPoints(x, y) {
    return {
        x: Math.round(x),
        y: Math.round(A4_HEIGHT - y)
    };
}

// Add accurate grid based on actual PDF measurements
function addPositioningGrid() {
    const canvas = $('.form-canvas');
    
    // Add grid every 10mm (28.6 points)
    const gridSize = 10 * MM_TO_POINTS; // 28.6px for 10mm
    
    canvas.css({
        'background-image': `
            linear-gradient(rgba(0, 0, 0, 0.1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0, 0, 0, 0.1) 1px, transparent 1px)
        `,
        'background-size': `${gridSize}px ${gridSize}px`
    });
    
    // Add rulers every 50 points (approximately 17.5mm)
    for (let x = 0; x <= A4_WIDTH; x += 50) {
        const mark = $(`<div class="ruler-mark" style="left: ${x}px; top: 0px; z-index: 100; background: rgba(255,255,255,0.8); padding: 1px 2px; font-size: 9px;">${x}</div>`);
        canvas.append(mark);
    }
    
    // Y-axis ruler marks
    for (let y = 0; y <= A4_HEIGHT; y += 50) {
        const screenY = transformPdfToScreen(0, y).y;
        const mark = $(`<div class="ruler-mark" style="left: 0px; top: ${screenY}px; z-index: 100; background: rgba(255,255,255,0.8); padding: 1px 2px; font-size: 9px;">${y}</div>`);
        canvas.append(mark);
    }
    
    // Update coordinate display
    canvas.on('mousemove', function(e) {
        const rect = canvas[0].getBoundingClientRect();
        const x = Math.round(e.clientX - rect.left);
        const y = Math.round(e.clientY - rect.top);
        
        const pdfCoords = transformScreenToPdf(x, y);
        const mmX = (pdfCoords.x / MM_TO_POINTS).toFixed(1);
        const mmY = (pdfCoords.y / MM_TO_POINTS).toFixed(1);
        
        $('#coords-display').text(`PDF: ${pdfCoords.x}pt, ${pdfCoords.y}pt (${mmX}mm, ${mmY}mm)`);
    });
    
    // Add coordinate display if it doesn't exist
    if ($('#coords-display').length === 0) {
        $('body').append('<div id="coords-display" style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 5px 10px; border-radius: 3px; font-size: 11px; font-family: monospace; z-index: 1001;"></div>');
    }
}

// Load form data from database and add debugging
function loadFormData(formNr, language) {
    console.log('Loading form data for:', formNr, language);
    $('#canvas').html('<div class="loading">Loading form data...</div>');
    
    $.ajax({
        url: 'load_form_data.php',
        type: 'POST',
        data: {
            formular: formNr,
            sprog: language
        },
        dataType: 'json',
        success: function(response) {
            console.log('Raw response data:', response); // Debug log
            
            if (response.success) {
                $('#canvas').empty();
                
                // Add elements to canvas
                response.data.forEach(function(elementData, index) {
                    console.log('Processing element', index, ':', elementData); // Debug log
                    
                    const element = createElementFromData(elementData);
                    if (element) {
                        $('#canvas').append(element);
                    }
                });
                
                // Initialize draggable functionality
                initializeDraggable();
                
                // Update element counter
                $('.element-counter').text(response.data.length + ' elements');
                
                console.log('Form loaded successfully:', response.form_name, response.data.length, 'elements');
            } else {
                $('#canvas').html('<div class="error">Error loading form: ' + response.error + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', xhr.responseText); // Debug log
            $('#canvas').html('<div class="error">Error loading form data: ' + error + '</div>');
            console.error('Load form error:', error);
        }
    });
}

// Improved element creation with debugging
function createElementFromData(data) {
    console.log('Creating element from data:', data); // Debug log
    
    let element;
    
    // Parse coordinates
    const xa = parseFloat(data.xa) || 0;
    const ya = parseFloat(data.ya) || 0;
    
    console.log('Original coordinates:', xa, ya); // Debug log
    
    // Try both coordinate transformation methods and log results
    const posMethod1 = transformPdfToScreen(xa, ya);
    const posMethod2 = transformPdfToScreenPoints(xa, ya);
    
    console.log('Method 1 (mm->points):', posMethod1);
    console.log('Method 2 (points):', posMethod2);
    
    // Use method 1 for now (assuming coordinates are in mm)
    const pos = window.usePointsDirectly ? posMethod2 : posMethod1;
    
    if (data.art == 2) { // Text element
        const fontClass = (data.font && data.font.toLowerCase()) || 'helvetica';
        const justClass = 'just-' + ((data.justering && data.justering.toLowerCase()) || 'v');
        const boldClass = (data.fed === 'on') ? 'fed' : '';
        const italicClass = (data.kursiv === 'on') ? 'kursiv' : '';
        const fontSize = data.str || 12;
        
        element = $(`<div class="form-element form-text ${fontClass} ${justClass} ${boldClass} ${italicClass}" 
            data-id="${data.id}" 
            data-art="${data.art}"
            data-xa="${xa}"
            data-ya="${ya}"
            data-str="${fontSize}"
            style="left: ${pos.x}px; top: ${pos.y}px; font-size: ${fontSize}pt; line-height: 1; border: 1px dashed red;">
            ${data.beskrivelse || 'Text'}
        </div>`);
        
        console.log('Created text element at screen position:', pos.x, pos.y);

    } else if (data.art == 1) { // Line element
        const xb = parseFloat(data.xb) || xa;
        const yb = parseFloat(data.yb) || ya;
        const lineWidth = data.str || 1;
        
        const startPos = window.usePointsDirectly ? transformPdfToScreenPoints(xa, ya) : transformPdfToScreen(xa, ya);
        const endPos = window.usePointsDirectly ? transformPdfToScreenPoints(xb, yb) : transformPdfToScreen(xb, yb);
        
        console.log('Line from', startPos, 'to', endPos);
        
        const deltaX = endPos.x - startPos.x;
        const deltaY = endPos.y - startPos.y;
        const length = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        
        let width, height, left, top, transform = '';
        
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            // Horizontal line
            width = Math.abs(deltaX);
            height = lineWidth;
            left = Math.min(startPos.x, endPos.x);
            top = Math.min(startPos.y, endPos.y) - Math.floor(lineWidth / 2);
        } else {
            // Vertical line
            width = lineWidth;
            height = Math.abs(deltaY);
            left = Math.min(startPos.x, endPos.x) - Math.floor(lineWidth / 2);
            top = Math.min(startPos.y, endPos.y);
        }
        
        // Diagonal lines
        if (Math.abs(deltaX) > 1 && Math.abs(deltaY) > 1) {
            const angle = Math.atan2(deltaY, deltaX) * 180 / Math.PI;
            width = length;
            height = lineWidth;
            left = startPos.x;
            top = startPos.y - Math.floor(lineWidth / 2);
            transform = `rotate(${angle}deg)`;
        }
        
        element = $(`<div class="form-element form-line"
            data-id="${data.id}"
            data-art="${data.art}"
            data-xa="${xa}"
            data-ya="${ya}"
            data-xb="${xb}"
            data-yb="${yb}"
            data-str="${lineWidth}"
            style="left: ${left}px; top: ${top}px; width: ${width}px; height: ${height}px; transform: ${transform}; transform-origin: left center; border: 1px dashed blue;">
        </div>`);
        
        console.log('Created line element at position:', left, top, 'size:', width, height);
    }

    return element;
}

// Add coordinate system toggle for debugging
function toggleCoordinateSystem() {
    window.usePointsDirectly = !window.usePointsDirectly;
    console.log('Switched to coordinate system:', window.usePointsDirectly ? 'Points directly' : 'MM to Points');
    
    // Reload current form to test
    const formNr = $('#form-selector').val();
    const language = $('#language-selector').val();
    if (formNr) {
        loadFormData(formNr, language);
    }
}

// Initialize draggable functionality
function initializeDraggable() {
    $('.form-element').draggable({
        containment: ".form-canvas",
        cursor: "move",
        start: function(event, ui) {
            selectElement($(this));
        },
        stop: function(event, ui) {
            const element = $(this);
            const position = element.position();
            
            console.log('Element dragged to screen position:', position.left, position.top);
            
            if (element.data('art') == 1) { // Line element
                const oldXa = parseFloat(element.data('xa'));
                const oldYa = parseFloat(element.data('ya'));
                const oldXb = parseFloat(element.data('xb'));
                const oldYb = parseFloat(element.data('yb'));
                
                // Calculate how far the line was moved
                const oldScreenPos = window.usePointsDirectly ? 
                    transformPdfToScreenPoints(oldXa, oldYa) : 
                    transformPdfToScreen(oldXa, oldYa);
                
                const deltaX = position.left - oldScreenPos.x;
                const deltaY = position.top - oldScreenPos.y;
                
                console.log('Line moved by:', deltaX, deltaY);
                
                // Calculate new PDF coordinates for both points
                const newStartPdf = window.usePointsDirectly ? 
                    transformScreenToPdfPoints(position.left, position.top) : 
                    transformScreenToPdf(position.left, position.top);
                
                const oldEndScreenPos = window.usePointsDirectly ?
                    transformPdfToScreenPoints(oldXb, oldYb) :
                    transformPdfToScreen(oldXb, oldYb);
                    
                const newEndPdf = window.usePointsDirectly ?
                    transformScreenToPdfPoints(oldEndScreenPos.x + deltaX, oldEndScreenPos.y + deltaY) :
                    transformScreenToPdf(oldEndScreenPos.x + deltaX, oldEndScreenPos.y + deltaY);
                
                console.log('New line coordinates - Start:', newStartPdf, 'End:', newEndPdf);
                
                // Update element data attributes
                element.data('xa', newStartPdf.x);
                element.data('ya', newStartPdf.y);
                element.data('xb', newEndPdf.x);
                element.data('yb', newEndPdf.y);
                
                // Also update HTML attributes for debugging
                element.attr('data-xa', newStartPdf.x);
                element.attr('data-ya', newStartPdf.y);
                element.attr('data-xb', newEndPdf.x);
                element.attr('data-yb', newEndPdf.y);
                
                // Update property panel if this element is selected
                if (element.hasClass('selected')) {
                    $('#element-x').val(newStartPdf.x);
                    $('#element-y').val(newStartPdf.y);
                }
                
            } else { // Text element
                // Convert screen position back to PDF coordinates
                const pdfPos = window.usePointsDirectly ? 
                    transformScreenToPdfPoints(position.left, position.top) : 
                    transformScreenToPdf(position.left, position.top);
                
                console.log('New text coordinates:', pdfPos);
                
                // Update element data attributes
                element.data('xa', pdfPos.x);
                element.data('ya', pdfPos.y);
                
                // Also update HTML attributes for debugging
                element.attr('data-xa', pdfPos.x);
                element.attr('data-ya', pdfPos.y);
                
                // Update property panel if this element is selected
                if (element.hasClass('selected')) {
                    $('#element-x').val(pdfPos.x);
                    $('#element-y').val(pdfPos.y);
                }
            }
            
            // Visual feedback that element has been moved
            element.css('border', '2px solid green');
            setTimeout(function() {
                element.css('border', '');
            }, 1000);
            
            console.log('Updated element data:', {
                id: element.data('id'),
                xa: element.data('xa'),
                ya: element.data('ya'),
                xb: element.data('xb'),
                yb: element.data('yb')
            });
        }
    });
    
    // Make elements selectable with click
    $('.form-element').off('click').on('click', function(e) {
        e.stopPropagation();
        selectElement($(this));
    });
    
    // Clicking on empty canvas deselects everything
    $('.form-canvas').off('click').on('click', function() {
        deselectAllElements();
    });
}

function selectElement(element) {
    deselectAllElements();
    
    element.addClass('selected');
    
    const art = element.data('art');
    
    $('#text-properties, #line-properties').hide();
    $('#element-properties .no-selection').hide();
    
    $('#element-x').val(element.data('xa'));
    $('#element-y').val(element.data('ya'));
    
    if (art == 2) { // Text element
        $('#text-properties').show();
        $('#element-text').val(element.text().trim());
        $('#element-size').val(element.data('str'));
        $('#element-justification').val(element.hasClass('just-c') ? 'C' : 
                                 (element.hasClass('just-h') ? 'H' : 'V'));
        $('#element-font').val(element.hasClass('times') ? 'Times' : 'Helvetica');
        $('#element-fed').prop('checked', element.hasClass('fed'));
        $('#element-kursiv').prop('checked', element.hasClass('kursiv'));
    } else if (art == 1) { // Line element
        $('#line-properties').show();
        $('#line-width').val(element.data('str'));
    }
}

function deselectAllElements() {
    $('.form-element').removeClass('selected');
    $('#element-properties .no-selection').show();
    $('#text-properties, #line-properties').hide();
}

function updateElementProperties() {
    const selectedElement = $('.form-element.selected');
    if (selectedElement.length === 0) return;
    
    const art = selectedElement.data('art');
    
    const x = parseFloat($('#element-x').val());
    const y = parseFloat($('#element-y').val());
    
    if (!isNaN(x) && !isNaN(y)) {
        const screenPos = window.usePointsDirectly ? transformPdfToScreenPoints(x, y) : transformPdfToScreen(x, y);
        selectedElement.css({
            left: screenPos.x + 'px',
            top: screenPos.y + 'px'
        });
        
        selectedElement.data('xa', x);
        selectedElement.data('ya', y);
        selectedElement.attr('data-xa', x);
        selectedElement.attr('data-ya', y);
    }
    
    if (art == 2) { // Text element
        const text = $('#element-text').val();
        selectedElement.text(text);
        
        const fontSize = parseInt($('#element-size').val());
        if (!isNaN(fontSize)) {
            selectedElement.css('font-size', fontSize + 'pt');
            selectedElement.data('str', fontSize);
            selectedElement.attr('data-str', fontSize);
        }
        
        const justification = $('#element-justification').val();
        selectedElement.removeClass('just-v just-c just-h');
        selectedElement.addClass('just-' + justification.toLowerCase());
        
        const font = $('#element-font').val();
        selectedElement.removeClass('helvetica times ocrbb12');
        selectedElement.addClass(font.toLowerCase());
        
        const bold = $('#element-fed').is(':checked');
        const italic = $('#element-kursiv').is(':checked');
        
        selectedElement.toggleClass('fed', bold);
        selectedElement.toggleClass('kursiv', italic);
        
    } else if (art == 1) { // Line element
        const lineWidth = parseInt($('#line-width').val());
        if (!isNaN(lineWidth)) {
            selectedElement.data('str', lineWidth);
            selectedElement.attr('data-str', lineWidth);
            
            const xa = selectedElement.data('xa');
            const ya = selectedElement.data('ya');
            const xb = selectedElement.data('xb');
            const yb = selectedElement.data('yb');
            
            const startPos = window.usePointsDirectly ? transformPdfToScreenPoints(xa, ya) : transformPdfToScreen(xa, ya);
            const endPos = window.usePointsDirectly ? transformPdfToScreenPoints(xb, yb) : transformPdfToScreen(xb, yb);
            const deltaX = endPos.x - startPos.x;
            const deltaY = endPos.y - startPos.y;
            
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                selectedElement.css('height', lineWidth + 'px');
            } else {
                selectedElement.css('width', lineWidth + 'px');
            }
        }
    }
}

function saveFormChanges(formNr, language) {
    const elements = [];
    
    $('.form-element').each(function() {
        const element = $(this);
        
        const elementData = {
            id: parseInt(element.data('id')),
            art: parseInt(element.data('art')),
            formular: parseInt(formNr),
            sprog: language,
            xa: parseFloat(element.data('xa')) || 0,
            ya: parseFloat(element.data('ya')) || 0,
            str: parseInt(element.data('str')) || (element.data('art') == 2 ? 12 : 1)
        };
        
        console.log('Processing element for save:', elementData);
        
        if (element.data('art') == 2) { // Text element
            elementData.beskrivelse = element.text().trim();
            elementData.justering = element.hasClass('just-c') ? 'C' : 
                                (element.hasClass('just-h') ? 'H' : 'V');
            elementData.font = element.hasClass('times') ? 'Times' : 'Helvetica';
            elementData.fed = element.hasClass('fed') ? 'on' : '';
            elementData.kursiv = element.hasClass('kursiv') ? 'on' : '';
        } else if (element.data('art') == 1) { // Line element
            elementData.xb = parseFloat(element.data('xb')) || elementData.xa;
            elementData.yb = parseFloat(element.data('yb')) || elementData.ya;
        }
        
        elements.push(elementData);
    });
    
    console.log('Sending to server:', {
        formular: formNr,
        sprog: language,
        elements_count: elements.length,
        elements: elements
    });
    
    const saveButton = $('#save-form');
    const originalText = saveButton.text();
    saveButton.text('Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'save_form_data.php',
        type: 'POST',
        data: {
            formular: formNr,
            sprog: language,
            elements: JSON.stringify(elements)
        },
        dataType: 'json',
        success: function(response) {
            console.log('Save response:', response);
            
            saveButton.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert('Form saved successfully! Updated ' + response.updated + ' elements.');
                $('.form-element').css('border', '');
            } else {
                alert('Error saving form: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            console.log('Save error response:', xhr.responseText);
            
            saveButton.text(originalText).prop('disabled', false);
            alert('Error saving form: ' + error);
        }
    });
}

// Document ready function
$(document).ready(function() {
    console.log('Document ready - initializing editor');
    
    // Initialize form selector with URL parameters
    const formNr = getUrlParameter('form_nr');
    const sprog = getUrlParameter('sprog');
    
    console.log('URL parameters:', formNr, sprog);
    
    if (formNr) {
        $('#form-selector').val(formNr);
    }
    if (sprog) {
        $('#language-selector').val(sprog);
    }
    
    // Load form immediately if parameters are provided
    if (formNr && sprog) {
        console.log('Auto-loading form:', formNr, sprog);
        loadFormData(formNr, sprog);
    }
    
    addPositioningGrid();
    
    // Property panel event handlers
    $('#element-x, #element-y, #element-text, #element-size, #element-justification, #element-font, #line-width').on('change', function() {
        updateElementProperties();
    });
    
    $('#element-fed, #element-kursiv').on('change', function() {
        updateElementProperties();
    });
    
    // Load Form button
    $('#load-form').on('click', function() {
        const formNr = $('#form-selector').val();
        const language = $('#language-selector').val();
        
        console.log('Load button clicked:', formNr, language);
        
        if (!formNr) {
            alert('Please select a form first');
            return;
        }
        
        loadFormData(formNr, language);
    });
    
    // Save changes button
    $('#save-form').on('click', function() {
        const formNr = $('#form-selector').val();
        const language = $('#language-selector').val();
        
        if (!formNr) {
            alert('Please select a form first');
            return;
        }
        
        saveFormChanges(formNr, language);
    });
    
    // Zoom functionality
    let zoomLevel = 100;
    
    $('#zoom-in').on('click', function() {
        zoomLevel += 10;
        updateZoom();
    });
    
    $('#zoom-out').on('click', function() {
        if (zoomLevel > 50) {
            zoomLevel -= 10;
            updateZoom();
        }
    });
    
    function updateZoom() {
        $('#zoom-level').text(zoomLevel + '%');
        $('.form-canvas').css('transform', `scale(${zoomLevel/100})`);
    }
    
    console.log('Editor initialization complete');
});
</script>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
    <h1>Visual Form Editor</h1>
    
    <div class="form-selector">
        <label>Form:</label>
        <select id="form-selector">
            <option value="">Select Form</option>
            <option value="1">Tilbud</option>
            <option value="2">Ordrebekræftelse</option>
            <option value="3">Følgeseddel</option>
            <option value="4">Faktura</option>
            <option value="5">Kreditnota</option>
            <option value="6">Rykker_1</option>
            <option value="7">Rykker_2</option>
            <option value="8">Rykker_3</option>
            <option value="9">Plukliste</option>
        </select>
        
        <label>Language:</label>
        <select id="language-selector">
            <option value="Dansk">Dansk</option>
            <option value="English">English</option>
            <option value="Norsk">Norsk</option>
        </select>
    </div>
    
    <div class="element-counter">0 elements</div>
    
    <div style="margin-left: auto; display: flex; gap: 10px;">
        <button id="load-form" class="button blue">Load Form</button>
        <button id="save-form" class="button blue">Save</button>
        <a href="formularkort.php" class="button gray">Back</a>
    </div>
</div>

<!-- Zoom control -->
<div class="zoom-control">
    <button id="zoom-out" class="button gray">-</button>
    <span id="zoom-level">100%</span>
    <button id="zoom-in" class="button gray">+</button>
</div>

<div class="main-container">
    <!-- Main canvas -->
    <div class="canvas-container">
        <div class="form-canvas" id="canvas">
            <div class="loading">Select a form and click "Load Form" to begin editing</div>
        </div>
    </div>

    <!-- Properties panel -->
    <div class="properties">
        <h3>Element Properties</h3>
        
        <div id="element-properties">
            <div class="no-selection">
                Select an element to edit its properties
            </div>
            
            <!-- Common properties -->
            <div class="property-group">
                <label>Position</label>
                <div class="coordinates">
                    <input type="number" id="element-x" class="inputbox" placeholder="X">
                    <input type="number" id="element-y" class="inputbox" placeholder="Y">
                </div>
            </div>
            
            <!-- Text element properties -->
            <div id="text-properties" style="display: none;">
                <div class="property-group">
                    <label>Text</label>
                    <input type="text" id="element-text" class="inputbox" placeholder="Element text">
                </div>
                
                <div class="property-group">
                    <label>Font Size</label>
                    <input type="number" id="element-size" class="inputbox" placeholder="12">
                </div>
                
                <div class="property-group">
                    <label>Justification</label>
                    <select id="element-justification" class="inputbox">
                        <option value="V">Left</option>
                        <option value="C">Center</option>
                        <option value="H">Right</option>
                    </select>
                </div>
                
                <div class="property-group">
                    <label>Font</label>
                    <select id="element-font" class="inputbox">
                        <option value="Helvetica">Helvetica</option>
                        <option value="Times">Times</option>
                    </select>
                </div>
                
                <div class="property-group">
                    <label>
                        <input type="checkbox" id="element-fed"> Bold
                    </label>
                    <label>
                        <input type="checkbox" id="element-kursiv"> Italic
                    </label>
                </div>
            </div>
            
            <!-- Line element properties -->
            <div id="line-properties" style="display: none;">
                <div class="property-group">
                    <label>Line Width</label>
                    <input type="number" id="line-width" class="inputbox" placeholder="1">
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>