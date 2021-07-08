<?php

namespace My\PDFCalendar;

if (! defined('ABSPATH')) {
    include './../../../wp-load.php';
}

$pdf = new PDFCalendar([
    'events' => [
        [
            'name'  => 'My Event',
            'start' => '2021-06-04 10:00:00',
            'end'   => '2021-06-04 12:00:00',
            'text'  => 'Text.'
        ],
        [
            'name'   => 'My Other Event',
            'start'  => '2021-06-01',
            'end'    => '2021-06-01',
            'text'  => 'Text.',
            'allDay' => true,
        ],
    ],
]);

$pdf->render('I');
