/**
 * Author:  Felix Jacobi
 * Created: 22.10.2016
 * License: http://opensource.org/licenses/MIT MIT license
 */

-- own user for session management
CREATE USER scmc_session;

-- session table
CREATE TABLE scmc_sessions (
    id              SERIAL              PRIMARY KEY,
    sessiontoken    VARCHAR(60) NOT NULL,
    sessionpw       VARCHAR(255) NOT NULL,
    sessionpwsalt   VARCHAR(255) NOT NULL,
    act             VARCHAR(255) REFERENCES users(act)
                             ON DELETE CASCADE
                             ON UPDATE CASCADE
                             NOT NULL,
    created         TIMESTAMPTZ(0) NOT NULL DEFAULT now()
);

-- table for storing user password state
CREATE TABLE scmc_userpasswords (
    act             VARCHAR(255) PRIMARY KEY REFERENCES users(act)
                             ON UPDATE CASCADE
                             ON DELETE CASCADE
                             NOT NULL,
    password        BOOLEAN NOT NULL
);

-- permissions
GRANT USAGE, SELECT ON "scmc_sessions_id_seq" TO "scmc_session";
GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_sessions" TO "scmc_session";

GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_userpasswords" TO "symfony";