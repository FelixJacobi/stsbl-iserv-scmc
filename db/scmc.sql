/**
 * Author:  Felix Jacobi
 * Created: 22.10.2016
 * License: http://opensource.org/licenses/MIT MIT license
 */

-- own user for session management
CREATE USER scmc_session;

-- session table
CREATE TABLE scmc_sessions (
    ID              SERIAL              PRIMARY KEY,
    Sessiontoken    VARCHAR(60) NOT NULL,
    SessionPW       VARCHAR(255) NOT NULL,
    SessionPWSalt   VARCHAR(255) NOT NULL,
    Act             VARCHAR(255) REFERENCES users(act)
                             ON DELETE CASCADE
                             ON UPDATE CASCADE
                             NOT NULL,
    Created         TIMESTAMPTZ(0) NOT NULL DEFAULT now()
);

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
    WebDomain       TEXT        NOT NULL,
    ActGrp           TEXT       REFERENCES groups(Act)
                                    ON UPDATE CASCADE
                                    ON DELETE SET NULL,
    SSHAct          TEXT        NOT NULL
);

CREATE UNIQUE INDEX scmc_servers_host_key ON scmc_servers (lower(Host));

-- permissions
GRANT USAGE, SELECT ON "scmc_sessions_id_seq" TO "scmc_session";
GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_sessions" TO "scmc_session";

GRANT SELECT ON "scmc_userpasswords" TO "symfony";

GRANT USAGE, SELECT ON "scmc_servers_id_seq" TO "symfony";
GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_servers" TO "symfony";
