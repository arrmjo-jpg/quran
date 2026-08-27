-- The test database, created where the dev database is created.
--
-- tests/bootstrap-testing-env.php points the suite at `quran_platform_test`
-- when TEST_DB=mysql, and CI does exactly that for `gate:suite`. It overrides
-- DB_DATABASE only -- credentials still come from the app container, so the
-- suite connects as `quran_user`.
--
-- MYSQL_DATABASE/MYSQL_USER create `quran_platform` and grant `quran_user` on
-- that one schema alone. Nothing ever created the test schema. It existed on
-- the machine this was developed on because it had been created there by
-- hand, which is why MySQL mode passed locally and failed on the first clean
-- runner with:
--
--   SQLSTATE[HY000] [1044] Access denied for user 'quran_user'@'%'
--                          to database 'quran_platform_test'
--
-- The entrypoint runs this after it has created MYSQL_USER, so the account is
-- already there to be granted to.
--
-- Only on FIRST initialisation of the data volume -- an existing volume has
-- already run its init scripts and will not re-run this one. CI builds a fresh
-- volume every run; a developer with an older volume either has the schema
-- already or recreates the volume (`docker compose down -v`).

CREATE DATABASE IF NOT EXISTS `quran_platform_test`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `quran_platform_test`.* TO 'quran_user'@'%';

FLUSH PRIVILEGES;
