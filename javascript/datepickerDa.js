/**
 * Multilingual Datepicker - supports dd-mm-yyyy format
 * Uses window.saldiLanguage and window.saldiTranslations from PHP
 */

(function($) {
    'use strict';
    
    // Month and day names by language (these cannot come from findtekst)
    var monthsByLang = {
        1: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'December'],
        2: ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'],
        3: ['Januar', 'Februar', 'Mars', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Desember']
    };
    
    var daysByLang = {
        1: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
        2: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
        3: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø']
    };
    
    function getLangId() {
        return parseInt(window.saldiLanguage, 10) || 1;
    }
    
    function getTrans() {
        return window.saldiTranslations || {};
    }
   
    var monthNames, dayNamesMin;
    
    function updateLanguage() {
        var langId = getLangId();
        monthNames = monthsByLang[langId] || monthsByLang[1];
        dayNamesMin = daysByLang[langId] || daysByLang[1];
    }
    
    updateLanguage();

    var currentPicker = null;

    function parseDate(dateStr) {
        if (!dateStr) return null;
        var parts = dateStr.split('-');
        if (parts.length === 3) {
            var day = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1;
            var year = parseInt(parts[2], 10);
            if (isNaN(day) || isNaN(month) || isNaN(year)) return null;
            // Only accept 4-digit years to avoid jumping to wrong centuries
            // while user is still typing
            if (parts[2].length < 4) return null;
            return new Date(year, month, day);
        }
        return null;
    }

    function formatDate(date) {
        if (!date) return '';
        var day = ('0' + date.getDate()).slice(-2);
        var month = ('0' + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear();
        return day + '-' + month + '-' + year;
    }

    function getWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }

    function getDaysInMonth(year, month) {
        return new Date(year, month + 1, 0).getDate();
    }

    function getFirstDayOfMonth(year, month) {
        return new Date(year, month, 1).getDay();
    }

    function createPicker($input) {
        var $picker = $('<div class="dp-picker" style="visibility: hidden;"></div>');
        $('body').append($picker);
        return $picker;
    }

    /**
     * Build just the calendar body (the <table> with days) — used for
     * partial updates so the header selects are NOT destroyed.
     */
    function generateCalendarBodyHTML(year, month, $input) {
        updateLanguage();
        var trans = getTrans();
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var selectedDate = parseDate($input.val());

        var html = '<table class="dp-calendar">';
        html += '<thead><tr><th class="dp-week">' + (trans.week || 'Week') + '</th>';
        for (var i = 0; i < 7; i++) {
            var dayIndex = (i + 1) % 7;
            html += '<th>' + dayNamesMin[dayIndex] + '</th>';
        }
        html += '</tr></thead><tbody>';

        var daysInMonth = getDaysInMonth(year, month);
        var firstDay = (getFirstDayOfMonth(year, month) + 6) % 7;
        var day = 1;
        var daysInPrevMonth = getDaysInMonth(year, month - 1);

        for (var row = 0; row < 6; row++) {
            var weekDate = new Date(year, month, day > daysInMonth ? daysInMonth : (day <= 0 ? 1 : day));
            html += '<tr><td class="dp-week">' + getWeekNumber(weekDate) + '</td>';
            for (var col = 0; col < 7; col++) {
                var cellDay, cellMonth = month, cellYear = year;
                var classes = ['dp-day'];
                if (row === 0 && col < firstDay) {
                    cellDay = daysInPrevMonth - (firstDay - col - 1);
                    cellMonth = month - 1;
                    if (cellMonth < 0) { cellMonth = 11; cellYear--; }
                    classes.push('dp-other-month');
                } else if (day > daysInMonth) {
                    cellDay = day - daysInMonth;
                    cellMonth = month + 1;
                    if (cellMonth > 11) { cellMonth = 0; cellYear++; }
                    classes.push('dp-other-month');
                    day++;
                } else {
                    cellDay = day;
                    day++;
                }
                var cellDate = new Date(cellYear, cellMonth, cellDay);
                cellDate.setHours(0, 0, 0, 0);
                if (cellDate.getTime() === today.getTime()) classes.push('dp-today');
                if (selectedDate && cellDate.getTime() === selectedDate.getTime()) classes.push('dp-selected');
                var dow = cellDate.getDay();
                if (dow === 0 || dow === 6) classes.push('dp-weekend');
                html += '<td data-date="' + cellDate.getTime() + '"><span class="' + classes.join(' ') + '">' + cellDay + '</span></td>';
            }
            html += '</tr>';
            if (day > daysInMonth && row >= 3) break;
        }
        html += '</tbody></table>';
        return html;
    }

    /**
     * Build the full picker HTML (header + calendar + footer).
     * Only used once when the picker first opens.
     */
    function generateCalendarHTML(viewDate, $input) {
        updateLanguage();
        var trans = getTrans();
        var year = viewDate.getFullYear();
        var month = viewDate.getMonth();

        var html = '<div class="dp-header">';
        html += '<button type="button" class="dp-prev">&#8249;</button>';
        html += '<div class="dp-title-selectors">';
        // Month dropdown
        html += '<select class="dp-month-select">';
        for (var mi = 0; mi < 12; mi++) {
            html += '<option value="' + mi + '"' + (mi === month ? ' selected' : '') + '>' + monthNames[mi] + '</option>';
        }
        html += '</select>';
        // Year dropdown
        var currentYear = new Date().getFullYear();
        var minY = currentYear - 100;
        var maxY = currentYear + 10;
        if (year < minY) minY = year;
        if (year > maxY) maxY = year;
        html += '<select class="dp-year-select">';
        for (var yi = maxY; yi >= minY; yi--) {
            html += '<option value="' + yi + '"' + (yi === year ? ' selected' : '') + '>' + yi + '</option>';
        }
        html += '</select>';
        html += '</div>';
        html += '<button type="button" class="dp-next">&#8250;</button>';
        html += '</div>';

        html += '<div class="dp-body">';
        html += generateCalendarBodyHTML(year, month, $input);
        html += '</div>';

        html += '<div class="dp-footer">';
        html += '<button type="button" class="dp-today-btn">' + (trans.today || 'Today') + '</button>';
        html += '<button type="button" class="dp-close-btn">' + (trans.close || 'Close') + '</button>';
        html += '</div>';

        return html;
    }

    /**
     * Update just the calendar days and the header selects
     * without destroying the whole picker DOM.
     */
    function refreshCalendar() {
        if (!currentPicker) return;
        var year = currentPicker.viewDate.getFullYear();
        var month = currentPicker.viewDate.getMonth();
        var $picker = currentPicker.$picker;
        var $input = currentPicker.$input;

        // Update the calendar body only
        $picker.find('.dp-body').html(generateCalendarBodyHTML(year, month, $input));

        // Sync the header selects (without triggering change)
        $picker.find('.dp-month-select').val(month);
        $picker.find('.dp-year-select').val(year);

        // If the year is not in the dropdown, rebuild just the year select
        if ($picker.find('.dp-year-select').val() != year) {
            var currentYear = new Date().getFullYear();
            var minY = currentYear - 100;
            var maxY = currentYear + 10;
            if (year < minY) minY = year;
            if (year > maxY) maxY = year;
            var opts = '';
            for (var yi = maxY; yi >= minY; yi--) {
                opts += '<option value="' + yi + '"' + (yi === year ? ' selected' : '') + '>' + yi + '</option>';
            }
            $picker.find('.dp-year-select').html(opts);
        }
    }

    function positionPicker($picker, $input) {
        var offset = $input.offset();
        var inputHeight = $input.outerHeight() || 20;
        var pickerHeight = $picker.outerHeight() || 300;
        var pickerWidth = $picker.outerWidth() || 300;
        var windowHeight = $(window).height();
        var windowWidth = $(window).width();
        var scrollTop = $(window).scrollTop();
        var scrollLeft = $(window).scrollLeft();

        var top = offset.top + inputHeight + 4;
        var left = offset.left;

        if (top + pickerHeight > scrollTop + windowHeight) {
            top = offset.top - pickerHeight - 4;
        }
        if (top < scrollTop + 10) {
            top = scrollTop + 10;
        }
        if (left + pickerWidth > scrollLeft + windowWidth) {
            left = scrollLeft + windowWidth - pickerWidth - 10;
        }
        if (left < scrollLeft + 10) {
            left = scrollLeft + 10;
        }

        $picker.css({
            top: Math.max(10, top) + 'px',
            left: Math.max(10, left) + 'px',
            position: 'absolute'
        });
    }

    function closePicker() {
        if (currentPicker) {
            currentPicker.$picker.remove();
            currentPicker = null;
        }
    }

    function openPicker($input) {
        // Don't re-open if already open for this input
        if (currentPicker && currentPicker.$input[0] === $input[0]) {
            return;
        }
        closePicker();

        var $picker = createPicker($input);
        var viewDate = parseDate($input.val()) || new Date();
        
        $picker.html(generateCalendarHTML(viewDate, $input));
        
        currentPicker = {
            $input: $input,
            $picker: $picker,
            viewDate: viewDate
        };

        // Position after DOM is updated, then make visible
        setTimeout(function() {
            positionPicker($picker, $input);
            $picker.css('visibility', 'visible');
        }, 20);

        // --- Navigation buttons ---
        $picker.on('click', '.dp-prev', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var m = currentPicker.viewDate.getMonth() - 1;
            var y = currentPicker.viewDate.getFullYear();
            if (m < 0) { m = 11; y--; }
            currentPicker.viewDate = new Date(y, m, 1);
            refreshCalendar();
        });

        $picker.on('click', '.dp-next', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var m = currentPicker.viewDate.getMonth() + 1;
            var y = currentPicker.viewDate.getFullYear();
            if (m > 11) { m = 0; y++; }
            currentPicker.viewDate = new Date(y, m, 1);
            refreshCalendar();
        });

        // --- Month / Year selects ---
        $picker.on('change', '.dp-month-select', function(e) {
            e.stopPropagation();
            var month = parseInt($(this).val(), 10);
            var year = currentPicker.viewDate.getFullYear();
            currentPicker.viewDate = new Date(year, month, 1);
            // Only refresh the calendar body, leave selects alone
            currentPicker.$picker.find('.dp-body').html(
                generateCalendarBodyHTML(year, month, currentPicker.$input)
            );
        });

        $picker.on('change', '.dp-year-select', function(e) {
            e.stopPropagation();
            var year = parseInt($(this).val(), 10);
            var month = currentPicker.viewDate.getMonth();
            currentPicker.viewDate = new Date(year, month, 1);
            // Only refresh the calendar body, leave selects alone
            currentPicker.$picker.find('.dp-body').html(
                generateCalendarBodyHTML(year, month, currentPicker.$input)
            );
        });

        // --- Day click ---
        $picker.on('click', '.dp-day:not(.dp-other-month)', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var timestamp = parseInt($(this).closest('td').data('date'), 10);
            var date = new Date(timestamp);
            $input.val(formatDate(date));
            $input.trigger('change');
            closePicker();
        });

        // --- Footer buttons ---
        $picker.on('click', '.dp-today-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $input.val(formatDate(new Date()));
            $input.trigger('change');
            closePicker();
        });

        $picker.on('click', '.dp-close-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closePicker();
        });

        // Prevent clicks inside picker from stealing focus from the input.
        // Exception: allow select elements to receive focus so their
        // native dropdown works properly.
        $picker.on('mousedown', function(e) {
            if (!$(e.target).is('select')) {
                e.preventDefault();
            }
        });
    }

    // ----------------------------------------------------------------
    // jQuery plugin
    // ----------------------------------------------------------------
    $.fn.datepickerDa = function(options) {
        return this.each(function() {
            var $input = $(this);
            
            if ($input.data('datepickerDa')) return;
            $input.data('datepickerDa', true);

            var typingTimer = null;

            $input.on('focus', function() {
                openPicker($input);
            });

            // While the user types, update the calendar view —
            // but ONLY when the typed value is a complete valid date.
            // The debounce (600 ms) avoids flickering on fast typing.
            $input.on('input', function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function() {
                    if (!currentPicker || currentPicker.$input[0] !== $input[0]) return;
                    var typed = parseDate($input.val());
                    if (typed) {
                        currentPicker.viewDate = new Date(typed.getFullYear(), typed.getMonth(), 1);
                        refreshCalendar();
                    }
                }, 600);
            });

            $input.on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape — close without saving
                    closePicker();
                }
                // Enter or Tab — close picker, let the normal form behaviour proceed
                if (e.keyCode === 13 || e.keyCode === 9) {
                    closePicker();
                }
            });
        });
    };

    // Close picker when clicking outside both the picker and its input
    $(document).on('mousedown', function(e) {
        if (currentPicker) {
            var $target = $(e.target);
            if (!$target.closest('.dp-picker').length && !$target.is(currentPicker.$input)) {
                closePicker();
            }
        }
    });

    // Re-position picker on scroll (don't close it)
    $(window).on('scroll', function() {
        if (currentPicker) {
            positionPicker(currentPicker.$picker, currentPicker.$input);
        }
    });

})(jQuery);
