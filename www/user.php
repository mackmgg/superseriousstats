<?php

/**
 * Copyright (c) 2007-2013, Jos de Ruijter <jos@dutnie.nl>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/**
 * Class for creating userstats.
 */
final class user
{
	/**
	 * Default settings for this script, can be overridden in the vars.php file. Should be present in $settings_whitelist in order to get changed.
	 */
	private $channel = '';
	private $db_host = '127.0.0.1';
	private $db_name = 'sss';
	private $db_pass = '';
	private $db_port = 3306;
	private $db_user = '';
	private $debug = false;
	private $mainpage = './';
	private $stylesheet = 'sss.css';
	private $timezone = 'UTC';

	/**
	 * Variables that shouldn't be tampered with.
	 */
	private $cid = '';
	private $color = array(
		'night' => 'b',
		'morning' => 'g',
		'afternoon' => 'y',
		'evening' => 'r');
	private $csnick = '';
	private $currentyear = 0;
	private $date_lastlogparsed = '';
	private $date_max = '';
	private $dayofmonth = 0;
	private $daysleft = 0;
	private $estimate = false;
	private $firstseen = '';
	private $l_avg = 0;
	private $l_max = 0;
	private $l_total = 0;
	private $lastseen = '';
	private $month = 0;
	private $mood = '';
	private $mysqli;
	private $nick = '';
	private $ruid = 0;
	private $settings_whitelist = array('channel', 'db_host', 'db_name', 'db_pass', 'db_port', 'db_user', 'debug', 'mainpage', 'stylesheet', 'timezone');
	private $year = 0;
	private $years = 0;

	public function __construct($cid, $nick)
	{
		$this->cid = $cid;
		$this->nick = $nick;

		/**
		 * Load settings from vars.php.
		 */
		if ((@include 'vars.php') === false) {
			exit('Missing configuration.');
		}

		if (empty($settings[$this->cid])) {
			exit('Not configured.');
		}

		/**
		 * $cid is the channel ID used in vars.php and is passed along in the URL so that channel specific settings can be identified and loaded.
		 */
		foreach ($settings[$this->cid] as $key => $value) {
			if (in_array($key, $this->settings_whitelist)) {
				$this->$key = $value;
			}
		}

		date_default_timezone_set($this->timezone);
	}

	public function make_html()
	{
		$this->mysqli = @mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port) or $this->output('critical', 'mysqli: '.mysqli_connect_error());
		mysqli_set_charset($this->mysqli, 'utf8') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$query = @mysqli_query($this->mysqli, 'select `ruid` from `user_status` join `user_details` on `user_status`.`uid` = `user_details`.`uid` where `csnick` = \''.mysqli_real_escape_string($this->mysqli, $this->nick).'\'') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		/**
		 * The user does not exist in the database.
		 */
		if (empty($rows)) {
			exit('No data.');
		}

		$result = mysqli_fetch_object($query);
		$this->ruid = (int) $result->ruid;
		$query = @mysqli_query($this->mysqli, 'select (select `csnick` from `user_details` where `uid` = '.$this->ruid.') as `csnick`, min(`firstseen`) as `firstseen`, max(`lastseen`) as `lastseen`, `l_total`, (`l_total` / `activedays`) as `l_avg` from `user_details` join `user_status` on `user_details`.`uid` = `user_status`.`uid` join `q_lines` on `user_status`.`ruid` = `q_lines`.`ruid` where `user_status`.`ruid` = '.$this->ruid.' and `firstseen` != \'0000-00-00 00:00:00\'') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);

		/**
		 * Exit if the user has no logged activity. Most functions don't expect to be run on an empty database so keep this check in place.
		 */
		if (empty($result->l_total)) {
			exit('No data.');
		}

		$this->csnick = $result->csnick;
		$this->firstseen = $result->firstseen;
		$this->lastseen = $result->lastseen;
		$this->l_total = (int) $result->l_total;
		$this->l_avg = (float) $result->l_avg;

		/**
		 * Fetch the users mood.
		 */
		$query = @mysqli_query($this->mysqli, 'select * from `q_smileys` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (!empty($rows)) {
			$result = mysqli_fetch_object($query);

			foreach ($result as $key => $value) {
				$smileys_totals[$key] = (int) $value;
			}

			arsort($smileys_totals);
			$smileys = array(
				's_01' => ':)',
				's_02' => ';)',
				's_03' => ':(',
				's_04' => ':P',
				's_05' => ':D',
				's_06' => ';(',
				's_07' => ':/',
				's_08' => '\\o/',
				's_09' => ':))',
				's_10' => '<3',
				's_11' => ':o',
				's_12' => '=)',
				's_13' => ':-)',
				's_14' => ':x',
				's_15' => ':\\',
				's_16' => 'D:',
				's_17' => ':|',
				's_18' => ';-)',
				's_19' => ';P',
				's_20' => '=]',
				's_21' => ':3',
				's_22' => '8)',
				's_23' => ':<',
				's_24' => ':>',
				's_25' => '=P',
				's_26' => ';x',
				's_27' => ':-D',
				's_28' => ';))',
				's_29' => ':]',
				's_30' => ';D',
				's_31' => '-_-',
				's_32' => ':S',
				's_33' => '=/',
				's_34' => '=\\',
				's_35' => ':((',
				's_36' => '=D',
				's_37' => ':-/',
				's_38' => ':-P',
				's_39' => ';_;',
				's_40' => ';/',
				's_41' => ';]',
				's_42' => ':-(',
				's_43' => ':\'(',
				's_44' => '=(',
				's_45' => '-.-',
				's_46' => ';((',
				's_47' => '=X',
				's_48' => ':[',
				's_49' => '>:(',
				's_50' => ';o');

			foreach ($smileys_totals as $key => $value) {
				if ($key != 'ruid') {
					$this->mood = htmlspecialchars($smileys[$key]);
					break;
				}
			}
		}

		/**
		 * Date and time variables used throughout the script. These are based on the date of the last logfile parsed and used to define our scope.
		 */
		$query = @mysqli_query($this->mysqli, 'select max(`date`) as `date` from `parse_history`') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);
		$this->date_lastlogparsed = $result->date;
		$this->dayofmonth = (int) date('j', strtotime($this->date_lastlogparsed));
		$this->month = (int) date('n', strtotime($this->date_lastlogparsed));
		$this->year = (int) date('Y', strtotime($this->date_lastlogparsed));
		$this->years = $this->year - (int) date('Y', strtotime($this->firstseen)) + 1;
		$this->daysleft = (int) date('z', strtotime('last day of December '.$this->year)) - (int) date('z', strtotime($this->date_lastlogparsed));
		$this->currentyear = (int) date('Y');

		/**
		 * If we have less than 3 years of data we set the amount of years to 3 so we have that many columns in our table. Looks better.
		 */
		if ($this->years < 3) {
			$this->years = 3;
		}

		/**
		 * If there are still days ahead of us in the current year, we try to calculate an estimated line count and display it in an additional column.
		 * Don't forget to add another 34px to the table width, a bit further down in the html head.
		 */
		if ($this->daysleft != 0 && $this->year == $this->currentyear) {
			/**
			 * We base our calculations on the activity of the last 90 days logged. If there is none we won't display the extra column.
			 */
			$query = @mysqli_query($this->mysqli, 'select count(*) as `activity` from `q_activity_by_day` where `ruid` = '.$this->ruid.' and `date` > \''.date('Y-m-d', mktime(0, 0, 0, $this->month, $this->dayofmonth - 90, $this->year)).'\'') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
			$result = mysqli_fetch_object($query);

			if (!empty($result->activity)) {
				$this->estimate = true;
			}
		}

		/**
		 * HTML Head.
		 */
		$query = @mysqli_query($this->mysqli, 'select `date`, `l_total` from `q_activity_by_day` where `ruid` = '.$this->ruid.' order by `l_total` desc, `date` asc limit 1') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);
		$this->date_max = $result->date;
		$this->l_max = (int) $result->l_total;
		$output = '<!DOCTYPE html>'."\n\n"
			. '<html>'."\n\n"
			. '<head>'."\n"
			. '<meta charset="utf-8">'."\n"
			. '<title>'.htmlspecialchars($this->csnick).', seriously.</title>'."\n"
			. '<link rel="stylesheet" href="'.$this->stylesheet.'">'."\n"
			. '<style type="text/css">'."\n"
			. '  .act-year { width:'.(2 + (($this->years + ($this->estimate ? 1 : 0)) * 34)).'px }'."\n"
			. '</style>'."\n"
			. '</head>'."\n\n"
			. '<body><div id="container">'."\n"
			. '<div class="info">'.htmlspecialchars($this->csnick).', seriously'.($this->mood != '' ? ' '.$this->mood : '.').'<br><br>'
			. 'First seen on '.date('M j, Y', strtotime($this->firstseen)).' and last seen on '.date('M j, Y', strtotime($this->lastseen)).'.<br><br>'
			. htmlspecialchars($this->csnick).' typed '.number_format($this->l_total).' line'.($this->l_total > 1 ? 's' : '').' on <a href="'.$this->mainpage.'">'.htmlspecialchars($this->channel).'</a> &ndash; an average of '.number_format($this->l_avg).' line'.($this->l_avg > 1 ? 's' : '').' per day.<br>'
			. 'Most active day was '.date('M j, Y', strtotime($this->date_max)).' with a total of '.number_format($this->l_max).' line'.($this->l_max > 1 ? 's' : '').' typed.</div>'."\n";

		/**
		 * Activity section.
		 */
		$output .= '<div class="section">Activity</div>'."\n";
		$output .= $this->make_table_activity_distribution_hour();
		$output .= $this->make_table_activity('day');
		$output .= $this->make_table_activity('month');
		$output .= $this->make_table_activity_distribution_day();
		$output .= $this->make_table_activity('year');

		/**
		 * HTML Foot.
		 */
		$output .= '<div class="info">Statistics created with <a href="http://sss.dutnie.nl">superseriousstats</a> on '.date('r').'.</div>'."\n";
		$output .= '</div></body>'."\n\n".'</html>'."\n";
		@mysqli_close($this->mysqli);
		return $output;
	}

	private function make_table_activity($type)
	{
		if ($type == 'day') {
			$class = 'act';
			$columns = 24;

			for ($i = 23; $i >= 0; $i--) {
				$dates[] = date('Y-m-d', mktime(0, 0, 0, $this->month, $this->dayofmonth - $i, $this->year));
			}

			$head = 'Activity by Day';
			$query = @mysqli_query($this->mysqli, 'select `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` from `q_activity_by_day` where `ruid` = '.$this->ruid.' and `date` > \''.date('Y-m-d', mktime(0, 0, 0, $this->month, $this->dayofmonth - 24, $this->year)).'\'') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		} elseif ($type == 'month') {
			$class = 'act';
			$columns = 24;

			for ($i = 23; $i >= 0; $i--) {
				$dates[] = date('Y-m', mktime(0, 0, 0, $this->month - $i, 1, $this->year));
			}

			$head = 'Activity by Month';
			$query = @mysqli_query($this->mysqli, 'select `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` from `q_activity_by_month` where `ruid` = '.$this->ruid.' and `date` > \''.date('Y-m', mktime(0, 0, 0, $this->month - 24, 1, $this->year)).'\'') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		} elseif ($type == 'year') {
			$class = 'act-year';
			$columns = $this->years;

			for ($i = $this->years - 1; $i >= 0; $i--) {
				$dates[] = $this->year - $i;
			}

			if ($this->estimate) {
				$columns++;
				$dates[] = 'estimate';
			}

			$head = 'Activity by Year';
			$query = @mysqli_query($this->mysqli, 'select `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` from `q_activity_by_year` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		}

		$rows = mysqli_num_rows($query);

		/**
		 * The queries above will either return one or more rows with activity, or no rows at all.
		 */
		if (empty($rows)) {
			return null;
		}

		$high_date = '';
		$high_value = 0;

		while ($result = mysqli_fetch_object($query)) {
			$l_night[$result->date] = (int) $result->l_night;
			$l_morning[$result->date] = (int) $result->l_morning;
			$l_afternoon[$result->date] = (int) $result->l_afternoon;
			$l_evening[$result->date] = (int) $result->l_evening;
			$l_total[$result->date] = (int) $result->l_total;

			if ($l_total[$result->date] > $high_value) {
				$high_date = $result->date;
				$high_value = $l_total[$result->date];
			}
		}

		if ($this->estimate && $type == 'year') {
			$query = @mysqli_query($this->mysqli, 'select (sum(`l_night`) / 90) as `l_night_avg`, (sum(`l_morning`) / 90) as `l_morning_avg`, (sum(`l_afternoon`) / 90) as `l_afternoon_avg`, (sum(`l_evening`) / 90) as `l_evening_avg`, (sum(`l_total`) / 90) as `l_total_avg` from `q_activity_by_day` where `ruid` = '.$this->ruid.' and `date` > \''.date('Y-m-d', mktime(0, 0, 0, $this->month, $this->dayofmonth - 90, $this->year)).'\'') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
			$result = mysqli_fetch_object($query);
			$l_night['estimate'] = $l_night[$this->currentyear] + round((float) $result->l_night_avg * $this->daysleft);
			$l_morning['estimate'] = $l_morning[$this->currentyear] + round((float) $result->l_morning_avg * $this->daysleft);
			$l_afternoon['estimate'] = $l_afternoon[$this->currentyear] + round((float) $result->l_afternoon_avg * $this->daysleft);
			$l_evening['estimate'] = $l_evening[$this->currentyear] + round((float) $result->l_evening_avg * $this->daysleft);
			$l_total['estimate'] = $l_total[$this->currentyear] + round((float) $result->l_total_avg * $this->daysleft);

			if ($l_total['estimate'] > $high_value) {
				$high_date = 'estimate';
				$high_value = $l_total['estimate'];
			}
		}

		$tr1 = '<tr><th colspan="'.$columns.'">'.$head;
		$tr2 = '<tr class="bars">';
		$tr3 = '<tr class="sub">';

		foreach ($dates as $date) {
			if (!array_key_exists($date, $l_total) || $l_total[$date] == 0) {
				$tr2 .= '<td><span class="grey">n/a</span>';
			} else {
				if ($l_total[$date] >= 999500) {
					$total = number_format($l_total[$date] / 1000000, 1).'M';
				} elseif ($l_total[$date] >= 10000) {
					$total = round($l_total[$date] / 1000).'K';
				} else {
					$total = $l_total[$date];
				}

				$times = array('evening', 'afternoon', 'morning', 'night');

				foreach ($times as $time) {
					if (${'l_'.$time}[$date] != 0) {
						$height[$time] = round((${'l_'.$time}[$date] / $high_value) * 100);
					} else {
						$height[$time] = 0;
					}
				}

				$tr2 .= '<td'.($date == 'estimate' ? ' class="est"' : '').'><ul><li class="num" style="height:'.($height['night'] + $height['morning'] + $height['afternoon'] + $height['evening'] + 14).'px">'.$total;

				foreach ($times as $time) {
					if ($time == 'evening') {
						$height_li = $height['night'] + $height['morning'] + $height['afternoon'] + $height['evening'];
					} elseif ($time == 'afternoon') {
						$height_li = $height['night'] + $height['morning'] + $height['afternoon'];
					} elseif ($time == 'morning') {
						$height_li = $height['night'] + $height['morning'];
					} elseif ($time == 'night') {
						$height_li = $height['night'];
					}

					if ($height[$time] != 0) {
						$tr2 .= '<li class="'.$this->color[$time].'" style="height:'.$height_li.'px">';
					}
				}

				$tr2 .= '</ul>';
			}

			if ($type == 'day') {
				$tr3 .= '<td'.($high_date == $date ? ' class="bold"' : '').'>'.date('D', strtotime($date)).'<br>'.date('j', strtotime($date));
			} elseif ($type == 'month') {
				$tr3 .= '<td'.($high_date == $date ? ' class="bold"' : '').'>'.date('M', strtotime($date.'-01')).'<br>'.date('\'y', strtotime($date.'-01'));
			} elseif ($type == 'year') {
				$tr3 .= '<td'.($high_date == $date ? ' class="bold"' : '').'>'.($date == 'estimate' ? 'Est.' : date('\'y', strtotime($date.'-01-01')));
			}
		}

		return '<table class="'.$class.'">'.$tr1.$tr2.$tr3.'</table>'."\n";
	}

	private function make_table_activity_distribution_day()
	{
		$query = @mysqli_query($this->mysqli, 'select `l_mon_night`, `l_mon_morning`, `l_mon_afternoon`, `l_mon_evening`, `l_tue_night`, `l_tue_morning`, `l_tue_afternoon`, `l_tue_evening`, `l_wed_night`, `l_wed_morning`, `l_wed_afternoon`, `l_wed_evening`, `l_thu_night`, `l_thu_morning`, `l_thu_afternoon`, `l_thu_evening`, `l_fri_night`, `l_fri_morning`, `l_fri_afternoon`, `l_fri_evening`, `l_sat_night`, `l_sat_morning`, `l_sat_afternoon`, `l_sat_evening`, `l_sun_night`, `l_sun_morning`, `l_sun_afternoon`, `l_sun_evening` from `q_lines` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);
		$high_day = '';
		$high_value = 0;
		$days = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');

		foreach ($days as $day) {
			$l_night[$day] = (int) $result->{'l_'.$day.'_night'};
			$l_morning[$day] = (int) $result->{'l_'.$day.'_morning'};
			$l_afternoon[$day] = (int) $result->{'l_'.$day.'_afternoon'};
			$l_evening[$day] = (int) $result->{'l_'.$day.'_evening'};
			$l_total[$day] = $l_night[$day] + $l_morning[$day] + $l_afternoon[$day] + $l_evening[$day];

			if ($l_total[$day] > $high_value) {
				$high_day = $day;
				$high_value = $l_total[$day];
			}
		}

		$tr1 = '<tr><th colspan="7">Activity Distribution by Day';
		$tr2 = '<tr class="bars">';
		$tr3 = '<tr class="sub">';

		foreach ($days as $day) {
			if ($l_total[$day] == 0) {
				$tr2 .= '<td><span class="grey">n/a</span>';
			} else {
				$percentage = ($l_total[$day] / $this->l_total) * 100;

				if ($percentage >= 9.95) {
					$percentage = round($percentage).'%';
				} else {
					$percentage = number_format($percentage, 1).'%';
				}

				$times = array('evening', 'afternoon', 'morning', 'night');

				foreach ($times as $time) {
					if (${'l_'.$time}[$day] != 0) {
						$height[$time] = round((${'l_'.$time}[$day] / $high_value) * 100);
					} else {
						$height[$time] = 0;
					}
				}

				$tr2 .= '<td><ul><li class="num" style="height:'.($height['night'] + $height['morning'] + $height['afternoon'] + $height['evening'] + 14).'px">'.$percentage;

				foreach ($times as $time) {
					if ($time == 'evening') {
						$height_li = $height['night'] + $height['morning'] + $height['afternoon'] + $height['evening'];
					} elseif ($time == 'afternoon') {
						$height_li = $height['night'] + $height['morning'] + $height['afternoon'];
					} elseif ($time == 'morning') {
						$height_li = $height['night'] + $height['morning'];
					} elseif ($time == 'night') {
						$height_li = $height['night'];
					}

					if ($height[$time] != 0) {
						$tr2 .= '<li class="'.$this->color[$time].'" style="height:'.$height_li.'px" title="'.number_format($l_total[$day]).'">';
					}
				}

				$tr2 .= '</ul>';
			}

			$tr3 .= '<td'.($high_day == $day ? ' class="bold"' : '').'>'.ucfirst($day);
		}

		return '<table class="act-day">'.$tr1.$tr2.$tr3.'</table>'."\n";
	}

	private function make_table_activity_distribution_hour()
	{
		$query = @mysqli_query($this->mysqli, 'select `l_00`, `l_01`, `l_02`, `l_03`, `l_04`, `l_05`, `l_06`, `l_07`, `l_08`, `l_09`, `l_10`, `l_11`, `l_12`, `l_13`, `l_14`, `l_15`, `l_16`, `l_17`, `l_18`, `l_19`, `l_20`, `l_21`, `l_22`, `l_23` from `q_lines` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);
		$high_key = '';
		$high_value = 0;

		foreach ($result as $key => $value) {
			if ((int) $value > $high_value) {
				$high_key = $key;
				$high_value = (int) $value;
			}
		}

		$tr1 = '<tr><th colspan="24">Activity Distribution by Hour';
		$tr2 = '<tr class="bars">';
		$tr3 = '<tr class="sub">';

		foreach ($result as $key => $value) {
			$hour = (int) preg_replace('/^l_0?/', '', $key);

			if ((int) $value == 0) {
				$tr2 .= '<td><span class="grey">n/a</span>';
			} else {
				$percentage = ((int) $value / $this->l_total) * 100;

				if ($percentage >= 9.95) {
					$percentage = round($percentage).'%';
				} else {
					$percentage = number_format($percentage, 1).'%';
				}

				$height = round(((int) $value / $high_value) * 100);
				$tr2 .= '<td><ul><li class="num" style="height:'.($height + 14).'px">'.$percentage;

				if ($height != 0) {
					if ($hour >= 0 && $hour <= 5) {
						$time = 'night';
					} elseif ($hour >= 6 && $hour <= 11) {
						$time = 'morning';
					} elseif ($hour >= 12 && $hour <= 17) {
						$time = 'afternoon';
					} elseif ($hour >= 18 && $hour <= 23) {
						$time = 'evening';
					}

					$tr2 .= '<li class="'.$this->color[$time].'" style="height:'.$height.'px" title="'.number_format((int) $value).'">';
				}

				$tr2 .= '</ul>';
			}

			$tr3 .= '<td'.($high_key == $key ? ' class="bold"' : '').'>'.$hour.'h';
		}

		return '<table class="act">'.$tr1.$tr2.$tr3.'</table>'."\n";
	}

	/**
	 * For compatibility reasons this function has the same name as the original version in the base class and accepts the same arguments. Its functionality
	 * is slightly different in that it exits on any type of message passed to it.
	 */
	private function output($type, $msg)
	{
		/**
		 * If $debug is set to true we exit with the given message, otherwise exit silently.
		 */
		if ($this->debug) {
			exit($msg);
		} else {
			exit;
		}
	}
}

/**
 * If the channel ID is not set, empty, or has the value "__global" we exit.
 */
if (!isset($_GET['cid']) || !preg_match('/^\S+$/', $_GET['cid']) || preg_match('/^__global$/', $_GET['cid'])) {
	exit;
}

$cid = $_GET['cid'];

/**
 * If nick is not set, empty, or has an erroneous value we also exit.
 */
if (!isset($_GET['nick']) || !preg_match('/^[][^{}|\\\`_0-9a-z-]{1,255}$/i', $_GET['nick'])) {
	exit;
}

$nick = $_GET['nick'];

/**
 * Create the statspage!
 */
$user = new user($cid, $nick);
echo $user->make_html();

?>
