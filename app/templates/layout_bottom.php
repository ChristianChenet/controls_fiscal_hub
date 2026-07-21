        <footer class="site-footer">
            CONTROL S CONSULTORIA — Direitos Reservados | CNPJ: 21.421.411/0001-20
        </footer>
    </main>
</div>
<script>
(function () {
    var root = document.documentElement;
    var key = 'controls.sidebar.collapsed';
    function syncSidebarLabels() {
        document.querySelectorAll('[data-sidebar-toggle] .toggle-label').forEach(function (label) {
            label.textContent = root.classList.contains('sidebar-collapsed') ? 'Expandir menu' : 'Recolher menu';
        });
    }
    if (localStorage.getItem(key) === '1') {
        root.classList.add('sidebar-collapsed');
    }
    syncSidebarLabels();
    var pageHeader = document.querySelector('.content > .page-header');
    var topbarModule = document.querySelector('[data-topbar-module]');
    if (pageHeader && topbarModule && !topbarModule.querySelector('.topbar-title')) {
        topbarModule.innerHTML = pageHeader.innerHTML;
        pageHeader.remove();
        topbarModule.classList.add('has-page-title');
    }
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            root.classList.toggle('sidebar-collapsed');
            localStorage.setItem(key, root.classList.contains('sidebar-collapsed') ? '1' : '0');
            syncSidebarLabels();
        });
    });
    document.querySelectorAll('[data-collapse-target]').forEach(function (button) {
        var target = document.querySelector(button.getAttribute('data-collapse-target'));
        if (!target) return;
        var collapseKey = button.getAttribute('data-collapse-key');
        function syncLabel() {
            var collapsed = target.classList.contains('is-collapsed');
            button.textContent = collapsed ? (button.getAttribute('data-show-label') || 'Mostrar') : (button.getAttribute('data-hide-label') || 'Recolher');
        }
        if (collapseKey) {
            var savedCollapse = localStorage.getItem(collapseKey);
            if (savedCollapse === '1') {
                target.classList.add('is-collapsed');
            } else if (savedCollapse === '0') {
                target.classList.remove('is-collapsed');
            }
        }
        syncLabel();
        button.addEventListener('click', function () {
            var collapsed = target.classList.toggle('is-collapsed');
            if (collapseKey) {
                localStorage.setItem(collapseKey, collapsed ? '1' : '0');
            }
            syncLabel();
        });
    });
    document.querySelectorAll('[data-multi-open]').forEach(function (button) {
        var id = button.getAttribute('data-multi-open');
        var modal = document.querySelector('[data-multi-modal="' + id + '"]');
        var field = button.closest('.compact-multi-field');
        var hidden = document.querySelector('[data-multi-hidden="' + id + '"]');
        if (!modal || !field || !hidden) return;
        var caption = button.querySelector('[data-multi-caption]');
        var baseName = hidden.getAttribute('data-multi-name') || '';
        function sync() {
            var checked = Array.from(modal.querySelectorAll('[data-multi-checkbox="' + id + '"]:checked'));
            var inputName = baseName;
            hidden.innerHTML = '';
            checked.forEach(function (checkbox) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName + '[]';
                input.value = checkbox.value;
                hidden.appendChild(input);
            });
            if (caption) {
                caption.textContent = checked.length === 0 ? 'Todas' : (checked.length === 1 ? '1 selecionada' : checked.length + ' selecionadas');
            }
        }
        button.addEventListener('click', function () {
            modal.classList.remove('is-hidden');
            modal.querySelector('[data-multi-search="' + id + '"]')?.focus();
        });
        modal.querySelectorAll('[data-multi-close="' + id + '"]').forEach(function (close) {
            close.addEventListener('click', function () { modal.classList.add('is-hidden'); });
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) modal.classList.add('is-hidden');
        });
        modal.querySelector('[data-multi-clear="' + id + '"]')?.addEventListener('click', function () {
            modal.querySelectorAll('[data-multi-checkbox="' + id + '"]').forEach(function (checkbox) { checkbox.checked = false; });
            sync();
        });
        modal.querySelectorAll('[data-multi-checkbox="' + id + '"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', sync);
        });
        modal.querySelector('[data-multi-search="' + id + '"]')?.addEventListener('input', function (event) {
            var term = (event.target.value || '').toLowerCase();
            modal.querySelectorAll('[data-multi-option]').forEach(function (option) {
                option.style.display = option.getAttribute('data-multi-option').indexOf(term) >= 0 ? '' : 'none';
            });
        });
        sync();
    });
})();
</script>
</body>
</html>
