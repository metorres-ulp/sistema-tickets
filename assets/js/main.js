/* main.js - Sistema de Tickets ULP */

'use strict';

// ─── Area selector (formulario público) ─────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // Área checkbox cards
    document.querySelectorAll('.area-checkbox-card').forEach(function (card) {
        card.addEventListener('click', function () {
            const input = this.querySelector('input[type="checkbox"]');
            if (!input) return;
            input.checked = !input.checked;
            this.classList.toggle('selected', input.checked);
            // Mostrar/ocultar tipos de trabajo del área
            const areaId = this.dataset.areaId;
            const tipos  = document.querySelector('.tipos-trabajo-group[data-area-id="' + areaId + '"]');
            if (tipos) tipos.classList.toggle('d-none', !input.checked);
        });
    });

    // Upload zone
    const uploadZone = document.getElementById('uploadZone');
    const fileInput  = document.getElementById('archivos');
    const fileList   = document.getElementById('fileList');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', function () { fileInput.click(); });
        uploadZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        uploadZone.addEventListener('dragleave', function () { this.classList.remove('dragover'); });
        uploadZone.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            updateFileList();
        });
        fileInput.addEventListener('change', updateFileList);
    }

    function updateFileList() {
        if (!fileList) return;
        fileList.innerHTML = '';
        const files = fileInput.files;
        for (let i = 0; i < files.length; i++) {
            const f    = files[i];
            const size = formatBytes(f.size);
            const li   = document.createElement('div');
            li.className = 'd-flex align-items-center gap-2 bg-white border rounded p-2 mb-1';
            li.innerHTML = '<i class="bi bi-file-earmark text-primary"></i>'
                         + '<span class="text-truncate small flex-grow-1">' + escHtml(f.name) + '</span>'
                         + '<span class="text-muted small">' + size + '</span>';
            fileList.appendChild(li);
        }
    }

    // Links de referencia dinámicos
    const addLink = document.getElementById('addLink');
    const linksContainer = document.getElementById('linksContainer');
    if (addLink && linksContainer) {
        addLink.addEventListener('click', function () {
            const idx = linksContainer.querySelectorAll('.link-row').length;
            const div = document.createElement('div');
            div.className = 'link-row d-flex gap-2 mb-2';
            div.innerHTML = '<input type="url" class="form-control form-control-sm" name="links[]" placeholder="https://ejemplo.com">'
                          + '<input type="text" class="form-control form-control-sm" name="links_desc[]" placeholder="Descripción (opcional)">'
                          + '<button type="button" class="btn btn-outline-danger btn-sm remove-link"><i class="bi bi-trash"></i></button>';
            linksContainer.appendChild(div);
        });
        linksContainer.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-link');
            if (btn) btn.closest('.link-row').remove();
        });
    }

    // Auto-dismiss alerts
    document.querySelectorAll('.alert[data-bs-autohide]').forEach(function (el) {
        setTimeout(function () {
            const alert = bootstrap.Alert.getOrCreateInstance(el);
            alert.close();
        }, 5000);
    });

    // Sidebar toggle (mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });

    // Form validation (Bootstrap)
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Confirmaciones de eliminación
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = this.dataset.confirm || '¿Estás seguro?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

});

// ─── Helpers ─────────────────────────────────────────────────
function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
