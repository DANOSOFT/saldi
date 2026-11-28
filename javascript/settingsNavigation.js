// 20261110 CDX/ASR Remember compact settings navigation and fold unrelated groups in the role workspace.
(function () {
    'use strict';
    document.querySelectorAll('.st-layout > .st-menu').forEach(function (menu) {
        if (menu.dataset.navigationReady) { return; }
        menu.dataset.navigationReady = '1';
        var layout = menu.parentElement;
        var button = menu.querySelector('.st-menu-toggle');
        var roleWorkspace = !!layout.querySelector('#ur-role-form');
        var key = 'saldi.settingsMenu.' + location.pathname.split('/').slice(0, -2).join('/');
        var preference = null;
        try { preference = localStorage.getItem(key); } catch (e) { /* storage is optional */ }
        function update() {
            var compact = preference === 'compact' || (preference === null && roleWorkspace && layout.clientWidth < 1160);
            layout.classList.toggle('is-menu-compact', compact);
            button.hidden = false;
            button.setAttribute('aria-expanded', String(!compact));
            button.textContent = compact ? button.dataset.open : button.dataset.close;
        }
        button.addEventListener('click', function () {
            preference = layout.classList.contains('is-menu-compact') ? 'expanded' : 'compact';
            try { localStorage.setItem(key, preference); } catch (e) { /* storage is optional */ }
            update();
        });
        if (roleWorkspace) {
            menu.querySelectorAll('.st-menu-sub:not(.is-off)').forEach(function (group) {
                var heading = group.querySelector('.st-menu-subh');
                var list = group.querySelector('.st-menu-rows');
                if (!heading || !list) { return; }
                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'st-menu-group-toggle';
                while (heading.firstChild) { toggle.appendChild(heading.firstChild); }
                heading.appendChild(toggle);
                list.id = heading.id + '-rows';
                toggle.setAttribute('aria-controls', list.id);
                function fold(open) {
                    toggle.setAttribute('aria-expanded', String(open));
                    list.hidden = !open;
                }
                fold(group.classList.contains('is-current'));
                toggle.addEventListener('click', function () { fold(list.hidden); });
            });
        }
        update();
        if (window.ResizeObserver) { new ResizeObserver(update).observe(layout); }
    });
}());
