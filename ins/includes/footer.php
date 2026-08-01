    </div>
    <footer class="footer mt-auto py-3 bg-white text-center">
        <div class="container-fluid">
            <span class="text-muted">
                Copyright &copy; 2026
                <a href="<?= h(app_url('index')); ?>" class="text-primary fw-semibold"><?= h(app_name()); ?></a>.
                All rights reserved.
            </span>
        </div>
    </footer>
</main>
</div>

<div class="scrollToTop">
    <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
</div>
<div id="responsive-overlay"></div>

<script src="<?= h(app_url('theme/assets/libs/@popperjs/core/umd/popper.min.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/libs/bootstrap/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/libs/sweetalert2/sweetalert2.all.min.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/js/defaultmenu.min.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/libs/node-waves/waves.min.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/js/sticky.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/libs/simplebar/simplebar.min.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/js/simplebar.js')); ?>"></script>
<script src="<?= h(app_url('theme/assets/js/custom.js')); ?>"></script>
<script>
(() => {
    if (window.Swal) {
        window.alert = (message) => Swal.fire({
            text: String(message || ''),
            icon: 'info',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            customClass: {
                popup: 'gr-swal-popup',
                confirmButton: 'btn btn-primary'
            }
        });
    }

    const create = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const normalizePanelContent = () => {
        document.querySelectorAll([
            '.settings-detail-card',
            '.settings-summary-table',
            '.settings-mini-table',
            '.settings-command-card',
            '.question-bank-shell',
            '.course-console-hero',
            '.instructor-hero',
            '.course-form-section',
            '.live-room-main',
            '.live-side-panel',
            '.live-panel',
            '.live-help-card',
            '.panel-card',
            '.content-card',
            '.list-card',
            '.stats-card'
        ].join(',')).forEach((card) => {
            card.classList.add('card', 'custom-card');
            if (!card.querySelector(':scope > .card-body') && !card.classList.contains('settings-command-card')) {
                Array.from(card.children).forEach((child) => {
                    if (child.classList.contains('detail-head') || child.classList.contains('table-head') || child.classList.contains('mini-table-title') || child.classList.contains('card-head') || child.classList.contains('section-head')) return;
                    if (child.classList.contains('card-body') || child.classList.contains('card-header')) return;
                    child.classList.add('card-body');
                });
            }
        });

        document.querySelectorAll('.detail-head, .table-head, .mini-table-title, .card-head, .section-head').forEach((head) => {
            head.classList.add('card-header', 'justify-content-between');
            const title = head.querySelector('h1, h2, h3');
            if (title) title.classList.add('card-title', 'mb-0');
            head.querySelectorAll('p').forEach((p) => p.classList.add('text-muted', 'mb-0', 'fs-13'));
        });

        document.querySelectorAll('.settings-command-card').forEach((hero) => {
            hero.classList.add('card', 'custom-card');
            hero.style.padding = '';
            const body = hero.querySelector(':scope > .card-body');
            if (!body) {
                const wrap = create('div', 'card-body d-flex flex-wrap align-items-center justify-content-between gap-3');
                Array.from(hero.childNodes).forEach((node) => wrap.appendChild(node));
                hero.appendChild(wrap);
            }
        });

        document.querySelectorAll('.modal-button:not(.btn), .table-edit-icon:not(.btn), .mini-action-btn:not(.btn), .soft-action:not(.btn), .question-mini-button:not(.btn)').forEach((button) => {
            button.classList.add('btn', 'btn-sm', 'btn-primary-light', 'btn-wave');
        });

        document.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not(.form-control), textarea:not(.form-control)').forEach((field) => {
            field.classList.add('form-control');
        });

        document.querySelectorAll('select:not(.form-select)').forEach((field) => {
            field.classList.add('form-select');
        });

        document.querySelectorAll('button:not(.btn):not(.btn-close), input[type="submit"]:not(.btn), input[type="button"]:not(.btn)').forEach((button) => {
            button.classList.add('btn', 'btn-primary', 'btn-wave');
        });

        document.querySelectorAll('label:not(.form-check-label)').forEach((label) => {
            if (label.querySelector('input, select, textarea')) return;
            label.classList.add('form-label');
        });

        document.querySelectorAll('.status-pill').forEach((pill) => {
            pill.classList.add('badge');
            if (pill.classList.contains('ready') || /active|set|published|approved|verified|live/i.test(pill.textContent)) {
                pill.classList.add('bg-success-transparent', 'text-success');
            } else if (pill.classList.contains('empty') || /not|pending|draft|missing|offline|scheduled/i.test(pill.textContent)) {
                pill.classList.add('bg-warning-transparent', 'text-warning');
            } else {
                pill.classList.add('bg-primary-transparent', 'text-primary');
            }
        });
    };

    normalizePanelContent();

    document.querySelectorAll('.gr-panel-shell table').forEach((table) => {
        table.classList.add('table', 'table-hover', 'align-middle', 'text-nowrap', 'mb-0');
        const parent = table.parentElement;
        if (parent && !parent.classList.contains('table-responsive')) {
            const wrap = create('div', 'table-responsive');
            parent.insertBefore(wrap, table);
            wrap.appendChild(table);
        }
    });

    document.querySelectorAll('table.smart-table:not([data-gr-table])').forEach((table) => {
        table.dataset.grTable = '1';
        const body = table.tBodies[0];
        const rows = body ? Array.from(body.rows) : [];
        if (!rows.length) return;
        const host = table.closest('.table-responsive') || table.parentElement;
        const toolbar = create('div', 'gr-table-toolbar');
        const search = create('input', 'form-control form-control-sm');
        search.type = 'search';
        search.placeholder = 'Search records...';
        const count = create('span', 'text-muted small');
        toolbar.append(search, count);
        host.parentElement.insertBefore(toolbar, host);
        const update = () => {
            const q = search.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach((row) => {
                const ok = !q || row.textContent.toLowerCase().includes(q);
                row.hidden = !ok;
                if (ok) shown += 1;
            });
            count.textContent = `${shown} row(s)`;
        };
        search.addEventListener('input', update);
        update();
    });

    document.querySelectorAll('table.gr-register-table:not([data-gr-register])').forEach((table) => {
        table.dataset.grRegister = '1';
        const body = table.tBodies[0];
        const rows = body ? Array.from(body.rows).filter((row) => row.cells.length > 1) : [];
        const emptyRows = body ? Array.from(body.rows).filter((row) => row.cells.length <= 1 || row.querySelector('td[colspan]')) : [];
        if (rows.length <= 10) return;

        const pageSize = Number(table.dataset.pageSize || 10);
        let currentPage = 1;
        const pageCount = () => Math.max(1, Math.ceil(rows.length / pageSize));
        const host = table.closest('.table-responsive') || table.parentElement;
        const pager = create('div', 'gr-register-pager');
        const info = create('span', 'text-muted small');
        const controls = create('div', 'btn-list mb-0');
        const prev = create('button', 'btn btn-sm btn-light btn-wave', 'Prev');
        const page = create('span', 'badge bg-primary-transparent text-primary');
        const next = create('button', 'btn btn-sm btn-light btn-wave', 'Next');
        prev.type = 'button';
        next.type = 'button';
        controls.append(prev, page, next);
        pager.append(info, controls);
        host.insertAdjacentElement('afterend', pager);

        const render = () => {
            const totalPages = pageCount();
            currentPage = Math.min(Math.max(1, currentPage), totalPages);
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            rows.forEach((row, index) => {
                row.hidden = index < start || index >= end;
            });
            emptyRows.forEach((row) => {
                row.hidden = rows.length > 0;
            });
            info.textContent = `${start + 1}-${Math.min(end, rows.length)} of ${rows.length} rows`;
            page.textContent = `${currentPage} / ${totalPages}`;
            prev.disabled = currentPage <= 1;
            next.disabled = currentPage >= totalPages;
        };

        prev.addEventListener('click', () => {
            currentPage -= 1;
            render();
        });
        next.addEventListener('click', () => {
            currentPage += 1;
            render();
        });
        render();
    });

    const notices = Array.from(document.querySelectorAll('.notice'));
    if (notices.length) {
        const stack = create('div', 'toast-container position-fixed top-0 end-0 p-3 gr-toast-stack');
        document.body.appendChild(stack);
        notices.forEach((notice) => {
            const toast = create('div', 'toast show gr-toast');
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `<div class="toast-header"><strong class="me-auto">Notification</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div><div class="toast-body"></div>`;
            toast.querySelector('.toast-body').textContent = notice.textContent.trim();
            stack.appendChild(toast);
            notice.remove();
        });
    }

    document.querySelectorAll('.sidemenu-toggle').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            document.documentElement.classList.toggle('gr-sidebar-collapsed');
        });
    });

    document.querySelectorAll('.has-sub > .side-menu__item').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const item = trigger.closest('.has-sub');
            const menu = item ? item.querySelector('.slide-menu') : null;
            if (!item || !menu) return;
            const open = !item.classList.contains('open');
            item.classList.toggle('open', open);
            menu.style.display = open ? 'block' : 'none';
        }, true);
    });
})();
</script>
</body>
</html>
