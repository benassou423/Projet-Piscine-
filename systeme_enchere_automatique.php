<?php
// Classe pour gérer le système d'enchères automatiques
class SystemeEnchereAutomatique {
    var $db_handle; // poignée de connexion à la BDD

    // Constructeur : on passe la poignée de connexion à la BDD en argument
    function __construct($db_handle) {
        $this->db_handle = $db_handle;
    }

    // Fonction pour placer ou mettre à jour une enchère automatique
    // Paramètres :
    //   $article_id : identifiant de l'article sur lequel on veut miser
    //   $acheteur_id : identifiant de l'acheteur qui place l'enchère
    //   $prix_max : montant maximum que l'acheteur est prêt à payer
    function placerEnchereAutomatique($article_id, $acheteur_id, $prix_max) {
        // On définit le fuseau horaire pour être sûr que les dates sont cohérentes
        date_default_timezone_set('Europe/Paris');

        // 1) Récupérer les infos de l'article pour vérifier qu'il existe et qu'il est en mode enchère
        $sql_article = "
            SELECT *
            FROM article
            WHERE id = ?
              AND type_vente = 'enchere'
              AND statut = 'disponible'
        ";
     $stmt = mysqli_prepare($this->db_handle, $sql_article);
// Prépare la requête SQL avec “?” pour chaque variable,

mysqli_stmt_bind_param($stmt, "i", $article_id);
// Lie la variable PHP $article_id au premier “?” de la requête.

mysqli_stmt_execute($stmt);
// Exécute la requête préparée, en envoyant la vraie valeur de $article_id à MySQL.

$result_article = mysqli_stmt_get_result($stmt);
// Récupère le résultat sous forme d’un objet mysqli_result, comme pour mysqli_query().

$article = mysqli_fetch_assoc($result_article);
// Lit la première ligne du résultat et la transforme en tableau associatif.
// Si aucune ligne, $article vaut null.


        // Si rien n'est retourné, l'article n'existe pas ou n'est pas en vente aux enchères
        if (!$article) {
            return ['success' => false, 'message' => 'Article non trouvé ou enchère non disponible'];
        }

        // 2) Vérifier que l'enchère est bien en cours : entre date_debut_enchere et date_fin_enchere
        $maintenant = date('Y-m-d H:i:s');
        if ($maintenant < $article['date_debut_enchere'] 
            || $maintenant > $article['date_fin_enchere']) {
            return ['success' => false, 'message' => 'L\'enchère n\'est pas en cours'];
        }

        // 3) Vérifier que le vendeur ne tente pas de miser sur son propre article
        if ($acheteur_id == $article['vendeur_id']) {
            return ['success' => false, 'message' => 'Le vendeur ne peut pas enchérir sur son propre article'];
        }

        // 4) Récupérer le prix actuel de l'article ou, s'il n'existe pas encore, le prix initial
        $prix_actuel = $article['prix_actuel'] ?? $article['prix_initial'];
        // On vérifie que l'enchère maximale proposée est supérieure au prix actuel
        if ($prix_max <= $prix_actuel) {
            return ['success' => false, 'message' => 'Votre enchère maximale doit être supérieure au prix actuel'];
        }

        // 5) Vérifier si l'utilisateur a déjà une enchère automatique pour cet article
        $sql_existing = "
            SELECT *
            FROM enchere
            WHERE article_id = ?
              AND acheteur_id = ?
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql_existing);
        mysqli_stmt_bind_param($stmt, "ii", $article_id, $acheteur_id);
        mysqli_stmt_execute($stmt);
        $result_existing = mysqli_stmt_get_result($stmt);
        $existing_bid = mysqli_fetch_assoc($result_existing);

        if ($existing_bid) {
            // 6a) Si l'enchère existe déjà, on la met à jour : on change le prix_max et on met la date_offre à maintenant
            $sql_update = "
                UPDATE enchere
                SET prix_max = ?, date_offre = NOW()
                WHERE article_id = ?
                  AND acheteur_id = ?
            ";
            $stmt = mysqli_prepare($this->db_handle, $sql_update);
            mysqli_stmt_bind_param($stmt, "dii", $prix_max, $article_id, $acheteur_id);
            $success = mysqli_stmt_execute($stmt);

            if ($success) {
                // 6a.1) Si la mise à jour a fonctionné, on recalcule le prix actuel de l'article
                $this->recalculerPrixActuel($article_id);
                return ['success' => true, 'message' => 'Votre enchère maximale a été mise à jour'];
            }
        } else {
            // 6b) Si l'enchère n'existe pas, on la crée avec INSERT
            $sql_insert = "
                INSERT INTO enchere (article_id, acheteur_id, prix_max, date_offre, date_enchere)
                VALUES (?, ?, ?, NOW(), ?)
            ";
            $stmt = mysqli_prepare($this->db_handle, $sql_insert);
            $date_fin = $article['date_fin_enchere'];
            mysqli_stmt_bind_param($stmt, "iids", $article_id, $acheteur_id, $prix_max, $date_fin);
            $success = mysqli_stmt_execute($stmt);

            if ($success) {
                // 6b.1) Si l'insertion a fonctionné, on recalcule aussi le prix actuel
                $this->recalculerPrixActuel($article_id);
                return ['success' => true, 'message' => 'Votre enchère a été enregistrée'];
            }
        }

        // 7) Si on arrive ici, c'est que l'insertion ou la mise à jour a échoué
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'enchère'];
    }

    // Fonction pour recalculer le prix actuel de l'article en se basant sur toutes les enchères enregistrées
    function recalculerPrixActuel($article_id) {
        // 1) Récupérer les données de l'article depuis la BDD pour avoir le prix_initial et le prix_actuel
        $sql_article = "SELECT * FROM article WHERE id = ?";
        $stmt = mysqli_prepare($this->db_handle, $sql_article);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        $result_article = mysqli_stmt_get_result($stmt);
        $article = mysqli_fetch_assoc($result_article);

        // Si l'article n'existe pas, on ne peut rien faire
        if (!$article) {
            return false;
        }

        // 2) Récupérer toutes les enchères pour cet article, triées par prix_max décroissant puis date_offre croissant
        $sql_encheres = "
            SELECT prix_max
            FROM enchere
            WHERE article_id = ?
            ORDER BY prix_max DESC, date_offre ASC
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql_encheres);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        $result_encheres = mysqli_stmt_get_result($stmt);

        $encheres = [];
        while ($row = mysqli_fetch_assoc($result_encheres)) {
            $encheres[] = $row['prix_max'];
        }

        // 3) Calculer le nouveau prix actuel selon le nombre d'enchères :
        //    - Si aucune enchère, le prix reste le prix initial
        //    - Si une seule enchère, le prix actuel devient prix_initial + 1
        //    - Si plusieurs enchères, le prix actuel est min(deuxième_enchère + 1, meilleure_enchère)
        $prix_initial = $article['prix_initial'];
        if (count($encheres) === 0) {
            $nouveau_prix_actuel = $prix_initial;
        } elseif (count($encheres) === 1) {
            $nouveau_prix_actuel = $prix_initial + 1;
        } else {
            $meilleure_enchere = $encheres[0];
            $deuxieme_enchere = $encheres[1];
            $nouveau_prix_actuel = min($deuxieme_enchere + 1, $meilleure_enchere);
        }

        // 4) Mettre à jour le prix_actuel dans la table article
        $sql_update = "UPDATE article SET prix_actuel = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->db_handle, $sql_update);
        mysqli_stmt_bind_param($stmt, "di", $nouveau_prix_actuel, $article_id);
        return mysqli_stmt_execute($stmt);
    }

    // Fonction pour obtenir l'enchère gagnante d'un article (la plus haute, ou en cas d'égalité, la plus ancienne)
    function obtenirEnchereGagnante($article_id) {
        $sql = "
            SELECT e.*, u.nom, u.prenom, u.email
            FROM enchere e
            JOIN Utilisateur u ON e.acheteur_id = u.id
            WHERE e.article_id = ?
            ORDER BY e.prix_max DESC, e.date_offre ASC
            LIMIT 1
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    // Fonction pour vérifier toutes les enchères terminées et clore celles dont la date est passée
    function verifierEncheresExpirees() {
        date_default_timezone_set('Europe/Paris');
        $maintenant = date('Y-m-d H:i:s');

        // 1) Sélectionner tous les articles en mode enchère dont la date de fin est passée et qui sont encore "disponible"
        $sql_terminees = "
            SELECT DISTINCT a.id AS article_id, a.titre AS article_nom, a.vendeur_id
            FROM article a
            WHERE a.type_vente = 'enchere'
              AND a.date_fin_enchere <= ?
              AND a.statut = 'disponible'
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql_terminees);
        mysqli_stmt_bind_param($stmt, "s", $maintenant);
        mysqli_stmt_execute($stmt);
        $result_terminees = mysqli_stmt_get_result($stmt);

        // 2) Pour chaque article terminé, déterminer le gagnant ou non
        while ($result_terminees && $enchere_finie = mysqli_fetch_assoc($result_terminees)) {
            $article_id  = $enchere_finie['article_id'];
            $article_nom = $enchere_finie['article_nom'];
            $vendeur_id  = $enchere_finie['vendeur_id'];

            // 2a) Récupérer l'enchère gagnante si elle existe
            $enchere_gagnante = $this->obtenirEnchereGagnante($article_id);

            if ($enchere_gagnante) {
                // 2a.1) S'il y a un gagnant
                $gagnant_id = $enchere_gagnante['acheteur_id'];
                $prix_final = $this->calculerPrixFinal($article_id);

                // Mettre l'article en statut 'vendu'
                $sql_update = "UPDATE article SET statut = 'vendu' WHERE id = ?";
                $stmt = mysqli_prepare($this->db_handle, $sql_update);
                mysqli_stmt_bind_param($stmt, "i", $article_id);
                mysqli_stmt_execute($stmt);

                // Marquer l'enchère gagnante avec l'état 'gagnant'
                $sql_update_winner = "
                    UPDATE enchere
                    SET etat = 'gagnant'
                    WHERE article_id = ?
                      AND acheteur_id = ?
                ";
                $stmt_winner = mysqli_prepare($this->db_handle, $sql_update_winner);
                mysqli_stmt_bind_param($stmt_winner, "ii", $article_id, $gagnant_id);
                mysqli_stmt_execute($stmt_winner);

                // Marquer toutes les autres enchères pour cet article comme 'perdu'
                $sql_update_losers = "
                    UPDATE enchere
                    SET etat = 'perdu'
                    WHERE article_id = ?
                      AND acheteur_id != ?
                ";
                $stmt_losers = mysqli_prepare($this->db_handle, $sql_update_losers);
                mysqli_stmt_bind_param($stmt_losers, "ii", $article_id, $gagnant_id);
                mysqli_stmt_execute($stmt_losers);

                // Envoyer une notification au gagnant
                $contenu_gagnant =
                    "Félicitations ! Vous avez gagné l'enchère '" .
                    mysqli_real_escape_string($this->db_handle, $article_nom) .
                    "' pour " . number_format($prix_final, 2) . "€.";
                $this->envoyerNotification($gagnant_id, $contenu_gagnant, $article_id);

                // Envoyer une notification au vendeur
                $contenu_vendeur =
                    "Votre article '" . mysqli_real_escape_string($this->db_handle, $article_nom) .
                    "' a été vendu pour " . number_format($prix_final, 2) . "€.";
                $this->envoyerNotification($vendeur_id, $contenu_vendeur, $article_id);

                // Notifier tous les autres enchérisseurs qu'ils ont perdu
                $this->notifierPerdants($article_id, $gagnant_id, $article_nom);
            } else {
                // 2b) S'il n'y a pas de gagnant (aucune enchère posée)
                $contenu_vendeur =
                    "L'enchère pour votre article '" . mysqli_real_escape_string($this->db_handle, $article_nom) .
                    "' s'est terminée sans aucune offre.";
                $this->envoyerNotification($vendeur_id, $contenu_vendeur, $article_id);
            }
        }
    }

    // Fonction pour calculer le prix final à payer pour un article (soit prix_actuel, soit prix_initial si pas d'enchère)
    function calculerPrixFinal($article_id) {
        $sql = "SELECT prix_actuel, prix_initial FROM article WHERE id = ?";
        $stmt = mysqli_prepare($this->db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $article = mysqli_fetch_assoc($result);

        // Si prix_actuel existe, on le renvoie ; sinon, on renvoie le prix_initial
        return $article['prix_actuel'] ?? $article['prix_initial'];
    }

    // Fonction pour envoyer une notification à un utilisateur (insère dans la table notification)
    function envoyerNotification($user_id, $contenu, $article_id = null) {
        $sql = "
            INSERT INTO notification (user_id, contenu, date_creation, article_id)
            VALUES (?, ?, NOW(), ?)
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $user_id, $contenu, $article_id);
        return mysqli_stmt_execute($stmt);
    }

    // Fonction pour notifier tous les perdants d'une enchère (tous sauf le gagnant)
    function notifierPerdants($article_id, $gagnant_id, $article_nom) {
        $sql_perdants = "
            SELECT DISTINCT acheteur_id
            FROM enchere
            WHERE article_id = ?
              AND acheteur_id != ?
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql_perdants);
        mysqli_stmt_bind_param($stmt, "ii", $article_id, $gagnant_id);
        mysqli_stmt_execute($stmt);
        $result_perdants = mysqli_stmt_get_result($stmt);

        // Pour chaque perdant, on envoie une notification
        while ($perdant = mysqli_fetch_assoc($result_perdants)) {
            $contenu_perdant =
                "L'enchère pour l'article '" . mysqli_real_escape_string($this->db_handle, $article_nom) .
                "' est terminée, vous n'avez pas gagné.";
            $this->envoyerNotification($perdant['acheteur_id'], $contenu_perdant, $article_id);
        }
    }

    // Fonction pour obtenir le statut d'enchère pour un utilisateur sur un article donné
    function obtenirStatutEnchereUtilisateur($article_id, $acheteur_id) {
        $sql_user_bid = "
            SELECT *
            FROM enchere
            WHERE article_id = ?
              AND acheteur_id = ?
        ";
        $stmt = mysqli_prepare($this->db_handle, $sql_user_bid);
        mysqli_stmt_bind_param($stmt, "ii", $article_id, $acheteur_id);
        mysqli_stmt_execute($stmt);
        $result_user_bid = mysqli_stmt_get_result($stmt);
        $user_bid = mysqli_fetch_assoc($result_user_bid);

        // Si l'utilisateur n'a pas d'enchère sur cet article
        if (!$user_bid) {
            return ['a_enchere' => false];
        }

        // Vérifier si c'est l'enchérisseur gagnant actuel
        $enchere_gagnante = $this->obtenirEnchereGagnante($article_id);
        $est_gagnant = $enchere_gagnante
            && $enchere_gagnante['acheteur_id'] == $acheteur_id;

        // Retourner les infos de l'enchère de l'utilisateur : s'il a enchéri, son prix_max, s'il est gagnant, et la date de son offre
        return [
            'a_enchere'  => true,
            'prix_max'   => $user_bid['prix_max'],
            'est_gagnant'=> $est_gagnant,
            'date_offre' => $user_bid['date_offre']
        ];
    }
}
?>
