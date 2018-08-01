-- Add host_id column to scmc_servers
ALTER TABLE ONLY scmc_servers ADD host_id INT;

UPDATE scmc_servers s SET host_id = (SELECT h.id FROM hosts h WHERE h.name = s.host);
