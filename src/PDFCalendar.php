<?php

namespace My\PDFCalendar;

class PDFCalendar
{
    protected $pdf        = null;
    protected $config     = [];
    protected $page       = [];
    protected $header     = [];
    protected $content    = [];
    protected $footer     = [];
    protected $days       = [];
    protected $hours      = [];
    protected $categories = [];
    protected $events     = [];
    protected $slots      = [];
    protected $year       = null;
    protected $week       = null;

    public function __construct($config = [])
    {
        $this->config = wp_parse_args($config, [
            'title'               => 'Untitled',
            'file_title'          => 'Untitled',
            'logo'                => '',
            'font_family'         => 'Arial',
            'font_weight'         => '',
            'font_size_base'      => 11,
            'font_size_small'     => 9,
            'heading_font_size'   => 14,
            'heading_font_weight' => 'B',
            'line_height'         => 5,
            'line_height_small'   => 4,
            'text_color'          => [0, 0, 0],
            'title_font_size'     => 16,
            'draw_color'          => [0, 0, 0],
            'fill_color'          => [255, 255, 255],
            'color_light'         => [147, 147, 147],
            'line_width'          => 0.1,
            'days_in_week'        => 7,
            'hour_start'          => 9,
            'hour_end'            => 17,
            'events'              => [],
            'categories'          => [],
            'event_default_color' => [0, 0, 0],
            'logo_wrapper_width'  => 50,
            'date_format'         => 'D j',
            'month_text'          => '',
            'debug'               => false,
        ]);

        $this->margin = [
            'x' => 10,
            'y' => 10,
        ];

        $this->page = [
            'w' => 297,
            'h' => 210,
        ];

        $this->header = [
            'x' => $this->margin['x'],
            'y' => $this->margin['y'],
            'w' => $this->page['w'] - $this->margin['x'] * 2,
            'h' => 10,
        ];

        $footer_height = $this->config['categories'] ? 3 : 0;

        $this->footer = [
            'x' => $this->margin['x'],
            'y' => $this->page['h'] - $this->margin['y'] - $footer_height,
            'w' => $this->page['w'] - $this->margin['x'] * 2,
            'h' => $footer_height,
        ];

        $content_margin_y = 4;

        $this->content = [
            'x' => $this->margin['x'],
            'y' => $this->header['y'] + $this->header['h'] + $content_margin_y,
            'w' => $this->page['w'] - $this->margin['x'] * 2,
            'h' => $this->page['h'] - $this->header['y'] - $this->header['h'] - $this->margin['y'] - $content_margin_y,
        ];

        if ($this->footer['h']) {
            $this->content['h'] -= $this->footer['h'] + $content_margin_y + 4;
        }

        $hours_w = 15;

        $this->days = [
            'x' => $this->content['x'] + $hours_w,
            'y' => $this->content['y'],
            'w' => $this->content['w'] - $hours_w,
            'h' => 12,
        ];

        $this->hours = [
            'x' => $this->content['x'],
            'y' => $this->content['y'] + $this->days['h'],
            'w' => $hours_w,
            'h' => $this->content['h'] - $this->days['h'],
        ];

        // Add categories.
        foreach ($this->config['categories'] as $category) {
            $this->addCategory($category);
        }

        // Add events.
        foreach ($this->config['events'] as $event) {
            $this->addEvent($event);
        }
    }

    protected function addCategory($args)
    {
        $category = wp_parse_args($args, [
            'id'    => '',
            'name'  => '',
            'color'  => [],
        ]);

        $this->categories[$category['id']] = $category;
    }

    protected function addEvent($args)
    {
        $event = wp_parse_args($args, [
            'id'       => '',
            'name'     => '',
            'date'     => '', // Y-m-d
            'start'    => '', // H:i:s
            'end'      => '', // H:i:s
            'text'     => '',
            'color'    => $this->config['event_default_color'],
            'category' => '',
        ]);

        $date = strtotime($args['date']);

        if ($time === false) {
            return;
        }

        $event = [];
        $event['id']          = $args['id'];
        $event['text']        = $args['text'];
        $event['name']        = $args['name'];
        $event['date']        = date('Y-m-d', $date);
        $event['start']       = Helpers::timeToHours($args['start']);
        $event['end']         = Helpers::timeToHours($args['end']);
        $event['year']        = intval(date('Y', $date));
        $event['month']       = intval(date('n', $date));
        $event['day']         = intval(date('j', $date));
        $event['week']        = intval(date('W', $date));
        $event['day_of_week'] = intval(date('N', $date));
        $event['color']       = $args['color'];
        $event['category']    = $args['category'];

        $this->events[$event['year']][$event['week']][$event['day_of_week']][$event['id']] = $event;
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
        return $this->days['w'] / $this->config['days_in_week'];
    }

    protected function getTimeY($hours)
    {
        return $this->hours['y'] + (($this->hours['h'] / ($this->hour_end - $this->hour_start)) * ($hours - $this->hour_start));
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
        $time_2 = strtotime(date('Y-m-d', $time_1) . ' + ' . $this->config['days_in_week'] . ' days');

        $month_1 = date_i18n('F', $time_1);
        $month_2 = date_i18n('F', $time_2);

        $year_1 = date('Y', $time_1);
        $year_2 = date('Y', $time_2);

        if ($month_1 == $month_2 && $year_1 == $year_2) {
            $return =  "{$month_1} {$year_1}";
        } else if ($month_1 != $month_2 && $year_1 == $year_2) {
            $return = "{$month_1} - {$month_2} {$year_1}";
        } else {
            $return =  "{$month_1} {$year_1} - {$month_2} {$year_2}";
        }

        $callback = $this->config['month_text'];

        if ($callback && is_callable($callback)) {
            $return = call_user_func($callback, $return, $time_1, $time_2);
        }

        return $return;
    }

    public function sortEvents($a, $b)
    {
        if ($a['start'] == $b['start']) {
            return 0;
        }
        return ($a['start'] < $b['start']) ? -1 : 1;
    }

    protected function prepare()
    {
        foreach ($this->events as $year => &$year_events) {
            ksort($year_events);
            foreach ($year_events as $week => &$week_events) {
                ksort($week_events);
                foreach ($week_events as $day_of_week => &$events) {
                    usort($events, [$this, 'sortEvents']);

                    $slots = [];
                    foreach ($events as &$event) {
                        // Prevents event overlapping when end time is equal to start time.
                        $event['end'] -= 1/(60*60);
                        // Get available slot
                        $index = 0;
                        $stop = false;
                        while (! $stop) {
                            $stop = true;
                            for ($hour = $event['start']; $hour <= $event['end']; $hour += 1/60) {
                                if (isset($slots["$hour"][$index])) {
                                    $stop = false;
                                    $index++;
                                    break;
                                }
                            }
                        }
                        // Add event to slot
                        for ($hour = $event['start']; $hour <= $event['end']; $hour += 1/60) {
                            $slots["$hour"][$index] = $event['id'];
                        }
                        // Store index
                        $event['index'] = $index;
                    }

                    foreach ($events as &$event) {
                        $width = 1;
                        for ($hour = $event['start']; $hour <= $event['end']; $hour += 1/60) {
                            $count = count($slots["$hour"]);
                            if ($count > $width) {
                                $width = $count;
                            }
                        }
                        $event['width'] = 1/$width;
                    }
                }
            }
        }
    }

    protected function renderLogo()
    {
        if (! $this->config['logo'] || ! file_exists($this->config['logo'])) {
            return;
        }

        $image_url = $this->config['logo'];

        // Setup wrapper.

        $wrapper = [
            'x' => $this->header['x'] + $this->header['w'] - $this->config['logo_wrapper_width'],
            'y' => $this->header['y'],
            'w' => $this->config['logo_wrapper_width'],
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

    protected function renderHeader()
    {
        // Logo
        $this->renderLogo();

        // Title
        $this->pdf->setXY($this->header['x'], $this->header['y'] - 3);
        $this->pdf->SetFont($this->config['font_family'], 'B', $this->config['title_font_size']);
        $this->pdf->MultiCell($this->header['w'], $this->header['h'], $this->pdf->SanitizeText($this->config['title']), 0, 'L');
        $this->pdf->SetFont($this->config['font_family'], '', $this->config['font_size_base']);

        // Display month and Year
        $this->pdf->setXY($this->header['x'], $this->header['y'] + $this->header['h'] - 1);
        $this->pdf->MultiCell($this->header['w'], 0, $this->getMonthTitle(), 0, 'L');
    }

    protected function renderDays()
    {
        // Render days.
        for ($day_in_week = 1; $day_in_week <= $this->config['days_in_week']; $day_in_week++) {
            $this->pdf->setXY($this->getDayX($day_in_week), $this->days['y']);
            $this->pdf->MultiCell(
                $this->getDayWidth(),
                $this->days['h'],
                $this->pdf->SanitizeText($this->getDateTime($this->config['date_format'], $day_in_week)),
                0,
                'C'
            );
        }

        // Draw vertical lines
        for ($day_in_week = 2; $day_in_week <= $this->config['days_in_week']; $day_in_week++) {
            $this->pdf->SetFillColor($this->config['color_light']);
            $this->pdf->SetDrawColor($this->config['color_light']);
            $this->pdf->Rect($this->getDayX($day_in_week), $this->hours['y'], $this->config['line_width'], $this->hours['h'], 'DF');
            $this->pdf->SetDrawColor($this->config['draw_color']);
            $this->pdf->SetFillColor($this->config['fill_color']);
        }
    }

    protected function renderHours()
    {
        for ($hour = $this->hour_start; $hour <= $this->hour_end; $hour += 0.5) {
            // Text
            $this->pdf->setXY($this->hours['x'], $this->getTimeY($hour));
            $this->pdf->setFontSize($this->config['font_size_small']);
            $this->pdf->SetTextColor($this->config['color_light']);
            $this->pdf->MultiCell($this->hours['w'], 0, $this->pdf->SanitizeText(Helpers::hoursToTime($hour)));
            $this->pdf->setFontSize($this->config['font_size_base']);
            $this->pdf->SetTextColor($this->config['text_color']);
            // Line (drawing a rectangle is more precise than a line)
            $this->pdf->SetFillColor($this->config['color_light']);
            $this->pdf->SetDrawColor($this->config['color_light']);
            $this->pdf->Rect($this->days['x'], $this->getTimeY($hour), $this->days['w'], $this->config['line_width'], 'DF');
            $this->pdf->SetDrawColor($this->config['draw_color']);
            $this->pdf->SetFillColor($this->config['fill_color']);
        }
    }

    protected function renderEvents()
    {
        $week_events = $this->getEvents();

        foreach ($week_events as $events) {
            foreach ($events as $event) {
                $this->renderEvent($event);
            }
        }
    }

    protected function renderEvent($event)
    {
        $x = $this->getDayX($event['day_of_week']);
        $y = $this->getTimeY($event['start']);
        $w = $this->getDayWidth();
        $h = $this->getTimeY($event['end']) - $y;

        // Overlap.
        $w = $w * $event['width'];
        $x += $w * $event['index'];

        $category = $event['category'] && isset($this->categories[$event['category']]) ? $this->categories[$event['category']] : null;

        // Draw frame.
        $this->pdf->Rect($x, $y, $w, $h, 'DF');

        // Draw color.
        $this->pdf->SetFillColor($category ? $category['color'] : $event['color']);
        $this->pdf->Rect($x, $y, $w, 1, 'F');
        $this->pdf->SetFillColor($this->config['fill_color']);

        // Draw starting time.
        $this->pdf->setXY($x, $y + 2);
        $this->pdf->SetFontSize($this->config['font_size_small']);
        $this->pdf->MultiCell($w, $this->config['line_height_small'], Helpers::hoursToTime($event['start']), 0, 'L');
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
        $cols = count($this->categories);
        $col_width = $this->footer['w'] / $cols;

        $categories = array_values($this->categories);

        for ($col = 0; $col < $cols; $col++) {
            $category = $categories[$col];

            $x = $this->footer['x'] + $col_width * $col + 1;
            $y = $this->footer['y'];
            $thumb_size = 3;

            $this->pdf->setXY($x, $y);

            // Draw color.
            $this->pdf->SetFillColor($category['color']);
            $this->pdf->Rect($x, $y, $thumb_size, $thumb_size, 'F');
            $this->pdf->SetFillColor($this->config['fill_color']);

            // Draw name.
            $this->pdf->setXY($x + $thumb_size + 1, $y - .5);
            $this->pdf->SetFont($this->config['font_family'], '', $this->config['font_size_small']);
            $this->pdf->MultiCell($col_width, $this->config['line_height_small'], $this->pdf->SanitizeText($category['name']), 0, 'L');
            $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);
        }
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

        //
        $this->hour_start = $this->config['hour_start'];
        $this->hour_end   = $this->config['hour_end'];

        $week_events = $this->getEvents();

        foreach ($week_events as $events) {
            foreach ($events as $event) {
                if ($event['start'] < $this->hour_start) {
                    $this->hour_start = $event['start'];
                }
                if ($event['end'] > $this->hour_end) {
                    $this->hour_end = $event['end'];
                }
            }
        }

        //

        $this->pdf->SetAutoPageBreak(false, $this->margin['y']);
        $this->pdf->AddPage($this->page['w'] > $this->page['h'] ? 'L' : 'P', [$this->page['w'], $this->page['h']]);

        $this->renderHeader();
        $this->renderDays();
        $this->renderHours();
        $this->renderEvents();
        $this->renderFooter();

        if ($this->config['debug']) {
            $this->renderGuides();
        }
    }

    public function render()
    {
        $this->prepare();

        //return;

        $this->pdf = new PDF();
        $this->pdf->SetTitle($this->config['file_title']);
        $this->pdf->setMargins(0, 0, 0);
        $this->pdf->SetFont($this->config['font_family'], $this->config['font_weight'], $this->config['font_size_base']);
        $this->pdf->SetLineWidth($this->config['line_width']);
        $this->pdf->SetDrawColor($this->config['draw_color']);
        $this->pdf->SetFillColor($this->config['fill_color']);

        foreach ($this->events as $year => $year_events) {
            foreach ($year_events as $week => $week_events) {
                $this->renderWeek($week, $year);
            }
        }

        return call_user_func_array([$this->pdf, 'Output'], func_get_args());
    }
}
