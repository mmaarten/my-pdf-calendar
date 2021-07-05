<?php

namespace My\PDFCalendar;

if (! defined('ABSPATH')) {
    include './../../../wp-load.php';
}

$pdf = new PDFCalendar([
    'events' => [
        [
            'name'  => 'My Event',
            'date'  => '2021-06-04',
            'start' => '10:00:00',
            'end'   => '11:00:00',
        ],
    ],
]);

$pdf->render('I');
