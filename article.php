<?php
date_default_timezone_set('Europe/Paris');

include_once "article_actions.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Article | Agora Francia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .fiche-photo {max-width: 380px; max-height: 280px; object-fit: contain; background: #f9f9f9;}
        .fiche-bloc {background: #fff; border-radius: 10px; box-shadow: 0 0 8px #bbb; padding: 22px;}
    </style>
</head>
<body>
<div class="container my-4">
    <?php if (!$article): ?>
        <div class="alert alert-warning text-center">Article introuvable.</div>
        <a href="catalogue.php" class="btn btn-secondary">Retour au catalogue</a>
    <?php else: ?>
        <div class="row fiche-bloc">
            <div class="col-md-5 text-center">
                <?php if ($article['photo']): ?>
                    <img src="<?= $article['photo'] ?>" alt="photo article" class="fiche-photo img-fluid mb-3">
                <?php else: ?>
                    <img src="images/placeholder.png" alt="aperçu" class="fiche-photo img-fluid mb-3">
                <?php endif; ?>
            </div>
            <div class="col-md-7">
                <h2><?= $article['titre'] ?></h2>
                <span class="badge bg-info mb-2"><?= $article['categorie'] ?></span>
                <h4 class="text-success">
                    <?php
                    if ($article['type_vente'] == 'immediat') {
                        echo number_format($article['prix_initial'],2,',',' ') . " €";
                    } else {
                        echo number_format($article['prix_actuel'] ?? $article['prix_initial'], 2, ',', ' ');

                    }
                    ?>
                </h4>
                <p class="my-3"><?= nl2br($article['description']) ?></p>
                <ul class="mb-3 list-unstyled">
                    <li><strong>Type de vente :</strong> <?= $article['type_vente'] ?></li>
                    <li><strong>Vendeur :</strong> <?= $article['vendeur_prenom'].' '.$article['vendeur_nom'] ?> (<?= $article['vendeur_email'] ?>)</li>
                    <li><strong>Statut :</strong> <?= $article['statut'] ?></li>
                </ul>

                <!-- BLOC ACHAT IMMÉDIAT -->
                <?php if (
                    $article['type_vente'] == 'immediat' &&
                    isset($_SESSION['user_id']) &&
                    $_SESSION['user_id'] != $article['vendeur_id'] &&
                    $article['statut'] == 'disponible'
                ): ?>
                    <form method="post" class="my-3">
                        <input type="hidden" name="id" value="<?= $article['id'] ?>">
                        <button type="submit" name="achat_direct" class="btn btn-success btn-lg">Acheter maintenant</button>
                    </form>
                <?php endif; ?>

                <!-- BLOC ENCHÈRE -->
                <?php if ($article['type_vente'] == 'enchere'): ?>
                    <div class="my-3">
                        <div>Enchère&nbsp;: <strong><?= $article['date_debut_enchere'] ?></strong> → <strong><?= $article['date_fin_enchere'] ?></strong></div>
                        <div>Prix actuel : <strong><?= number_format($article['prix_actuel'],2,',',' ') ?> €</strong></div>
                        <?php if ($en_cours): ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $article['vendeur_id']): ?>
                                <form method="post" class="mt-2">
                                    <input type="hidden" name="id" value="<?= $article['id'] ?>">
                                    <input type="number" name="prix_max" min="<?= $article['prix_actuel']+1 ?>" step="1" required placeholder="Votre enchère maximum (€)">
                                    <button type="submit" name="placer_enchere" class="btn btn-warning btn-sm">Placer mon enchère</button>
                                </form>
                                <?php if ($enchere_message): ?><div class="alert alert-info mt-2"><?= $enchere_message ?></div><?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-secondary mt-2">Cette enchère est terminée ou pas encore ouverte.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- BLOC NEGOCIATION -->
                <?php if ($article['type_vente'] == 'negociation'): ?>
                    <div class="my-4 p-3 bg-light border rounded">
                        <h5 class="mb-3">Négociation</h5>
                        <?php if ($nego_message): ?>
                            <div class="alert alert-info"><?= $nego_message ?></div>
                        <?php endif; ?>

                        <!-- Historique des négociations -->
                        <?php
                        $nego_affichee = false;
                        if (isset($_SESSION['user_id'])) {
                            $user_id = intval($_SESSION['user_id']);
                            $sql = "SELECT * FROM Negociation WHERE article_id=$id AND (acheteur_id=$user_id OR vendeur_id=$user_id) ORDER BY tour ASC";
                            $res = mysqli_query($db_handle, $sql);
                            if ($res && mysqli_num_rows($res) > 0) {
                                echo '<table class="table table-sm"><thead><tr>
                                        <th>Tour</th><th>Offre acheteur</th><th>Contre-offre vendeur</th><th>Etat</th><th>Date</th>
                                    </tr></thead><tbody>';
                                while ($n = mysqli_fetch_assoc($res)) {
                                    echo "<tr>
                                        <td>{$n['tour']}</td>
                                        <td>" . ($n['offre_acheteur'] ? number_format($n['offre_acheteur'],2,',',' ') . " €" : "-") . "</td>
                                        <td>" . ($n['contre_offre_vendeur'] ? number_format($n['contre_offre_vendeur'],2,',',' ') . " €" : "-") . "</td>
                                        <td>{$n['etat']}</td>
                                        <td>{$n['date_action']}</td>
                                    </tr>";
                                }
                                echo '</tbody></table>';
                                $nego_affichee = true;
                            }
                        }

                        // Formulaire pour l'acheteur
                        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $article['vendeur_id']) {
                            $sql = "SELECT * FROM Negociation WHERE article_id=$id AND acheteur_id={$_SESSION['user_id']} ORDER BY tour DESC LIMIT 1";
                            $res = mysqli_query($db_handle, $sql);
                            $nego = $res && mysqli_num_rows($res) ? mysqli_fetch_assoc($res) : null;

                            // 1. Si contre-offre du vendeur, proposer le bouton accepter la contre-offre
if ($nego && $nego['etat'] == 'en cours' && $nego['contre_offre_vendeur'] && $nego['contre_offre_vendeur'] > 0) {
    echo '<div class="alert alert-warning mb-2">
            Le vendeur vous propose une contre-offre de <b>' . number_format($nego['contre_offre_vendeur'],2,',',' ') . ' €</b>.
          </div>
          <form method="post" class="d-inline-block me-2">
            <input type="hidden" name="accept_contre_offre" value="1">
            <input type="hidden" name="tour" value="'.$nego['tour'].'">
            <button type="submit" class="btn btn-success btn-sm">Accepter la contre-offre</button>
          </form>';
    // (optionnel) bouton pour refuser ou proposer encore si tours restants
}


                            if (!$nego || ($nego['etat'] == 'en cours' && $nego['tour'] < 5 && $nego['contre_offre_vendeur'])) {
                                echo '<form method="post" class="mt-3">
                                    <div class="input-group">
                                        <input type="number" name="prix_offre" step="0.01" min="0" class="form-control" placeholder="Votre offre (€)" required>
                                        <button type="submit" name="faire_offre" class="btn btn-success">Proposer</button>
                                    </div>
                                </form>';
                            } elseif ($nego && $nego['etat'] == 'en cours' && !$nego['contre_offre_vendeur']) {
                                echo '<div class="alert alert-secondary">Offre en attente de réponse du vendeur.</div>';
                            } elseif ($nego && $nego['etat'] == 'accepte') {
                                echo '<div class="alert alert-success">Votre offre a été acceptée ! L\'article est à vous.</div>';
                            } elseif ($nego && $nego['etat'] == 'refuse') {
                                echo '<div class="alert alert-danger">Votre offre a été refusée.</div>';
                            }
                        }

                        // Formulaire pour le vendeur
                        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $article['vendeur_id']) {
                            $sql = "SELECT * FROM Negociation WHERE article_id=$id AND etat='en cours' AND (contre_offre_vendeur IS NULL OR contre_offre_vendeur=0) ORDER BY date_action ASC LIMIT 1";
                            $res = mysqli_query($db_handle, $sql);
                            if ($res && $nego = mysqli_fetch_assoc($res)) {
                                echo '<form method="post" class="mt-3">
                                    <input type="hidden" name="acheteur_id" value="'.$nego['acheteur_id'].'">
                                    <input type="hidden" name="tour" value="'.$nego['tour'].'">
                                    <div class="mb-2">
                                        <button type="submit" name="accepter" class="btn btn-success" name="repondre_nego">Accepter l\'offre ('.$nego['offre_acheteur'].' €)</button>
                                        <button type="submit" name="refuser" class="btn btn-danger" name="repondre_nego">Refuser</button>
                                    </div>
                                    <div class="input-group mb-2">
                                        <input type="number" name="prix_contre_offre" step="0.01" min="0" class="form-control" placeholder="Votre contre-offre (€)">
                                        <button type="submit" name="contre_offre" class="btn btn-warning" name="repondre_nego">Contre-offre</button>
                                    </div>
                                    <input type="hidden" name="repondre_nego" value="1">
                                </form>';
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <a href="catalogue.php" class="btn btn-secondary mt-2">Retour au catalogue</a>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
