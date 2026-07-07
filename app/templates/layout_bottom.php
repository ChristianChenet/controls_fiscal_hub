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
})();
</script>
</body>
</html>
