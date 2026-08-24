<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Pure parser converting Mesures V1 payloads into normalized rows.
 */
class LmdbEnedisMeasureParser
{
	/**
	 * Parse a Mesures V1 response.
	 *
	 * @param string              $resourceCode Resource code
	 * @param array<string,mixed> $payload      Decoded JSON response
	 * @return array<int,array<string,mixed>>
	 */
	public static function parse($resourceCode, $payload)
	{
		$meterReading = isset($payload['meter_reading']) && is_array($payload['meter_reading']) ? $payload['meter_reading'] : array();
		if ($meterReading === array()) {
			return array();
		}

		if ($resourceCode === 'index_consumption' || $resourceCode === 'index_production') {
			return self::parseIndexes($resourceCode, $meterReading);
		}

		return self::parseIntervals($resourceCode, $meterReading);
	}

	/**
	 * @param string              $resourceCode Resource code
	 * @param array<string,mixed> $meterReading Meter reading
	 * @return array<int,array<string,mixed>>
	 */
	private static function parseIntervals($resourceCode, $meterReading)
	{
		$readingType = isset($meterReading['reading_type']) && is_array($meterReading['reading_type']) ? $meterReading['reading_type'] : array();
		$items = isset($meterReading['interval_reading']) && is_array($meterReading['interval_reading']) ? $meterReading['interval_reading'] : array();
		$rows = array();
		foreach ($items as $item) {
			if (!is_array($item) || empty($item['date']) || !isset($item['value']) || !is_numeric($item['value'])) {
				continue;
			}
			$date = self::normalizeDate((string) $item['date']);
			if ($date === '') {
				continue;
			}
			$identity = array($resourceCode, $date);
			$rows[] = array(
				'data_key' => hash('sha256', implode('|', $identity)),
				'measure_date' => $date,
				'value' => (float) $item['value'],
				'unit' => self::arrayString($readingType, 'unit'),
				'quality' => self::arrayString($meterReading, 'quality'),
				'reading_context' => '',
				'interval_length' => self::arrayString($item, 'interval_length'),
				'measure_type' => self::arrayString($item, 'measure_type'),
				'flow_direction' => self::arrayString($readingType, 'flow_direction'),
				'measurement_kind' => self::arrayString($readingType, 'measurement_kind'),
				'aggregate_kind' => self::arrayString($readingType, 'aggregate'),
				'measuring_period' => self::arrayString($readingType, 'measuring_period'),
				'calendar_id' => '',
				'calendar_label' => '',
				'temporal_class_id' => '',
				'temporal_class_label' => '',
				'quadrant_id' => '',
				'source_start' => self::normalizeDate(self::arrayString($meterReading, 'start')),
				'source_end' => self::normalizeDate(self::arrayString($meterReading, 'end')),
			);
		}

		return $rows;
	}

	/**
	 * @param string              $resourceCode Resource code
	 * @param array<string,mixed> $meterReading Meter reading
	 * @return array<int,array<string,mixed>>
	 */
	private static function parseIndexes($resourceCode, $meterReading)
	{
		$reading = self::firstArray(isset($meterReading['reading']) ? $meterReading['reading'] : array());
		$readingType = self::firstArray(isset($meterReading['reading_type']) ? $meterReading['reading_type'] : array());
		$common = array(
			'unit' => self::arrayString($readingType, 'unit'),
			'quality' => self::arrayString($reading, 'quality'),
			'reading_context' => self::arrayString($reading, 'context'),
			'interval_length' => '',
			'measure_type' => '',
			'flow_direction' => self::arrayString($readingType, 'flow_direction'),
			'measurement_kind' => self::arrayString($readingType, 'measurement_kind'),
			'aggregate_kind' => '',
			'measuring_period' => '',
			'source_start' => self::normalizeDate(self::arrayString($meterReading, 'start')),
			'source_end' => self::normalizeDate(self::arrayString($meterReading, 'end')),
		);
		$rows = array();
		$calendars = isset($meterReading['calendar']) && is_array($meterReading['calendar']) ? $meterReading['calendar'] : array();
		foreach ($calendars as $calendar) {
			if (!is_array($calendar)) {
				continue;
			}
			$classes = isset($calendar['temporal_class']) && is_array($calendar['temporal_class']) ? $calendar['temporal_class'] : array();
			foreach ($classes as $temporalClass) {
				if (!is_array($temporalClass)) {
					continue;
				}
				$values = isset($temporalClass['values']) && is_array($temporalClass['values']) ? $temporalClass['values'] : array();
				foreach ($values as $value) {
					$row = self::buildIndexRow($resourceCode, $value, $common, array(
						'calendar_id' => self::arrayString($calendar, 'id_calendar'),
						'calendar_label' => self::arrayString($calendar, 'label_calendar'),
						'temporal_class_id' => self::arrayString($temporalClass, 'id_temporal_class'),
						'temporal_class_label' => self::arrayString($temporalClass, 'label_temporal_class'),
						'quadrant_id' => self::arrayString($temporalClass, 'id_quadrant'),
					));
					if ($row !== null) {
						$rows[] = $row;
					}
				}
			}
		}

		$totalizers = isset($meterReading['temporal_class_totalizer']) && is_array($meterReading['temporal_class_totalizer']) ? $meterReading['temporal_class_totalizer'] : array();
		foreach ($totalizers as $totalizer) {
			if (!is_array($totalizer)) {
				continue;
			}
			$values = isset($totalizer['values']) && is_array($totalizer['values']) ? $totalizer['values'] : array();
			foreach ($values as $value) {
				$row = self::buildIndexRow($resourceCode, $value, $common, array(
					'calendar_id' => '',
					'calendar_label' => '',
					'temporal_class_id' => '',
					'temporal_class_label' => '',
					'quadrant_id' => self::arrayString($totalizer, 'id_quadrant'),
				));
				if ($row !== null) {
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	/**
	 * @param string              $resourceCode Resource code
	 * @param mixed               $value        Index value structure
	 * @param array<string,mixed> $common       Common metadata
	 * @param array<string,string> $dimensions  Index dimensions
	 * @return array<string,mixed>|null
	 */
	private static function buildIndexRow($resourceCode, $value, $common, $dimensions)
	{
		if (!is_array($value) || empty($value['date']) || !isset($value['value']) || !is_numeric($value['value'])) {
			return null;
		}
		$date = self::normalizeDate((string) $value['date']);
		if ($date === '') {
			return null;
		}
		$identity = array(
			$resourceCode,
			$date,
			$dimensions['calendar_id'],
			$dimensions['temporal_class_id'],
			$dimensions['quadrant_id'],
		);

		return array_merge($common, $dimensions, array(
			'data_key' => hash('sha256', implode('|', $identity)),
			'measure_date' => $date,
			'value' => (float) $value['value'],
		));
	}

	/**
	 * @param mixed $value Value that may be a list
	 * @return array<string,mixed>
	 */
	private static function firstArray($value)
	{
		if (!is_array($value)) {
			return array();
		}
		if (isset($value[0]) && is_array($value[0])) {
			return $value[0];
		}

		return $value;
	}

	/**
	 * @param array<string,mixed> $array Source
	 * @param string              $key   Key
	 * @return string
	 */
	private static function arrayString($array, $key)
	{
		return isset($array[$key]) && is_scalar($array[$key]) ? trim((string) $array[$key]) : '';
	}

	/**
	 * @param string $date API date
	 * @return string SQL UTC date or empty string
	 */
	private static function normalizeDate($date)
	{
		if ($date === '') {
			return '';
		}
		try {
			$utc = new DateTimeZone('UTC');
			$value = new DateTimeImmutable($date, $utc);

			return $value->setTimezone($utc)->format('Y-m-d H:i:s');
		} catch (Exception $e) {
			return '';
		}
	}
}
