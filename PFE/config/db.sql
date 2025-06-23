-- Drop and recreate the database
DROP DATABASE IF EXISTS mentora2;
CREATE DATABASE mentora2;
USE mentora2;

-- ======================================
-- Table: Utilisateur
-- ======================================
CREATE TABLE Utilisateur (
    idUtilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nomUtilisateur VARCHAR(50) NOT NULL,
    prenomUtilisateur VARCHAR(50) NOT NULL,
    emailUtilisateur VARCHAR(200) NOT NULL UNIQUE,
    motDePasse VARCHAR(255) NOT NULL,
    role ENUM('etudiant', 'mentor') NOT NULL,
    ville VARCHAR(100),
    photoUrl VARCHAR(255) DEFAULT 'default_avatar.png',
    verified BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX idx_email (emailUtilisateur)
);

-- ======================================
-- Table: Etudiant  <-- THIS WAS MISSING
-- ======================================
CREATE TABLE Etudiant (
    idEtudiant INT AUTO_INCREMENT PRIMARY KEY,
    niveau VARCHAR(50) DEFAULT 'Non spécifié',
    sujetRecherche VARCHAR(255) DEFAULT 'Besoin d\'aide générale',
    idUtilisateur INT NOT NULL UNIQUE,
    FOREIGN KEY (idUtilisateur) REFERENCES Utilisateur(idUtilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ======================================
-- Table: Mentor
-- ======================================
CREATE TABLE Mentor (
    idMentor INT AUTO_INCREMENT PRIMARY KEY,
    competences TEXT,
    idUtilisateur INT NOT NULL UNIQUE,
    FOREIGN KEY (idUtilisateur) REFERENCES Utilisateur(idUtilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ======================================
-- Table: Disponibilite
-- ======================================
CREATE TABLE Disponibilite (
    idDisponibilite INT AUTO_INCREMENT PRIMARY KEY,
    jourSemaine ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche') NOT NULL,
    heureDebut TIME NOT NULL,
    heureFin TIME NOT NULL,
    idUtilisateur INT NOT NULL,
    FOREIGN KEY (idUtilisateur) REFERENCES Utilisateur(idUtilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_dispo_user_day (idUtilisateur, jourSemaine),
    CHECK (heureFin > heureDebut)
);

-- ======================================
-- Table: Administrateur
-- ======================================
CREATE TABLE Administrateur (
    idAdministrateur INT AUTO_INCREMENT PRIMARY KEY,
    nomAdministrateur VARCHAR(50) NOT NULL,
    prenomAdministrateur VARCHAR(50) NOT NULL,
    emailAdministrateur VARCHAR(200) NOT NULL UNIQUE,
    motDePasse VARCHAR(255) NOT NULL
);

-- ======================================
-- Table: Session
-- ======================================
CREATE TABLE Session (
    idSession INT AUTO_INCREMENT PRIMARY KEY,
    titreSession VARCHAR(150) NOT NULL,
    sujet VARCHAR(100),
    dateSession DATE NOT NULL,
    heureSession TIME NOT NULL,
    statutSession ENUM('en_attente', 'validee', 'annulee', 'terminee', 'disponible') NOT NULL,
    lienReunion VARCHAR(255),
    typeSession ENUM('en_ligne', 'presentiel') DEFAULT 'en_ligne',
    tarifSession DECIMAL(10, 2) DEFAULT 0.00,
    duree_minutes INT,
    niveau VARCHAR(50),
    idEtudiantDemandeur INT,
    idMentorAnimateur INT,
    FOREIGN KEY (idEtudiantDemandeur) REFERENCES Etudiant(idEtudiant) ON DELETE SET NULL,
    FOREIGN KEY (idMentorAnimateur) REFERENCES Mentor(idMentor) ON DELETE SET NULL,
    INDEX idx_mentor_date (idMentorAnimateur, dateSession)
);

-- ======================================
-- Table: Participation
-- ======================================
CREATE TABLE Participation (
    idParticipation INT AUTO_INCREMENT PRIMARY KEY,
    idEtudiant INT NOT NULL,
    idSession INT NOT NULL,
    notation INT CHECK (notation BETWEEN 1 AND 5),
    commentaire TEXT,
    FOREIGN KEY (idEtudiant) REFERENCES Etudiant(idEtudiant)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idSession) REFERENCES Session(idSession)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ======================================
-- Table: Animation
-- ======================================
CREATE TABLE Animation (
    idMentor INT NOT NULL,
    idSession INT NOT NULL,
    remarque TEXT,
    PRIMARY KEY (idMentor, idSession),
    FOREIGN KEY (idMentor) REFERENCES Mentor(idMentor)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idSession) REFERENCES Session(idSession)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ======================================
-- Table: Message
-- ======================================
CREATE TABLE Message (
    idMessage INT AUTO_INCREMENT PRIMARY KEY,
    idExpediteur INT NOT NULL,
    idDestinataire INT NOT NULL,
    contenuMessage TEXT NOT NULL,
    dateEnvoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estLue BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (idExpediteur) REFERENCES Utilisateur(idUtilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idDestinataire) REFERENCES Utilisateur(idUtilisateur)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ======================================
-- Table: Badge
-- ======================================
CREATE TABLE Badge (
    idBadge INT AUTO_INCREMENT PRIMARY KEY,
    nomBadge VARCHAR(100) NOT NULL,
    descriptionBadge TEXT NOT NULL
);

-- ======================================
-- Table: Attribution
-- ======================================
CREATE TABLE Attribution (
    idAttribution INT AUTO_INCREMENT PRIMARY KEY,
    idBadge INT NOT NULL,
    idUtilisateur INT NOT NULL,
    dateAttribution DATE NOT NULL,
    FOREIGN KEY (idBadge) REFERENCES Badge(idBadge) ON DELETE CASCADE,
    FOREIGN KEY (idUtilisateur) REFERENCES Utilisateur(idUtilisateur) ON DELETE CASCADE
);

-- ======================================
-- Table: Ressource
-- ======================================
CREATE TABLE Ressource (
    idRessource INT AUTO_INCREMENT PRIMARY KEY,
    titreRessource VARCHAR(100) NOT NULL,
    cheminRessource VARCHAR(255) NOT NULL,
    typeFichier ENUM('pdf', 'docx', 'pptx', 'video', 'audio', 'image') NOT NULL,
    idUtilisateur INT NOT NULL,
    FOREIGN KEY (idUtilisateur) REFERENCES Utilisateur(idUtilisateur) ON DELETE CASCADE
);

-- ======================================
-- Table: Notification
-- ======================================
CREATE TABLE Notification (
    idNotification INT AUTO_INCREMENT PRIMARY KEY,
    idUtilisateur INT NOT NULL,
    typeNotification ENUM('session', 'message', 'badge') NOT NULL,
    contenuNotification VARCHAR(200) NOT NULL,
    estParcourue BOOLEAN DEFAULT FALSE,
    dateNotification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idUtilisateur) REFERENCES Utilisateur(idUtilisateur) ON DELETE CASCADE
);

-- ======================================
-- Table: Paiement
-- ======================================
CREATE TABLE Paiement (
    idPaiement INT AUTO_INCREMENT PRIMARY KEY,
    idSession INT NOT NULL,
    idEtudiant INT NOT NULL,
    idMentor INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    statut ENUM('en_attente', 'effectué', 'échoué') DEFAULT 'en_attente',
    datePaiement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modePaiement ENUM('carte', 'paypal', 'stripe') DEFAULT 'carte',
    FOREIGN KEY (idSession) REFERENCES Session(idSession),
    FOREIGN KEY (idEtudiant) REFERENCES Etudiant(idEtudiant),
    FOREIGN KEY (idMentor) REFERENCES Mentor(idMentor)
);

-- ======================================
-- Données de test / initiales
-- ======================================

-- Inserting into Utilisateur
INSERT INTO Utilisateur (nomUtilisateur, prenomUtilisateur, emailUtilisateur, motDePasse, role, ville)
VALUES 
('Dupont', 'Alice', 'alice.dupont@example.com', 'hashed_pwd1', 'etudiant', 'Paris'),
('Martin', 'Bob', 'bob.martin@example.com', 'hashed_pwd2', 'mentor', 'Lyon'),
('Leclerc', 'Claire', 'claire.leclerc@example.com', 'hashed_pwd3', 'etudiant', 'Marseille'),
('Durand', 'David', 'david.durand@example.com', 'hashed_pwd4', 'mentor', 'Toulouse'),
('Bernard', 'Emma', 'emma.bernard@example.com', 'hashed_pwd5', 'etudiant', 'Nice');

-- Inserting into Etudiant
INSERT INTO Etudiant (niveau, sujetRecherche, idUtilisateur)
VALUES 
('L1 Informatique', 'Programmation en Python', 1),
('L2 Mathématiques', 'Statistiques avancées', 3),
('L3 Physique', 'Optique', 5);

-- Inserting into Mentor
INSERT INTO Mentor (competences, idUtilisateur)
VALUES 
('Python, Java, Algorithmique', 2),
('Analyse de données, R, Machine Learning', 4);

-- Inserting into Disponibilite
INSERT INTO Disponibilite (jourSemaine, heureDebut, heureFin, idUtilisateur)
VALUES 
('lundi', '09:00:00', '12:00:00', 2),
('mardi', '14:00:00', '17:00:00', 4),
('mercredi', '10:00:00', '13:00:00', 2),
('jeudi', '15:00:00', '18:00:00', 4),
('vendredi', '08:00:00', '10:00:00', 2);

-- Inserting into Administrateur
INSERT INTO Administrateur (nomAdministrateur, prenomAdministrateur, emailAdministrateur, motDePasse)
VALUES 
('Admin', 'Super', 'admin@mentora.com', 'admin_hash_pwd'),
('Gérant', 'Nina', 'nina.admin@mentora.com', 'admin_hash_pwd2');

INSERT INTO Session (titreSession, sujet, dateSession, heureSession, statutSession, typeSession, tarifSession, duree_minutes, niveau, idEtudiantDemandeur, idMentorAnimateur)
VALUES 
('Initiation à Python', 'Informatique', '2025-06-25', '10:00:00', 'validee', 'en_ligne', 20.00, 60, 'L1', 1, 1),
('Analyse de données R', 'Informatique', '2025-06-26', '14:00:00', 'en_attente', 'presentiel', 30.00, 90, 'L2', 2, 2),
('Optique de base', 'Physique', '2025-06-27', '09:00:00', 'validee', 'en_ligne', 15.00, 45, 'L3', 3, 2),
('Machine Learning 101', 'Informatique', '2025-07-01', '11:00:00', 'terminee', 'en_ligne', 40.00, 120, 'L2', 2, 2),
('Introduction à Java', 'Informatique', '2025-07-02', '13:00:00', 'annulee', 'presentiel', 25.00, 60, 'L1', 1, 1);


-- Inserting into Participation
INSERT INTO Participation (idEtudiant, idSession, notation, commentaire)
VALUES 
(1, 1, 5, 'Très clair et bien expliqué.'),
(2, 2, 4, 'Un peu rapide mais utile.'),
(3, 3, 5, 'Excellente session.'),
(2, 4, 3, 'Intéressant mais complexe.'),
(1, 5, NULL, NULL);

-- Inserting into Animation
INSERT INTO Animation (idMentor, idSession, remarque)
VALUES 
(1, 1, 'Bonne participation'),
(2, 2, 'À confirmer'),
(2, 3, 'Sujet bien maîtrisé'),
(2, 4, 'Session interactive'),
(1, 5, 'Annulée en dernière minute');

-- Inserting into Message
INSERT INTO Message (idExpediteur, idDestinataire, contenuMessage)
VALUES 
(1, 2, 'Bonjour, je suis intéressée par une session sur Python.'),
(2, 1, 'Bonjour, je suis disponible lundi matin.'),
(3, 4, 'Est-ce que vous proposez aussi du JavaScript ?'),
(4, 3, 'Non, mais je peux recommander quelqu\'un.'),
(5, 2, 'Merci pour la session d\'optique !');

-- Inserting into Badge
INSERT INTO Badge (nomBadge, descriptionBadge)
VALUES
('Débutant', 'Première inscription complétée'),
('Mentor engagé', '10 sessions animées'),
('Assidu', 'Participation régulière'),
('Orateur', 'Très bien noté par les étudiants'),
('Expert', '50 heures d\'animation'),
('Premier Message', 'Premier message envoyé sur la plateforme'),
('Communicateur', 'Premier message reçu sur la plateforme');

-- Inserting into Attribution
INSERT INTO Attribution (idBadge, idUtilisateur, dateAttribution)
VALUES 
(1, 1, '2025-06-19'),
(2, 2, '2025-06-19'),
(3, 3, '2025-06-20'),
(4, 4, '2025-06-20'),
(5, 2, '2025-06-21');

-- Inserting into Ressource
INSERT INTO Ressource (titreRessource, cheminRessource, typeFichier, idUtilisateur)
VALUES 
('Cours Python', 'docs/python_intro.pdf', 'pdf', 2),
('Guide R', 'docs/guide_r.docx', 'docx', 4),
('TP Optique', 'docs/optique_tp.pptx', 'pptx', 4),
('Support Java', 'docs/java_basics.pdf', 'pdf', 2),
('Vidéo ML', 'videos/ml_intro.mp4', 'video', 2);

-- Inserting into Notification
INSERT INTO Notification (idUtilisateur, typeNotification, contenuNotification)
VALUES 
(1, 'session', 'Votre session "Initiation à Python" a été validée.'),
(2, 'message', 'Vous avez reçu un nouveau message.'),
(3, 'badge', 'Vous avez obtenu le badge Assidu.'),
(4, 'session', 'La session "Analyse de données R" est planifiée.'),
(5, 'session', 'Votre session a été annulée.');

-- Inserting into Paiement
INSERT INTO Paiement (idSession, idEtudiant, idMentor, montant, statut, modePaiement)
VALUES 
(1, 1, 1, 20.00, 'effectué', 'carte'),
(2, 2, 2, 30.00, 'en_attente', 'paypal'),
(3, 3, 2, 15.00, 'effectué', 'stripe'),
(4, 2, 2, 40.00, 'effectué', 'carte'),
(5, 1, 1, 25.00, 'échoué', 'paypal');


-- Second Insert --
-- Inserting new Utilisateurs (8 mentors, 8 students)
INSERT INTO Utilisateur (nomUtilisateur, prenomUtilisateur, emailUtilisateur, motDePasse, role, ville, photoUrl, verified)
VALUES
('Laurent', 'Julie', 'julie.laurent@example.com', 'hashed_pwd6', 'etudiant', 'Bordeaux', 'https://i.pravatar.cc/150?img=11', TRUE),
('Morel', 'Antoine', 'antoine.morel@example.com', 'hashed_pwd7', 'mentor', 'Paris', 'https://i.pravatar.cc/150?img=12', TRUE),
('Renard', 'Lucie', 'lucie.renard@example.com', 'hashed_pwd8', 'etudiant', 'Nice', 'https://i.pravatar.cc/150?img=13', TRUE),
('Picard', 'Hugo', 'hugo.picard@example.com', 'hashed_pwd9', 'mentor', 'Strasbourg', 'https://i.pravatar.cc/150?img=14', TRUE),
('Petit', 'Nina', 'nina.petit@example.com', 'hashed_pwd10', 'etudiant', 'Nantes', 'https://i.pravatar.cc/150?img=15', TRUE),
('Baron', 'Sami', 'sami.baron@example.com', 'hashed_pwd11', 'mentor', 'Lille', 'https://i.pravatar.cc/150?img=16', TRUE),
('Lopez', 'Clara', 'clara.lopez@example.com', 'hashed_pwd12', 'etudiant', 'Grenoble', 'https://i.pravatar.cc/150?img=17', TRUE),
('Garnier', 'Paul', 'paul.garnier@example.com', 'hashed_pwd13', 'mentor', 'Montpellier', 'https://i.pravatar.cc/150?img=18', TRUE),
('Mercier', 'Emma', 'emma.mercier@example.com', 'hashed_pwd14', 'etudiant', 'Dijon', 'https://i.pravatar.cc/150?img=19', TRUE),
('Chevalier', 'Lucas', 'lucas.chevalier@example.com', 'hashed_pwd15', 'mentor', 'Limoges', 'https://i.pravatar.cc/150?img=20', TRUE),
('Roux', 'Anna', 'anna.roux@example.com', 'hashed_pwd16', 'etudiant', 'Aix-en-Provence', 'https://i.pravatar.cc/150?img=21', TRUE),
('Faure', 'Hassan', 'hassan.faure@example.com', 'hashed_pwd17', 'mentor', 'Rouen', 'https://i.pravatar.cc/150?img=22', TRUE),
('Dupuis', 'Laura', 'laura.dupuis@example.com', 'hashed_pwd18', 'etudiant', 'Metz', 'https://i.pravatar.cc/150?img=23', TRUE),
('Muller', 'Mathieu', 'mathieu.muller@example.com', 'hashed_pwd19', 'mentor', 'Reims', 'https://i.pravatar.cc/150?img=24', TRUE),
('Charpentier', 'Inès', 'ines.charpentier@example.com', 'hashed_pwd20', 'etudiant', 'Tours', 'https://i.pravatar.cc/150?img=25', TRUE),
('Lemoine', 'Elias', 'elias.lemoine@example.com', 'hashed_pwd21', 'mentor', 'Perpignan', 'https://i.pravatar.cc/150?img=26', TRUE);


-- Inserting Etudiants
INSERT INTO Etudiant (niveau, sujetRecherche, idUtilisateur)
VALUES
('L1 Biologie', 'Écologie végétale', 6),
('L2 Informatique', 'Programmation orientée objet', 8),
('L3 Mathématiques', 'Analyse complexe', 10),
('L1 Économie', 'Microéconomie', 12),
('M1 Histoire', 'Révolution française', 14),
('L3 Informatique', 'Développement web', 16),
('M2 Droit', 'Droit européen', 18),
('L2 Physique', 'Mécanique quantique', 20);


-- Inserting Mentors
INSERT INTO Mentor (competences, idUtilisateur)
VALUES
('Biologie, Écologie', 7),
('POO, Java, UML', 9),
('Mathématiques, Analyse, LaTeX', 11),
('Économie, Statistiques', 13),
('Histoire moderne, Révolution française', 15),
('JS, React, HTML/CSS', 17),
('Droit public, Droit international', 19),
('Physique, Thermodynamique, Mécanique quantique', 21);


-- Inserting Sessions
INSERT INTO Session (titreSession, sujet, dateSession, heureSession, statutSession, typeSession, tarifSession, duree_minutes, niveau, idEtudiantDemandeur, idMentorAnimateur)
VALUES
('Découverte de la Biologie', 'Biologie', '2025-06-30', '10:00:00', 'validee', 'en_ligne', 15.00, 60, 'L1', 1, 3),
('Java pour Débutants', 'Informatique', '2025-07-01', '11:00:00', 'validee', 'en_ligne', 20.00, 90, 'L2', 2, 4),
('Analyse Complexe', 'Mathématiques', '2025-07-02', '14:00:00', 'validee', 'en_ligne', 25.00, 120, 'L3', 3, 5),
('Théories Microéco', 'Économie', '2025-07-03', '15:00:00', 'en_attente', 'presentiel', 10.00, 45, 'L1', 4, 6),
('La Révolution Française', 'Histoire', '2025-07-04', '09:00:00', 'validee', 'presentiel', 30.00, 90, 'M1', 5, 7),
('Créer un site Web', 'Développement Web', '2025-07-05', '13:00:00', 'validee', 'en_ligne', 40.00, 120, 'L3', 6, 8),
('Droit Européen - Introduction', 'Droit', '2025-07-06', '17:00:00', 'en_attente', 'en_ligne', 35.00, 90, 'M2', 7, 9),
('Introduction à la Physique Quantique', 'Physique', '2025-07-07', '16:00:00', 'validee', 'en_ligne', 50.00, 120, 'L2', 8, 10);

-- Adding some available sessions for testing student booking workflow
INSERT INTO Session (titreSession, sujet, dateSession, heureSession, statutSession, typeSession, tarifSession, duree_minutes, niveau, idMentorAnimateur, descriptionSession)
VALUES
('Initiation au JavaScript', 'Informatique', '2025-07-10', '10:00:00', 'disponible', 'en_ligne', 25.00, 90, 'L1', 4, 'Apprenez les bases du JavaScript avec des exemples pratiques'),
('Mathématiques Appliquées', 'Mathématiques', '2025-07-11', '14:00:00', 'disponible', 'en_ligne', 0.00, 60, 'L2', 5, 'Session gratuite sur les mathématiques appliquées'),
('Introduction à la Biologie', 'Biologie', '2025-07-12', '09:00:00', 'disponible', 'presentiel', 20.00, 120, 'L1', 3, 'Découvrez les fondamentaux de la biologie'),
('Économie de Base', 'Économie', '2025-07-13', '16:00:00', 'disponible', 'en_ligne', 15.00, 75, 'L1', 6, 'Les principes de base de l\'économie expliqués simplement');
