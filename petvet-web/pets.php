<?php
$pageTitle = 'Patients';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$clients = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name")->fetchAll();
?>

<div class="page-header">
    <h1>Patients</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Patient</button>
</div>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search patients..." oninput="loadPets()">
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Pet Name</th><th>Species</th><th>Breed</th><th>Owner</th><th>Sex</th><th>Actions</th></tr></thead>
            <tbody id="petsTable"></tbody>
        </table>
    </div>
    <div id="pagination" style="padding:12px;text-align:center;"></div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Add Patient</h2>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form id="petForm" onsubmit="savePet(event)">
            <input type="hidden" id="petId">
            <div class="form-row">
                <div class="form-group"><label>Owner *</label><select id="petOwner" required><?= implode('', array_map(fn($c)=>"<option value='{$c['id']}'>".htmlspecialchars($c['client_name'])."</option>", $clients)) ?></select></div>
                <div class="form-group"><label>Pet Name *</label><input type="text" id="petName" required></div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label>Species</label><select id="petSpecies"><option>Dog</option><option>Cat</option><option>Bird</option><option>Rabbit</option><option>Other</option></select></div>
                <div class="form-group"><label>Breed</label><input type="text" id="petBreed"></div>
                <div class="form-group"><label>Sex</label><select id="petSex"><option>Unknown</option><option>Male</option><option>Female</option></select></div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label>Color</label><input type="text" id="petColor"></div>
                <div class="form-group"><label>Date of Birth</label><input type="date" id="petDob"></div>
                <div class="form-group"><label>Age</label><input type="text" id="petAge" placeholder="e.g. 2 years"></div>
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
async function loadPets(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const res = await fetch(`api/pets.php?action=list&page=${page}&search=${encodeURIComponent(search)}`);
    const json = await res.json();
    const tbody = document.getElementById('petsTable');
    if (!json.data.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No patients found</td></tr>'; return; }
    tbody.innerHTML = json.data.map(p => `<tr>
        <td><strong>${esc(p.pet_name)}</strong></td>
        <td>${esc(p.species)}</td>
        <td>${esc(p.breed||'-')}</td>
        <td>${esc(p.client_name)}</td>
        <td>${esc(p.sex)}</td>
        <td class="btn-group">
            <button class="btn btn-sm btn-secondary" onclick='editPet(${JSON.stringify(p)})'>Edit</button>
            <button class="btn btn-sm btn-danger" onclick="deletePet(${p.id})">Delete</button>
        </td>
    </tr>`).join('');
    document.getElementById('pagination').innerHTML = Array.from({length:json.pages},(_,i)=>`<button class="btn btn-sm ${i+1==currentPage?'btn-primary':'btn-secondary'}" onclick="loadPets(${i+1})" style="margin:0 2px">${i+1}</button>`).join('');
}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openAdd(){document.getElementById('petId').value='';document.getElementById('petName').value='';document.getElementById('petBreed').value='';document.getElementById('petColor').value='';document.getElementById('petDob').value='';document.getElementById('petAge').value='';document.getElementById('modalTitle').textContent='Add Patient';openModal('addModal');}
function editPet(p){document.getElementById('petId').value=p.id;document.getElementById('petOwner').value=p.client_id;document.getElementById('petName').value=p.pet_name;document.getElementById('petSpecies').value=p.species;document.getElementById('petBreed').value=p.breed||'';document.getElementById('petSex').value=p.sex;document.getElementById('petColor').value=p.color||'';document.getElementById('petDob').value=p.date_of_birth||'';document.getElementById('petAge').value=p.age||'';document.getElementById('modalTitle').textContent='Edit Patient';openModal('addModal');}
async function savePet(e){e.preventDefault();const id=document.getElementById('petId').value;const data={client_id:document.getElementById('petOwner').value,pet_name:document.getElementById('petName').value,species:document.getElementById('petSpecies').value,breed:document.getElementById('petBreed').value,sex:document.getElementById('petSex').value,color:document.getElementById('petColor').value,date_of_birth:document.getElementById('petDob').value,age:document.getElementById('petAge').value};const url=id?`api/pets.php?action=edit&id=${id}`:'api/pets.php?action=add';const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const json=await res.json();if(json.success){showToast(id?'Patient updated':'Patient added');closeModal('addModal');loadPets(currentPage);}else showToast(json.error,'error');}
async function deletePet(id){if(!confirm('Delete this patient?'))return;await fetch(`api/pets.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Patient deleted');loadPets(currentPage);}
loadPets();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
