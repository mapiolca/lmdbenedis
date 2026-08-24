<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../class/lmdbenedismeasureparser.class.php';

$intervalPayload = array('meter_reading' => array(
	'start' => '2026-08-01T00:00:00Z',
	'end' => '2026-08-02T00:00:00Z',
	'quality' => 'BRUT',
	'reading_type' => array('flow_direction' => 'forward', 'measurement_kind' => 'power', 'unit' => 'W', 'aggregate' => 'average'),
	'interval_reading' => array(array('date' => '2026-08-01T00:30:00Z', 'value' => '540', 'interval_length' => 'PT30M', 'measure_type' => 'B')),
));
$rows = LmdbEnedisMeasureParser::parse('consumption_load_curve', $intervalPayload);
if (count($rows) !== 1 || $rows[0]['value'] !== 540.0 || $rows[0]['unit'] !== 'W' || $rows[0]['measure_date'] !== '2026-08-01 00:30:00') {
	fwrite(STDERR, "Interval parser test failed\n");
	exit(1);
}

$indexPayload = array('meter_reading' => array(
	'start' => '2026-08-01',
	'end' => '2026-08-03',
	'reading' => array(array('quality' => 'BEST', 'context' => 'COL')),
	'reading_type' => array(array('flow_direction' => 'forward', 'measurement_kind' => 'EA', 'unit' => 'Wh')),
	'calendar' => array(array(
		'id_calendar' => 'CAL',
		'label_calendar' => 'Calendar',
		'temporal_class' => array(array(
			'id_temporal_class' => 'HC',
			'label_temporal_class' => 'Heures creuses',
			'id_quadrant' => 'IDX_HC',
			'values' => array(array('date' => '2026-08-02 00:00:00', 'value' => 12345)),
		)),
	)),
	'temporal_class_totalizer' => array(array(
		'id_quadrant' => 'IDX_EAS_T',
		'values' => array(array('date' => '2026-08-02 00:00:00', 'value' => 54321)),
	)),
));
$rows = LmdbEnedisMeasureParser::parse('index_consumption', $indexPayload);
if (count($rows) !== 2 || $rows[0]['value'] !== 12345.0 || $rows[0]['temporal_class_id'] !== 'HC' || $rows[0]['quality'] !== 'BEST' || $rows[1]['quadrant_id'] !== 'IDX_EAS_T') {
	fwrite(STDERR, "Index parser test failed\n");
	exit(1);
}

$dailyPayload = array('meter_reading' => array(
	'start' => '2026-08-01T00:00:00+02:00',
	'end' => '2026-08-03T00:00:00+02:00',
	'quality' => 'BEST',
	'reading_type' => array('flow_direction' => 'reverse', 'measurement_kind' => 'energy', 'unit' => 'Wh'),
	'interval_reading' => array(array('date' => '2026-08-02T00:00:00+02:00', 'value' => '8400')),
));
$rows = LmdbEnedisMeasureParser::parse('daily_production', $dailyPayload);
if (count($rows) !== 1 || $rows[0]['value'] !== 8400.0 || $rows[0]['flow_direction'] !== 'reverse' || $rows[0]['measure_date'] !== '2026-08-01 22:00:00') {
	fwrite(STDERR, "Daily production parser test failed\n");
	exit(1);
}

$maxPowerPayload = array('meter_reading' => array(
	'start' => '2026-08-01T00:00:00Z',
	'end' => '2026-08-02T00:00:00Z',
	'quality' => 'BRUT',
	'reading_type' => array('flow_direction' => 'forward', 'measurement_kind' => 'power', 'measuring_period' => 'P1D', 'unit' => 'VA', 'aggregate' => 'maximum'),
	'interval_reading' => array(array('date' => '2026-08-01T12:00:00Z', 'value' => '6900')),
));
$rows = LmdbEnedisMeasureParser::parse('daily_consumption_max_power', $maxPowerPayload);
if (count($rows) !== 1 || $rows[0]['value'] !== 6900.0 || $rows[0]['measuring_period'] !== 'P1D' || $rows[0]['aggregate_kind'] !== 'maximum') {
	fwrite(STDERR, "Maximum power parser test failed\n");
	exit(1);
}

$invalidRows = LmdbEnedisMeasureParser::parse('daily_consumption', array('meter_reading' => array(
	'interval_reading' => array(array('date' => 'invalid', 'value' => 'not-a-number')),
)));
if ($invalidRows !== array()) {
	fwrite(STDERR, "Invalid payload parser test failed\n");
	exit(1);
}

print "LmdbEnedisMeasureParser tests passed\n";
