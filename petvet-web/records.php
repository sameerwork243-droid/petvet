<?php
$pageTitle = 'Medical Records';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$patients = $pdo->query("SELECT p.id, p.pet_name, c.client_name FROM pets p JOIN clients c ON p.client_id=c.id ORDER BY p.pet_name")->fetchAll();
?>

<div class="page-header"><h1>Medical Records</h1></div>

<div class="search-bar">
    <select id="patientSelect" onchange="loadRecords()" style="flex:1;padding:10px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary)">
        <option value="">Select Patient...</option>
        <?php foreach ($patients as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['pet_name']) ?> (<?= htmlspecialchars($p['client_name']) ?>)</option>
        <?php endforeach; ?>
    </select>
</div>

<div id="recordsContent" style="display:none;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <!-- SOAP Notes -->
        <div class="card">
            <div class="card-header"><h2>SOAP Notes</h2><button class="btn btn-sm btn-primary" onclick="openSoap()">+ Add</button></div>
            <div id="soapList"></div>
        </div>
        <!-- Vaccinations -->
        <div class="card">
            <div class="card-header"><h2>Vaccinations</h2><button class="btn btn-sm btn-primary" onclick="openVax()">+ Add</button></div>
            <div id="vaxList"></div>
        </div>
        <!-- Prescriptions -->
        <div class="card">
            <div class="card-header"><h2>Prescriptions</h2><button class="btn btn-sm btn-primary" onclick="openRx()">+ Add</button></div>
            <div id="rxList"></div>
        </div>
        <!-- Vitals -->
        <div class="card">
            <div class="card-header"><h2>Latest Vitals</h2></div>
            <div id="vitalsInfo" style="color:var(--text-muted);font-size:14px;">Select a patient</div>
        </div>
    </div>
</div>

<!-- SOAP Modal -->
<div class="modal-overlay" id="soapModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header"><h2>SOAP Note</h2><button class="modal-close" onclick="closeModal('soapModal')">&times;</button></div>
        <form onsubmit="saveSoap(event)">
            <div class="form-group"><label>Doctor</label><input type="text" id="soapDoc"></div>
            <div class="form-row"><div class="form-group"><label>Subjective</label><textarea id="soapSubj" rows="2"></textarea></div><div class="form-group"><label>Objective</label><textarea id="soapObj" rows="2"></textarea></div></div>
            <div class="form-row"><div class="form-group"><label>Assessment</label><textarea id="soapAssess" rows="2"></textarea></div><div class="form-group"><label>Diagnosis</label><textarea id="soapDiag" rows="2"></textarea></div></div>
            <div class="form-group"><label>Plan</label><textarea id="soapPlan" rows="2"></textarea></div>
            <div class="form-row-3">
                <div class="form-group"><label>Temp (°C)</label><input type="number" id="soapTemp" step="0.1"></div>
                <div class="form-group"><label>Heart Rate</label><input type="number" id="soapHR"></div>
                <div class="form-group"><label>Weight (kg)</label><input type="number" id="soapWt" step="0.1"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('soapModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Vaccination Modal -->
<div class="modal-overlay" id="vaxModal">
    <div class="modal">
        <div class="modal-header"><h2>Vaccination</h2><button class="modal-close" onclick="closeModal('vaxModal')">&times;</button></div>
        <form onsubmit="saveVax(event)">
            <div class="form-group"><label>Vaccine Name *</label><input type="text" id="vaxName" required></div>
            <div class="form-row"><div class="form-group"><label>Administered On</label><input type="date" id="vaxDate"></div><div class="form-group"><label>Next Due</label><input type="date" id="vaxNext"></div></div>
            <div class="form-row"><div class="form-group"><label>Batch Number</label><input type="text" id="vaxBatch"></div><div class="form-group"><label>Administered By</label><input type="text" id="vaxBy"></div></div>
            <div class="form-group"><label>Notes</label><textarea id="vaxNotes" rows="2"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('vaxModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Prescription Modal -->
<div class="modal-overlay" id="rxModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header"><h2>Prescription</h2><button class="modal-close" onclick="closeModal('rxModal')">&times;</button></div>
        <form onsubmit="saveRx(event)">
            <div class="form-row"><div class="form-group"><label>Prescribed By</label><input type="text" id="rxDoc"></div><div class="form-group"><label>Date</label><input type="date" id="rxDate"></div></div>
            <div class="form-group"><label>Notes</label><textarea id="rxNotes" rows="2"></textarea></div>
            <div style="font-weight:600;margin:12px 0 8px;">Medications</div>
            <div id="medsList"></div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addMedRow()">+ Add Medication</button>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('rxModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
let selectedPet = null;
function getSelectedPet(){return document.getElementById('patientSelect').value;}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

async function loadRecords(){
    const pid=getSelectedPet();if(!pid){document.getElementById('recordsContent').style.display='none';return;}
    selectedPet=pid;document.getElementById('recordsContent').style.display='block';
    loadSoap(pid);loadVax(pid);loadRx(pid);loadVitals(pid);
}

async function loadSoap(pid){
    const r=await fetch(`api/records.php?action=soap_notes&pet_id=${pid}`);const j=await r.json();
    document.getElementById('soapList').innerHTML=j.data.length?j.data.map(s=>`<div style="padding:10px;border-bottom:1px solid var(--border);font-size:13px;">
        <div style="display:flex;justify-content:space-between"><strong>${esc(s.doctor||'No doctor')}</strong><span style="color:var(--text-muted)">${esc(s.created_at?.split(' ')[0]||'')}</span></div>
        <div style="color:var(--text-secondary);margin-top:4px;">${esc(s.diagnosis||s.assessment||s.subjective||'No notes').substring(0,100)}</div>
        ${s.temperature?`<div style="color:var(--text-muted);margin-top:4px;">Temp: ${s.temperature}°C | HR: ${s.heart_rate||'-'} | Wt: ${s.weight||'-'}kg</div>`:''}
        <button class="btn btn-sm btn-danger" style="margin-top:4px" onclick="delSoap(${s.id})">Delete</button>
    </div>`).join(''):'<div style="padding:16px;text-align:center;color:var(--text-muted)">No SOAP notes</div>';
}

async function loadVax(pid){
    const r=await fetch(`api/records.php?action=vaccinations&pet_id=${pid}`);const j=await r.json();
    document.getElementById('vaxList').innerHTML=j.data.length?j.data.map(v=>`<div style="padding:10px;border-bottom:1px solid var(--border);font-size:13px;">
        <div style="display:flex;justify-content:space-between"><strong>${esc(v.vaccine_name)}</strong><button class="btn btn-sm btn-danger" onclick="delVax(${v.id})">X</button></div>
        <div style="color:var(--text-secondary)">Given: ${esc(v.administered_on||'-')} | Next: ${esc(v.next_due_date||'-')}</div>
        <div style="color:var(--text-muted)">Batch: ${esc(v.batch_number||'-')} | By: ${esc(v.administered_by||'-')}</div>
    </div>`).join(''):'<div style="padding:16px;text-align:center;color:var(--text-muted)">No vaccinations</div>';
}

async function loadRx(pid){
    const r=await fetch(`api/records.php?action=prescriptions&pet_id=${pid}`);const j=await r.json();
    document.getElementById('rxList').innerHTML=j.data.length?j.data.map(rx=>`<div style="padding:10px;border-bottom:1px solid var(--border);font-size:13px;">
        <div style="display:flex;justify-content:space-between"><strong>${esc(rx.prescribed_by||'Doctor')}</strong><button class="btn btn-sm btn-danger" onclick="delRx(${rx.id})">X</button></div>
        <div style="color:var(--text-muted)">${esc(rx.prescribed_on||'-')}</div>
        ${rx.medications.map(m=>`<div style="color:var(--text-secondary);margin-top:2px;">${esc(m.name)} - ${esc(m.dosage)} - ${esc(m.frequency)} - ${esc(m.duration)}</div>`).join('')}
    </div>`).join(''):'<div style="padding:16px;text-align:center;color:var(--text-muted)">No prescriptions</div>';
}

async function loadVitals(pid){
    const r=await fetch(`api/records.php?action=soap_notes&pet_id=${pid}`);const j=await r.json();
    if(j.data.length){const v=j.data[0];document.getElementById('vitalsInfo').innerHTML=`
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>Temperature: <strong>${v.temperature||'-'}</strong> °C</div><div>Heart Rate: <strong>${v.heart_rate||'-'}</strong> bpm</div>
            <div>Resp Rate: <strong>${v.respiratory_rate||'-'}</strong></div><div>Weight: <strong>${v.weight||'-'}</strong> kg</div>
        </div>`;}else{document.getElementById('vitalsInfo').textContent='No vitals recorded';}
}

function openSoap(){document.getElementById('soapDoc').value='';document.getElementById('soapSubj').value='';document.getElementById('soapObj').value='';document.getElementById('soapAssess').value='';document.getElementById('soapDiag').value='';document.getElementById('soapPlan').value='';document.getElementById('soapTemp').value='';document.getElementById('soapHR').value='';document.getElementById('soapWt').value='';openModal('soapModal');}
function openVax(){document.getElementById('vaxName').value='';document.getElementById('vaxDate').value='';document.getElementById('vaxNext').value='';document.getElementById('vaxBatch').value='';document.getElementById('vaxBy').value='';document.getElementById('vaxNotes').value='';openModal('vaxModal');}
function openRx(){document.getElementById('rxDoc').value='';document.getElementById('rxDate').value=new Date().toISOString().split('T')[0];document.getElementById('rxNotes').value='';document.getElementById('medsList').innerHTML='';openModal('rxModal');addMedRow();}

function addMedRow(){const d=document.getElementById('medsList');const r=document.createElement('div');r.className='form-row-3';r.style.marginBottom='8px';
r.innerHTML=`<div class="form-group"><input type="text" class="med-name" placeholder="Drug name" required></div><div class="form-group"><input type="text" class="med-dose" placeholder="Dosage"></div><div class="form-group" style="display:flex;gap:4px"><input type="text" class="med-freq" placeholder="Frequency"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.form-row-3').remove()">X</button></div>`;d.appendChild(r);}

async function saveSoap(e){e.preventDefault();const d={pet_id:selectedPet,doctor:document.getElementById('soapDoc').value,subjective:document.getElementById('soapSubj').value,objective:document.getElementById('soapObj').value,assessment:document.getElementById('soapAssess').value,diagnosis:document.getElementById('soapDiag').value,plan:document.getElementById('soapPlan').value,temperature:document.getElementById('soapTemp').value||null,heart_rate:document.getElementById('soapHR').value||null,weight:document.getElementById('soapWt').value||null};const r=await fetch('api/records.php?action=add_soap',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('SOAP note saved');closeModal('soapModal');loadRecords();}else showToast(j.error,'error');}

async function saveVax(e){e.preventDefault();const d={pet_id:selectedPet,vaccine_name:document.getElementById('vaxName').value,administered_on:document.getElementById('vaxDate').value,next_due_date:document.getElementById('vaxNext').value,batch_number:document.getElementById('vaxBatch').value,administered_by:document.getElementById('vaxBy').value,notes:document.getElementById('vaxNotes').value};const r=await fetch('api/records.php?action=add_vaccination',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('Vaccination recorded');closeModal('vaxModal');loadRecords();}else showToast(j.error,'error');}

async function saveRx(e){e.preventDefault();const meds=[];document.querySelectorAll('#medsList .form-row-3').forEach(r=>{const n=r.querySelector('.med-name').value;if(n)meds.push({name:n,dosage:r.querySelector('.med-dose').value,frequency:r.querySelector('.med-freq').value,duration:''});});const d={pet_id:selectedPet,prescribed_by:document.getElementById('rxDoc').value,prescribed_on:document.getElementById('rxDate').value,notes:document.getElementById('rxNotes').value,medications:meds};const r=await fetch('api/records.php?action=add_prescription',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('Prescription saved');closeModal('rxModal');loadRecords();}else showToast(j.error,'error');}

async function delSoap(id){if(!confirm('Delete?'))return;await fetch(`api/records.php?action=delete_soap&id=${id}`,{method:'DELETE'});loadRecords();}
async function delVax(id){if(!confirm('Delete?'))return;await fetch(`api/records.php?action=delete_vaccination&id=${id}`,{method:'DELETE'});loadRecords();}
async function delRx(id){if(!confirm('Delete?'))return;await fetch(`api/records.php?action=delete_prescription&id=${id}`,{method:'DELETE'});loadRecords();}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
