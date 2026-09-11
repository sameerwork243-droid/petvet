<?php
$pageTitle = 'Products';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="page-header">
    <h1>Products</h1>
    <button class="btn btn-primary" onclick="openAdd()">+ Add Product</button>
</div>

<div class="search-bar"><input type="text" id="searchInput" placeholder="Search products..." oninput="load()"></div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Barcode</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
            <tbody id="tbl"></tbody>
        </table>
    </div>
    <div id="pagination" style="padding:12px;text-align:center;"></div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><h2 id="modalTitle">Add Product</h2><button class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
        <form onsubmit="save(event)">
            <input type="hidden" id="pid">
            <div class="form-row">
                <div class="form-group"><label>Barcode</label><input type="text" id="pBarcode"></div>
                <div class="form-group"><label>Name *</label><input type="text" id="pName" required></div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label>Category</label><input type="text" id="pCat"></div>
                <div class="form-group"><label>Price (₱)</label><input type="number" id="pPrice" step="0.01" value="0"></div>
                <div class="form-group"><label>Quantity</label><input type="number" id="pQty" value="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<script>
let cp=1;
async function load(p=1){cp=p;const s=document.getElementById('searchInput').value;const r=await fetch(`api/products.php?action=list&page=${p}&search=${encodeURIComponent(s)}`);const j=await r.json();
document.getElementById('tbl').innerHTML=j.data.map(p=>`<tr>
    <td>${esc(p.barcode_number||'-')}</td><td><strong>${esc(p.name)}</strong></td><td>${esc(p.category||'-')}</td><td>₱${parseFloat(p.price).toFixed(2)}</td>
    <td><span class="badge ${p.quantity<=5?'badge-red':'badge-green'}">${p.quantity}</span></td>
    <td class="btn-group"><button class="btn btn-sm btn-secondary" onclick='edit(${JSON.stringify(p)})'>Edit</button><button class="btn btn-sm btn-danger" onclick="del(${p.id})">Delete</button></td>
</tr>`).join('')||'<tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No products</td></tr>';
document.getElementById('pagination').innerHTML=Array.from({length:j.pages},(_,i)=>`<button class="btn btn-sm ${i+1==cp?'btn-primary':'btn-secondary'}" onclick="load(${i+1})" style="margin:0 2px">${i+1}</button>`).join('');}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function openAdd(){document.getElementById('pid').value='';document.getElementById('pBarcode').value='';document.getElementById('pName').value='';document.getElementById('pCat').value='';document.getElementById('pPrice').value='0';document.getElementById('pQty').value='0';document.getElementById('modalTitle').textContent='Add Product';openModal('addModal');}
function edit(p){document.getElementById('pid').value=p.id;document.getElementById('pBarcode').value=p.barcode_number||'';document.getElementById('pName').value=p.name;document.getElementById('pCat').value=p.category||'';document.getElementById('pPrice').value=p.price;document.getElementById('pQty').value=p.quantity;document.getElementById('modalTitle').textContent='Edit Product';openModal('addModal');}
async function save(e){e.preventDefault();const id=document.getElementById('pid').value;const d={barcode_number:document.getElementById('pBarcode').value,name:document.getElementById('pName').value,category:document.getElementById('pCat').value,price:document.getElementById('pPrice').value,quantity:document.getElementById('pQty').value};const url=id?`api/products.php?action=edit&id=${id}`:'api/products.php?action=add';const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});const j=await r.json();if(j.success){showToast('Saved');closeModal('addModal');load(cp);}else showToast(j.error,'error');}
async function del(id){if(!confirm('Delete?'))return;await fetch(`api/products.php?action=delete&id=${id}`,{method:'DELETE'});showToast('Deleted');load(cp);}
load();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
