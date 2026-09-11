<?php
$pageTitle = 'Employees';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="page-header">
    <h1>Employees</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Employee</button>
</div>

<div class="search-bar"><input type="text" id="searchInput" placeholder="Search..." oninput="load()"></div>

<div class="card">
    <div class="table-wrapper">
        <table><thead><tr><th>Name</th><th>Position</th><th>Contact</th><th>Salary</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody id="tbl"></tbody></table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h2 id="modalTitle">Add Employee</h2><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <form onsubmit="save(event)">
            <input type="hidden" id="eid">
            <div class="form-row"><div class="form-group"><label>Name *</label><input type="text" id="eName" required></div><div class="form-group"><label>Position</label><input type="text" id="ePos"></div></div>
            <div class="form-row"><div class="form-group"><label>Contact</label><input type="text" id="eContact"></div><div class="form-group"><label>Salary (₱)</label><input type="number" id="eSalary" step="0.01" value="0"></div></div>
            <div class="form-group"><label>Joined On</label><input type="date" id="eJoined"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
async function load(){const s=document.getElementById('searchInput').value;const r=await fetch(`api/employees.php?action=list&search=${encodeURIComponent(s)}`);const j=await r.json();
document.getElementById('tbl').innerHTML=j.data.map(e=>`<tr><td><strong>${esc(e.name)}</strong></td><td>${esc(e.position||'-')}</td><td>${esc(e.contact||'-')}</td><td>₱${parseFloat(e.salary).toFixed(2)}</td><td>${esc(e.joined_on||'-')}</td>
<td class="btn-group"><button class="btn btn-sm btn-secondary" onclick='edit(${JSON.stringify(e)})'>Edit</button><button class="btn btn-sm btn-danger" onclick="del(${e.id})">Delete</button></td></tr>`).join('')||'<tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No employees</td></tr>';}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openAdd(){document.getElementById('eid').value='';document.getElementById('eName').value='';document.getElementById('ePos').value='';document.getElementById('eContact').value='';document.getElementById('eSalary').value='0';document.getElementById('eJoined').value='';document.getElementById('modalTitle').textContent='Add Employee';openModal('addModal');}
function edit(e){document.getElementById('eid').value=e.id;document.getElementById('eName').value=e.name;document.getElementById('ePos').value=e.position||'';document.getElementById('eContact').value=e.contact||'';document.getElementById('eSalary').value=e.salary;document.getElementById('eJoined').value=e.joined_on||'';document.getElementById('modalTitle').textContent='Edit Employee';openModal('addModal');}
async function save(ev){ev.preventDefault();const id=document.getElementById('eid').value;const d={name:document.getElementById('eName').value,position:document.getElementById('ePos').value,contact:document.getElementById('eContact').value,salary:document.getElementById('eSalary').value,joined_on:document.getElementById('eJoined').value};const url=id?`api/employees.php?action=edit&id=${id}`:'api/employees.php?action=add';const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('Saved');closeModal('addModal');load();}else showToast(j.error,'error');}
async function del(id){if(!confirm('Delete?'))return;await fetch(`api/employees.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Deleted');load();}
load();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
