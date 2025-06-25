-- Buat database
CREATE DATABASE IF NOT EXISTS feelingfound_db;
USE feelingfound_db;

-- Tabel users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('guru', 'siswa', 'ortu') NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    kelas VARCHAR(20),
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel mood_entries
CREATE TABLE mood_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mood_value INT NOT NULL CHECK (mood_value BETWEEN 1 AND 5),
    notes TEXT,
    date DATE NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel reports
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('kinerja_osis', 'kinerja_guru', 'kasus') NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'processed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel aspirations
CREATE TABLE aspirations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    content TEXT NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    is_anonymous BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel gallery
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data contoh (optional)
INSERT INTO users (username, password, role, nama_lengkap, kelas) VALUES 
('guru1', 'y', 'guru', 'Bu Guru Contoh', NULL),
('siswa1', 'o', 'siswa', 'Anak Siswa 2', 'XII IPA 1'),
('ortu1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ortu', 'Orang Tua 1', NULL);

-- Update parent_id untuk ortu
UPDATE users SET parent_id = 3 WHERE username = 'ortu1';