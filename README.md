# iCalEws

iCalEws is a PHP script for creating (cached) [iCalendar](https://tools.ietf.org/html/rfc5545) files for Apple Calendars from an EWS (Microsoft Exchange Web Services) backend.

## How to use

Rename config-sample.inc.php and configure to you needs. The important thing is the host. Username and password are passend from HTTP Basic auth to EWS.
**Use SSL/TLS for your webserver to protect the credentials!**

You can control the script with the following HTTP-Get parameters:

Paramter | Function | Example
--- | --- | ---
(none) | Generates iCal output from cache file | `https://ical.myserver.tld`
`update` | Updates cache file | `https://ical.myserver.tld/?update`
`update` | Shows debug output in Browser | `https://ical.myserver.tld/?debug`
`verbose` | Extends the debug in update mode output. Works only with update and debug | `https://ical.myserver.tld/?update&debug&verbose`
`wife` | See [Wife-Mode](##Wife-Mode) | `https://ical.myserver.tld/?wifemode`

## Wife-Mode

The _Wife-Mode_ does **not** what you might think at first. It show only appointsments that 
 * longer than one day
 * ending at 18:00 (6 pm) or later