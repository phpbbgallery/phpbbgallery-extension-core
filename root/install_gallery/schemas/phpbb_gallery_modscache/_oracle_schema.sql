/*

 $Id$

*/

/*
  This first section is optional, however its probably the best method
  of running phpBB on Oracle. If you already have a tablespace and user created
  for phpBB you can leave this section commented out!

  The first set of statements create a phpBB tablespace and a phpBB user,
  make sure you change the password of the phpBB user before you run this script!!
*/

/*
CREATE TABLESPACE "PHPBB"
	LOGGING 
	DATAFILE 'E:\ORACLE\ORADATA\LOCAL\PHPBB.ora' 
	SIZE 10M
	AUTOEXTEND ON NEXT 10M
	MAXSIZE 100M;

CREATE USER "PHPBB" 
	PROFILE "DEFAULT" 
	IDENTIFIED BY "phpbb_password" 
	DEFAULT TABLESPACE "PHPBB" 
	QUOTA UNLIMITED ON "PHPBB" 
	ACCOUNT UNLOCK;

GRANT ANALYZE ANY TO "PHPBB";
GRANT CREATE SEQUENCE TO "PHPBB";
GRANT CREATE SESSION TO "PHPBB";
GRANT CREATE TABLE TO "PHPBB";
GRANT CREATE TRIGGER TO "PHPBB";
GRANT CREATE VIEW TO "PHPBB";
GRANT "CONNECT" TO "PHPBB";

COMMIT;
DISCONNECT;

CONNECT phpbb/phpbb_password;
*/
/*
	Table: 'phpbb_gallery_modscache'
*/
CREATE TABLE phpbb_gallery_modscache (
	album_id number(8) DEFAULT '0' NOT NULL,
	user_id number(8) DEFAULT '0' NOT NULL,
	username varchar2(255) DEFAULT '' ,
	group_id number(8) DEFAULT '0' NOT NULL,
	group_name varchar2(255) DEFAULT '' ,
	display_on_index number(1) DEFAULT '1' NOT NULL
)
/

CREATE INDEX phpbb_gallery_modscache_disp_idx ON phpbb_gallery_modscache (display_on_index)
/
CREATE INDEX phpbb_gallery_modscache_album_id ON phpbb_gallery_modscache (album_id)
/

