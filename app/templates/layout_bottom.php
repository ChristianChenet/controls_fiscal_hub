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
        button.addEventListener('click', function () {
            var collapsed = target.classList.toggle('is-collapsed');
            button.classList.toggle('is-collapsed', collapsed);
            button.textContent = collapsed ? (button.getAttribute('data-show-label') || 'Mostrar') : (button.getAttribute('data-hide-label') || 'Recolher');
        });
    });
})();
</script>
</body>
</html>
