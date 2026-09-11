<?php
$pageTitle = 'Services';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="page-header">
    <h1>Services</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Service</button>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Name</th><th>Category</th><th>Rate</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody id="svcTable"></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h2 id="modalTitle">Add Service</h2><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <form onsubmit="save(event)">
            <input type="hidden" id="sid">
            <div class="form-row">
                <div class="form-group"><label>Name *</label><input type="text" id="sname" required></div>
                <div class="form-group"><label>Category</label><input type="text" id="sCat" value="General"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Base Rate (₱)</label><input type="number" id="sRate" step="0.01" value="0"></div>
                <div class="form-group"><label>Purchase Price (₱)</label><input type="number" id="sPrice" step="0.01" value="0"></div>
            </div>
            <div class="form-group"><label><input type="checkbox" id="sGroom"> Grooming Service</label></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
async function load(){
    const r=await fetch('api/services.php?action=list');const j=await r.json();
    document.getElementById('svcTable').innerHTML=j.data.map(s=>`<tr>
        <td><strong>${esc(s.name)}</strong></td><td>${esc(s.category)}</td><td>₱${parseFloat(s.base_rate).toFixed(2)}</td>
        <td>${s.is_grooming?'<span class="badge badge-blue">Grooming</span>':'<span class="badge badge-gray">Service</span>'}</td>
        <td class="btn-group"><button class="btn btn-sm btn-secondary" onclick='edit(${JSON.stringify(s)})'>Edit</button><button class="btn btn-sm btn-danger" onclick="del(${s.id})">Delete</button></td>
    </tr>`).join('')||'<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No services</td></tr>';
}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openAdd(){document.getElementById('sid').value='';document.getElementById('sname').value='';document.getElementById('sCat').value='General';document.getElementById('sRate').value='0';document.getElementById('sPrice').value='0';document.getElementById('sGroom').checked=false;document.getElementById('modalTitle').textContent='Add Service';openModal('addModal');}
function edit(s){document.getElementById('sid').value=s.id;document.getElementById('sname').value=s.name;document.getElementById('sCat').value=s.category;document.getElementById('sRate').value=s.base_rate;document.getElementById('sPrice').value=s.purchase_price;document.getElementById('sGroom').checked=!!s.is_grooming;document.getElementById('modalTitle').textContent='Edit Service';openModal('addModal');}
async function save(e){e.preventDefault();const id=document.getElementById('sid').value;const d={name:document.getElementById('sname').value,category:document.getElementById('sCat').value,base_rate:document.getElementById('sRate').value,purchase_price:document.getElementById('sPrice').value,is_grooming:document.getElementById('sGroom').checked?1:0};const url=id?`api/services.php?action=edit&id=${id}`:'api/services.php?action=add';const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('Saved');closeModal('addModal');load();}else showToast(j.error,'error');}
async function del(id){if(!confirm('Delete?'))return;await fetch(`api/services.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Deleted');load();}
load();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
