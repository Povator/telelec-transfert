const modal = document.getElementById('editModal');
const closeBtn = document.getElementsByClassName('close')[0];
const editForm = document.getElementById('editForm');

function editFile(id) {
    modal.style.display = 'block';
    document.getElementById('fileId').value = id;
    
    // Récupérer les données actuelles du fichier
    const row = document.querySelector(`tr[data-id="${id}"]`);
    document.getElementById('filename').value = row.children[1].textContent;
    document.getElementById('company').value = row.children[3].textContent;
    document.getElementById('downloadCode').value = row.children[4].textContent;
}

closeBtn.onclick = function() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

editForm.onsubmit = function(e) {
    e.preventDefault();
    
    const formData = new FormData(editForm);
    fetch('update_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la mise à jour');
        }
    });
}

function refreshDatabase() {
    if (confirm('Voulez-vous vraiment nettoyer la base de données ?')) {
        fetch('/clean_database.php')
            .then(response => response.text())
            .then(result => {
                alert(result);
                window.location.href = '/admin/dashboard.php';
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du nettoyage de la base de données');
            });
    }
}