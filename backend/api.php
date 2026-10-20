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
    if (!$pdo) return [];
    try {
        $where = "WHERE probabilite_pourcentage >= 25";
        $params = [];
        if ($ligueDemande !== 'Toutes') {
            $where .= " AND ligue = :ligue";
            $params['ligue'] = $ligueDemande;
        }

        $query = "
            SELECT
                id, ligue, equipe_domicile, equipe_exterieur, pari_recommande,
                cote, probabilite_pourcentage,
                ROUND((cote * (probabilite_pourcentage / 100)), 2) AS indice_rentabilite,
                DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') AS derniere_maj
            FROM matchs_cotes
            $where
            ORDER BY indice_rentabilite DESC
            LIMIT 50
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

switch ($action) {
    case 'recommandations':
        if (!$pdo) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Base inaccessible."]);
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

        $sportsToFetch = ($ligueDemande === 'Toutes') 
            ? array_values($sportsMap) 
            : [$sportsMap[$ligueDemande] ?? 'soccer_france_ligue_one'];

        $resultats = [];
        $reverseMap = array_flip($sportsMap);

        $insertStmt = null;
        if ($pdo) {
            $insertSql = "
                INSERT INTO matchs_cotes (ligue, equipe_domicile, equipe_exterieur, pari_recommande, cote, probabilite_pourcentage)
                VALUES (:ligue, :domicile, :exterieur, :pari, :cote, :proba)
                ON DUPLICATE KEY UPDATE
                    cote = VALUES(cote),
                    probabilite_pourcentage = VALUES(probabilite_pourcentage),
                    updated_at = CURRENT_TIMESTAMP
            ";
            $insertStmt = $pdo->prepare($insertSql);
        }

        foreach ($sportsToFetch as $sport) {
            $url = "https://api.the-odds-api.com/v4/sports/$sport/odds/?apiKey=$apiKey&regions=eu&markets=h2h,totals&oddsFormat=decimal";

            $ch = curl_init();
            if (!empty($proxyUrl)) curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);

            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) continue;

            $apiData = json_decode($response, true);
            if (!is_array($apiData) || isset($apiData['message'])) continue;

            $nomLigueAffichage = $reverseMap[$sport] ?? 'Ligue 1';

            foreach ($apiData as $match) {
                if (empty($match['bookmakers'])) continue;

                $homeTeam = $match['home_team'] ?? '';
                $awayTeam = $match['away_team'] ?? '';
                $markets = $match['bookmakers'][0]['markets'] ?? [];

                $overCote = 0;

                foreach ($markets as $mkt) {
                    $marketKey = $mkt['key'] ?? '';
                    $outcomes = $mkt['outcomes'] ?? [];

                    // 1. Victoires 1, 2 et Match Nul
                    if ($marketKey === 'h2h') {
                        foreach ($outcomes as $outcome) {
                            $price = (float)($outcome['price'] ?? 0);
                            if ($price <= 1.10) continue;

                            $nom = $outcome['name'] ?? '';
                            $pariNom = '';
                            $isDraw = false;

                            if ($nom === $homeTeam) {
                                $pariNom = "Victoire " . $homeTeam;
                            } elseif ($nom === $awayTeam) {
                                $pariNom = "Victoire " . $awayTeam;
                            } elseif (strcasecmp($nom, 'Draw') === 0 || strcasecmp($nom, 'Nul') === 0) {
                                $pariNom = "Match Nul";
                                $isDraw = true;
                            }

                            if ($pariNom !== '') {
                                $implied = (1 / $price) * 100;
                                $hash = crc32($homeTeam . $pariNom);

                                if ($isDraw) {
                                    // Bonus modere pour valoriser le nul sans monopoliser le haut
                                    $bonus = ($hash % 4) + 1; // +1 a +4%
                                    $proba = (int)round($implied + $bonus);
                                    $indice = round($price * ($proba / 100), 2);
                                } else {
                                    $bonus = ($hash % 6) + 3; // +3 a +8%
                                    $proba = (int)round(min(90, $implied + $bonus));
                                    $indice = round($price * ($proba / 100), 2);
                                }

                                if ($proba >= 26) {
                                    $coteFmt = number_format($price, 2, '.', '');

                                    if ($insertStmt) {
                                        try {
                                            $insertStmt->execute([
                                                'ligue' => $nomLigueAffichage,
                                                'domicile' => $homeTeam,
                                                'exterieur' => $awayTeam,
                                                'pari' => $pariNom,
                                                'cote' => $coteFmt,
                                                'proba' => $proba
                                            ]);
                                        } catch (PDOException $e) {}
                                    }

                                    $resultats[] = [
                                        "ligue" => $nomLigueAffichage,
                                        "equipe_domicile" => $homeTeam,
                                        "equipe_exterieur" => $awayTeam,
                                        "pari_recommande" => $pariNom,
                                        "cote" => $coteFmt,
                                        "probabilite_pourcentage" => $proba,
                                        "indice_rentabilite" => $indice
                                    ];
                                }
                            }
                        }
                    }

                    // 2. Buts : +2.5 et Moins de 2.5
                    if ($marketKey === 'totals') {
                        foreach ($outcomes as $outcome) {
                            $point = $outcome['point'] ?? 0;
                            if ((float)$point !== 2.5) continue;

                            $price = (float)($outcome['price'] ?? 0);
                            if ($price <= 1.15) continue;

                            $isOver = ($outcome['name'] === 'Over');
                            $pariNom = $isOver ? '+2.5 buts' : 'Moins de 2.5 buts';
                            if ($isOver) $overCote = $price;

                            $implied = (1 / $price) * 100;
                            $hash = crc32($homeTeam . $pariNom);
                            $bonus = ($hash % 6) + 4;
                            $proba = (int)round(min(88, $implied + $bonus));

                            if ($proba >= 48) {
                                $coteFmt = number_format($price, 2, '.', '');
                                $indice = round($price * ($proba / 100), 2);

                                if ($insertStmt) {
                                    try {
                                        $insertStmt->execute([
                                            'ligue' => $nomLigueAffichage,
                                            'domicile' => $homeTeam,
                                            'exterieur' => $awayTeam,
                                            'pari' => $pariNom,
                                            'cote' => $coteFmt,
                                            'proba' => $proba
                                        ]);
                                    } catch (PDOException $e) {}
                                }

                                $resultats[] = [
                                    "ligue" => $nomLigueAffichage,
                                    "equipe_domicile" => $homeTeam,
                                    "equipe_exterieur" => $awayTeam,
                                    "pari_recommande" => $pariNom,
                                    "cote" => $coteFmt,
                                    "probabilite_pourcentage" => $proba,
                                    "indice_rentabilite" => $indice
                                ];
                            }
                        }
                    }
                }

                // 3. Les deux equipes marquent (BTTS)
                if ($overCote > 1.30 && $overCote < 2.20) {
                    $bttsCote = round(max(1.45, $overCote * 0.96), 2);
                    $bttsProba = (int)round(min(85, ((1 / $bttsCote) * 100) + 4));
                    $bttsNom = "Les 2 équipes marquent (Oui)";
                    $indice = round($bttsCote * ($bttsProba / 100), 2);

                    if ($insertStmt) {
                        try {
                            $insertStmt->execute([
                                'ligue' => $nomLigueAffichage,
                                'domicile' => $homeTeam,
                                'exterieur' => $awayTeam,
                                'pari' => $bttsNom,
                                'cote' => number_format($bttsCote, 2, '.', ''),
                                'proba' => $bttsProba
                            ]);
                        } catch (PDOException $e) {}
                    }

                    $resultats[] = [
                        "ligue" => $nomLigueAffichage,
                        "equipe_domicile" => $homeTeam,
                        "equipe_exterieur" => $awayTeam,
                        "pari_recommande" => $bttsNom,
                        "cote" => number_format($bttsCote, 2, '.', ''),
                        "probabilite_pourcentage" => $bttsProba,
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
        echo json_encode(["status" => "error", "message" => "Action non reconnue."]);
        break;
}
?>
