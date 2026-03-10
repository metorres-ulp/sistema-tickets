/* dashboard.js - Panel privado */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ─── Polling de notificaciones ────────────────────────────
    pollNotifications();
    setInterval(pollNotifications, 30000); // cada 30 seg

    function pollNotifications() {
        fetch('/api/notificaciones.php?check=1', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;
                const badge = document.getElementById('notif-badge');
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }
                if (data.new_tickets && data.new_tickets.length > 0) {
                    data.new_tickets.forEach(function (t) {
                        showToast('Nuevo ticket: ' + t.numero, t.solicitante, 'info');
                    });
                }
            })
            .catch(function () {});
    }

    // ─── Toast helper ─────────────────────────────────────────
    window.showToast = function (title, body, type) {
        type = type || 'info';
        const container = document.getElementById('toastContainer') || createToastContainer();
        const id = 'toast-' + Date.now();
        const colors = { info: 'primary', success: 'success', warning: 'warning', error: 'danger' };
        const color  = colors[type] || 'primary';
        const div = document.createElement('div');
        div.className = 'toast align-items-center text-bg-' + color + ' border-0';
        div.id        = id;
        div.setAttribute('role', 'alert');
        div.setAttribute('aria-live', 'assertive');
        div.setAttribute('aria-atomic', 'true');
        div.innerHTML = '<div class="d-flex"><div class="toast-body"><strong>' + escHtml(title) + '</strong><br><small>' + escHtml(body) + '</small></div>'
                      + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        container.appendChild(div);
        const toast = new bootstrap.Toast(div, { autohide: true, delay: 6000 });
        toast.show();
        div.addEventListener('hidden.bs.toast', function () { div.remove(); });
    };

    function createToastContainer() {
        const c = document.createElement('div');
        c.id = 'toastContainer';
        c.className = 'toast-container position-fixed top-0 end-0 p-3';
        c.style.zIndex = '9999';
        document.body.appendChild(c);
        return c;
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Kanban drag-and-drop (para admins/referentes) ────────
    initKanban();

    function initKanban() {
        const columns = document.querySelectorAll('.kanban-column-body');
        if (!columns.length) return;

        let dragging = null;

        document.querySelectorAll('.ticket-card[draggable="true"]').forEach(function (card) {
            card.addEventListener('dragstart', function (e) {
                dragging = this;
                this.classList.add('opacity-50');
                e.dataTransfer.effectAllowed = 'move';
            });
            card.addEventListener('dragend', function () {
                this.classList.remove('opacity-50');
                dragging = null;
            });
        });

        columns.forEach(function (col) {
            col.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                col.classList.add('bg-primary-subtle');
            });
            col.addEventListener('dragleave', function () {
                col.classList.remove('bg-primary-subtle');
            });
            col.addEventListener('drop', function (e) {
                e.preventDefault();
                col.classList.remove('bg-primary-subtle');
                if (!dragging) return;
                const newStatus = col.dataset.status;
                const ticketId  = dragging.dataset.ticketId;
                if (!newStatus || !ticketId) return;
                // AJAX update
                fetch('/api/update-status.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ ticket_id: ticketId, estado: newStatus }),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        col.appendChild(dragging);
                        // Update badge
                        const badge = dragging.querySelector('.ticket-status-badge');
                        if (badge) badge.outerHTML = data.badge_html || '';
                    } else {
                        showToast('Error', data.message || 'No se pudo cambiar el estado', 'error');
                    }
                })
                .catch(function () {
                    showToast('Error', 'Error de conexión', 'error');
                });
            });
        });
    }

    // ─── Filtros de tickets (listado) ─────────────────────────
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.querySelectorAll('select, input').forEach(function (el) {
            el.addEventListener('change', function () { filterForm.submit(); });
        });
    }

});
