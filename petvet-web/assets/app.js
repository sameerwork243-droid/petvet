// PetVet Web - Core JavaScript
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

async function apiCall(url, method = 'GET', data = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    const res = await fetch(url, opts);
    return res.json();
}

async function apiDelete(url) {
    if (!confirm('Are you sure you want to delete this?')) return false;
    const res = await fetch(url, { method: 'DELETE' });
    const data = await res.json();
    if (data.success) { showToast('Deleted successfully'); return true; }
    showToast(data.error || 'Delete failed', 'error');
    return false;
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
}

// Close modal on overlay click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// Close modal on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
