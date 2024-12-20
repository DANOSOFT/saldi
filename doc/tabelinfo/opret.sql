-- Saldi database oprettelse ny version

CREATE TABLE currency (
);

CREATE TABLE postnr {
};

CREATE TABLE adresse (
	id 				serial PRIMARY KEY,
    fornavn         VARCHAR( 64 ),
    efternavn       VARCHAR( 64 ), 
	firmanavn 		VARCHAR( 64 ),
	addr1 			varchar( 128 ), 
	addr2 			varchar( 128 ), 
	postnr 			VARCHAR( 10 ), 
	bynavn 			varchar( 64 ), 
	land 			varchar( 32 ), 
	kontakt 		varchar( 128 ), 
	tlf 			varchar( 32 ), 
	fax 			varchar( 32 ), 
	email 			varchar( 128 ), 
	web 			varchar( 255 ),
    -- bank data  
	bank_navn 		varchar, 
	bank_reg 		varchar, 
	bank_konto 		varchar, 
	notes 			varchar,
    -- virksomheds relateret data
	rabat 			numeric, 
	momskonto 		integer, 
	kreditmax 		numeric, 
	betalingsbet 	varchar, 
	betalingsdage 	smallint DEFAULT 0, 
	kontonr 		varchar, 
	cvrnr 			varchar, 	
	ean 			varchar, 
	institution 	varchar, 
	art 			varchar, 
	gruppe 			smallint
);

CREATE TABLE ansat (
	id 				serial PRIMARY KEY, 
	konto_id        INTEGER REFERENCES konto( id ), --- XXX, konto plan ?
    adresse_id      INTEGER REFERENCES adresse( id ),
	cprnr 			varchar, 
	afd 			integer
);

CREATE TABLE rettighed (
    id              SERIAL PRIMARY KEY,
    navn            VARCHAR( 32 ),  -- Rettigheds navn
    info            TEXT
);

CREATE TABLE bruger (
	id 				serial PRIMARY KEY,
	brugernavn 		varchar( 32 ) UNIQUE, 
	kode 			varchar( 32 ),
	status 			varchar,   -- XXX ?
	regnskabsaar 	smallint,  -- XXX, Hører det til her ? 
	ansat_id 		INTEGER REFERENCES ansatte( id )
);

CREATE TABLE bruger_rettighed (
    rettighed_id    INTEGER REFERENCES rettighed( id ),
    bruger_id       INTEGER REFERENCES bruger( id )
);

CREATE TABLE gruppe (
	id 				serial PRIMARY KEY,
	beskrivelse 	TEXT, 
	kode 			varchar,  
	kodenr 			varchar, 
	art 			varchar 
);

CREATE TABLE gruppe_box (
    id              SERIAL PRIMARY KEY,
    gruppe_id       INTEGER REFERENCES gruppe( id ),
    info            TEXT 
);


CREATE TABLE kladdeliste (
	id 				serial PRIMARY KEY,
	kladdedate 		DATE DEFAULT now(), 
	bogforingsdate 	date, 
	kladdenote 		varchar, 
	bogfort 		varchar, 
	oprettet_af 	varchar,   -- XXX, Bruger id ?
	bogfort_af 		varchar,   -- XXX, bruger id ?
	hvem 			varchar,   -- XXX 
	tidspkt 		TIME
);

CREATE TABLE kassekladde (
	id 				serial PRIMARY KEY,
	bilag 			integer, 
	transdate 		DATE DEFAULT now(), 
	beskrivelse 	TEXT, 
	d_type 			varchar, 
	debet 			integer, 
	k_type 			varchar, 
	kredit 			integer, 
	faktura 		varchar, 
	amount 			NUMERIC( 8, 2 ), 
	kladdeliste_id  INTEGER REFERENCES kladdeliste( id ),
	momsfri 		varchar, 
	afd 			integer, 
);


-- XXX, skulle den ikke bare hedde konto ?
CREATE TABLE kontoplan (
	id 				serial PRIMARY KEY,
	kontonr         numeric, 
	beskrivelse 	varchar, 
	kontotype 		varchar, 
	moms 			varchar, 
	fra_kto 		varchar, 
	til_kto 		varchar, 
	lukket 			varchar, 
	primo 			numeric, 
	regnskabsaar 	smallint
);

CREATE TABLE konto_md (
    id              SERIAL PRIMARY KEY,
    kontoplan_id    INTEGER REFERENCES kontoplan( id ),
    navn            VARCHAR( 64 ),
    sum             NUMERIC( 8,2 )
);

CREATE TABLE kontokort (
	id 				serial PRIMARY KEY,
	ref_id 			integer, 
	faktnr 			integer, 
	refnr 			integer, 
	beskrivelse 	TEXT, 
	kredit 			NUMERIC(8,2), 
	debet 			NUMERIC(8,2), 
	transdate 		DATE DEFAULT now()
);

-- XXX, burde den ikke have en ref til en købs og en leverings addresse record ?
CREATE TABLE ordre (
	id 				serial PRIMARY KEY
	konto_id 		INTEGER REFERENCES kontoplan( id ), 
    betalings_adresse_id INTEGER REFERENCES adresse( id ),
    leverings_adresse_id INTEGER REFERENCES adresse( id ),
	ordrenr         integer, -- XXX hvorfor kun integer
	sum             NUMERIC( 8, 2 ), 
	momssats        numeric, 
	status 			smallint, -- XXX, hvad betyder dette ? 
	ref 			varchar, 
	fakturanr       varchar, 
	modtagelse 		integer, 
	kred_ord_id 	integer,
	lev_adr 		varchar, 
	kostpris 		numeric, 
	moms 			numeric, 
	hvem 			varchar, 
	tidspkt 		varchar
);

CREATE TABLE ordrelinje (
	id 				serial PRIMARY KEY,
	varenr 			varchar, 
	beskrivelse 	TEXT, 
	enhed 			varchar, 
	posnr 			VARCHAR( 10 ), 
	pris 			numeric( 8,2 ), 
	rabat 			numeric, 
	lev_varenr 		varchar, 
	ordre_id 		INTEGER REFERENCES ordre( id ) NOT NULL, 
	serienr 		varchar,
	vare_id 		INTEGER REFERENCES vare( id ), 
	antal 			INTEGER, 
	leveres 		numeric, 
	bogf_konto 		integer, 
	oprettet_af 	varchar,  -- XXX bruger id ? 
	bogfort_af 		varchar,  -- XXX bruger id ?
	hvem 			varchar,  -- XXX
	tidspkt 		TIME, 
	kred_linje_id 	integer, 
	momsfri 		varchar
);

CREATE TABLE openpost (
	id 				serial PRIMARY KEY,
	konto_id 		integer REFERENCES kontokort( id ), 
	konto_nr 		varchar, 
	faktnr 			varchar, 
	amount 			NUMERIC(8,2), 
	refnr 			integer, 
	beskrivelse 	TEXT, 
	udlignet 		varchar, 
	transdate 		date,
    kassekladde_id 	INTEGER REFERENCES kassekladde( id )
);

-- kassekladde og denne bør vel være samme layout 
CREATE TABLE transaktion (
	id 				serial NOT NULL, 
	kontonr 		integer, 
	bilag 			integer, 
	transdate 		date, 
	logtime 		TIMESTAMP DEFAULT now(), 
	beskrivelse 	varchar, 
	debet 			numeric( 8,2 ), 
	kredit 			numeric( 8,2 ), 
	faktura 		varchar, 
	kladde_id 		INTEGER REFERENCES kassekladde( id ), 
	projekt_id 		integer, 
	afd 			integer
);

CREATE TABLE vare (
	id 				serial PRIMARY KEY, 
	varenr 			varchar, 	
	beskrivelse 	varchar, 
	enhed 			varchar, 
	enhed2 			varchar, 
	forhold 		numeric, 
	gruppe 			varchar, 
	salgspris 		numeric, 
	kostpris 		numeric, 
	notes 			varchar, 
	lukket 			varchar, 
	serienr 		varchar, 
	beholdning 		INTEGER, 
	samlevare 		varchar, 
	delvare 		varchar, 
	min_lager 		INTEGER, 
	max_lager 		INTEGER
);

CREATE TABLE lagerstatus (
	id 				SERIAL PRIMARY KEY,
	lager 			INTEGER, 
	vare_id 		INTEGER REFERENCES vare( id ), 
	beholdning 		INTEGER
);

CREATE TABLE batch_kob (
	id 				SERIAL PRIMARY KEY,
	obsdate 		date, 
	fakturadate 	date, 
	vare_id 		INTEGER REFERENCES vare( id ), 
	linje_id 		integer, -- XXX ?
	ordre_id 		integer REFERENCES ordre( id ), 
	pris 			numeric( 8,2 ), 
	antal 			INTEGER, 
	rest 			numeric, 
	lager 			integer
);

CREATE TABLE batch_salg (
	id 				serial PRIMARY KEY,
	salgsdate 		date, 
	fakturadate 	date, 
	batch_kob_id 	INTEGER REFERENCES batch_kob( id ), 
	vare_id 		INTEGER REFERENCES vare( id ), 
	linje_id 		integer, 
	ordre_id 		INTEGER REFERENCES ordre( id ), 
	pris 			numeric( 8,2 ), 
	antal 			INTEGER, 
	lev_nr 			integer
);

CREATE TABLE serienr (
	id 				serial PRIMARY KEY, 
	vare_id 		integer REFERENCES vare( id ), 
	kobslinje_id 	integer, 
	salgslinje_id 	integer, 
	batch_kob_id 	INTEGER REFERENCES batch_kob( id ), 
	batch_salg_id 	INTEGER REFERENCES batch_salg( id ), 
	serienr 		VARCHAR( 64 )
);

CREATE TABLE stykliste (
	id 				serial PRIMARY KEY, 
	vare_id 		INTEGER REFERENCES vare( id ), 
	indgaar_i 		integer, 
	antal 			INTEGER, 
	posnr 			varchar( 32 )
);

CREATE TABLE enhed (
	id 				serial PRIMARY KEY, 
	betegnelse 		varchar, 
	beskrivelse 	varchar
);

CREATE TABLE materiale (
	id 				serial PRIMARY KEY, 
	beskrivelse 	TEXT, 
	densitet 		numeric
);

CREATE TABLE vare_lev (
	id 				serial PRIMARY KEY, 
	posnr 			varchar( 32 ), 
	lev_id 			integer, 
	vare_id 		integer REFERENCES vare( id ), 
	lev_varenr 		varchar, 
	kostpris 		numeric( 8, 2 )
);

CREATE TABLE reservation (
    id              SERIAL PRIMARY KEY,
	linje_id 		integer, 
	batch_kob_id 	integer REFERENCES batch_kob( id ), 
	batch_salg_id 	integer REFERENCES batch_salg( id ), 
	vare_id 		integer REFERENCES vare( id ), 
	antal 			INTEGER, 
	lager 			integer
);

CREATE TABLE formulare (
	id 				serial PRIMARY KEY, 
	formular_id     INTEGER REFERENCES formular( id ), 
	art 			integer, 
	beskrivelse 	varchar, 
	placering 		varchar, 	
	xa 				numeric, 
	ya 				numeric, 
	xb 				numeric, 
	yb 				numeric, 
	str 			numeric, 
	color 			integer, 
	font 			varchar, 
	fed 			varchar, 
	kursiv 			varchar, 
	side 			varchar
);