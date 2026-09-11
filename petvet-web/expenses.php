<?php
$pageTitle = 'Expenses';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$cats = $pdo->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();
?>

<div class="page-header">
    <h1>Expenses</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Expense</button>
</div>

<div class="search-bar">
    <input type="date" id="dateFilter" onchange="load()">
</div>

<div class="card">
    <div id="totalDisplay" style="font-size:18px;font-weight:700;margin-bottom:12px;color:var(--accent)"></div>
    <div class="table-wrapper">
        <table><thead><tr><th>Date</th><th>Name</th><th>Category</th><th>Amount</th><th>Notes</th><th>Actions</th></tr></thead>
        <tbody id="tbl"></tbody></table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h2 id="modalTitle">Add Expense</h2><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <form onsubmit="save(event)">
            <input type="hidden" id="xid">
            <div class="form-row">
                <div class="form-group"><label>Name *</label><input type="text" id="xName" required></div>
                <div class="form-group"><label>Category</label><select id="xCat"><option value="">None</option><?= implode('', array_map(fn($c)=>"<option value='{$c['id']}'>".htmlspecialchars($c['name'])."</option>", $cats)) ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Amount (₱)</label><input type="number" id="xAmt" step="0.01" value="0"></div>
                <div class="form-group"><label>Date</label><input type="date" id="xDate"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea id="xNotes" rows="2"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
async function load(){const d=document.getElementById('dateFilter').value;const r=await fetch(`api/expenses.php?action=list&date=${d}`);const j=await r.json();
let total=j.data.reduce((s,e)=>s+parseFloat(e.amount),0);
document.getElementById('totalDisplay').textContent='Total: ₱'+total.toFixed(2);
document.getElementById('tbl').innerHTML=j.data.map(e=>`<tr><td>${esc(e.date)}</td><td><strong>${esc(e.name)}</strong></td><td>${esc(e.category_name||'-')}</td><td>₱${parseFloat(e.amount).toFixed(2)}</td><td>${esc(e.notes||'-')}</td>
<td class="btn-group"><button class="btn btn-sm btn-secondary" onclick='edit(${JSON.stringify(e)})'>Edit</button><button class="btn btn-sm btn-danger" onclick="del(${e.id})">Delete</button></td></tr>`).join('')||'<tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No expenses</td></tr>';}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openAdd(){document.getElementById('xid').value='';document.getElementById('xName').value='';document.getElementById('xCat').value='';document.getElementById('xAmt').value='0';document.getElementById('xDate').value=new Date().toISOString().split('T')[0];document.getElementById('xNotes').value='';document.getElementById('modalTitle').textContent='Add Expense';openModal('addModal');}
function edit(e){document.getElementById('xid').value=e.id;document.getElementById('xName').value=e.name;document.getElementById('xCat').value=e.category_id||'';document.getElementById('xAmt').value=e.amount;document.getElementById('xDate').value=e.date;document.getElementById('xNotes').value=e.notes||'';document.getElementById('modalTitle').textContent='Edit Expense';openModal('addModal');}
async function save(ev){ev.preventDefault();const id=document.getElementById('xid').value;const d={name:document.getElementById('xName').value,category_id:document.getElementById('xCat').value||null,amount:document.getElementById('xAmt').value,date:document.getElementById('xDate').value,notes:document.getElementById('xNotes').value};const url=id?`api/expenses.php?action=edit&id=${id}`:'api/expenses.php?action=add';const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('Saved');closeModal('addModal');load();}else showToast(j.error,'error');}
async function del(id){if(!confirm('Delete?'))return;await fetch(`api/expenses.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Deleted');load();}
load();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
