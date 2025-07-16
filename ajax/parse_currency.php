// Tambahkan script ini di bagian JavaScript form_barang.php

// ALTERNATIVE: Menggunakan AJAX untuk parse currency
async function parseCurrency(priceString) {
    try {
        const response = await fetch(`ajax/parse_currency.php?price_string=${encodeURIComponent(priceString)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) throw new Error('Network response error.');
        
        const data = await response.json();
        return data.success ? data.value : null;
    } catch (error) {
        console.error('Parse currency error:', error);
        return null;
    }
}

// Validasi form sebelum submit
document.querySelector('form').addEventListener('submit', async function(e) {
    const hargaBeliInput = document.getElementById('harga_beli');
    const hargaJualInput = document.getElementById('harga_jual');
    
    // Parse harga menggunakan AJAX
    const hargaBeli = await parseCurrency(hargaBeliInput.value);
    const hargaJual = await parseCurrency(hargaJualInput.value);
    
    if (hargaBeli === null || hargaJual === null) {
        e.preventDefault();
        Swal.fire({
            title: 'Error!',
            text: 'Format harga tidak valid.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // Set nilai yang sudah diparsing ke input hidden
    const hiddenHargaBeli = document.createElement('input');
    hiddenHargaBeli.type = 'hidden';
    hiddenHargaBeli.name = 'harga_beli_parsed';
    hiddenHargaBeli.value = hargaBeli;
    
    const hiddenHargaJual = document.createElement('input');
    hiddenHargaJual.type = 'hidden';
    hiddenHargaJual.name = 'harga_jual_parsed';
    hiddenHargaJual.value = hargaJual;
    
    this.appendChild(hiddenHargaBeli);
    this.appendChild(hiddenHargaJual);
});