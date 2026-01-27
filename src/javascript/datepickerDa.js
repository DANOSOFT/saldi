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
            // Handle 2-digit year
            if (year < 100) {
                year += year < 50 ? 2000 : 1900;
            }
            return new Date(year, month, day);
        }
        return null;
    }

    function formatDate(date) {
        if (!date) return '';
        var day = ('0' + date.getDate()).slice(-2);
        var month = ('0' + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear(); // Use 4-digit year to match existing format
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

    function generateCalendarHTML(viewDate, $input) {
        updateLanguage();
        var trans = getTrans();
        
        var year = viewDate.getFullYear();
        var month = viewDate.getMonth();
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var selectedDate = parseDate($input.val());
        
        var html = '<div class="dp-header">';
        html += '<button type="button" class="dp-prev" data-year="' + year + '" data-month="' + month + '">&#8249;</button>';
        html += '<span class="dp-title">' + monthNames[month] + ' ' + year + '</span>';
        html += '<button type="button" class="dp-next" data-year="' + year + '" data-month="' + month + '">&#8250;</button>';
        html += '</div>';

        html += '<table class="dp-calendar">';
        html += '<thead><tr><th class="dp-week">' + (trans.week || 'Week') + '</th>';
        
        for (var i = 0; i < 7; i++) {
            var dayIndex = (i + 1) % 7;
            html += '<th>' + dayNamesMin[dayIndex] + '</th>';
        }
        html += '</tr></thead>';
        html += '<tbody>';

        var daysInMonth = getDaysInMonth(year, month);
        var firstDay = getFirstDayOfMonth(year, month);
        firstDay = (firstDay + 6) % 7;

        var day = 1;
        var daysInPrevMonth = getDaysInMonth(year, month - 1);
        
        for (var row = 0; row < 6; row++) {
            var weekDate = new Date(year, month, day > daysInMonth ? daysInMonth : (day <= 0 ? 1 : day));
            html += '<tr><td class="dp-week">' + getWeekNumber(weekDate) + '</td>';
            
            for (var col = 0; col < 7; col++) {
                var cellDay, cellMonth = month, cellYear = year;
                var classes = ['dp-day'];
                
                if (row === 0 && col < firstDay) {
                    // Previous month days
                    cellDay = daysInPrevMonth - (firstDay - col - 1);
                    cellMonth = month - 1;
                    if (cellMonth < 0) { cellMonth = 11; cellYear--; }
                    classes.push('dp-other-month');
                } else if (day > daysInMonth) {
                    // Next month days
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

                if (cellDate.getTime() === today.getTime()) {
                    classes.push('dp-today');
                }
                if (selectedDate && cellDate.getTime() === selectedDate.getTime()) {
                    classes.push('dp-selected');
                }
                // Weekend (Sat = 6, Sun = 0)
                var dayOfWeek = cellDate.getDay();
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    classes.push('dp-weekend');
                }

                html += '<td data-date="' + cellDate.getTime() + '"><span class="' + classes.join(' ') + '">' + cellDay + '</span></td>';
            }
            html += '</tr>';
            
            if (day > daysInMonth && row >= 3) break;
        }

        html += '</tbody></table>';
        
        html += '<div class="dp-footer">';
        html += '<button type="button" class="dp-today-btn">' + (trans.today || 'Today') + '</button>';
        html += '<button type="button" class="dp-close-btn">' + (trans.close || 'Close') + '</button>';
        html += '</div>';

        return html;
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

        // Default position: below the input
        var top = offset.top + inputHeight + 4;
        var left = offset.left;

        // Check if picker would go below viewport - show above input instead
        if (top + pickerHeight > scrollTop + windowHeight) {
            top = offset.top - pickerHeight - 4;
        }
        
        // Make sure it doesn't go above the viewport
        if (top < scrollTop + 10) {
            top = scrollTop + 10;
        }

        // Check if picker would go off right edge
        if (left + pickerWidth > scrollLeft + windowWidth) {
            left = scrollLeft + windowWidth - pickerWidth - 10;
        }
        
        // Make sure it doesn't go off left edge
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
        closePicker();

        var $picker = createPicker($input);
        var viewDate = parseDate($input.val()) || new Date();
        
        // Update picker with correct date view
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

        // Event handlers for the picker
        $picker.on('click', '.dp-prev', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var year = parseInt($(this).data('year'), 10);
            var month = parseInt($(this).data('month'), 10) - 1;
            if (month < 0) { month = 11; year--; }
            currentPicker.viewDate = new Date(year, month, 1);
            $picker.html(generateCalendarHTML(currentPicker.viewDate, $input));
        });

        $picker.on('click', '.dp-next', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var year = parseInt($(this).data('year'), 10);
            var month = parseInt($(this).data('month'), 10) + 1;
            if (month > 11) { month = 0; year++; }
            currentPicker.viewDate = new Date(year, month, 1);
            $picker.html(generateCalendarHTML(currentPicker.viewDate, $input));
        });

        $picker.on('click', '.dp-day:not(.dp-other-month)', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $day = $(this);
            var $td = $day.closest('td');
            var timestamp = parseInt($td.data('date'), 10);
            var date = new Date(timestamp);
            $input.val(formatDate(date));
            $input.trigger('change');
            closePicker();
        });

        $picker.on('click', '.dp-today-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var today = new Date();
            $input.val(formatDate(today));
            $input.trigger('change');
            closePicker();
        });

        $picker.on('click', '.dp-close-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closePicker();
        });

        // Prevent clicks inside picker from closing it
        $picker.on('mousedown', function(e) {
            e.preventDefault();
        });
    }

    // jQuery plugin
    $.fn.datepickerDa = function(options) {
        return this.each(function() {
            var $input = $(this);
            
            if ($input.data('datepickerDa')) return;
            $input.data('datepickerDa', true);

            $input.on('focus', function() {
                openPicker($input);
            });

            $input.on('keydown', function(e) {
                if (e.keyCode === 27) { // Escape
                    closePicker();
                }
            });
        });
    };

    // Close picker when clicking outside
    $(document).on('mousedown', function(e) {
        if (currentPicker) {
            var $target = $(e.target);
            if (!$target.closest('.dp-picker').length && !$target.is(currentPicker.$input)) {
                closePicker();
            }
        }
    });

    // Close picker on scroll
    $(window).on('scroll', function() {
        if (currentPicker) {
            positionPicker(currentPicker.$picker, currentPicker.$input);
        }
    });

})(jQuery);
