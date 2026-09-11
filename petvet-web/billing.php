<?php
$pageTitle = 'Billing';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$clients = $pdo->query("SELECT id, client_name, contact_number FROM clients ORDER BY client_name")->fetchAll();
$products = $pdo->query("SELECT id, name, price, quantity FROM products WHERE quantity > 0 ORDER BY name")->fetchAll();
?>

<div class="page-header">
    <h1>Billing</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ New Invoice</button>
</div>

<div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search invoices..." oninput="load()">
    <select id="statusFilter" onchange="load()" style="padding:10px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary)">
        <option value="">All</option><option value="PAID">Paid</option><option value="UNPAID">Unpaid</option><option value="PARTIALLY_PAID">Partial</option>
    </select>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Invoice</th><th>Customer</th><th>Items</th><th>Total</th><th>Paid</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody id="tbl"></tbody>
        </table>
    </div>
    <div id="pagination" style="padding:12px;text-align:center;"></div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header"><h2>New Invoice</h2><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <form onsubmit="save(event)">
            <div class="form-row">
                <div class="form-group"><label>Customer Name</label><input type="text" id="bName"></div>
                <div class="form-group"><label>Phone</label><input type="text" id="bPhone"></div>
            </div>
            <div class="form-group"><label>Link to Client</label><select id="bClient"><option value="">Walk-in</option><?= implode('', array_map(fn($c)=>"<option value='{$c['id']}'>".htmlspecialchars($c['client_name'])."</option>", $clients)) ?></select></div>
            
            <div style="margin:16px 0 8px;font-weight:600;">Line Items</div>
            <div id="itemsList"></div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addItem()" style="margin:8px 0">+ Add Item</button>
            
            <div class="form-row">
                <div class="form-group"><label>Discount (₱)</label><input type="number" id="bDiscount" value="0" step="0.01" oninput="calcTotal()"></div>
                <div class="form-group"><label>Payment Mode</label><select id="bPayMode"><option>CASH</option><option>CARD</option><option>BANK_TRANSFER</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Amount Paid (₱)</label><input type="number" id="bPaid" value="0" step="0.01"></div>
                <div class="form-group" style="display:flex;align-items:end"><div style="font-size:18px;font-weight:700;">Total: <span id="bTotal">₱0.00</span></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Complete Payment</button></div>
        </form>
    </div>
</div>

<script>
let cp=1;const prods=<?= json_encode($products) ?>;
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

function addItem(){
    const div=document.getElementById('itemsList');const row=document.createElement('div');row.className='form-row-3';row.style.marginBottom='8px';
    row.innerHTML=`<div class="form-group"><select class="i-prod" onchange="updPrice(this)"><option value="">Select product</option>${prods.map(p=>`<option value="${p.id}" data-price="${p.price}" data-qty="${p.quantity}">${esc(p.name)} (stock: ${p.quantity})</option>`).join('')}</select></div>
    <div class="form-group"><input type="number" class="i-qty" value="1" min="1" oninput="calcTotal()"></div>
    <div class="form-group" style="display:flex;gap:4px;align-items:center"><input type="number" class="i-price" placeholder="Price" step="0.01" oninput="calcTotal()"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.form-row-3').remove();calcTotal()">X</button></div>`;
    div.appendChild(row);
}

function updPrice(sel){const opt=sel.options[sel.selectedIndex];sel.closest('.form-row-3').querySelector('.i-price').value=opt.dataset.price||0;calcTotal();}

function calcTotal(){
    let total=0;
    document.querySelectorAll('#itemsList .form-row-3').forEach(r=>{total+=parseFloat(r.querySelector('.i-price').value||0)*parseInt(r.querySelector('.i-qty').value||1);});
    total-=parseFloat(document.getElementById('bDiscount').value||0);
    document.getElementById('bTotal').textContent='₱'+total.toFixed(2);
}

async function load(p=1){cp=p;const s=document.getElementById('searchInput').value;const st=document.getElementById('statusFilter').value;
const r=await fetch(`api/billing.php?action=list&page=${p}&search=${encodeURIComponent(s)}&status=${st}`);const j=await r.json();
document.getElementById('tbl').innerHTML=j.data.map(b=>`<tr>
    <td><strong>${esc(b.invoice_no||'-')}</strong></td><td>${esc(b.customer_name||'-')}</td><td>${b.items.length} items</td>
    <td>₱${parseFloat(b.final_total).toFixed(2)}</td><td>₱${parseFloat(b.amount_paid).toFixed(2)}</td>
    <td><span class="badge ${b.status=='PAID'?'badge-green':b.status=='UNPAID'?'badge-red':'badge-yellow'}">${b.status}</span></td>
    <td>${esc(b.created_at?.split(' ')[0]||'-')}</td>
    <td class="btn-group"><button class="btn btn-sm btn-danger" onclick="del(${b.id})">Delete</button></td>
</tr>`).join('')||'<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">No invoices</td></tr>';
document.getElementById('pagination').innerHTML=Array.from({length:j.pages},(_,i)=>`<button class="btn btn-sm ${i+1==cp?'btn-primary':'btn-secondary'}" onclick="load(${i+1})" style="margin:0 2px">${i+1}</button>`).join('');}

function openAdd(){document.getElementById('bName').value='';document.getElementById('bPhone').value='';document.getElementById('bClient').value='';document.getElementById('bDiscount').value='0';document.getElementById('bPaid').value='0';document.getElementById('bPayMode').value='CASH';document.getElementById('itemsList').innerHTML='';document.getElementById('bTotal').textContent='₱0.00';openModal('addModal');addItem();}

async function save(e){
    e.preventDefault();const items=[];
    document.querySelectorAll('#itemsList .form-row-3').forEach(r=>{
        const sel=r.querySelector('.i-prod');if(sel.value)items.push({product_id:sel.value,name:sel.options[sel.selectedIndex].text.split(' (')[0],quantity:parseInt(r.querySelector('.i-qty').value)||1,price:parseFloat(r.querySelector('.i-price').value)||0});
    });
    let sub=items.reduce((s,i)=>s+i.price*i.quantity,0);const disc=parseFloat(document.getElementById('bDiscount').value)||0;
    const d={customer_name:document.getElementById('bName').value,customer_phone:document.getElementById('bPhone').value,client_id:document.getElementById('bClient').value||null,subtotal:sub,discount:disc,final_total:sub-disc,amount_paid:document.getElementById('bPaid').value,payment_mode:document.getElementById('bPayMode').value,items};
    const r=await fetch('api/billing.php?action=add',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});
    const j=await r.json();if(j.success){showToast('Invoice #'+j.invoice_no+' created');closeModal('addModal');load();}else showToast(j.error||'Error','error');
}
async function del(id){if(!confirm('Delete invoice?'))return;await fetch(`api/billing.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Deleted');load(cp);}
load();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
