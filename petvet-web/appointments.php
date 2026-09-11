<?php
$pageTitle = 'Appointments';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$clients = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetchAll();
$services = $pdo->query("SELECT id, name, base_rate FROM services ORDER BY name")->fetchAll();
?>

<div class="page-header">
    <h1>Appointments</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ New Appointment</button>
</div>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search..." oninput="loadAppts()">
    <input type="date" id="dateFilter" onchange="loadAppts()">
    <select id="statusFilter" onchange="loadAppts()" style="padding:10px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary)">
        <option value="">All Status</option>
        <option value="CONFIRMED">Confirmed</option>
        <option value="COMPLETED">Completed</option>
        <option value="CANCELLED">Cancelled</option>
    </select>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Date</th><th>Time</th><th>Pet</th><th>Client</th><th>Doctor</th><th>Services</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="apptTable"></tbody>
        </table>
    </div>
    <div id="pagination" style="padding:12px;text-align:center;"></div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h2 id="modalTitle">New Appointment</h2>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form id="apptForm" onsubmit="saveAppt(event)">
            <input type="hidden" id="apptId">
            <div class="form-row-3">
                <div class="form-group"><label>Client *</label><select id="apptClient" required onchange="loadClientPets()"><option value="">Select...</option><?= implode('', array_map(fn($c)=>"<option value='{$c['id']}'>".htmlspecialchars($c['client_name'])."</option>", $clients)) ?></select></div>
                <div class="form-group"><label>Pet *</label><select id="apptPet" required><option value="">Select client first</option></select></div>
                <div class="form-group"><label>Doctor</label><input type="text" id="apptDoctor"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Date *</label><input type="date" id="apptDate" required></div>
                <div class="form-group"><label>Time</label><input type="time" id="apptTime"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea id="apptNotes" rows="2"></textarea></div>
            
            <div style="margin:16px 0 8px;font-weight:600;font-size:14px;">Services</div>
            <div id="servicesList"></div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addServiceRow()" style="margin-top:8px">+ Add Service</button>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentPage = 1;
const servicesData = <?= json_encode($services) ?>;
const clientsData = <?= json_encode($clients) ?>;
let apptPets = {};

async function loadClientPets() {
    const clientId = document.getElementById('apptClient').value;
    const sel = document.getElementById('apptPet');
    sel.innerHTML = '<option value="">Loading...</option>';
    if (!clientId) { sel.innerHTML = '<option value="">Select client first</option>'; return; }
    const res = await fetch(`api/pets.php?action=list&client_id=${clientId}`);
    const json = await res.json();
    apptPets[clientId] = json.data;
    sel.innerHTML = '<option value="">Select pet</option>' + json.data.map(p => `<option value="${p.id}">${esc(p.pet_name)}</option>`).join('');
}

function addServiceRow() {
    const div = document.getElementById('servicesList');
    const row = document.createElement('div');
    row.className = 'form-row-3';
    row.style.marginBottom = '8px';
    row.innerHTML = `
        <div class="form-group"><select class="svc-select" onchange="this.closest('.form-row-3').querySelector('.svc-rate').value=this.options[this.selectedIndex].dataset.rate||0"><option value="">Select service</option>${servicesData.map(s=>`<option value="${s.id}" data-rate="${s.base_rate}">${esc(s.name)} - ₱${s.base_rate}</option>`).join('')}</select></div>
        <div class="form-group"><input type="number" class="svc-qty" value="1" min="1" placeholder="Qty"></div>
        <div class="form-group" style="display:flex;gap:4px;align-items:center"><input type="number" class="svc-rate" placeholder="Rate" step="0.01"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.form-row-3').remove()" style="margin-left:4px">X</button></div>
    `;
    div.appendChild(row);
}

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

async function loadAppts(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const date = document.getElementById('dateFilter').value;
    const status = document.getElementById('statusFilter').value;
    const res = await fetch(`api/appointments.php?action=list&page=${page}&search=${encodeURIComponent(search)}&date=${date}&status=${status}`);
    const json = await res.json();
    const tbody = document.getElementById('apptTable');
    if (!json.data.length) { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted)">No appointments</td></tr>'; return; }
    tbody.innerHTML = json.data.map(a => `<tr>
        <td>${esc(a.appointment_date)}</td>
        <td>${esc(a.appointment_time||'-')}</td>
        <td><strong>${esc(a.pet_name)}</strong></td>
        <td>${esc(a.client_name)}</td>
        <td>${esc(a.doctor||'-')}</td>
        <td>${a.services.map(s=>esc(s.service_name)).join(', ')||'-'}</td>
        <td>₱${parseFloat(a.total_amount||0).toFixed(2)}</td>
        <td><span class="badge ${a.status=='CONFIRMED'?'badge-green':a.status=='CANCELLED'?'badge-red':'badge-blue'}">${a.status}</span></td>
        <td class="btn-group">
            <button class="btn btn-sm btn-secondary" onclick="updateStatus(${a.id},'COMPLETED')">Done</button>
            <button class="btn btn-sm btn-danger" onclick="deleteAppt(${a.id})">X</button>
        </td>
    </tr>`).join('');
    document.getElementById('pagination').innerHTML = Array.from({length:json.pages},(_,i)=>`<button class="btn btn-sm ${i+1==currentPage?'btn-primary':'btn-secondary'}" onclick="loadAppts(${i+1})" style="margin:0 2px">${i+1}</button>`).join('');
}

function openAdd(){
    document.getElementById('apptId').value='';document.getElementById('apptClient').value='';document.getElementById('apptPet').innerHTML='<option value="">Select client first</option>';
    document.getElementById('apptDate').value=new Date().toISOString().split('T')[0];document.getElementById('apptTime').value='';document.getElementById('apptDoctor').value='';document.getElementById('apptNotes').value='';
    document.getElementById('servicesList').innerHTML='';document.getElementById('modalTitle').textContent='New Appointment';openModal('addModal');addServiceRow();
}

async function saveAppt(e){
    e.preventDefault();
    const services=[];
    document.querySelectorAll('#servicesList .form-row-3').forEach(row=>{
        const svcId=row.querySelector('.svc-select').value;
        const qty=row.querySelector('.svc-qty').value;
        const rate=row.querySelector('.svc-rate').value;
        if(svcId)services.push({service_id:svcId,quantity:parseInt(qty)||1,rate:parseFloat(rate)||0,service_name:row.querySelector('.svc-select').options[ row.querySelector('.svc-select').selectedIndex].text.split(' - ')[0]});
    });
    const data={client_id:document.getElementById('apptClient').value,pet_id:document.getElementById('apptPet').value,appointment_date:document.getElementById('apptDate').value,appointment_time:document.getElementById('apptTime').value,doctor:document.getElementById('apptDoctor').value,notes:document.getElementById('apptNotes').value,status:'CONFIRMED',services};
    const res=await fetch('api/appointments.php?action=add',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const json=await res.json();
    if(json.success){showToast('Appointment created');closeModal('addModal');loadAppts();}else showToast(json.error||'Error','error');
}

async function updateStatus(id,status){await fetch(`api/appointments.php?action=edit&id=${id}`,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({status,pet_id:1,client_id:1,appointment_date:'2025-01-01'})});showToast('Updated');loadAppts(currentPage);}
async function deleteAppt(id){if(!confirm('Delete?'))return;await fetch(`api/appointments.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Deleted');loadAppts(currentPage);}
loadAppts();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
