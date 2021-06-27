<?php

namespace My\PDFCalendar;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

class PDF extends \Fpdf\Fpdf
{
    /**
     * Construct
     */
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
    }

    /**
     * Set font style.
     */
    public function SetFontStyle($style)
    {
        $this->SetFont($this->FontFamily, $style, $this->FontSizePt);
    }

    /**
     * Set draw color.
     */
    public function SetDrawColor($r, $g = null, $b = null)
    {
        if (is_array($r)) {
            list($r, $g, $b) = $r;
        }

        parent::SetDrawcolor($r, $g, $b);
    }

    /**
     * Set fill color.
     */
    public function SetFillColor($r, $g = null, $b = null)
    {
        if (is_array($r)) {
            list($r, $g, $b) = $r;
        }

        parent::SetFillColor($r, $g, $b);
    }

    /**
     * Set text color.
     */
    public function SetTextColor($r, $g = null, $b = null)
    {
        if (is_array($r)) {
            list($r, $g, $b) = $r;
        }

        parent::SetTextColor($r, $g, $b);
    }

    /**
     * Sanitize text.
     *
     * @link https://stackoverflow.com/questions/6334134/fpdf-utf-8-encoding-how-to
     */
    public function SanitizeText($text)
    {
        return iconv('UTF-8', 'windows-1252', $text);
    }
}

// phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
