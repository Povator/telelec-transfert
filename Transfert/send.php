<!DOCTYPE html>
<html lang="fr">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Transfert Tetelec</title>
      <link rel="stylesheet" href="/style.css">
  </head>
  <body>
    <?php include '../Present/header.php'; ?>
    <main>
      <form action="uploads.php" method="post" enctype="multipart/form-data">
        Séléctionner un document a partager : 
        <input type="file" name="fileToUpload" id="fileToUpload">
        <input type="submit" value="Envoyer le fichier" name="submit">
      </form>    
    </main>

    <?php include '../Present/footer.php'; ?>
</body>

</html>