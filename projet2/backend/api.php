<?php
// En-têtes pour une API REST standard
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Sécurisation et optimisation de la connexion PDO
try {
    $dsn = 'mysql:host=db;dbname=' . getenv('MYSQL_DATABASE') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false // Sécurité contre les injections SQL complexes
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Base de données inaccessible."]);
    exit;
}

// 2. Routage de l'API
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'recommandations';

switch ($action) {
    case 'recommandations':
        try {
            // L'algorithme cherche les paris Safe (probabilite >= 65%)
            // et calcule la rentabilité mathématique (Cote * (Proba/100))
            $query = "
                SELECT
                    id,
                    ligue,
                    equipe_domicile,
                    equipe_exterieur,
                    pari_recommande,
                    cote,
                    probabilite_pourcentage,
                    ROUND((cote * (probabilite_pourcentage / 100)), 2) as indice_rentabilite
                FROM matchs_cotes
                WHERE probabilite_pourcentage >= 65
                ORDER BY indice_rentabilite DESC
                LIMIT 50
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $resultats = $stmt->fetchAll();

            http_response_code(200);
            echo json_encode(["data" => $resultats]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Erreur de traitement des statistiques."]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Point d'accès non reconnu."]);
        break;
}
?>
