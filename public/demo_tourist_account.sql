-- Import this if you want a guaranteed tourist login on your local database.
-- Password: tourist123
INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, statut, date_creation, num_tel, role_id, theme_preference, language)
SELECT 'Touriste', 'Demo', 'tourist.demo@tabaany.local', '$2y$13$jKHM0QUCsB5bG/rHnVh6MO6/g5TwDdQDQBRYkw3kWRN.dxoqrpx5i', 'ACTIF', NOW(), 11111111, 1, 'SYSTEM', 'fr'
WHERE NOT EXISTS (SELECT 1 FROM utilisateur WHERE email = 'tourist.demo@tabaany.local');
