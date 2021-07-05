<?php

namespace My\PDFCalendar;

class PDFCalendar
{
    protected $pdf = null;
    protected $config = [];
    protected $page = [];
    protected $header = [];
    protected $content = [];
    protected $footer = [];
    protected $days = [];
    protected $hours = [];
    protected $events = [];
    protected $year = null;
    protected $week = null;

    public function __construct($config = [])
    {
        $this->config = wp_parse_args($config, [
            'file_title' => 'Untitled',
            'logo'       => '',
            'all_day_text' => 'all-day',
            'font_family' => 'Arial',
            'font_weight' => '',
            'font_size_base' => 11,
            'font_size_small' => 9,
            'heading_font_size' => 14,
            'heading_font_weight' => 'B',
            'line_height' => 5,
            'line_height_small' => 4,
            'text_color' => [0, 0, 0],
            'title' => 'Untitled',
            'title_font_size' => 16,
            'draw_color' => [0, 0, 0],
            'fill_color' => [255, 255, 255],
            'color_light' => [147, 147, 147],
            'days' => 7,
            'line_width' => 0.1,
            'hour_start' => 9,
            'hour_end' => 17,
            'events' => [],
            'event_default_color' => [0, 0, 0],
        ]);

        $this->reset();

        // Add events.
        foreach ($this->config['events'] as $event) {
            $this->addEvent($event);
        }

        $this->prepare();
    }

    protected function reset()
    {
        $this->margin = [
            'x' => 10,
            'y' => 10,
        ];

        $this->page = [
            'w' => 297,
            'h' => 220,
        ];

        $this->header = [
            'x' => $this->margin['x'],
            'y' => $this->margin['y'],
            'w' => $this->page['w'] - $this->margin['x'] * 2,
            'h' => 10,
        ];

        $this->footer = [
            'x' => $this->margin['x'],
            'y' => $this->page['h'] - $this->margin['y'] - 3,
            'w' => $this->page['w'] - $this->margin['x'] * 2,
            'h' => 0,
        ];

        $this->content = [
            'x' => $this->margin['x'],
            'y' => $this->header['y'] + $this->header['h'] + 4,
            'w' => $this->page['w'] - $this->margin['x'] * 2,
            'h' => $this->page['h'] - $this->header['y'] - $this->header['h'] - $this->footer['h'] - $this->margin['y'] - 14,
        ];

        $hours_w = 15;

        $this->days = [
            'x' => $this->content['x'] + $hours_w,
            'y' => $this->content['y'],
            'w' => $this->content['w'] - $hours_w,
            'h' => 15,
        ];

        $this->hours = [
            'x' => $this->content['x'],
            'y' => $this->content['y'] + $this->days['h'],
            'w' => $hours_w,
            'h' => $this->content['h'] - $this->days['h'],
        ];
    }

    public function addEvent($args)
    {
        $event = wp_parse_args($args, [
            'name'        => '',
            'date'        => '',
            'from'        => '',
            'until'       => '',
            'text'        => '',
            'allday'      => false,
            'color'       => $this->config['event_default_color'],
        ]);

        $time = strtotime($event['date']);

        if ($time === false) {
            return;
        }

        $year        = (int) date('Y', $time);
        $week        = (int) date('W', $time);
        $month       = (int) date('n', $time);
        $day         = (int) date('j', $time);
        $day_of_week = (int) date('N', $time);
        $from        = Helpers::timeToHours($event['from']);
        $until       = Helpers::timeToHours($event['until']);

        $event = [
            'month'       => $month,
            'day'         => $day,
            'day_of_week' => $day_of_week,
            'from'        => $from,
            'until'       => $until,
        ] + $event;

        $this->events[$year][$week][] = $event;
    }

    protected function getEvents()
    {
        if (isset($this->events[$this->year][$this->week])) {
            return $this->events[$this->year][$this->week];
        }

        return [];
    }

    protected function getDayX($day_of_week)
    {
        return $this->days['x'] + $this->getDayWidth() * ($day_of_week - 1);
    }

    protected function getDayWidth()
    {
        return $this->days['w'] / $this->config['days'];
    }

    protected function getTimeY($hours)
    {
        return $this->hours['y'] + (($this->hours['h'] / ($this->hour_end - $this->hour_start)) * ($hours - $this->hour_start));
    }

    protected function getOverlappingEvents($event, $events)
    {
        $return = [];

        foreach ($events as $key => $_event) {
            if ($event['day'] == $_event['day'] && $_event['from'] < $event['until'] && $event['from'] < $_event['until']) {
                $return[$key] = $_event;
            }
        }

        return $return;
    }

    protected function getDateTime($format, $day_of_week = 1)
    {
        $obj = new \DateTime();
        $obj->setISODate($this->year, $this->week, $day_of_week);

        return date_i18n($format, $obj->format('U'));
    }

    protected function getMonthTitle()
    {
        $time_1 = $this->getDateTime('U');
        $time_2 = strtotime(date('Y-m-d', $time_1) . " + {$this->config['days']} days");

        $month_1 = date_i18n('F', $time_1);
        $month_2 = date_i18n('F', $time_2);

        $year_1 = date('Y', $time_1);
        $year_2 = date('Y', $time_2);

        if ($month_1 == $month_2 && $year_1 == $year_2) {
            return "{$month_1} {$year_1}";
        }

        if ($month_1 != $month_2 && $year_1 == $year_2) {
            return "{$month_1} - {$month_2} {$year_1}";
        }

        return "{$month_1} {$year_1} - {$month_2} {$year_2}";
    }

    protected function prepare()
    {
        foreach ($this->events as $year => $year_events) {
            foreach ($year_events as $week => $events) {
                $a = [];
                foreach ($events as $event) {
                    $overlapping = $this->getOverlappingEvents($event, $events);
                    if (! $overlapping) {
                        continue;
                    }

                    $i = 0;
                    foreach ($overlapping + [$event] as $key => $_event) {
                        if (isset($a[$key])) {
                            continue;
                        }
                        $_event['overlap'] = true;
                        $_event['overlap_index'] = $i;
                        $_event['overlap_total'] = count($overlapping);
                        $this->events[$year][$week][$key] = $_event;
                        $a[$key] = true;
                        $i++;
                    }
                }
            }
        }
    }

    protected function renderHeader()
    {
        // Logo
        if ($this->config['logo'] && file_exists($this->config['logo'])) {

            $image_url = $this->config['logo'];

            // Setup wrapper.

            $wrapper_width = 50;

            $wrapper = [
                'x' => $this->header['x'] + $this->header['w'] - $wrapper_width,
                'y' => $this->header['y'],
                'w' => $wrapper_width,
                'h' => $this->header['h'],
            ];

            // Get image properties.

            list($image_width, $image_height) = @getimagesize($image_url);

            if (! $image_width || ! $image_height) {
                return;
            }

            // Scale to fit wrapper.

            $image = Helpers::scaleToFit($image_width, $image_height, $wrapper['w'], $wrapper['h']);

            // Center inside wrapper.

            $x = $wrapper['x'] + $wrapper['w'] - $image['width']; // Right.
            $y = $wrapper['y'] + ($wrapper['h'] - $image['height']) / 2; // Center.

            // Output.

            $this->pdf->Image($image_url, $x, $y, $image['width'], $image['height']);
        }

        // Title
        $this->pdf->setXY($this->header['x'], $this->header['y'] - 3);
        $this->pdf->SetFont($this->config['font_family'], 'B', $this->config['title_font_size']);
        $this->pdf->MultiCell($this->header['w'], $this->header['h'], $this->pdf->SanitizeText($this->config['title']), 0, 'L');
        $this->pdf->SetFont($this->config['font_family'], '', $this->config['font_size_base']);

        // Display month and Year
        $this->pdf->setXY($this->header['x'], $this->header['y'] + $this->header['h'] - 1);
        $this->pdf->MultiCell($this->header['w'], 0, $this->getMonthTitle(), 0, 'L');
    }

    protected function renderContent()
    {
        $this->renderDays();
        $this->renderAllDay();
        $this->renderHours();
        $this->renderEvents();
    }

    protected function renderDays()
    {
        // Render days.
        for ($day = 1; $day <= $this->config['days']; $day++) {
            $this->pdf->setXY($this->getDayX($day), $this->days['y']);
            $this->pdf->MultiCell($this->getDayWidth(), $this->days['h'], $this->pdf->SanitizeText($this->getDateTime('D j', $day)), 0, 'C');
        }

        // Draw vertical lines
        for ($day = 1; $day <= $this->config['days']; $day++) {
            if ($day === 1) {
                continue;
            }

            $x = $this->getDayX($day);
            $y = $this->hours['y'];
            $h = $this->hours['h'];

            $this->pdf->SetFillColor($this->config['color_light']);
            $this->pdf->SetDrawColor($this->config['color_light']);
            $this->pdf->Rect($x, $y, $this->config['line_width'], $h, 'DF');
            $this->pdf->SetDrawColor($this->config['draw_color']);
            $this->pdf->SetFillColor($this->config['fill_color']);
        }
    }

    protected function renderHours()
    {
        for ($hour = $this->hour_start; $hour <= $this->hour_end; $hour += 0.5) {
            $y = $this->getTimeY($hour);
            // Text
            $this->pdf->setXY($this->hours['x'], $y);
            $this->pdf->setFontSize($this->config['font_size_small']);
            $this->pdf->SetTextColor($this->config['color_light']);
            $this->pdf->MultiCell($this->hours['w'], 0, $this->pdf->SanitizeText(Helpers::hoursToTime($hour)));
            $this->pdf->setFontSize($this->config['font_size_base']);
            $this->pdf->SetTextColor($this->config['text_color']);
            // Line (drawing a rectangle is more precise than a line)
            $this->pdf->SetFillColor($this->config['color_light']);
            $this->pdf->SetDrawColor($this->config['color_light']);
            $this->pdf->Rect($this->days['x'], $y, $this->days['w'], $this->config['line_width'], 'DF');
            $this->pdf->SetDrawColor($this->config['draw_color']);
            $this->pdf->SetFillColor($this->config['fill_color']);
        }
    }

    protected function renderEvents()
    {
        $events = $this->getEvents();

        foreach ($events as $event) {
            if ($event['allday']) {
            } else {
                $this->renderEvent($event);
            }
        }
    }

    protected function renderAllDay()
    {
        $events = $this->getEvents();

        $my_events = [];
        foreach ($events as $event) {
            if ($event['allday']) {
                $my_events[$event['day_of_week']][] = $event;
            }
        }

        if (! $my_events) {
            return;
        }

        $all_day = [
            'x' => $this->days['x'],
            'y' => $this->days['y'] + $this->days['h'],
            'w' => $this->days['w'],
            'h' => 0,
        ];

        $_y = 0;

        foreach ($my_events as $day_of_week => $events) {
            $this->pdf->setY($all_day['y']);

            $this->pdf->setY($this->pdf->getY() + 1);

            foreach ($events as $event) {
                $x = $this->getDayX($day_of_week);

                // Draw name.
                $this->pdf->setX($x);
                $this->pdf->SetFont($this->config['font_family'], 'B', $this->config['font_size_small']);
                $this->pdf->MultiCell($this->getDayWidth(), $this->config['line_height_small'], $this->pdf->SanitizeText($event['name']), 0, 'L');
                $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);

                // Draw text.
                if ($event['text']) {
                    $this->pdf->setXY($x, $this->pdf->getY());
                    $this->pdf->SetFont($this->config['font_family'], 'I', $this->config['font_size_small']);
                    $this->pdf->MultiCell($w, $this->config['line_height_small'], $this->pdf->SanitizeText($event['text']), 0, 'L');
                    $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);
                }
            }

            $this->pdf->setY($this->pdf->getY() + 1);

            $y = $this->pdf->getY();

            if ($y > $_y) {
                $_y = $y;
            }
        }

        $all_day['h'] = $y - $all_day['y'];
        $this->hours['h'] = $this->hours['h'] + ($this->hours['y'] - $_y);
        $this->hours['y'] = $_y;

        // Draw vertical lines
        for ($day = 1; $day <= $this->config['days']; $day++) {
            if ($day === 1) {
                continue;
            }
            $x = $this->getDayX($day);
            $y = $all_day['y'];
            $h = $all_day['h'];

            $this->pdf->SetFillColor($this->config['color_light']);
            $this->pdf->SetDrawColor($this->config['color_light']);
            $this->pdf->Rect($x, $y, $this->config['line_width'], $h, 'DF');
            $this->pdf->SetDrawColor($this->config['draw_color']);
            $this->pdf->SetFillColor($this->config['fill_color']);
        }

        // Draw horizontal lines
        $this->pdf->SetFillColor($this->config['color_light']);
        $this->pdf->SetDrawColor($this->config['color_light']);
        $this->pdf->Rect($all_day['x'], $all_day['y'], $all_day['w'], $this->config['line_width'], 'DF');
        $this->pdf->Rect($all_day['x'], $all_day['y'] + $all_day['h'], $all_day['w'], .5, 'DF');
        $this->pdf->SetDrawColor($this->config['draw_color']);
        $this->pdf->SetFillColor($this->config['fill_color']);

        // Text
        $this->pdf->setXY($this->hours['x'], $all_day['y']);
        $this->pdf->setFontSize($this->config['font_size_small']);
        $this->pdf->SetTextColor($this->config['color_light']);
        $this->pdf->MultiCell($this->hours['w'], 0, $this->pdf->SanitizeText($this->config['all_day_text']));
        $this->pdf->setFontSize($this->config['font_size_base']);
        $this->pdf->SetTextColor($this->config['text_color']);
    }

    protected function renderEvent($event)
    {
        $x = $this->getDayX($event['day_of_week']);
        $y = $this->getTimeY($event['from']);
        $w = $this->getDayWidth();
        $h = $this->getTimeY($event['until']) - $y;

        // Overlap.
        if (! empty($event['overlap'])) {
            $x += $w / $event['overlap_total'] * $event['overlap_index'];
            $w = $w / $event['overlap_total'];
        }

        // Draw frame.
        $this->pdf->Rect($x, $y, $w, $h, 'DF');

        // Draw color.
        $this->pdf->SetFillColor($event['color']);
        $this->pdf->Rect($x, $y, $w, 1, 'F');
        $this->pdf->SetFillColor($this->config['fill_color']);

        // Draw starting time.
        $this->pdf->setXY($x, $y + 2);
        $this->pdf->SetFontSize($this->config['font_size_small']);
        $this->pdf->MultiCell($w, $this->config['line_height_small'], Helpers::hoursToTime($event['from']), 0, 'L');
        $this->pdf->SetFontSize($this->config['font_size_base']);

        // Draw name.
        $this->pdf->setXY($x, $y + 6);
        $this->pdf->SetFont($this->config['font_family'], 'B', $this->config['font_size_small']);
        $this->pdf->MultiCell($w, $this->config['line_height_small'], $this->pdf->SanitizeText($event['name']), 0, 'L');
        $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);

        // Draw text.
        if ($event['text']) {
            $this->pdf->setXY($x, $this->pdf->getY());
            $this->pdf->SetFont($this->config['font_family'], 'I', $this->config['font_size_small']);
            $this->pdf->MultiCell($w, $this->config['line_height_small'], $this->pdf->SanitizeText($event['text']), 0, 'L');
            $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);
        }
    }

    protected function renderFooter()
    {
    }

    protected function renderGuides()
    {
        $this->pdf->SetDrawColor([255, 0, 0]);

        foreach ([$this->header, $this->content, $this->days, $this->hours, $this->footer] as $subject) {
            $this->pdf->Rect($subject['x'], $subject['y'], $subject['w'], $subject['h'], 'D');
        }

        $this->pdf->SetDrawColor($this->config['draw_color']);
    }

    public function renderWeek($week, $year)
    {
        $this->week = $week;
        $this->year = $year;

        $events = $this->getEvents();

        // Set hour range

        $this->hour_start = $this->config['hour_start'];
        $this->hour_end   = $this->config['hour_end'];

        foreach ($events as $event) {
            if (! $event['allday']) {
                if ($event['from'] < $this->hour_start) {
                    $this->hour_start = $event['from'];
                }

                if ($event['until'] > $this->hour_end) {
                    $this->hour_end = $event['until'];
                }
            }
        }

        $this->pdf->SetAutoPageBreak(false, $this->margin['y']);
        $this->pdf->AddPage($this->page['w'] > $this->page['h'] ? 'L' : 'P', [$this->page['w'], $this->page['h']]);

        $this->renderHeader();
        $this->renderContent();
        $this->renderFooter();

        $this->reset();

        //$this->renderGuides();
    }

    public function render()
    {
        $this->pdf = new PDF();
        $this->pdf->SetTitle($this->config['file_title']);
        $this->pdf->setMargins(0, 0, 0);
        $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);
        $this->pdf->SetLineWidth($this->config['line_width']);
        $this->pdf->SetDrawColor($this->config['draw_color']);
        $this->pdf->SetFillColor($this->config['fill_color']);

        // Sort events

        ksort($this->events, SORT_NUMERIC);

        foreach ($this->events as $year => $year_events) {
            ksort($year_events, SORT_NUMERIC);

            foreach ($year_events as $week => $week_events) {
                $this->renderWeek($week, $year);
            }
        }

        return call_user_func_array([$this->pdf, 'Output'], func_get_args());
    }
}
