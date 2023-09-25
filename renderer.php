<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * OU matrix question renderer classes.
 *
 * @package    qtype_oumatrix
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for generating the bits of output common to oumatrix
 * single choice and multiple response questions.
 *
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_oumatrix_renderer_base extends qtype_with_combined_feedback_renderer {

    /**
     * Method to generating the bits of output after question choices.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options controls what should and should not be displayed.
     *
     * @return string HTML output.
     */
    abstract protected function after_choices(question_attempt $qa, question_display_options $options);

    abstract protected function get_input_type();

    abstract protected function get_input_name(question_attempt $qa, $value, $columnnumber);

    abstract protected function get_input_value($value);

    abstract protected function get_input_id(question_attempt $qa, $value, $columnnumber);

    abstract protected function prompt();

    /**
     * Whether a choice should be considered right or wrong.
     * @param question_definition $question the question
     * @param int $rowkey representing the row.
     * @param int $columnkey representing the column.
     * @return float 1.0, 0.0 or something in between, respectively.
     */
    protected function is_right(question_definition $question, $rowkey, $columnkey) {
        $row = $question->rows[$rowkey];
        if ($row->correctanswers != '') {
            foreach ($question->columns as $column) {
                if ($column->number == $columnkey && array_key_exists($column->id, $row->correctanswers)) {
                    return 1;
                }
            }
        }
    }

    protected function feedback_class($fraction) {
        return question_state::graded_state_for_fraction($fraction)->get_feedback_class();
    }

    /**
     * Return an appropriate icon (green tick, red cross, etc.) for a grade.
     * @param float $fraction grade on a scale 0..1.
     * @param bool $selected whether to show a big or small icon. (Deprecated)
     * @return string html fragment.
     */
    protected function feedback_image($fraction, $selected = true) {
        $feedbackclass = question_state::graded_state_for_fraction($fraction)->get_feedback_class();

        return $this->output->pix_icon('i/grade_' . $feedbackclass, get_string($feedbackclass, 'question'));
    }

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        $question = $qa->get_question();
        $result = '';
        //$hidden = '';
        //
        //if (!$options->readonly && $this->get_input_type() == 'multiple') {
        //    $hidden = html_writer::empty_tag('input', array(
        //            'type' => 'hidden',
        //            'name' => $inputattributes['name'],
        //            'value' => 0,
        //    ));
        //}

        $result .= html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));
        $result .= html_writer::start_tag('fieldset', array('class' => 'ablock no-overflow visual-scroll-x'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        $result .= $this->get_matrix($qa, $options);
        // TODO: get inputtype

        $result .= html_writer::end_tag('div'); // Answer.
        $result .= html_writer::end_tag('fieldset'); // Ablock.

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()),
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    public function get_matrix(question_attempt $qa, question_display_options $options) {

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $caption = "Matrix question";
        $colname[] = null;
        $table = "
            <table class='generaltable'>
                <caption class='table_caption'>$caption</caption>
                <tr>
                    <th scope='col'></th>";
        $index = 0;

        // Creating the matrix headers.
        foreach ($question->columns as $value) {
            $colname[$index] = $value->name;
            $table .= "<th scope='col'><span id=col" . $index . " class='answer_col' >$colname[$index]</span></th>";
            $index += 1;
        }
        // Adding an extra column for feedback.
        $table .= "<th></th></tr>";

        // Creating table rows for the row questions.
        $table .= "<tr> ";

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        // Set the input attribute based on the single or multiple answer mode.
        if ( $this->get_input_type() == "single") {
            $inputattributes['type'] = "radio";
        } else {
            $inputattributes['type'] = "checkbox";
        }

        foreach ($question->get_order($qa) as $rowkey => $rowid) {
            $row = $question->rows[$rowid];
            $rowname = $row->name;
            $rownewid = 'row_'. $rowkey;
            $feedback = '';
            $table .= "<th scope='col'><span id='$rownewid'>$rowname</span></th>";

            for ($j = 0; $j < count($colname); $j++) {
                $inputattributes['name'] = $this->get_input_name($qa, $rowkey, $j);
                $inputattributes['value'] = $this->get_input_value($j);
                $inputattributes['id'] = $this->get_input_id($qa, $rowkey, $j);
                $inputattributes['aria-labelledby'] = $inputattributes['id'] . '_label';

                $isselected = $question->is_choice_selected($response, $rowkey, $j);

                // Get the row per feedback.
                if ($options->feedback && empty($options->suppresschoicefeedback) && $feedback == '' &&
                        $isselected && trim($row->feedback)) {
                    $feedback = html_writer::tag('div',
                        $question->make_html_inline($question->format_text($row->feedback, $row->feedbackformat,
                            $qa, 'qtype_oumatrix', 'feedback', $rowid)),
                        ['class' => 'specificfeedback']);
                }

                $class = 'r' . ($rowkey % 2);
                $feedbackimg = '';

                // Select the radio button or checkbox and display feedback image.
                if ($isselected) {
                    $inputattributes['checked'] = 'checked';
                    if ($options->correctness) {
                        // Feedback images will be rendered using Font awesome.
                        // Font awesome icons are actually characters(text) with special glyphs,
                        // so the icons cannot be aligned correctly even if the parent div wrapper is using align-items: flex-start.
                        // To make the Font awesome icons follow align-items: flex-start, we need to wrap them inside a span tag.
                        $feedbackimg = html_writer::span($this->feedback_image($this->is_right($question, $rowid, $j)), 'ml-1');
                        $class .= ' ' . $this->feedback_class($this->is_right($question, $rowid, $j));
                    }
                } else {
                    unset($inputattributes['checked']);
                }

                // Write row and its attributes.
                $button = html_writer::empty_tag('input', $inputattributes);
                $table .= "<td>" . html_writer::start_tag('div', ['class' => 'answer']);
                $table .= html_writer::tag('div', $button . ' ' . $feedbackimg,
                                    ['class' => $class]) . "\n";
                $table .= html_writer::end_tag('div');
                $table .= "</td>";
            }
            $table .= "<td>" . $feedback . "</td>";
            $table .= "</tr>";
        }
        $table .= "</table>";
        return $table;
    }

    protected function number_html($qnum) {
        return $qnum . '. ';
    }


    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * Function returns string based on number of correct answers
     * @param array $right An Array of correct responses to the current question
     * @return string based on number of correct responses
     */
    protected function correct_choices(array $right) {
        // Return appropriate string for single/multiple correct answer(s).
        if (count($right) == 1) {
            return get_string('correctansweris', 'qtype_multichoice',
                    implode(', ', $right));
        } else if (count($right) > 1) {
            return get_string('correctanswersare', 'qtype_multichoice',
                    implode(', ', $right));
        } else {
            return "";
        }
    }
}


/**
 * Subclass for generating the bits of output specific to oumatrix
 * single choice questions.
 *
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumatrix_single_renderer extends qtype_oumatrix_renderer_base {
    protected function get_input_type() {
        return 'single';
    }

    protected function get_input_name(question_attempt $qa, $value, $columnnumber) {
        return $qa->get_qt_field_name('rowanswers' . $value);
    }

    protected function get_input_value($value) {
        return $value;
    }

    protected function get_input_id(question_attempt $qa, $value, $columnnumber) {
        return $qa->get_qt_field_name('rowanswers' . $value . '_' . $columnnumber);
    }

    protected function prompt() {
        return get_string('selectone', 'qtype_multichoice');
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $right = [];
        foreach ($question->rows as $row) {
            if ($row->correctanswers != '') {
                $right[] = $row->name . " => " . $question->columns[array_key_first($row->correctanswers)]->name;
            }
        }
        return $this->correct_choices($right);
    }

    public function after_choices(question_attempt $qa, question_display_options $options) {
        // Only load the clear choice feature if it's not read only.
        if ($options->readonly) {
            return '';
        }

        $question = $qa->get_question();
        $response = $question->get_response($qa);
        $hascheckedchoice = false;
        foreach ($question->get_order($qa) as $value => $ansid) {
            if ($question->is_choice_selected($response, $value)) {
                $hascheckedchoice = true;
                break;
            }
        }

        $clearchoiceid = $this->get_input_id($qa, -1);
        $clearchoicefieldname = $qa->get_qt_field_name('clearchoice');
        $clearchoiceradioattrs = [
                'type' => $this->get_input_type(),
                'name' => $qa->get_qt_field_name('answer'),
                'id' => $clearchoiceid,
                'value' => -1,
                'class' => 'sr-only',
                'aria-hidden' => 'true'
        ];
        $clearchoicewrapperattrs = [
                'id' => $clearchoicefieldname,
                'class' => 'qtype_multichoice_clearchoice',
        ];

        // When no choice selected during rendering, then hide the clear choice option.
        // We are using .sr-only and aria-hidden together so while the element is hidden
        // from both the monitor and the screen-reader, it is still tabbable.
        $linktabindex = 0;
        if (!$hascheckedchoice && $response == -1) {
            $clearchoicewrapperattrs['class'] .= ' sr-only';
            $clearchoicewrapperattrs['aria-hidden'] = 'true';
            $clearchoiceradioattrs['checked'] = 'checked';
            $linktabindex = -1;
        }
        // Adds an hidden radio that will be checked to give the impression the choice has been cleared.
        $clearchoiceradio = html_writer::empty_tag('input', $clearchoiceradioattrs);
        $clearchoice = html_writer::link('#', get_string('clearchoice', 'qtype_multichoice'),
                ['tabindex' => $linktabindex, 'role' => 'button', 'class' => 'btn btn-link ml-3 mt-n1']);
        $clearchoiceradio .= html_writer::label($clearchoice, $clearchoiceid);

        // Now wrap the radio and label inside a div.
        $result = html_writer::tag('div', $clearchoiceradio, $clearchoicewrapperattrs);

        // Load required clearchoice AMD module.
        $this->page->requires->js_call_amd('qtype_multichoice/clearchoice', 'init',
                [$qa->get_outer_question_div_unique_id(), $clearchoicefieldname]);

        return $result;
    }

}

/**
 * Subclass for generating the bits of output specific to oumatrix
 * multiple choice questions.
 *
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumatrix_multiple_renderer extends qtype_oumatrix_renderer_base {
    protected function after_choices(question_attempt $qa, question_display_options $options) {
        return '';
    }

    protected function get_input_type() {
        return 'multiple';
    }

    protected function get_input_name(question_attempt $qa, $value, $columnnumber) {
        return $qa->get_qt_field_name('rowanswers' . $value . '_' . $columnnumber);
    }

    protected function get_input_value($value) {
        return 1;
    }

    protected function get_input_id(question_attempt $qa, $value, $columnnumber) {
        return $this->get_input_name($qa, $value, $columnnumber);
    }

    protected function prompt() {
        return get_string('selectmulti', 'qtype_multichoice');
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        foreach ($question->rows as $row) {
            // Get the correct row.
            $rowanswer = $row->name . " => ";
            $answers = [];
            if ($row->correctanswers != '') {
                foreach ($row->correctanswers as $columnkey => $notused) {
                    $answers[] = $question->columns[$columnkey]->name;
                }
                $rowanswer .= implode(', ', $answers);
                $rightanswers[] = $rowanswer;
            }
        }
        return $this->correct_choices($rightanswers);
    }

    protected function num_parts_correct(question_attempt $qa) {
        if ($qa->get_question()->get_num_selected_choices($qa->get_last_qt_data()) >
                $qa->get_question()->get_num_correct_choices()) {
            return get_string('toomanyselected', 'qtype_multichoice');
        }

        return parent::num_parts_correct($qa);
    }
}
