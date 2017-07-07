/**
 * Author:  Felix Jacobi
 * Created: 22.10.2016
 * License: http://opensource.org/licenses/MIT MIT license
 */

-- table for storing user password state
CREATE TABLE scmc_userpasswords (
    Act             TEXT PRIMARY KEY REFERENCES users(act)
                             ON UPDATE CASCADE
                             ON DELETE CASCADE
                             NOT NULL,
    Password        BOOLEAN NOT NULL
);

-- table for scmc servers
CREATE TABLE scmc_servers (
    ID              SERIAL      PRIMARY KEY,
    Host            TEXT        REFERENCES hosts(Name)
                                    ON UPDATE CASCADE
                                    ON DELETE CASCADE
                                    NOT NULL,
    TomcatType      TEXT        NOT NULL 
                                    CHECK (TomcatType IN
        ('tomcat6', 'tomcat7', 'tomact8')),
    WebDomain       TEXT        NOT NULL UNIQUE,
    ActGrp           TEXT       REFERENCES groups(Act)
                                    ON UPDATE CASCADE
                                    ON DELETE SET NULL,
    SSHAct          TEXT        NOT NULL
);

CREATE UNIQUE INDEX scmc_servers_host_key ON scmc_servers (lower(Host));

-- table for scmc rooms
CREATE TABLE scmc_rooms (
  ID                 SERIAL PRIMARY KEY,
  Room		           TEXT		NOT NULL
                          REFERENCES rooms(Name)
                          ON DELETE CASCADE
                          ON UPDATE CASCADE
);

-- permissions
GRANT SELECT ON "scmc_userpasswords" TO "symfony";

GRANT USAGE, SELECT ON "scmc_servers_id_seq" TO "symfony";
GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_servers" TO "symfony";

GRANT USAGE, SELECT ON "scmc_rooms_id_seq" TO "symfony";
GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_rooms" TO "symfony";