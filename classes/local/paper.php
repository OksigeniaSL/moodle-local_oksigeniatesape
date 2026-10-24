<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_oksigeniatesape\local;

/**
 * The PDFs of Tesapé (the report of the class, the summaries for the families, the pack for home): A4, without the
 * header and footer of the library nor its line at the end, in a font with every letter.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class paper {
    /**
     * A new PDF.
     *
     * @param string $orientation P (portrait) or L (landscape).
     * @param int $size Of the font, in points.
     * @return \pdf
     */
    public static function pdf(string $orientation = 'P', int $size = 11): \pdf {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $pdf = new class ($orientation, 'mm', 'A4') extends \pdf {
            /**
             * Without the line the library writes at the end of the document.
             *
             * @param string $orientation
             * @param string $unit
             * @param string $format
             */
            public function __construct($orientation, $unit, $format) {
                parent::__construct($orientation, $unit, $format);
                $this->tcpdflink = false;
            }
        };
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('freesans', '', $size);
        return $pdf;
    }
}
