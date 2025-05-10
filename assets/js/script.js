function hapusBus(id) {
    if (confirm('Apakah Anda yakin ingin menghapus bus ini?')) {
        window.location.href = 'hapus_bus.php?id=' + id;
    }
}