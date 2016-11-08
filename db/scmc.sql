/**
 * Author:  Felix Jacobi
 * Created: 22.10.2016
 * License: http://gnu.org/licenses/gpl-3.0 GNU General Public License 
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

-- permissions
GRANT USAGE, SELECT ON "scmc_sessions_id_seq" TO "scmc_session";
GRANT INSERT, DELETE, SELECT, UPDATE ON "scmc_sessions" TO "scmc_session";