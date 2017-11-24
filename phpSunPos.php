<?php
/*

https://github.com/KiboOst/php-sunPos

*/

class sunPos{

    public $_version = '1.0';

    //user functions======================================================
	public function getSunPos()
	{
		//get day:
		$date = clone  $this->date;
		$date->setTimezone(new DateTimeZone('UTC'));

		$year = $date->format("Y");
		$month = $date->format("m");
		$day = $date->format("d");

		//get time:
		$hour = $date->format("H");
		$min = $date->format("i");

		$pos = $this->getSunPosition($this->latitude, $this->longitude, $year, $month, $day, $hour, $min);
		$this->elevation = $pos[0];
		$this->azimuth = $pos[1];
		return array('elevation'=>$pos[0], 'azimuth'=>$pos[1]);
	}

	public function getDayPeriod()
	{
		//compensate GMT:
		$ts = $this->date->getTimestamp();
		$sun_info = date_sun_info($ts, $this->latitude, $this->longitude);
		$sunrise = date("H:i:s", $sun_info["sunrise"]);
		$transit = date("H:i:s", $sun_info["transit"]);
		$sunset = date("H:i:s", $sun_info["sunset"]);

		$this->sunrise = $sunrise;
		$this->transit = $transit;
		$this->sunset = $sunset;

		$isDay = 0;
		$isMorning = 0;
		$isNoon = 0;
		$isAfternoon = 0;
		$isEvening = 0;

		$now = $this->date->format('H:i:s');
		if ($now > $sunrise and $now < $sunset) $isDay = 1;
		if ($isDay == 1)
		{
			//do all in minutes!
			$now = new DateTime($now);

			$sunrise = new DateTime($sunrise);
			$intervalRise = $sunrise->diff($now );
			$intervalRise = $intervalRise->h * 60 + $intervalRise->i;
			if ($now < $sunrise) $intervalRise *= -1;

			$transit = new DateTime($transit);
			$intervalTrans = $transit->diff($now );
			$intervalTrans = $intervalTrans->h * 60 + $intervalTrans->i;
			if ($now < $transit) $intervalTrans *= -1;

			$sunset = new DateTime($sunset);
			$intervalSet = $sunset->diff($now );
			$intervalSet = $intervalSet->h * 60 + $intervalSet->i;
			if ($now < $sunset) $intervalSet *= -1;

			//get sun period along day according to day lenght:
			$dayLenght = $sunset->diff($sunrise );
			$dayLenght = $dayLenght->h * 60 + $dayLenght->i;
			$sunnyFactor = $dayLenght / (24 * 60);

			//compares all these:
			$delta = 150 * $sunnyFactor; //larger delta in longer summer days

			/*
			echo 'intervalRise: ', $intervalRise, "<br>";
			echo 'intervalTrans: ', $intervalTrans, "<br>";
			echo 'intervalSet: ', $intervalSet, "<br>";
			echo 'dayLenght: ', $dayLenght, "<br>";
			echo 'sunnyFactor: ', $sunnyFactor, "<br>";
			echo 'delta: ', $delta, "<br>";
			*/

			if ($intervalTrans < -$delta) $isMorning = 1;

			if ($intervalTrans >= -$delta and $intervalTrans <= $delta)
			{
				$isMorning = 0;
				$isNoon = 1;
			}

			if ($intervalTrans > $delta)
			{
				$isNoon = 0;
				$isAfternoon = 1;
			}

			if ($intervalSet >= -$delta)
			{
				$isNoon = 0;
				$isAfternoon = 0;
				$isEvening = 1;
			}
		}

		$this->isDay = $isDay;
		$this->isMorning = $isMorning;
		$this->isNoon = $isNoon;
		$this->isAfternoon = $isAfternoon;
		$this->isEvening = $isEvening;
	}

	public function isSunny($from=0, $to=0)
	{
		if (is_null($this->azimuth))
		{
			$pos = $this->getSunPos();
			$this->elevation = $pos['elevation'];
			$this->azimuth = $pos['azimuth'];
		}

		if ($to < $from)
		{
			if ($this->azimuth < $to) $this->azimuth += 360;
			$to += 360;
		}
		if ($this->azimuth > $from and $this->azimuth < $to) return true;
		return false;
	}


    //INTERNAL FUNCTIONS==================================================
	public function getSunPosition($lat, $long, $year, $month, $day, $hour, $min)
	{
		// From: http://stackoverflow.com/questions/8708048/position-of-the-sun-given-time-of-day-latitude-and-longitude?rq=1
		// online check: https://www.esrl.noaa.gov/gmd/grad/solcalc/

		// Get Julian date for date at noon
		$jd = gregoriantojd($month,$day,$year);

		//correct for half-day offset
		$dayfrac = $hour / 24 - .5;

		//now set the fraction of a day
		$frac = $dayfrac + $min / 60 / 24;
		$jd = $jd + $frac;

		// The input to the Atronomer's almanach is the difference between
		// the Julian date and JD 2451545.0 (noon, 1 January 2000)
		$time = ($jd - 2451545);

		// Ecliptic coordinates

		// Mean longitude
		$mnlong = (280.460 + 0.9856474 * $time);
		$mnlong = fmod($mnlong,360);
		if ($mnlong < 0) $mnlong = ($mnlong + 360);

		// Mean anomaly
		$mnanom = (357.528 + 0.9856003 * $time);
		$mnanom = fmod($mnanom,360);
		if ($mnanom < 0) $mnanom = ($mnanom + 360);
		$mnanom = deg2rad($mnanom);

		// Ecliptic longitude and obliquity of ecliptic
		$eclong = ($mnlong + 1.915 * sin($mnanom) + 0.020 * sin(2 * $mnanom));
		$eclong = fmod($eclong,360);
		if ($eclong < 0) $eclong = ($eclong + 360);
		$oblqec = (23.439 - 0.0000004 * $time);
		$eclong = deg2rad($eclong);
		$oblqec = deg2rad($oblqec);

		// Celestial coordinates
		// Right ascension and declination
		$num = (cos($oblqec) * sin($eclong));
		$den = (cos($eclong));
		$ra = (atan($num / $den));
		if ($den < 0) $ra = ($ra + pi());
		if ($den >= 0 && $num <0) $ra = ($ra + 2*pi());
		$dec = (asin(sin($oblqec) * sin($eclong)));

		// Local coordinates
		// Greenwich mean sidereal time
		//$h = $hour + $min / 60 + $sec / 3600;
		$h = $hour + $min / 60;
		$gmst = (6.697375 + .0657098242 * $time + $h);
		$gmst = fmod($gmst,24);
		if ($gmst < 0) $gmst = ($gmst + 24);

		// Local mean sidereal time
		$lmst = ($gmst + $long / 15);
		$lmst = fmod($lmst,24);
		if ($lmst < 0) $lmst = ($lmst + 24);
		$lmst = deg2rad($lmst * 15);

		// Hour angle
		$ha = ($lmst - $ra);
		if ($ha < pi()) $ha = ($ha + 2*pi());
		if ($ha > pi()) $ha = ($ha - 2*pi());

		// Latitude to radians
		$lat = deg2rad($lat);

		// Azimuth and elevation
		$el = (asin(sin($dec) * sin($lat) + cos($dec) * cos($lat) * cos($ha)));
		$az = (asin(-cos($dec) * sin($ha) / cos($el)));

		// For logic and names, see Spencer, J.W. 1989. Solar Energy. 42(4):353
		if ((sin($dec) - sin($el) * sin($lat)) >00) {
		if(sin($az) < 0) $az = ($az + 2*pi());
		} else {
		$az = (pi() - $az);
		}

		$el = rad2deg($el);
		$az = rad2deg($az);
		$lat = rad2deg($lat);

		return array(number_format($el, 2), number_format($az, 2));
	}

	public $latitude = null;
	public $longitude = null;
	public $date = null;
	public $timezone = null;

	public $elevation = null;
	public $azimuth = null;
	public $sunrise = null;
	public $transit = null;
	public $sunset = null;

	public $isDay = null;
	public $isMorning = null;
	public $isNoon = null;
	public $isAfternoon = null;
	public $isEvening = null;

	protected $dateFormat = 'Y-m-d';

    function __construct($latitude=0, $longitude=0, $timezone=false, $date=false, $time=false)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        if ($timezone)
        {
        	$this->timezone = $timezone;
        	date_default_timezone_set($timezone);
        }
        else $this->timezone = date_default_timezone_get();

        if ($date) $this->date = DateTime::createFromFormat($this->dateFormat, $date);
        else $this->date = new DateTime('NOW', new DateTimeZone($this->timezone));

        if ($time)
        {
        	$var = explode(':', $time);
        	$this->date->setTime($var[0], $var[1]);
    	}

    	$this->getSunPos();
    	$this->getDayPeriod();
    }
//sunPos end
}

?>