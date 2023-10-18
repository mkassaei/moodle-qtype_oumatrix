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

use qtype_oumatrix\row;
use qtype_oumatrix\column;

/**
 * Editing form for the oumatrix question type.
 *
 * @package   qtype_oumatrix
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumatrix_edit_form extends question_edit_form {

    /** The default starting number of columns (answers). */
    private const COL_NUM_START = 3;

    /** The number of columns (answers) that get added at a time. */
    private const COL_NUM_ADD = 2;

    /**The default starting number of rows (question row). */
    private const ROW_NUM_START = 4;

    /** The number of rows (question row) that get added at a time.*/
    private const ROW_NUM_ADD = 2;

    /** @var int Number of columns. */
    private $numcolumns;

    /** @var string answermode of rows. */
    private $inputtype;

    /**
     * Set the inputtype and number of rows and columns method.
     *
     * @return void
     */
    protected function set_current_settings(): void {
        $inputtype = optional_param('inputtype', '', PARAM_ALPHA);
        if ($inputtype == '') {
            $inputtype = $this->question->options->inputtype ?? get_config('qtype_oumatrix', 'inputtype');
        }
        $this->inputtype = $inputtype;

        if (isset($this->question->columns)) {
            $this->numcolumns = count($this->question->columns);
        } else {
            $this->numcolumns = self::COL_NUM_START;
        }
    }

    protected function definition_inner($mform) {

        // Set the number of columns and rows.
        $this->set_current_settings();

        $answermodemenu = [
            'single' => get_string('answermodesingle', 'qtype_oumatrix'),
            'multiple' => get_string('answermodemultiple', 'qtype_oumatrix'),
        ];
        $mform->addElement('select', 'inputtype', get_string('answermode', 'qtype_oumatrix'), $answermodemenu);
        $mform->setDefault('inputtype', $this->get_default_value('inputtype',
                get_config('qtype_oumatrix', 'inputtype')));

        $grademethodmenu = [
            'partial' => get_string('gradepartialcredit', 'qtype_oumatrix'),
            'allnone' => get_string('gradeallornothing', 'qtype_oumatrix'),
        ];
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'qtype_oumatrix'), $grademethodmenu);
        $mform->addHelpButton('grademethod', 'grademethod', 'qtype_oumatrix');
        $mform->setDefault('grademethod', $this->get_default_value('grademethod',
                get_config('qtype_oumatrix', 'grademethod')));
        $mform->disabledIf('grademethod', 'inputtype', 'eq', 'single');

        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shuffleanswers', 'qtype_oumatrix'));
        $mform->addHelpButton('shuffleanswers', 'shuffleanswers', 'qtype_oumatrix');
        $mform->setDefault('shuffleanswers', $this->get_default_value('shuffleanswers',
                get_config('qtype_oumatrix', 'shuffleanswers')));

        // Add update field.
        $mform->addElement('submit', 'updateform', get_string('updateform', 'qtype_oumatrix'));
        $mform->registerNoSubmitButton('updateform');

        $this->add_per_column_fields($mform, get_string('column', 'qtype_oumatrix', '{no}'));
        $this->add_per_row_fields($mform, get_string('row', 'qtype_oumatrix', '{no}'));

        $this->add_combined_feedback_fields(true);

        $this->add_interactive_settings(false, true);
    }

    /**
     * Add a set of form fields, obtained from get_per_column_fields.
     *
     * @param stdClass $mform the form being built.
     * @param string $label the label to use for each column.
     * @param int $minoptions the minimum number of column blanks to display. Default COL_NUM_START.
     * @param int $addoptions the number of column blanks to add. Default COL_NUM_ADD.
     */
    protected function add_per_column_fields(object $mform, string $label) {
        $mform->addElement('header', 'columnshdr', get_string('columnshdr', 'qtype_oumatrix'));
        $mform->setExpanded('columnshdr', 1);
        $repeatedoptions = [];

        $this->repeat_elements($this->get_per_column_fields($mform, $label, $repeatedoptions),
            $this->numcolumns, $repeatedoptions,
            'nocolumns', 'addcolumns', self::COL_NUM_ADD,
            get_string('addmoreblanks', 'qtype_oumatrix', 'columns'), true);
    }

    protected function get_per_column_fields($mform, $label, $repeatedoptions) {
        $repeated = [];
        $repeated[] = $mform->createElement('text', 'columnname', $label, ['size' => 40]);
        $mform->setType('columnname', PARAM_RAW);
        $repeatedoptions['column']['type'] = PARAM_RAW;
        return $repeated;
    }

    /**
     * Add a set of form fields, obtained from get_per_row_fields.
     *
     * @param stdClass $mform the form being built.
     * @param string $label the label to use for each row.
     * @param int $minoptions the minimum number of row blanks to display. Default COL_NUM_START.
     * @param int $addoptions the number of row blanks to add. Default COL_NUM_ADD.
     */
    protected function add_per_row_fields(object $mform, string $label) {
        $mform->addElement('header', 'rowshdr', get_string('rowshdr', 'qtype_oumatrix'));
        $mform->setExpanded('rowshdr', 1);
        $repeatedoptions = [];

        if (isset($this->question->rows)) {
            $repeatsatstart = count($this->question->rows);
        } else {
            $repeatsatstart = self::ROW_NUM_START;
        }

        $this->repeat_elements($this->get_per_row_fields($mform, $label, $repeatedoptions),
                $repeatsatstart, $repeatedoptions,
                'norows', 'addrows', self::ROW_NUM_ADD,
                get_string('addmoreblanks', 'qtype_oumatrix', 'rows'), true);
    }

    /**
     * Returns a row object with relevant input fields.
     *
     * @param stdClass $mform
     * @param string $label
     * @param array $repeatedoptions
     * @return array
     */
    protected function get_per_row_fields(object $mform, string $label, array &$repeatedoptions): array {
        $repeated = [];
        $rowoptions = [];
        $rowoptions[] = $mform->createElement('text', 'rowname', '', ['size' => 40]);
        $mform->setType('rowname', PARAM_RAW);

        $rowanswerlistlabel = ($this->inputtype === 'single') ?
            get_string('correctanswer', 'qtype_oumatrix') :
            get_string('correctanswers', 'qtype_oumatrix');
        $rowoptions[] = $mform->createElement('html',
            html_writer::tag('span', $rowanswerlistlabel, ['class' => 'rowanswerlistlabel']));

        // Get the list answer input type (radio buttons or checkboxes).
        for ($i = 0; $i < $this->numcolumns; $i++) {
            $anslabel = get_string('a', 'qtype_oumatrix', $i + 1);
            $columnvalue = 'a' . ($i + 1);
            if ($this->inputtype === 'single') {
                $rowoptions[] = $mform->createElement('radio', 'rowanswers', '', $anslabel, $columnvalue);
            } else {
                $rowoptions[] = $mform->createElement('checkbox', "rowanswers$columnvalue", '', $anslabel);
            }
        }
        $repeated[] = $mform->createElement('group', 'rowoptions', $label, $rowoptions, null, false);
        $repeated[] = $mform->createElement('editor', 'feedback',
                get_string('feedback', 'question'), ['rows' => 2], $this->editoroptions);
        $repeatedoptions['rowname']['type'] = PARAM_RAW;
        return $repeated;
    }

    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_options($question);
        $question = $this->data_preprocessing_columns($question);
        $question = $this->data_preprocessing_rows($question);
        $question = $this->data_preprocessing_combined_feedback($question, true);
        $question = $this->data_preprocessing_hints($question, true, true);
        return $question;
    }

    /**
     * Perform the necessary preprocessing for the options fields.
     *
     * @param stdClass $question the data being passed to the form.
     * @return object $question the modified data.
     */
    protected function data_preprocessing_options($question) {
        if (empty($question->options)) {
            return $question;
        }
        $question->inputtype = $question->options->inputtype;
        $question->grademethod = $question->options->grademethod;
        $question->shuffleanswers = $question->options->shuffleanswers;
        $question->shownumcorrect = $question->options->shownumcorrect;
        return $question;
    }

    /**
     * Perform the necessary preprocessing for columns (answers) fields.
     *
     * @param stdClass $question The data being passed to the form.
     * @return object The modified data.
     */
    private function data_preprocessing_columns($question) {
        if (empty($question->columns)) {
            return $question;
        }
        $question->columnname = [];
        foreach ($question->columns as $column) {
            if (trim($column->name ?? '') === '') {
                continue;
            }
            $question->columnname[] = $column->name;
        }
        $this->numcolumns = count($question->columnname);
        return $question;
    }

    /**
     * Perform the necessary preprocessing for rows (sub-questions) fields.
     *
     * @param stdClass $question The data being passed to the form.
     * @return object The modified data.
     */
    private function data_preprocessing_rows($question) {
        // Preprocess rows.
        if (empty($question->rows)) {
            return $question;
        }
        $key = 0;
        $question->rowname = [];
        /**
         * @var int $index
         * @var row $row
         */
        foreach ($question->rows as $index => $row) {
            $question->rowname[$row->number] = $row->name;
            $decodedanswers = json_decode($row->correctanswers, true);
            foreach ($question->columns as $key => $column) {
                if (array_key_exists($column->id, $decodedanswers)) {
                    $columnvalue = 'a' . ($column->number + 1);
                    if ($question->options->inputtype == 'single') {
                        $question->rowanswers[$row->number] = $columnvalue;
                    } else {
                        $rowanswerslabel = "rowanswers" . $columnvalue;
                        $question->{$rowanswerslabel}[$row->number] = $decodedanswers[$column->id];
                    }
                }
            }
            $itemid = (int) $row->id ?? null;

            // Prepare the feedback editor to display files in draft area.
            $feedback[$key] = [];
            $feedbackdraftitemid = file_get_submitted_draft_itemid('feedback[' . $key . ']');
            $feedback[$key]['text'] = file_prepare_draft_area(
                    $feedbackdraftitemid,
                    $this->context->id,
                    'qtype_oumatrix',
                    'feedback',
                    $itemid,
                    $this->fileoptions,
                    $row->feedback
            );
            $feedback[$key]['itemid'] = $feedbackdraftitemid;
            $feedback[$key]['format'] = $row->feedbackformat ?? FORMAT_HTML;
            $question->rows[$index]->feedbackformat = $feedback[$key]['format'];
            $question->rows[$index]->feedback = $feedback[$key]['text'];
            $question->feedback[$row->number] = $feedback[$key];
            $key++;
        }
        return $question;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate minimum required number of columns.
        $filteredcolscount = count(array_filter($data['columnname']));
        if ($filteredcolscount < column::MIN_NUMBER_OF_COLUMNS) {
            $errors['columnname[' . $filteredcolscount . ']'] = get_string('notenoughanswercols', 'qtype_oumatrix',
                    column::MIN_NUMBER_OF_COLUMNS);
        }

        // Validate duplication of columns.
        $uniquecolscount = count(array_unique($data['columnname']));
        $duplicate = [];
        if ($uniquecolscount < count($data['columnname'])) {
            foreach ($data['columnname'] as $key => $name) {
                // If duplicate, then display error message.
                if ($name != '' && in_array($name, $duplicate)) {
                    $errors['columnname[' . $key . ']'] = get_string('duplicates', 'qtype_oumatrix', $name);
                }
                $duplicate[] = $name;
            }
        }

        // Validate if there are any empty columns in the middle.
        // If there are any empty, then the count of filtered array will be lesser than the columns array.
        if ($filteredcolscount < count($data['columnname'])) {
            $valuefound = false;
            $i = count($data['columnname']) - 1;
            for ($i; $i >= 0; $i--) {
                // The blanks at the end of the columns list could be ignored.
                // This checks if there are empty columns in the middle.
                if ($data['columnname'][$i] != '') {
                    $valuefound = true;
                    continue;
                }
                if ($valuefound) {
                    $errors['columnname[' . $i . ']'] = get_string('blankcolumnsnotallowed', 'qtype_oumatrix');
                }
            }
        }

        // Validate minimum required number of rows.
        $countrows = count(array_filter($data['rowname']));
        if ($countrows < row::MIN_NUMBER_OF_ROWS) {
            $errors['rowoptions[' . $countrows . ']'] = get_string('notenoughquestionrows', 'qtype_oumatrix',
                    row::MIN_NUMBER_OF_ROWS);
        }

        // Validate duplication of rows.
        $duplicate = [];
        $uniquerowscount = count(array_unique($data['rowname']));
        if ($uniquerowscount < count($data['rowname'])) {
            foreach ($data['rowname'] as $key => $name) {
                if ($name != '' && in_array($name, $duplicate)) {
                    $errors['rowoptions[' . $key . ']'] = get_string('duplicates', 'qtype_oumatrix', $name);
                }
                $duplicate[] = $name;
            }
        }

        // Validate if correct answers have been input for oumatrix single choice question.
        $nonemptyrows = array_filter($data['rowname']);
        if ($data['inputtype'] == 'single') {
            foreach ($nonemptyrows as $key => $rowname) {
                if (!isset($data['rowanswers']) || !array_key_exists($key, $data['rowanswers'])) {
                    $errors['rowoptions[' . $key . ']'] = get_string('noinputanswer', 'qtype_oumatrix');
                }
            }
        } else {
            // Validate if correct answers have been input for oumatrix multiple choice question.
            foreach ($nonemptyrows as $rowkey => $rowname) {
                $answerfound = false;
                foreach ($data['columnname'] as $colkey => $unused) {
                    $rowanswerslabel = "rowanswers" . 'a' . ($colkey + 1);
                    if (isset($data[$rowanswerslabel]) && array_key_exists($rowkey, $data[$rowanswerslabel])) {
                        $answerfound = true;
                        break;
                    }
                }
                if (!$answerfound) {
                    $errors['rowoptions[' . $rowkey . ']'] = get_string('noinputanswer', 'qtype_oumatrix');
                }
            }
        }
        return $errors;
    }

    /**
     * Returns the question type name.
     *
     * @return string The question type name.
     */
    public function qtype() {
        return 'oumatrix';
    }
}
