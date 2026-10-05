<?php
// Entry point for the SAF-T 2.1 export. Reads the same inputs as the 1.0 export (see saftCreator.php),
// builds the SaftParameters and writes the AuditFile to the temp folder.
@session_start();
$s_id = session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once __DIR__ . '/../includes/xml_class.php';
include_once __DIR__ . '/../integration/SAF-T/AuditFile.php';

/**
 * Builds a date from the separate year/month/day inputs of the report form.
 */
function saft21Date(string $year, string $month, string $day): DateTimeImmutable {
	if (!checkdate((int) $month, (int) $day, (int) $year)) {
		throw new InvalidArgumentException("Invalid date: $year-$month-$day");
	}
	return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

$regnaar = (int) ifset($_POST, 'regnaar', 0);
$kontoFra = ifset($_POST, 'konto_fra', '');
$kontoTil = ifset($_POST, 'konto_til', '');

$params = new SaftParameters(
	fiscalYearId: $regnaar,
	periodStart: saft21Date(ifset($_POST, 'aar_fra', ''), ifset($_POST, 'maaned_fra', ''), ifset($_POST, 'dato_fra', '')),
	periodEnd: saft21Date(ifset($_POST, 'aar_til', ''), ifset($_POST, 'maaned_til', ''), ifset($_POST, 'dato_til', '')),
	accountFrom: $kontoFra === '' ? null : (int) $kontoFra,
	accountTo: $kontoTil === '' ? null : (int) $kontoTil,
	includeSourceDocuments: !empty($_POST['sourceDocuments']),
);

/** @var string $db included in online.php */
$xmlFilePath = "../temp/$db/financial/";
if (!is_dir($xmlFilePath)) {
	mkdir($xmlFilePath, 0777, true);
}
$auditFileName = 'SAF-T Financial 2.1_' . date('YmdHis') . '.xml';

(new xmlStreamWriter($xmlFilePath . $auditFileName))->writeDocument(new AuditFile($params));

$_SESSION['fileName'] = $auditFileName;
$_SESSION['filePath'] = $xmlFilePath . $auditFileName;
