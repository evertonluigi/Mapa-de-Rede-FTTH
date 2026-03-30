
</div><!-- .main-content -->
</div><!-- .app-layout -->

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
function toggleUserMenu() {
    const m = document.getElementById('userMenu');
    m.style.display = m.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', (e) => {
    const menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-badge') && !e.target.closest('#userMenu')) {
        menu.style.display = 'none';
    }
});

function showToast(message, type = 'success') {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type]||icons.success}"></i><span class="toast-msg">${message}</span>`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity 0.3s'; setTimeout(()=>toast.remove(),300); }, 4000);
}

// Check for flash messages in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('saved')) showToast('Registro salvo com sucesso!', 'success');
if (urlParams.get('deleted')) showToast('Registro removido.', 'warning');
if (urlParams.get('error')) showToast(decodeURIComponent(urlParams.get('error')), 'error');
</script>
</body>
</html>
