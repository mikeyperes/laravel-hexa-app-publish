<script>
document.addEventListener('DOMContentLoaded', function () {
    const allowedPrefixes = [
        '/dashboard',
        '/publish/sites',
        '/profile',
        '/settings/security',
    ];

    const nodes = document.querySelectorAll('aside a[href], nav a[href]');
    nodes.forEach((link) => {
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }

        const url = href.startsWith('http') ? new URL(href) : new URL(href, window.location.origin);
        const path = url.pathname;
        const allowed = allowedPrefixes.some((prefix) => path === prefix || path.startsWith(prefix + '/'));

        if (!allowed) {
            const block = link.closest('li, .menu-link-wrapper, .sidebar-item, .sidebar-link, .px-3, .px-4, .group');
            if (block) {
                block.remove();
            } else {
                link.remove();
            }
        }
    });
});
</script>
