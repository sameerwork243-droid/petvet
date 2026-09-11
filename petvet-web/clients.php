<?php
$pageTitle = 'Clients';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="page-header">
    <h1>Clients</h1>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Client</button>
</div>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search clients..." oninput="loadClients()">
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>Pets</th><th>Actions</th></tr></thead>
            <tbody id="clientsTable"></tbody>
        </table>
    </div>
    <div id="pagination" style="padding:12px;text-align:center;"></div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Add Client</h2>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form id="clientForm" onsubmit="saveClient(event)">
            <input type="hidden" id="clientId">
            <div class="form-group"><label>Client Name *</label><input type="text" id="clientName" required></div>
            <div class="form-row">
                <div class="form-group"><label>Contact Number</label><input type="text" id="clientPhone"></div>
                <div class="form-group"><label>Address</label><input type="text" id="clientAddress"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentPage = 1;
async function loadClients(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const res = await fetch(`api/clients.php?action=list&page=${page}&search=${encodeURIComponent(search)}`);
    const json = await res.json();
    const tbody = document.getElementById('clientsTable');
    if (!json.data.length) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No clients found</td></tr>'; return; }
    tbody.innerHTML = json.data.map(c => `<tr>
        <td><strong>${esc(c.client_name)}</strong></td>
        <td>${esc(c.contact_number||'-')}</td>
        <td>${esc(c.address||'-')}</td>
        <td><span class="badge badge-blue">${c.pet_count}</span></td>
        <td class="btn-group">
            <button class="btn btn-sm btn-secondary" onclick='editClient(${JSON.stringify(c)})'>Edit</button>
            <button class="btn btn-sm btn-danger" onclick="deleteClient(${c.id})">Delete</button>
        </td>
    </tr>`).join('');
    document.getElementById('pagination').innerHTML = Array.from({length:json.pages},(_,i)=>`<button class="btn btn-sm ${i+1==currentPage?'btn-primary':'btn-secondary'}" onclick="loadClients(${i+1})" style="margin:0 2px">${i+1}</button>`).join('');
}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openAdd(){document.getElementById('clientId').value='';document.getElementById('clientName').value='';document.getElementById('clientPhone').value='';document.getElementById('clientAddress').value='';document.getElementById('modalTitle').textContent='Add Client';openModal('addModal');}
function editClient(c){document.getElementById('clientId').value=c.id;document.getElementById('clientName').value=c.client_name;document.getElementById('clientPhone').value=c.contact_number||'';document.getElementById('clientAddress').value=c.address||'';document.getElementById('modalTitle').textContent='Edit Client';openModal('addModal');}
async function saveClient(e){e.preventDefault();const id=document.getElementById('clientId').value;const data={client_name:document.getElementById('clientName').value,contact_number:document.getElementById('clientPhone').value,address:document.getElementById('clientAddress').value};const url=id?`api/clients.php?action=edit&id=${id}`:'api/clients.php?action=add';const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const json=await res.json();if(json.success){showToast(id?'Client updated':'Client added');closeModal('addModal');loadClients(currentPage);}else showToast(json.error,'error');}
async function deleteClient(id){if(!confirm('Delete this client?'))return;await fetch(`api/clients.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Client deleted');loadClients(currentPage);}
document.getElementById('addModal').querySelector('.btn-primary').parentElement.parentElement.querySelector('button[type=button]').onclick=()=>{document.getElementById('clientId').value='';openAdd();};
loadClients();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
