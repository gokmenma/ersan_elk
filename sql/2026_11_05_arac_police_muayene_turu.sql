ALTER TABLE arac_police_dosyalari
    MODIFY COLUMN police_turu ENUM('sigorta','kasko','muayene') NOT NULL;
