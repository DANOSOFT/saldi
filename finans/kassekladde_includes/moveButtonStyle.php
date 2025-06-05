<?php
print "<style>
.position-controls {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
    white-space: nowrap;
    vertical-align: middle;
}

.move-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border: none;
    border-radius: 100px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    transition: all 0.2s ease;
    margin: 0 1px;
    vertical-align: middle;
}

.move-up {
    background: #4CAF50;
    color: white;
}

.move-up:hover {
    background: #45a049;
    transform: scale(1.1);
}

.move-down {
    background: #2196F3;
    color: white;
}

.move-down:hover {
    background: #1976D2;
    transform: scale(1.1);
}

.move-btn:active {
    transform: scale(0.9);
}

.move-btn-disabled {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    font-size: 12px;
    color: #ccc;
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    margin: 0 1px;
    vertical-align: middle;
}

.position-number {
    display: inline-block;
    min-width: 20px;
    text-align: center;
    font-weight: bold;
    font-size: 12px;
    color: #333;
    margin: 0 2px;
    vertical-align: middle;
}

td.position-cell {
    width: 80px;
    min-width: 80px;
    max-width: 80px;
    text-align: center;
    vertical-align: middle;
    padding: 2px;
}

.drag-handle {
    opacity: 0;
    transition: opacity 0.2s, color 0.2s ease;
    pointer-events: none;
    color: #4A90E2;
}

/* Show drag handle on row hover or while dragging */
tr:hover .drag-handle,
tr.dragging .drag-handle {
    opacity: 1;
    pointer-events: auto;
}

tr:hover .drag-handle:hover {
    color: #3A7BC8;
}

tr.dragging .drag-handle {
    cursor: grabbing;
    color: #3A7BC8;
}
</style>";