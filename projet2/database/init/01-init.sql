DROP TABLE IF EXISTS matchs_cotes;
CREATE TABLE matchs_cotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ligue VARCHAR(50) NOT NULL,
    equipe_domicile VARCHAR(50) NOT NULL,
    equipe_exterieur VARCHAR(50) NOT NULL,
    pari_recommande VARCHAR(100) NOT NULL,
    cote DECIMAL(4,2) NOT NULL,
    probabilite_pourcentage INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO matchs_cotes (ligue, equipe_domicile, equipe_exterieur, pari_recommande, cote, probabilite_pourcentage) VALUES
-- LA LIGA (10 matchs)
('La Liga', 'Real Sociedad', 'Osasuna', 'Victoire Real Sociedad', 1.85, 68),
('La Liga', 'Real Madrid', 'FC Barcelone', '+2.5 buts', 1.60, 75),
('La Liga', 'Atletico Madrid', 'Seville FC', 'Victoire Atletico', 1.55, 70),
('La Liga', 'Athletic Bilbao', 'Valence CF', 'Moins de 3.5 buts', 1.35, 82),
('La Liga', 'Girona', 'Villarreal', 'Double chance : Nul ou Villarreal', 1.65, 66),
('La Liga', 'Betis Seville', 'Celta Vigo', 'Victoire Betis', 1.95, 65),
('La Liga', 'Getafe', 'Rayo Vallecano', 'Moins de 2.5 buts', 1.50, 72),
('La Liga', 'Majorque', 'Espanyol', 'Double chance : Majorque ou Nul', 1.25, 85),
('La Liga', 'Alaves', 'Las Palmas', 'Victoire Alaves', 2.10, 67),
('La Liga', 'Valladolid', 'Leganes', 'Moins de 3.5 buts', 1.30, 78),

-- PREMIER LEAGUE (10 matchs)
('Premier League', 'Arsenal', 'Manchester City', '+2.5 buts', 1.75, 70),
('Premier League', 'Liverpool', 'Chelsea', 'Victoire Liverpool', 1.65, 72),
('Premier League', 'Manchester United', 'Everton', 'Moins de 2.5 buts', 1.90, 67),
('Premier League', 'Tottenham', 'West Ham', 'Victoire Tottenham', 1.50, 75),
('Premier League', 'Aston Villa', 'Newcastle', 'Double chance : Nul ou Newcastle', 1.60, 69),
('Premier League', 'Brighton', 'Crystal Palace', 'Victoire Brighton', 1.72, 68),
('Premier League', 'Fulham', 'Brentford', 'Moins de 3.5 buts', 1.40, 77),
('Premier League', 'Wolverhampton', 'Nottingham Forest', 'Double chance : Wolves ou Nul', 1.35, 80),
('Premier League', 'Southampton', 'Leicester City', '+1.5 buts', 1.22, 88),
('Premier League', 'Bournemouth', 'Ipswich Town', 'Victoire Bournemouth', 1.55, 71),

-- LIGUE 1 (9 matchs)
('Ligue 1', 'Toulouse FC', 'FC Nantes', 'Double chance Nul ou Toulouse', 1.35, 75),
('Ligue 1', 'PSG', 'Marseille', '+3.5 buts', 2.10, 66),
('Ligue 1', 'RC Lens', 'Lille OSC', 'Victoire Lens', 1.70, 70),
('Ligue 1', 'Olympique Lyonnais', 'AS Monaco', '+2.5 buts', 1.55, 74),
('Ligue 1', 'Stade Rennais', 'Brest', 'Victoire Rennes', 1.85, 68),
('Ligue 1', 'OGC Nice', 'Montpellier', 'Moins de 3.5 buts', 1.30, 85),
('Ligue 1', 'Strasbourg', 'Reims', 'Double chance : Nul ou Strasbourg', 1.40, 78),
('Ligue 1', 'Le Havre', 'Auxerre', 'Moins de 2.5 buts', 1.65, 69),
('Ligue 1', 'Angers', 'Saint-Etienne', 'Double chance : Saint-Etienne ou Nul', 1.50, 73),

-- SERIE A (10 matchs)
('Serie A', 'Juventus', 'AC Milan', 'Moins de 2.5 buts', 1.65, 72),
('Serie A', 'Inter Milan', 'AS Roma', 'Victoire Inter Milan', 1.45, 80),
('Serie A', 'Napoli', 'Lazio', '+1.5 buts', 1.25, 88),
('Serie A', 'Atalanta', 'Fiorentina', 'Victoire Atalanta', 1.75, 66),
('Serie A', 'Monza', 'Torino', 'Double chance : Nul ou Torino', 1.38, 79),
('Serie A', 'Bologne', 'Parme', 'Victoire Bologne', 1.60, 70),
('Serie A', 'Genoa', 'Empoli', 'Moins de 3.5 buts', 1.28, 85),
('Serie A', 'Hellas Verone', 'Venise', 'Victoire Verone', 1.90, 65),
('Serie A', 'Lecce', 'Udinese', 'Double chance : Lecce ou Nul', 1.42, 75),
('Serie A', 'Cagliari', 'Como', 'Moins de 2.5 buts', 1.70, 68),

-- TOP 14 (7 matchs)
('Top 14', 'Aviron Bayonnais', 'Biarritz Olympique', 'Victoire Bayonne (Safe)', 1.60, 65),
('Top 14', 'Section Paloise', 'Stade Toulousain', 'Victoire Toulouse', 1.45, 78),
('Top 14', 'Union Bordeaux Bègles', 'La Rochelle', 'Moins de 40.5 points', 1.85, 67),
('Top 14', 'Castres Olympique', 'Clermont', 'Victoire Castres', 1.55, 71),
('Top 14', 'Racing 92', 'Toulon', 'Victoire Racing 92', 1.40, 76),
('Top 14', 'Stade Français', 'Lyon OU', 'Victoire Stade Français', 1.50, 73),
('Top 14', 'Montpellier', 'USA Perpignan', 'Moins de 45.5 points', 1.75, 66);
