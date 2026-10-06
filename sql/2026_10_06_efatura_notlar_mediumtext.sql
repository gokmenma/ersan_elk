-- EDM UBL notes can exceed the 65,535-byte TEXT limit. Preserve the complete source notes.
-- Safe to run repeatedly; existing notes are preserved.
ALTER TABLE faturalar MODIFY COLUMN notlar MEDIUMTEXT NULL;
