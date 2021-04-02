<?php

namespace foorschtbar\iCalEws;

use \DateTime;
use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\Request\FindItemType;

use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;

use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;

use \jamesiarmes\PhpEws\Type\CalendarViewType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;

date_default_timezone_set('UTC');

class Main
{

	private $config;
	private $client;
	private $events;
	private $ical;
	private $cache_hash;
	private $cache_lastmodified;
	private $items;
	private $auth_username;
	private $auth_password;

	public $debug = false;
	public $verbose = false;
	public $cat_blacklist = array();
	public $wifemode = false;

	public function __construct($config)
	{
		$this->config->host = $config['host'] ?? "";
		$this->config->realm = $config['realm'] ?? "iCal Auth";
		$this->config->logdir = $config['logdir'] ?? "logs";
		$this->config->cachedir = $config['cachedir'] ?? "cache";
		$this->config->start_date = new DateTime($config['timerange_start'] ?? "-90 days");
		$this->config->end_date = new DateTime($config['timerange_end'] ?? "+180 days");
		$this->config->version = $config['version'] ?? Client::VERSION_2016;

		if ($this->config->host == "") {
			$this->log("Missing host. Check config");
			exit;
		}

		$this->log("+++ Start iCalEws +++");
	}

	public function httpauth()
	{
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			header('WWW-Authenticate: Basic realm="' . $this->config->realm . '"');
			header('HTTP/1.0 401 Unauthorized');
			echo 'HTTP/1.0 401 Unauthorized';
			exit;
		} else {
			$this->log("HTTP Basic auth done");
			$this->auth_username = $_SERVER['PHP_AUTH_USER'];
			$this->auth_password = $_SERVER['PHP_AUTH_PW'];
		}
	}

	public function cachesave()
	{
		$cachefile = $this->config->cachedir . "/events.json";
		if (!empty($this->events)) {
			$json = json_encode($this->events);
			$this->cache_hash = sha1($json);
			$this->cache_lastmodified = time();
			if ($this->cache_hash != sha1_file($cachefile)) {
				file_put_contents($cachefile, $json);
				$this->log("Saved events to cache file " . $cachefile);
			} else {
				$this->log("Cache file " . $cachefile . " is up to date");
			}
		} else {
			$this->log("Error cache to cache file " . $cachefile);
		}
	}

	public function cacheload()
	{
		$cachefile = $this->config->cachedir . "/events.json";
		$this->cache_lastmodified = filemtime($cachefile);
		$this->cache_hash = sha1_file($cachefile);
		$file = file_get_contents($cachefile);
		$this->events = json_decode($file, true);
		if (json_last_error() == JSON_ERROR_NONE) {

			$this->log("Load Events from cachefile");
		} else {
			$this->log("Error load json");
		}
	}

	private function ewsClient($timezone = 'UTC')
	{
		$this->client = new Client($this->config->host, $this->auth_username, $this->auth_password, $this->config->version);
		$this->client->setTimezone($timezone);
	}

	public function getitems()
	{

		$start = microtime();

		$this->ewsClient();

		$this->log("Start getitems. Timerange: " . $this->config->start_date->format('c') . " to " . $this->config->end_date->format('c'));

		$request = new FindItemType();
		$request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();

		// Return all event properties.
		$request->ItemShape = new ItemResponseShapeType();
		$request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;

		$folder_id = new DistinguishedFolderIdType();
		$folder_id->Id = DistinguishedFolderIdNameType::CALENDAR;
		$request->ParentFolderIds->DistinguishedFolderId[] = $folder_id;

		$request->CalendarView = new CalendarViewType();
		$request->CalendarView->StartDate = $this->config->start_date->format('c');
		$request->CalendarView->EndDate = $this->config->end_date->format('c');

		try {
			$response = $this->client->FindItem($request);

			// Iterate over the results, printing any error messages or event ids.
			$response_messages = $response->ResponseMessages->FindItemResponseMessage;
			foreach ($response_messages as $response_message) {
				// Make sure the request succeeded.
				if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
					$code = $response_message->ResponseCode;
					$message = $response_message->MessageText;
					$this->log("Failed to search for events with \"$code: $message\"\n");
					continue;
				}

				// Iterate over the events that were found, printing some data for each.
				$this->items = $response_message->RootFolder->Items->CalendarItem;
			}
		} catch (\Exception $e) {
			$this->log($e->getMessage());
			exit;
		}



		$this->log("End getitems. Got " . count($this->items) . " CalendarItems in " . (microtime() - $start) . "s");
		if ($this->verbose) {
			$this->log("", $this->items);
		}
		return $this->items;
	}

	public function getevents()
	{


		$this->log("Start getevents.");

		$use_cat_blacklist = false;
		if (count($this->cat_blacklist) > 0) {
			$use_cat_blacklist = true;
		}

		$count = 0;
		$filter = 0;
		foreach ($this->items as $item) {

			unset($categories);
			if (!empty($item->Categories->String)) {
				foreach ($item->Categories->String as $category) {
					if ($use_cat_blacklist && in_array($category, $this->cat_blacklist)) {
						$filter++;
						continue 2;
					}
					$categories[] = $category;
				}
			}

			$events[] = array(
				"uid" => sha1($item->ItemId->Id), // $item->UID
				"start" => $item->Start,
				"end" => $item->End,
				"summary" => $item->Subject,
				"organizer" => $item->Organizer->Mailbox->Name,
				"location" => $item->Location,
				"categories" => $categories,
				"status" => $item->LegacyFreeBusyStatus
			);
			$count++;
		}

		$this->log("End getevents. Filtered " . $filter . " of " . ($count + $filter) . " Events.");
		if ($this->verbose) {
			$this->log("", $events);
		}
		$this->events = $events;
		return $this->events;
	}

	public function icalbuild()
	{

		if ($this->wifemode) {
			$this->log("Filter events for my wife...");
		}

		if (!is_array($this->events)) {
			$this->log("Error. No Events. use getevents first.");
		} else {
			$count = 0;
			$ical = 'BEGIN:VCALENDAR
			METHOD:PUBLISH
			VERSION:2.0
			PRODID:-//foorschtbar//iCalEws//EN
			CALSCALE:GREGORIAN
			BEGIN:VTIMEZONE
			TZID:Europe/Berlin
			BEGIN:DAYLIGHT
			TZOFFSETFROM:+0100
			TZOFFSETTO:+0200
			DTSTART:19810329T020000
			RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
			TZNAME:CEST
			END:DAYLIGHT
			BEGIN:STANDARD
			TZOFFSETFROM:+0200
			TZOFFSETTO:+0100
			DTSTART:19961027T030000
			RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
			TZNAME:CET
			END:STANDARD
			END:VTIMEZONE
			';

			foreach ($this->events as $event) {

				$summary = $event['summary'];

				switch (strtolower($event['status'])) {
					case "tentative": // tentative=vorläufig
						$summary = "[Terminanfrage] " . $summary;
						break;
				}

				$uid = $event['uid'];
				$location = $event['location'];
				$organizer = $event['organizer']; //
				$from = $this->veventdate($event['start']);
				$to = $this->veventdate($event['end']);

				if ($this->wifemode) {
					//$this->log($from." ".strftime("%Y-%m-%d", strtotime($from)));

					if (strftime("%Y-%m-%d", strtotime($from)) != strftime("%Y-%m-%d", strtotime($to))) {
						// events longer than one day
						// keep
					} elseif (intval(strftime("%H", strtotime($to)) >= 18)) {
						// end 18:00 or later
						// keep
					} else {
						// skip all other events
						continue;
					}
				}

				$ical .= 'BEGIN:VEVENT
				SUMMARY:' . $summary . '
				UID:' . $uid . '
				STATUS:CONFIRMED
				DTSTART:' . $from . '
				DTEND:' . $to . '
				LOCATION:' . $location . '' . ($organizer != "" ? "\n" . 'ORGANIZER;CN="' . $organizer . '"' : "") . '
				DTSTAMP:' . gmdate('Ymd') . 'T' . gmdate('His') . 'Z
				END:VEVENT
				';

				$count++;
			}

			$ical .= 'END:VCALENDAR';
			$this->ical = trim(preg_replace('/\t+/', '', $ical));
		}

		$this->log("Generated iCal with " . $count . " events");
	}

	public function icalout()
	{

		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == '"' . $this->cache_hash . '"') {
			// Client's cache IS current, so we just respond '304 Not Modified'.
			if (!$this->debug) {
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->cache_lastmodified) . ' GMT', true, 304);
				header('HTTP/1.1 304 Not Modified');
			}

			$this->log('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->cache_lastmodified) . ' GMT');
			$this->log('HTTP/1.1 304 Not Modified');
		} else {

			if (!$this->debug) {
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->cache_lastmodified) . ' GMT', true, 200);
				header('ETag: "' . $this->cache_hash . '"');
				header('Content-type: text/calendar; charset=utf-8');
				header('Content-Disposition: inline; filename=calendar.ics');
			}

			$this->log('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->cache_lastmodified) . ' GMT');
			$this->log('ETag: "' . $this->cache_hash . '"');

			if (!$this->debug) ob_start("ob_gzhandler");
			if ($this->debug) $this->log("Begin with iCal output:\n");

			echo $this->ical;

			if (!$this->debug) ob_end_flush();
		}
	}

	public function veventdate($date)
	{
		return date("Ymd", strtotime($date)) . "T" . date("His", strtotime($date)) . "Z";
	}

	public function log($string, $data = "")
	{
		//$headers = apache_request_headers();
		$file = $this->config->logdir . "/log_" . date("Y") . "-" . date("m") . "-" . date("d") . ".txt";
		$string = date("c") . "\t" . $_SERVER['REMOTE_ADDR'] . "\t" . $string;
		if ($data != "") {
			$string .= print_r($data, true);
		}
		$string .= "\n";
		if ($this->debug) {
			echo $string;
		}
		file_put_contents($file, $string, FILE_APPEND);
	}
}
