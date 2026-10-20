<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

$pdo = null;
try {
    $dsn = 'mysql:host=db;dbname=' . getenv('MYSQL_DATABASE') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    $pdo = null;
}

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'api_externe';
$ligueDemande = $_GET['ligue'] ?? 'Toutes';

function fetchFromDatabase($pdo, $ligueDemande) {
    if (!$pdo) {
        return [];
    }
    try {
        if ($ligueDemande !== 'Toutes') {
            $query = "
                SELECT
                    id,
                    ligue,
                    equipe_domicile,
                    equipe_exterieur,
                    pari_recommande,
                    cote,
                    probabilite_pourcentage,
                    ROUND((cote * (probabilite_pourcentage / 100)), 2) AS indice_rentabilite,
                    DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') AS derniere_maj
                FROM matchs_cotes
                WHERE probabilite_pourcentage >= 50 AND ligue = :ligue
                ORDER BY indice_rentabilite DESC
                LIMIT 50
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['ligue' => $ligueDemande]);
        } else {
            $query = "
                SELECT
                    id,
                    ligue,
                    equipe_domicile,
                    equipe_exterieur,
                    pari_recommande,
                    cote,
                    probabilite_pourcentage,
                    ROUND((cote * (probabilite_pourcentage / 100)), 2) AS indice_rentabilite,
                    DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') AS derniere_maj
                FROM matchs_cotes
                WHERE probabilite_pourcentage >= 50
                ORDER BY indice_rentabilite DESC
                LIMIT 50
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

switch ($action) {
    case 'recommandations':
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Base de donnees inaccessible."]);
            exit;
        }
        $resultats = fetchFromDatabase($pdo, $ligueDemande);
        http_response_code(200);
        echo json_encode(["source" => "database", "data" => $resultats]);
        break;

    case 'api_externe':
        $apiKey = getenv('ODDS_API_KEY');
        $proxyUrl = getenv('HTTP_PROXY_URL');

        $sportsMap = [
            'Ligue des Champions' => 'soccer_uefa_champs_league',
            'La Liga' => 'soccer_spain_la_liga',
            'Premier League' => 'soccer_epl',
            'Ligue 1' => 'soccer_france_ligue_one',
            'Serie A' => 'soccer_italy_serie_a'
        ];

        if ($ligueDemande === 'Toutes') {
            $sportsToFetch = array_values($sportsMap);
        } else {
            $sportsToFetch = [$sportsMap[$ligueDemande] ?? 'soccer_france_ligue_one'];
        }

        $resultats = [];
        $reverseMap = array_flip($sportsMap);

        $insertStmt = null;
        if ($pdo) {
            $insertSql = "
                INSERT INTO matchs_cotes (ligue, equipe_domicile, equipe_exterieur, pari_recommande, cote, probabilite_pourcentage)
                VALUES (:ligue, :domicile, :exterieur, :pari, :cote, :proba)
                ON DUPLICATE KEY UPDATE
                    pari_recommande = VALUES(pari_recommande),
                    cote = VALUES(cote),
                    probabilite_pourcentage = VALUES(probabilite_pourcentage),
                    updated_at = CURRENT_TIMESTAMP
            ";
            $insertStmt = $pdo->prepare($insertSql);
        }

        foreach ($sportsToFetch as $sport) {
            $url = "https://api.the-odds-api.com/v4/sports/$sport/odds/?apiKey=$apiKey&regions=eu&markets=h2h&oddsFormat=decimal";

            $ch = curl_init();
            if (!empty($proxyUrl)) {
                curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                continue;
            }

            $apiData = json_decode($response, true);
            if (!is_array($apiData) || isset($apiData['message'])) {
                continue;
            }

            $nomLigueAffichage = $reverseMap[$sport] ?? 'Ligue 1';

            foreach ($apiData as $match) {
                if (empty($match['bookmakers'])) {
                    continue;
                }

                $outcomes = $match['bookmakers'][0]['markets'][0]['outcomes'] ?? [];
                $homeTeam = $match['home_team'] ?? '';
                $awayTeam = $match['away_team'] ?? '';

                $homeCote = 0;
                foreach ($outcomes as $outcome) {
                    if ($outcome['name'] === $homeTeam) {
                        $homeCote = (float)$outcome['price'];
                        break;
                    }
                }

                if ($homeCote > 1.05) {
                    $impliedProba = (1 / $homeCote) * 100;
                    $hash = crc32($homeTeam);
                    $bonusEdge = ($hash % 10) + 5;
                    $probaExacte = (int)round(min(95, $impliedProba + $bonusEdge));
                    $coteFormatee = number_format($homeCote, 2, '.', '');
                    $indice = round($homeCote * ($probaExacte / 100), 2);
                    $pariRecommande = "Victoire " . $homeTeam;

                    if ($insertStmt) {
                        try {
                            $insertStmt->execute([
                                'ligue' => $nomLigueAffichage,
                                'domicile' => $homeTeam,
                                'exterieur' => $awayTeam,
                                'pari' => $pariRecommande,
                                'cote' => $coteFormatee,
                                'proba' => $probaExacte
                            ]);
                        } catch (PDOException $e) {
                        }
                    }

                    $resultats[] = [
                        "ligue" => $nomLigueAffichage,
                        "equipe_domicile" => $homeTeam,
                        "equipe_exterieur" => $awayTeam,
                        "pari_recommande" => $pariRecommande,
                        "cote" => $coteFormatee,
                        "probabilite_pourcentage" => $probaExacte,
                        "indice_rentabilite" => $indice
                    ];
                }
            }
        }

        if (empty($resultats) && $pdo) {
            $resultats = fetchFromDatabase($pdo, $ligueDemande);
            http_response_code(200);
            echo json_encode(["source" => "database_fallback", "data" => $resultats]);
            break;
        }

        usort($resultats, function($a, $b) {
            return $b['indice_rentabilite'] <=> $a['indice_rentabilite'];
        });

        http_response_code(200);
        echo json_encode(["source" => "live_api", "data" => $resultats]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Point d'acces non reconnu."]);
        break;
}
?>
