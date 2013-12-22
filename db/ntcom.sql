--delete from updates; delete from checks;
--delete from sections; delete from items; delete from info;
--drop table sections; drop table items; drop table updates; drop table checks; drop table info;

create table "checks" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	"time_start" INTEGER NOT NULL,
	"duration" INTEGER,
	"updates" INTEGER DEFAULT 0
);

create table "updates" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	"id_check" INTEGER NOT NULL,
	"id_message" VARCHAR NOT NULL,
	"file" VARCHAR NOT NULL,
	"time" INTEGER NOT NULL
);

create table "items" (
	"id" INTEGER NOT NULL,
	"id_section" INTEGER NOT NULL,
	"id_update" INTEGER NOT NULL,
	"art" VARCHAR DEFAULT '',
	"name" VARCHAR NOT NULL,
	"price" REAL,
	"price_diff" REAL,
	"warranty" INTEGER,
	"is_new" INTEGER DEFAULT 0
);

create table "info" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	"id_update" INTEGER NOT NULL,
	"id_item" INTEGER NOT NULL,
	"price" REAL
);

create table "sections" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	"id_update" INTEGER NOT NULL,
	"name" VARCHAR NOT NULL,
	"items_total" INTEGER DEFAULT 0,
	"items_last" INTEGER DEFAULT 0,
	"items_new" INTEGER DEFAULT 0,
	"order" INTEGER DEFAULT 0,
	"is_active" INTEGER DEFAULT 1,
	"sum_inc" FLOAT DEFAULT 0,
	"sum_dec" FLOAT DEFAULT 0
);

create index updates_id_check   on updates ( id_check );
create index updates_id_message on updates ( id_message );
create index items_id_section   on items   ( id_section );
create index items_id_update    on items   ( id_update );
create index items_name         on items   ( name );
create index items_art          on items   ( art );
create index items_price        on items   ( price );
create index items_is_new       on items   ( is_new );
create index info_id_update     on info    ( id_update );
create index info_id_item       on info    ( id_item );
create index info_price         on info    ( price );
create index sections_id_update on sections( id_update );
create index sections_name      on sections( name );
create index sections_is_active on sections( is_active );

vacuum;
